#!/bin/bash
# Deploy Script for apps.bizorca.com (SiteGround)
#
# Usage: ./deploy.sh
#
SSH_OPTS="-p 18765 -i ~/.ssh/bizorca_key"
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
  ./ "${SSH_HOST}:${REMOTE_PUBLIC}/"

echo ""
echo "Deployed!"
echo "  Live at: https://apps.bizorca.com/"
echo ""
