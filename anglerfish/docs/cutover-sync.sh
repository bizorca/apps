#!/bin/bash
# Copy Anglerfish's data from SiteGround to tools.bizorca.com (Cloudways).
# Safe to re-run: it makes Cloudways match SiteGround, it never writes to
# SiteGround. Nothing is written to the Mac: the database streams through its
# pipe, the files go server to server.
#
#   docs/cutover-sync.sh            database + files + verification
#   docs/cutover-sync.sh --db       database only
#   docs/cutover-sync.sh --files    files only (new or changed since last run; never deletes)
#   docs/cutover-sync.sh --verify   compare row counts, checksums and file MD5s
#
# The database copy replaces every af_ table's rows with SiteGround's, so run
# the final one only after the last write on SiteGround: stop the Mac worker
# (launchctl) and push scripts first, and do not use the old site after.
#
# Not touched: SiteGround's users table (Bizorca SSO, replaced by the shared
# tools account) and schema_migrations (tracked by the shared migrate.php).

set -euo pipefail

SG=(ssh -i "$HOME/.ssh/dispatch_bizorca" -p 18765 -o IdentitiesOnly=yes -o ServerAliveInterval=30
    u2361-smkk6swqmgxj@gcam1203.siteground.biz)
CW=(ssh -o ServerAliveInterval=30 cloudways-bizorca)
SG_APP='www/anglerfish.bizorca.com/anglerfish'
CW_PRIV='applications/qukjzcxeas/private_html'
CW_DATA="$CW_PRIV/data/anglerfish"

MODE="${1:-all}"

# MySQL credentials go into a 600 option file on each server for the length
# of one command, so a password never appears in argv, a log or this terminal.
SG_CNF='cd ~/'"$SG_APP"' && umask 077 && php -r '\''require ".env.php"; printf("[client]\nuser=%s\npassword=\"%s\"\nhost=%s\n", $_ENV["DB_USER"], addcslashes($_ENV["DB_PASS"], "\\\""), $_ENV["DB_HOST"]);'\'' > ~/.af-sync.cnf && DB=$(php -r '\''require ".env.php"; echo $_ENV["DB_NAME"];'\'')'
CW_CNF='cd ~/'"$CW_PRIV"' && umask 077 && php -r '\''$e = require ".env.php"; printf("[client]\nuser=%s\npassword=\"%s\"\nhost=%s\n", $e["DB_USER"], addcslashes($e["DB_PASS"], "\\\""), $e["DB_HOST"]);'\'' > ~/.af-sync.cnf && DB=$(php -r '\''$e = require ".env.php"; echo $e["DB_NAME"];'\'')'

sync_db() {
    echo "== database"
    "${SG[@]}" "$SG_CNF"' && mysqldump --defaults-extra-file=$HOME/.af-sync.cnf --single-transaction \
            --no-create-info --skip-triggers --no-tablespaces --hex-blob --set-gtid-purged=OFF \
            --ignore-table=$DB.users --ignore-table=$DB.schema_migrations $DB; rc=$?; rm -f ~/.af-sync.cnf; exit $rc' \
    | "${CW[@]}" "$CW_CNF"' && {
            echo "SET FOREIGN_KEY_CHECKS=0;"
            mysql --defaults-extra-file=$HOME/.af-sync.cnf -N $DB -e "SELECT CONCAT(\"TRUNCATE TABLE \`\", table_name, \"\`;\") FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE \"af\\_%\""
            sed -E "s/^(INSERT INTO|LOCK TABLES|\/\*!40000 ALTER TABLE) \`([a-z_]+)\`/\1 \`af_\2\`/"
            echo "SET FOREIGN_KEY_CHECKS=1;"
        } | mysql --defaults-extra-file=$HOME/.af-sync.cnf $DB; rc=$?; rm -f ~/.af-sync.cnf; exit $rc'
    echo "   imported"
}

# MD5 of every file on each side, for verify. Lock files are runtime state and
# stay behind.
manifest_sg() { "${SG[@]}" "cd ~/$SG_APP/storage && find . -type f ! -name '.*.lock' -print0 | sort -z | xargs -0 md5sum"; }
manifest_cw() { "${CW[@]}" "cd ~/$CW_DATA && find . -type f ! -name '.*.lock' -print0 | sort -z | xargs -0 -r md5sum"; }

# Server to server: Cloudways pulls from SiteGround with rsync, authenticating
# with this Mac's key through a forwarded agent (the key never leaves the Mac;
# it sits in the agent for an hour). Relaying through the Mac ran at ~0.3 MB/s;
# direct is fast and resumable, and only new or changed files move.
sync_files() {
    echo "== files"
    ssh-add -t 3600 "$HOME/.ssh/dispatch_bizorca" >/dev/null 2>&1
    ssh -A -o ServerAliveInterval=30 cloudways-bizorca "cd ~/$CW_DATA \
        && rsync -rltz --partial --stats --exclude='.*.lock' \
             -e 'ssh -p 18765 -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30' \
             u2361-smkk6swqmgxj@gcam1203.siteground.biz:$SG_APP/storage/ ./ \
           | grep -E 'Number of (regular )?files transferred|Total transferred file size' \
        && find . -type d ! -perm 2775 -exec chmod 2775 {} + \
        && find . -type f ! -perm 664 -exec chmod 664 {} +"
}

verify() {
    echo "== verify: rows and CHECKSUM TABLE (SiteGround | Cloudways)"
    local sg cw
    local loop='do n=$(mysql --defaults-extra-file=$HOME/.af-sync.cnf -N $DB -e "SELECT COUNT(*) FROM \`$t\`")
            c=$(mysql --defaults-extra-file=$HOME/.af-sync.cnf -N $DB -e "CHECKSUM TABLE \`$t\`" | cut -f2)
            echo "${t#af_} $n $c"; done; rm -f ~/.af-sync.cnf'
    sg=$("${SG[@]}" "$SG_CNF"' && for t in $(mysql --defaults-extra-file=$HOME/.af-sync.cnf -N $DB -e "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = \"BASE TABLE\" AND table_name NOT IN (\"users\", \"schema_migrations\") ORDER BY table_name"); '"$loop")
    cw=$("${CW[@]}" "$CW_CNF"' && for t in $(mysql --defaults-extra-file=$HOME/.af-sync.cnf -N $DB -e "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE \"af\\_%\" ORDER BY table_name"); '"$loop")
    local bad=0
    while read -r t n c; do
        local other
        other=$(echo "$cw" | awk -v t="$t" '$1 == t {print $2, $3}')
        if [[ "$other" == "$n $c" ]]; then
            printf '   ok    %-22s %8s rows\n' "$t" "$n"
        else
            printf '   DIFF  %-22s SG %s %s | CW %s\n' "$t" "$n" "$c" "${other:-missing}"
            bad=1
        fi
    done <<< "$sg"

    echo "== verify: files"
    local want have
    want=$(manifest_sg); have=$(manifest_cw)
    if [[ "$want" == "$have" ]]; then
        echo "   ok    $(echo "$want" | wc -l | tr -d ' ') files, every MD5 matches"
    else
        echo "   DIFF  SG $(echo "$want" | wc -l | tr -d ' ') files, CW $(echo "$have" | grep -c . || true) files"
        diff <(echo "$want") <(echo "$have") | head -20
        bad=1
    fi
    return $bad
}

case "$MODE" in
    --db)     sync_db ;;
    --files)  sync_files ;;
    --verify) verify ;;
    all)      sync_db; sync_files; verify ;;
    *)        echo "usage: $0 [--db|--files|--verify]" >&2; exit 64 ;;
esac
