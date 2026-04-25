# Watercolor.LK – Deployment Guide

This document is the single source of truth for how to develop, deploy, and maintain the Watercolor.LK ecommerce site. Read this at the start of any new development session.

---

## 1. Project Overview

| Detail | Value |
|---|---|
| Project name | Watercolor.LK Ecommerce |
| Live site | https://watercolor.lk/api |
| GitHub repo | https://github.com/Dinu-Sri/watercolorlk-test-erp |
| Default branch | main |
| ERP base URL | https://erppro.lk/public |
| ERP business location | BL0002 (location_id = 5) |
| Local project path | C:\Users\User\Desktop\watercolorlK test ERP |

---

## 2. File Inventory

### Production files (deployed to live server via .cpanel.yml)

| File | Purpose |
|---|---|
| index.html | Product listing page (ecommerce storefront) |
| product.html | Product detail page |
| erp-proxy.php | Server-side proxy to ERP API, handles auth and CORS |

### GitHub-only files (never deployed to server)

| File | Purpose |
|---|---|
| .cpanel.yml | cPanel deployment config, controls which files go live |
| API_REFERENCE.md | Full ERP API documentation and integration notes |
| DEPLOYMENT.md | This file |
| api-test.html | Development-only API tester (not for production) |

---

## 3. Git Branch Strategy

```
main          → always production-ready, deployed to live server
dev           → active development, tested locally before merging
feature/*     → optional, for specific features before merging to dev
```

### Standard workflow

```
1. Work on dev branch locally
2. Test locally
3. Merge dev into main
4. Push main to GitHub
5. Pull and deploy from cPanel
6. Verify live site
```

---

## 4. Local Development

### Prerequisites

Install one of these for local PHP + Apache:
- XAMPP: https://www.apachefriends.org/
- Laragon: https://laragon.org/

### Steps

```powershell
# Navigate to project folder
cd "C:\Users\User\Desktop\watercolorlK test ERP"

# Switch to dev branch
git checkout dev

# Start XAMPP or Laragon (manual step)
# Open browser: http://localhost/watercolorlk/

# Make your changes, test locally
# Then stage, commit, push
git add .
git commit -m "your change description"
git push
```

### Merging dev to main for release

```powershell
git checkout main
git merge dev
git push
# Then deploy from cPanel
```

---

## 5. GitHub Repository

| Detail | Value |
|---|---|
| Repository URL | https://github.com/Dinu-Sri/watercolorlk-test-erp |
| Clone URL | https://github.com/Dinu-Sri/watercolorlk-test-erp.git |
| Visibility | Public |
| GitHub account | Dinu-Sri |

### Useful CLI commands

```powershell
# Check current status
git status

# Pull latest from GitHub
git pull

# Push changes
git add .
git commit -m "describe change"
git push

# View recent commits
git log --oneline -10

# Rollback to a previous commit
# 1. Find the commit hash
git log --oneline

# 2. Revert to that commit (creates a new safe revert commit)
git revert HEAD

# Or hard reset to a specific commit (use with care on main)
git reset --hard <commit-hash>
git push --force
```

---

## 6. cPanel Deployment

### cPanel details

| Detail | Value |
|---|---|
| Deployment type | Git Version Control |
| Clone URL | https://github.com/Dinu-Sri/watercolorlk-test-erp.git |
| Repository path | /home/cpaneluser/repositories/watercolorlk-test-erp |
| Deploy target | $HOME/public_html/api |
| Deployment config | .cpanel.yml |

### Deployment steps (every release)

1. Push code to GitHub main branch (see step 5 above)
2. Open cPanel → Git Version Control
3. Find the watercolorlk-test-erp repository
4. Click Manage
5. Click Pull or Update from Remote
6. Click Deploy HEAD Commit
7. Open https://watercolor.lk/api in browser
8. Hard refresh: Ctrl+F5
9. Verify the change is live

### What .cpanel.yml does

The `.cpanel.yml` file in the repository root controls which files are deployed. It only copies production runtime files:

```yaml
deployment:
  tasks:
    - export DEPLOYPATH=$HOME/public_html/api
    - /bin/mkdir -p $DEPLOYPATH
    - /bin/cp erp-proxy.php $DEPLOYPATH/erp-proxy.php
    - /bin/cp index.html $DEPLOYPATH/index.html
    - /bin/cp product.html $DEPLOYPATH/product.html
```

Documentation files, test files, and the deployment config itself are not copied to the live server.

---

## 7. Rollback a Bad Release

### Option A: Safe revert (recommended)

```powershell
# Creates a new commit that undoes the last bad commit
git revert HEAD
git push
# Then deploy from cPanel as normal
```

### Option B: Rollback to any older commit

```powershell
# Find the commit hash you want to roll back to
git log --oneline

# Hard reset to that commit
git reset --hard <commit-hash>
git push --force

# Then deploy from cPanel as normal
```

### Option C: Rollback directly in GitHub

1. Open https://github.com/Dinu-Sri/watercolorlk-test-erp/commits/main
2. Find the last good commit
3. Click Browse files at that commit
4. Download or copy needed files
5. Re-upload or commit them

---

## 8. ERP API Quick Reference

For full details see API_REFERENCE.md.

| Item | Value |
|---|---|
| Token endpoint | POST /oauth/token |
| Products endpoint | GET /connector/api/product?location_id=5 |
| Locations endpoint | GET /connector/api/business-location |
| Auth type | OAuth2 password grant |
| Client ID | 3 |
| Location ID for Watercolor.LK | 5 |

Credentials are in erp-proxy.php server-side only, never in frontend code.

---

## 9. Adding New Pages or Features

### Standard steps

1. Create the new file locally (example: cart.html, checkout.html)
2. Test locally
3. Add it to `.cpanel.yml` deployment config:

```yaml
- /bin/cp cart.html $DEPLOYPATH/cart.html
```

4. Commit and push all changes including updated .cpanel.yml
5. Deploy from cPanel

---

## 10. If You Need a Database

When cart, orders, or user accounts are added, you will need a MySQL database.

### One-time cPanel setup

1. cPanel → MySQL Databases
2. Create database: watercolorlk_db
3. Create user: watercolorlk_user
4. Assign user to DB with ALL PRIVILEGES
5. Note the credentials

### Config file approach

Create a file outside the public folder on the server (never in GitHub):

```
/home/cpaneluser/config/db.php
```

Content:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'watercolorlk_db');
define('DB_USER', 'watercolorlk_user');
define('DB_PASS', 'your_db_password');
define('DB_PORT', '3306');
```

Load it in PHP files with:

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../config/db.php';
```

Credentials never go into GitHub.

---

## 11. Checklist Before Every Release

- [ ] Tested locally and working
- [ ] No credentials or passwords in any committed file
- [ ] .cpanel.yml updated if new files were added
- [ ] Merged dev into main
- [ ] Pushed to GitHub
- [ ] Pulled in cPanel
- [ ] Deployed HEAD commit in cPanel
- [ ] Verified live URL with hard refresh

---

## 12. Checklist for New Development Session

- [ ] `git pull` in local project folder to get latest code
- [ ] Check current branch: `git branch`
- [ ] Switch to dev: `git checkout dev` or create: `git checkout -b dev`
- [ ] Review last few commits: `git log --oneline -5`
- [ ] Read API_REFERENCE.md if working on ERP integration
- [ ] Start local server (XAMPP or Laragon)
