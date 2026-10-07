# apps.bizorca.com — Project Instructions

## Overview
Static HTML landing page for apps.bizorca.com. Portfolio of Bizorca small business apps, "why I'm building this" narrative, services pitch, and TidyCal consultation booking embed.

## Tech Stack
- **Single file**: `index.html` — Tailwind CSS via CDN, no build step
- **Hosting**: Cloudways (143.198.64.127), app folder `qukjzcxeas`
- **Repo**: github.com/bizorca/apps

## Deployment
- **URL**: tools.bizorca.com (Cloudways test URL: phpstack-1676077-6715614.cloudwaysapps.com). apps.bizorca.com is to be redirected here
- **Deploy**: `./deploy.sh` (git archive HEAD + rsync; `--dry-run` to preview). GitHub Actions removed
- **SSH**: `ssh cloudways-bizorca` (see Archipelago root CLAUDE.md)
- **Remote web root**: `applications/qukjzcxeas/public_html` (relative to master home)

## TidyCal Embed
In index.html, find `data-path="YOUR_TIDYCAL_PATH"` and replace with the actual TidyCal booking path (e.g., `jassen/consultation`).

## Editing
Edit `index.html` directly — no build process, no dependencies. After editing, run `./deploy.sh` to push live.
