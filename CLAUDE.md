# apps.bizorca.com — Project Instructions

## Overview
Static HTML landing page for apps.bizorca.com. Portfolio of Bizorca small business apps, "why I'm building this" narrative, services pitch, and TidyCal consultation booking embed.

## Tech Stack
- **Single file**: `index.html` — Tailwind CSS via CDN, no build step
- **Hosting**: SiteGround (same server as astrology.bizorca.com)
- **Repo**: github.com/bizorca/apps

## Deployment
- **URL**: apps.bizorca.com
- **Deploy**: `./deploy.sh` (rsync to SiteGround via SSH)
- **SSH**: `-p 18765 -i ~/.ssh/bizorca_key u2361-smkk6swqmgxj@gcam1203.siteground.biz`
- **Remote web root**: `/home/customer/www/apps.bizorca.com/public_html/`

## TidyCal Embed
In index.html, find `data-path="YOUR_TIDYCAL_PATH"` and replace with the actual TidyCal booking path (e.g., `jassen/consultation`).

## Editing
Edit `index.html` directly — no build process, no dependencies. After editing, run `./deploy.sh` to push live.
