#!/bin/bash
# Deploy tools.bizorca.com to Cloudways.
#
# Usage:
#   ./deploy.sh            deploy the committed HEAD
#   ./deploy.sh --dry-run  show what would change, transfer nothing
#
# Ships the committed tree only (git archive HEAD), so untracked notes in the
# working copy never reach the web root. --delete also removes Cloudways' stock
# index.php placeholder, which would otherwise be served ahead of index.html.

set -euo pipefail

SSH_HOST="cloudways-bizorca"   # ~/.ssh/config alias: master user, ~/.ssh/cloudways_bizorca
# Relative to the remote home on purpose: master's home is /home/master, and an
# absolute path built from the username makes rsync deploy into a phantom tree.
APP_DIR="applications/qukjzcxeas/public_html"

# Apps served from this docroot as tools.bizorca.com/<dir>, each its own
# codebase with its own deploy. The rsync below runs with --delete, so an
# undeclared subdirectory is not cruft, it is a deleted application. The
# preflight aborts on any server directory that is neither listed here nor in
# this repo: add it here, or remove it from the server, never let rsync decide.
# Their server-side files go in private_html/<dir>/, never loose in private_html.
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

ssh "$SSH_HOST" "test -d ${APP_DIR}" || { echo "${APP_DIR} not found on server" >&2; exit 1; }

UNKNOWN=$(ssh "$SSH_HOST" "cd ${APP_DIR} && find . -mindepth 1 -maxdepth 1 -type d -printf '%f\\n'" | while read -r d; do
    [[ -d "$d" ]] && continue
    for a in ${SUBAPPS[@]+"${SUBAPPS[@]}"}; do [[ "$a" == "$d" ]] && continue 2; done
    echo "$d"
done)
if [[ -n "$UNKNOWN" ]]; then
    echo "Refusing to deploy: these server directories are not in SUBAPPS or this repo," >&2
    echo "and --delete would remove them:" >&2
    echo "$UNKNOWN" | sed 's/^/  /' >&2
    exit 1
fi

PROTECT=()
for a in ${SUBAPPS[@]+"${SUBAPPS[@]}"}; do PROTECT+=(--exclude="/${a}/"); done

BUILD=$(mktemp -d)
trap 'rm -rf "$BUILD"' EXIT
git archive HEAD | tar -x -C "$BUILD"

echo "Deploying $(git log -1 --format='%h %s') to ${SSH_HOST}:${APP_DIR}"

# -rltz, not -a: public_html is owned by the app user, so never try to set owner,
# group or permissions on it. -O: skip directory times. nginx serves .md/.txt as
# plain text here, so they stay excluded.
rsync -rltzO --delete ${DRY[@]+"${DRY[@]}"} ${PROTECT[@]+"${PROTECT[@]}"} -v \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='deploy.sh' \
    --exclude='*.md' \
    --exclude='*.txt' \
    "$BUILD/" "${SSH_HOST}:${APP_DIR}/"

# Cloudways' Varnish caches the static page at the origin (x-cache: HIT), so a
# deploy is invisible until it expires. A PURGE from the server itself clears it.
if [[ ${#DRY[@]} -eq 0 ]]; then
    ssh "$SSH_HOST" 'curl -s -o /dev/null -X PURGE -H "Host: tools.bizorca.com" http://127.0.0.1/'
fi

echo ""
echo "Deployed. Check the origin directly (works before and after the DNS cutover):"
echo "  curl -sk --resolve tools.bizorca.com:443:143.198.64.127 https://tools.bizorca.com/"
