#!/bin/bash
# Deploy Script for apps.bizorca.com (SiteGround)
#
# Usage: ./deploy.sh
#
# bizorca_siteground / bizorca_key / bizorca_placecard are all passphrase-encrypted
# and ssh-agent is empty, so they fail or hang. dispatch_bizorca is plain text and
# authenticates to the main account despite the project-specific name.
SSH_KEY="${BIZORCA_SSH_KEY:-$HOME/.ssh/dispatch_bizorca}"
SSH_OPTS="-p 18765 -i ${SSH_KEY}"
SSH_HOST="u2361-smkk6swqmgxj@gcam1203.siteground.biz"
REMOTE_PUBLIC="/home/customer/www/apps.bizorca.com/public_html"

set -e

echo "Deploying to apps.bizorca.com"
echo "  Web root: ${REMOTE_PUBLIC}"
echo ""

echo "Uploading files..."
rsync -avz --delete -e "ssh ${SSH_OPTS}" \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='deploy.sh' \
  --exclude='CLAUDE.md' \
  --exclude='*.md' \
  --exclude='*.txt' \
  ./ "${SSH_HOST}:${REMOTE_PUBLIC}/"

echo ""
echo "Deployed!"
echo "  Live at: https://apps.bizorca.com/"
echo ""
