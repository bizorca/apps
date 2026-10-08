#!/bin/bash
# Deploy tools.bizorca.com to Cloudways.
#
# Usage:
#   ./deploy.sh            deploy the committed HEAD, then run pending migrations
#   ./deploy.sh --dry-run  show what would change, transfer nothing, migrate nothing
#
# Repo layout -> server layout (applications/<app>/):
#
#   public_html/            -> public_html/              landing page, request form, /account
#   private_html/           -> private_html/             shared core: includes, migrations, bin
#   <tool>/public/          -> public_html/<tool>/       one per entry in TOOLS
#   <tool>/{includes,...}   -> private_html/<tool>/      everything else in the tool's folder
#
# private_html is outside the web root. Server-only files there are never
# touched: .env.php (secrets) and data/ (request log, mail log, rate limits).
#
# Ships the committed tree only (git archive HEAD), so local scratch files, the
# local .env.php and dev-router.php never reach the server.

set -euo pipefail

SSH_HOST="cloudways-bizorca"   # ~/.ssh/config alias: master user, ~/.ssh/cloudways_bizorca
# Relative to the remote home on purpose: master's home is /home/master, and an
# absolute path built from the username makes rsync deploy into a phantom tree.
APP="applications/qukjzcxeas"

# Tools ported into this repo, each a folder at the repo root with a public/.
TOOLS=(
  proforma
  kit
  tinybooks
  thinkrep
  lattice
  dispatch
  fathom
  foundry
)

# Directories in the server web root that belong to something else and must
# survive --delete. The preflight refuses to deploy if public_html holds a
# directory that is not listed here, not a tool, and not in public_html/:
# add it here or remove it from the server, never let rsync decide.
SUBAPPS=(
)

DRY=()
[[ "${1:-}" == "--dry-run" ]] && DRY=(--dry-run)

cd "$(dirname "$0")"

if ! git diff --quiet HEAD --; then
    echo "Uncommitted changes to tracked files. This deploys HEAD only, so commit first." >&2
    git status --short --untracked-files=no >&2
    exit 1
fi

ssh "$SSH_HOST" "test -d ${APP}/public_html && test -d ${APP}/private_html" \
    || { echo "${APP} not found on server" >&2; exit 1; }

BUILD=$(mktemp -d)
trap 'rm -rf "$BUILD"' EXIT
git archive HEAD | tar -x -C "$BUILD"

# ---------------------------------------------------------------- preflight

KNOWN=" ${TOOLS[*]:-} ${SUBAPPS[*]:-} $(cd "$BUILD/public_html" && find . -mindepth 1 -maxdepth 1 -type d | sed 's#^\./##' | tr '\n' ' ') "
UNKNOWN=$(ssh "$SSH_HOST" "cd ${APP}/public_html && find . -mindepth 1 -maxdepth 1 -type d -printf '%f\\n'" \
    | while read -r d; do [[ "$KNOWN" == *" $d "* ]] || echo "$d"; done)
if [[ -n "$UNKNOWN" ]]; then
    echo "Refusing to deploy: these server directories are not a tool, a SUBAPP or in public_html/," >&2
    echo "and --delete would remove them:" >&2
    echo "$UNKNOWN" | sed 's/^/  /' >&2
    exit 1
fi

echo "Deploying $(git log -1 --format='%h %s') to ${SSH_HOST}:${APP}"

# -rltz, not -a: the app folders are owned by the app user, so never try to set
# owner, group or permissions on them. -O: skip directory times. nginx serves
# .md as plain text on this app, so docs never ship.
RSYNC=(rsync -rltzO --delete ${DRY[@]+"${DRY[@]}"} --exclude='.DS_Store' --exclude='*.md')

# ---------------------------------------------------------------- web root

PROTECT=()
for t in ${TOOLS[@]+"${TOOLS[@]}"} ${SUBAPPS[@]+"${SUBAPPS[@]}"}; do PROTECT+=(--exclude="/${t}/"); done
echo "== public_html"
"${RSYNC[@]}" -i ${PROTECT[@]+"${PROTECT[@]}"} "$BUILD/public_html/" "${SSH_HOST}:${APP}/public_html/"

# ---------------------------------------------------------------- shared core

PROTECT=(--exclude='/.env.php' --exclude='/data/')
for t in ${TOOLS[@]+"${TOOLS[@]}"}; do PROTECT+=(--exclude="/${t}/"); done
echo "== private_html"
"${RSYNC[@]}" -i "${PROTECT[@]}" "$BUILD/private_html/" "${SSH_HOST}:${APP}/private_html/"

# ---------------------------------------------------------------- tools

for t in ${TOOLS[@]+"${TOOLS[@]}"}; do
    echo "== $t"
    "${RSYNC[@]}" -i "$BUILD/$t/public/" "${SSH_HOST}:${APP}/public_html/$t/"
    # bin/ (CLI scripts) goes to private_html with the rest: anything in the
    # web root of an nginx-only Cloudways app is reachable over HTTP.
    "${RSYNC[@]}" -i --exclude='/public/' --exclude='/data/' "$BUILD/$t/" "${SSH_HOST}:${APP}/private_html/$t/"
done

if [[ ${#DRY[@]} -gt 0 ]]; then
    echo ""
    echo "Dry run: nothing transferred, no migrations run."
    exit 0
fi

# ---------------------------------------------------------------- after

echo ""
echo "== migrations"
ssh "$SSH_HOST" "php ${APP}/private_html/bin/migrate.php"

# Cloudways' Varnish caches static pages at the origin (x-cache: HIT), so a
# deploy is invisible until it expires. A PURGE from the server clears it.
ssh "$SSH_HOST" 'curl -s -o /dev/null -X PURGE -H "Host: tools.bizorca.com" http://127.0.0.1/'

echo ""
echo "Deployed: https://tools.bizorca.com/"
