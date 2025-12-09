# Browser Scraper Deployment Checklist

## Files to Copy to Live Server

### 1. New Files (Required)
- `app/bin/scrape-via-browser.js` - Node.js browser scraper script
- `app/src/Services/BrowserScraper.php` - PHP wrapper for Node.js script
- `app/bin/package.json` - Updated with Puppeteer dependency

### 2. Modified Files (Required)
- `app/src/Services/ReserveCaliforniaScraper.php` - Updated to use browser method

### 3. Installation Steps on Live Server

1. **Copy files to server:**
   ```bash
   # From your local machine
   scp app/bin/scrape-via-browser.js user@server:/var/www/campsite-agent/app/bin/
   scp app/bin/package.json user@server:/var/www/campsite-agent/app/bin/
   scp app/src/Services/BrowserScraper.php user@server:/var/www/campsite-agent/app/src/Services/
   scp app/src/Services/ReserveCaliforniaScraper.php user@server:/var/www/campsite-agent/app/src/Services/
   ```

2. **Make script executable:**
   ```bash
   ssh user@server
   cd /var/www/campsite-agent/app/bin
   chmod +x scrape-via-browser.js
   ```

3. **Install Puppeteer:**
   ```bash
   cd /var/www/campsite-agent/app/bin
   npm install
   ```
   (This will download Chromium ~300MB, takes a few minutes)

4. **Verify installation:**
   ```bash
   node scrape-via-browser.js facilities 627
   ```
   Should output: `[{"FacilityId":"439","Name":"Rolling M. Ranch Campground","PlaceId":"627"}]`

## Files NOT Needed (Do Not Copy)

These are development/debugging files:
- `app/bin/investigate-api.php`
- `app/bin/investigate-website-html.php`
- `app/bin/investigate-browser.js`
- `app/bin/test-chino-hills-api.php`
- `SCRAPER_REWRITE_PLAN.md`
- `SCRAPER_REWRITE_IMPLEMENTATION.md`
- `BROWSER_SCRAPER_SETUP.md`
- `DEPLOYMENT_CHECKLIST.md` (this file)

## Quick Deploy Script

You can create a deploy script to automate this:

```bash
#!/bin/bash
# deploy-scraper.sh

SERVER="user@your-server.com"
SERVER_PATH="/var/www/campsite-agent"

echo "Deploying browser scraper files..."

# Copy files
scp app/bin/scrape-via-browser.js $SERVER:$SERVER_PATH/app/bin/
scp app/bin/package.json $SERVER:$SERVER_PATH/app/bin/
scp app/src/Services/BrowserScraper.php $SERVER:$SERVER_PATH/app/src/Services/
scp app/src/Services/ReserveCaliforniaScraper.php $SERVER:$SERVER_PATH/app/src/Services/

echo "Files copied. Now SSH to server and run:"
echo "  cd $SERVER_PATH/app/bin"
echo "  chmod +x scrape-via-browser.js"
echo "  npm install"
```

## Verification

After deployment, test on the server:
```bash
cd /var/www/campsite-agent/app/bin
node scrape-via-browser.js facilities 627
```

Then test through the admin interface to ensure the full scraper works.


