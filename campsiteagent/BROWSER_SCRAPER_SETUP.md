# Browser Scraper Setup Instructions

## Overview

The ReserveCalifornia API is now blocked, so we've implemented a browser-based scraper using Puppeteer (headless Chrome) to access the website like a real browser.

## Architecture

```
ScraperService.php
    ↓
ReserveCaliforniaScraper.php (PHP)
    ↓ (uses browser method by default)
BrowserScraper.php (PHP wrapper)
    ↓ (executes Node.js script)
scrape-via-browser.js (Node.js + Puppeteer)
    ↓
ReserveCalifornia Website (via headless browser)
```

## Installation Steps

### 1. Install Node.js (if not already installed)

Check if Node.js is installed:
```bash
which node
node --version
```

If not installed, install Node.js:
- **macOS**: `brew install node`
- **Linux**: `apt-get install nodejs npm` or `yum install nodejs npm`

### 2. Install Puppeteer

Navigate to the bin directory and install Puppeteer:
```bash
cd /var/www/campsite-agent/app/bin
npm install puppeteer --save
```

**Note**: If you get permission errors, you may need to:
- Run with `sudo` (not recommended for production)
- Fix npm permissions: `sudo chown -R $(whoami) ~/.npm`
- Or install in a different location

### 3. Verify Installation

Test that the browser scraper works:
```bash
cd /var/www/campsite-agent/app/bin
node scrape-via-browser.js facilities 627
```

This should output JSON with facilities for Chino Hills SP (PlaceId: 627).

### 4. Test Availability Scraping

Test availability fetching:
```bash
node scrape-via-browser.js availability 627 674 2026-01-07 1
```

Replace:
- `627` = PlaceId (Chino Hills SP)
- `674` = FacilityId (you'll get this from the facilities command)
- `2026-01-07` = Start date
- `1` = Number of nights

## Configuration

### Environment Variables

You can control browser usage with environment variables:

- `RC_USE_BROWSER=false` - Disable browser method (will try API only)
- `NODE_PATH=/path/to/node` - Override Node.js path (auto-detected if not set)

### PHP Configuration

The `BrowserScraper` class automatically finds Node.js. If it can't find it, you can:

1. Ensure Node.js is in PATH
2. Set `NODE_PATH` environment variable
3. Pass Node.js path to `BrowserScraper` constructor (requires code change)

## How It Works

1. **PHP calls Node.js script**: `ReserveCaliforniaScraper` uses `BrowserScraper` to execute the Node.js script
2. **Puppeteer launches browser**: The script launches a headless Chrome browser
3. **Navigates to website**: Browser navigates to ReserveCalifornia booking pages
4. **Extracts data**: Script extracts facilities/availability from:
   - Network requests (intercepts API calls)
   - Page DOM (dropdowns, embedded data)
   - JavaScript variables
5. **Returns JSON**: Script outputs JSON to stdout, PHP parses it

## Troubleshooting

### "Node.js not found"
- Ensure Node.js is installed and in PATH
- Check `which node` returns a path
- Try setting `NODE_PATH` environment variable

### "Browser scraper timed out"
- Increase timeout in `BrowserScraper` constructor (default: 60 seconds)
- Check server resources (browser uses memory)
- Verify network connectivity

### "Puppeteer not installed"
- Run `npm install puppeteer` in `/var/www/campsite-agent/app/bin`
- Check `package.json` exists and has puppeteer dependency

### "Permission denied" errors
- Check file permissions on `scrape-via-browser.js` (should be executable)
- Check npm cache permissions: `sudo chown -R $(whoami) ~/.npm`
- Check Node.js binary permissions

### Browser detection issues
- The script includes anti-detection measures (webdriver override, realistic user agent)
- If still detected, may need to add more delays or use different techniques

## Performance Considerations

- **Slower than API**: Browser scraping is much slower (5-10 seconds per request vs <1 second)
- **Resource usage**: Each browser instance uses ~100-200MB RAM
- **Concurrency**: Limit concurrent browser instances to avoid overwhelming server

## Monitoring

Check logs for:
- `ReserveCaliforniaScraper: Browser method failed` - Browser scraping errors
- `ReserveCaliforniaScraper: API method failed` - API fallback errors
- Timeout errors - May need to increase timeout or optimize script

## Next Steps

1. ✅ Install Puppeteer
2. ✅ Test facilities fetching
3. ✅ Test availability fetching
4. ✅ Run full scraper test
5. ✅ Monitor performance and errors

