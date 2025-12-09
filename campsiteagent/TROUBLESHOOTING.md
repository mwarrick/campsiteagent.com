# Troubleshooting Browser Scraper

## Issue: No Sites Found After Deployment

If the scraper runs but finds 0 sites, check the following:

### 1. Check Server Error Logs

```bash
# Check PHP error log
tail -f /var/log/php-fpm/error.log
# or
tail -f /var/log/apache2/error.log
# or check your PHP error log location
```

Look for errors like:
- "Browser method failed for availability"
- "Node.js not found"
- "Browser scraper timed out"
- "No grid data or units returned"

### 2. Test Node.js Script Directly

SSH to server and test:

```bash
cd /var/www/campsite-agent/app/bin

# Test facilities (should work)
node scrape-via-browser.js facilities 627

# Test availability (may not work yet - this is the issue)
node scrape-via-browser.js availability 627 439 2026-01-07 1
```

### 3. Check Browser Scraper Permissions

```bash
# Make sure script is executable
chmod +x /var/www/campsite-agent/app/bin/scrape-via-browser.js

# Check Node.js path
which node
node --version

# Check Puppeteer is installed
cd /var/www/campsite-agent/app/bin
ls node_modules/puppeteer
```

### 4. Check PHP Can Execute Node.js

Test from PHP:

```bash
cd /var/www/campsite-agent/app/bin
php -r "echo exec('node --version');"
```

### 5. Common Issues

**Issue: "Node.js not found"**
- Solution: Install Node.js or set NODE_PATH environment variable
- Check: `which node` on server

**Issue: "Browser scraper timed out"**
- Solution: Increase timeout in `BrowserScraper.php` constructor
- Current default: 60 seconds

**Issue: "No grid data or units returned"**
- This means facilities are found but availability is not
- The availability scraping function needs to be fixed
- Check if the page interaction is working

**Issue: "Facilities found but 0 sites"**
- Facilities are working (good!)
- Availability scraping is not working (needs fix)
- The availability function needs to properly capture grid data from API

### 6. Debug Mode

Add this to see what's happening:

In `ReserveCaliforniaScraper.php`, the debug callback should show:
- "🔍 Found X facility(ies)"
- "→ Fetching facility: ..."
- "⚠️ No grid data or units returned"

Check the admin scraping interface for these debug messages.

### 7. Next Steps

The availability scraping function (`scrapeAvailability` in `scrape-via-browser.js`) needs to:
1. Navigate to the park page
2. Select the facility
3. Set the date
4. Trigger search
5. Capture the grid API response

Currently, it's trying to do this but may not be capturing the right API response. We need to identify the correct API endpoint for grid/availability data.


