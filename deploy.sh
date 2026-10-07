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

DRY=()
[[ "${1:-}" == "--dry-run" ]] && DRY=(--dry-run)

cd "$(dirname "$0")"

if ! git diff --quiet HEAD --; then
    echo "Uncommitted changes to tracked files. This deploys HEAD only, so commit first." >&2
    git status --short --untracked-files=no >&2
    exit 1
fi

ssh "$SSH_HOST" "test -d ${APP_DIR}" || { echo "${APP_DIR} not found on server" >&2; exit 1; }

BUILD=$(mktemp -d)
trap 'rm -rf "$BUILD"' EXIT
git archive HEAD | tar -x -C "$BUILD"

echo "Deploying $(git log -1 --format='%h %s') to ${SSH_HOST}:${APP_DIR}"

# -rltz, not -a: public_html is owned by the app user, so never try to set owner,
# group or permissions on it. -O: skip directory times. nginx serves .md/.txt as
# plain text here, so they stay excluded.
rsync -rltzO --delete ${DRY[@]+"${DRY[@]}"} -v \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='deploy.sh' \
    --exclude='*.md' \
    --exclude='*.txt' \
    "$BUILD/" "${SSH_HOST}:${APP_DIR}/"

echo ""
echo "Deployed. Check the origin directly (works before and after the DNS cutover):"
echo "  curl -sk --resolve tools.bizorca.com:443:143.198.64.127 https://tools.bizorca.com/"
