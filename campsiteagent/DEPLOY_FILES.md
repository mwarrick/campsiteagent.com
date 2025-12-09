# Files to Deploy to Live Server

## Files to Copy (Required)

### `app/bin/`
- ✅ `scrape-via-browser.js` - Node.js browser scraper script (UPDATED - fixed availability scraping)
- ✅ `package.json` - Updated with Puppeteer dependency

### `app/src/Services/`
- ✅ `BrowserScraper.php` - PHP wrapper for Node.js script (NEW)
- ✅ `ReserveCaliforniaScraper.php` - Updated to use browser method (MODIFIED)

---

## Files NOT to Deploy (Debug/Development Only)

### `app/bin/` (Do NOT copy)
- ❌ `investigate-api.php` - Debug script
- ❌ `investigate-website-html.php` - Debug script
- ❌ `investigate-browser.js` - Debug script
- ❌ `test-chino-hills-api.php` - Test script

### Root directory (Do NOT copy)
- ❌ `SCRAPER_REWRITE_PLAN.md` - Documentation
- ❌ `SCRAPER_REWRITE_IMPLEMENTATION.md` - Documentation
- ❌ `BROWSER_SCRAPER_SETUP.md` - Documentation
- ❌ `DEPLOYMENT_CHECKLIST.md` - Documentation
- ❌ `DEPLOY_FILES.md` - This file
- ❌ `TROUBLESHOOTING.md` - Documentation

---

## Quick Deploy Commands

```bash
# From your local machine
scp app/bin/scrape-via-browser.js root@your-server:/var/www/campsite-agent/app/bin/
scp app/bin/package.json root@your-server:/var/www/campsite-agent/app/bin/
scp app/src/Services/BrowserScraper.php root@your-server:/var/www/campsite-agent/app/src/Services/
scp app/src/Services/ReserveCaliforniaScraper.php root@your-server:/var/www/campsite-agent/app/src/Services/
```

## After Copying (On Server)

```bash
# Make script executable
chmod +x /var/www/campsite-agent/app/bin/scrape-via-browser.js

# Install Puppeteer (if not already done)
cd /var/www/campsite-agent/app/bin
npm install
```

