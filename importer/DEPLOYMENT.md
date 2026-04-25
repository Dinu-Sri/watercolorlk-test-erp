# Deployment Guide - Google Reviews Importer

This guide explains how to deploy the review importer to your live production server.

## Pre-Deployment Checklist

- [ ] Test locally with `php importer/validate.php`
- [ ] Confirm MySQL is running on the server
- [ ] Database `watercolorlk_store` exists
- [ ] User has database credentials configured in `config/local.php`
- [ ] `importer/reviews_images/` directory is writable
- [ ] All code files are committed to git

## Deployment Steps

### 1. Pull Latest Code

```bash
cd /path/to/watercolorlk
git pull origin main
```

### 2. Set Database Credentials (If Not Already Set)

Edit `config/local.php` on the server:

```php
<?php
return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_NAME' => 'watercolorlk_store',
    'DB_USER' => 'watercolor_user',     // Your cPanel MySQL user
    'DB_PASS' => 'your_password',       // Your cPanel MySQL password
];
```

### 3. Create Database Table

If this is the first deployment, create the Google reviews table:

```bash
php importer/setup.php
```

Expected output:
```
✅ Table 'google_reviews' already exists
📊 Reviews in database: 0
```

### 4. Run the Scraper (First Time Only)

From your local machine or a server with Python/Selenium:

```bash
cd tmp-google-reviews-scraper-pro
python start.py scrape --url "https://www.google.com/maps/place/Watercolor.LK/@7.4827764,79.8408781,15z/data=..." --max-reviews 100
cd ..
```

### 5. Upload JSON File to Server

Transfer `google_reviews.json` from the scraper folder to the server:

```bash
# From your local machine
scp tmp-google-reviews-scraper-pro/google_reviews.json \
    user@yourserver.com:/path/to/watercolorlk/tmp-google-reviews-scraper-pro/
```

Or if using FTP/cPanel File Manager:
- Upload to `tmp-google-reviews-scraper-pro/google_reviews.json`

### 6. Import Reviews

SSH into your server and run:

```bash
cd /path/to/watercolorlk
php importer/import.php
```

Expected output:
```
✅ IMPORT COMPLETE
  Total Processed:    25
  Newly Imported:     23
  Updated:            2
  Images Downloaded:  24/25
  
📊 Total Reviews in DB: 48
✨ You can now view reviews on your product page!
```

## New Workflow: Upload JSON + Profile Images, Then Push to DB

Use this flow when you want the server to use already-downloaded profile images (no re-download during import).

### 1. Run scraper locally

```bash
cd tmp-google-reviews-scraper-pro
python start.py scrape --url "https://www.google.com/maps/place/Watercolor.LK/..." --max-reviews 100 -q
cd ..
```

This generates:
- `tmp-google-reviews-scraper-pro/google_reviews.json`
- `tmp-google-reviews-scraper-pro/review_images/[place_id]/profiles/*`

### 2. Upload artifacts to cPanel server

From Windows PowerShell:

```powershell
./scripts/upload-reviews-to-cpanel.ps1 `
  -Server your.server.com `
  -Username your_ssh_user `
  -RemoteProjectPath /home/yourcpanel/repositories/watercolorlk-test-erp
```

### 3. Push uploaded data into MySQL

On server:

```bash
cd /home/yourcpanel/repositories/watercolorlk-test-erp
php importer/push-uploaded-reviews.php
```

Optional full refresh:

```bash
php importer/push-uploaded-reviews.php --delete-existing
```

You can also trigger this from browser/URL (protected by `SYNC_WEBHOOK_KEY`):

```text
https://your-domain.com/api/importer/push-uploaded-reviews.php?key=YOUR_SYNC_WEBHOOK_KEY
```

Optional URL parameters:
- `delete_existing=1` to clear and re-import
- `json=/absolute/path/to/google_reviews.json`
- `images=/absolute/path/to/review_images`

What this script does:
- Reads uploaded `google_reviews.json`
- Imports/updates reviews in `google_reviews`
- Copies uploaded profile images into `importer/reviews_images/profiles/`
- Stores matching `profile_picture_local_path` values in DB

### 7. Verify Reviews Are Displaying

Test on your live product page:
- Visit: `https://yoursite.com/product.php?id=1`
- Scroll down to see the Google reviews section
- Check that images load correctly
- Verify ratings and text display properly

## For cPanel Hosting

If your hosting uses cPanel:

### SSH Access

1. Login to cPanel
2. Go to **Advanced → Terminal**
3. Run commands as described above

### Setting File Permissions

If you get "permission denied" errors:

```bash
chmod -R 755 importer/reviews_images/
chmod 644 config/local.php
```

### Database Credentials

Find your MySQL credentials in cPanel:
- **Databases → MySQL Databases**
- Look for your database user and host

## Recurring Imports

To update reviews regularly:

### Option 1: Manual Update (Simple)

Run whenever you want fresh reviews:

```bash
php importer/import.php
```

### Option 2: Automated with Cron (Advanced)

1. **Schedule the scraper** (daily at 2 AM):
   ```bash
   0 2 * * * cd /path/to/watercolorlk && python tmp-google-reviews-scraper-pro/start.py scrape --url "..." -q > /dev/null 2>&1
   ```

2. **Schedule the importer** (daily at 3 AM):
   ```bash
   0 3 * * * cd /path/to/watercolorlk && php importer/import.php > /dev/null 2>&1
   ```

Access cron jobs in cPanel:
- **Advanced → Cron Jobs**
- Add the commands above
- Set Email address to receive logs/errors

## Troubleshooting

### Error: "Can't connect to MySQL server"

**Solution:** Verify database credentials in `config/local.php`
```bash
php -r "require 'bootstrap.php'; echo appDb() ? 'Connected!' : 'Failed';"
```

### Error: "Permission denied" on image directory

**Solution:** Fix directory permissions
```bash
mkdir -p importer/reviews_images/profiles
chmod -R 755 importer/reviews_images/
chown -R username:groupname importer/reviews_images/  # Replace with your user
```

### Reviews not showing on product page

**Solution:** Check if reviews are in database
```bash
php importer/validate.php
```

Then check product page includes the review section (see `INTEGRATION.md`).

### Images not downloading

**Solution:** Images are optional. The importer will:
- Skip images that fail to download
- Still store review text and data
- Log which images failed

You can re-run the importer to try again.

## Rollback / Remove All Reviews

If you need to delete all imported reviews:

```bash
php importer/import.php --delete-existing
```

This will:
1. Clear the `google_reviews` table
2. Re-import fresh from `google_reviews.json`
3. Download all images again

## Security Notes

1. **Never commit `config/local.php`** - It contains passwords
2. **Protect database credentials** - Only admins should see `local.php`
3. **Image folder should not be web-accessible for modification** - Only reading is allowed
4. **Validate JSON file** - Use `php importer/validate.php` before importing

## Monitoring

Check import logs after each run:

```bash
ls -lah importer/import_*.log
tail -f importer/import_YYYY-MM-DD_HH-MM-SS.log
```

Each import creates a timestamped log file with:
- Review count
- Image download status
- Any errors encountered
- Database statistics

## Support

For issues:
1. Check the import log: `importer/import_*.log`
2. Run validator: `php importer/validate.php`
3. Verify database: `php -r "require 'bootstrap.php'; var_dump(appDb()->query('SHOW TABLES LIKE \"google_reviews\"')->fetch());"`
