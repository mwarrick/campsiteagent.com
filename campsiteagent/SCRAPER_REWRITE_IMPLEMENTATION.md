# ScraperService Rewrite - Implementation Plan

## Current Situation
- ✅ All API endpoints (`calirdr.usedirect.com`) return HTML instead of JSON
- ✅ Direct page access returns 403 Forbidden
- ✅ Bot detection is active on ReserveCalifornia website
- ✅ Need to use headless browser to bypass detection

## Solution: Browser-Based Scraper

### Architecture

```
ScraperService.php
    ↓
ReserveCaliforniaScraper.php (PHP)
    ↓
BrowserScraper.php (PHP wrapper)
    ↓
scrape-via-browser.js (Node.js + Puppeteer)
    ↓
ReserveCalifornia Website
```

### Implementation Steps

#### 1. Install Puppeteer
```bash
cd /var/www/campsite-agent/app/bin
npm install puppeteer --save
```

#### 2. Create Browser Scraper (Node.js)
- File: `app/bin/scrape-via-browser.js`
- Uses Puppeteer to:
  - Navigate to ReserveCalifornia pages
  - Wait for JavaScript to load
  - Extract embedded data from page
  - Intercept network requests to find API calls
  - Return JSON data to PHP

#### 3. Create PHP Browser Wrapper
- File: `app/src/Services/BrowserScraper.php`
- Wraps Node.js script execution
- Handles communication between PHP and Node.js
- Manages timeouts and errors

#### 4. Rewrite ReserveCaliforniaScraper
- Update `fetchParkFacilities()` to use browser
- Update `fetchFacilityAvailability()` to use browser
- Keep same interface so ScraperService doesn't need changes

#### 5. Fallback Strategy
- Try browser method first
- If browser fails, log error and return empty array
- Add retry logic with delays

### Key Challenges

1. **Performance**: Browser scraping is slower than API
   - Solution: Cache results, scrape in parallel where possible
   
2. **Reliability**: Browser can be detected
   - Solution: Use realistic user agent, add delays, randomize behavior
   
3. **Data Extraction**: Need to find where data is embedded
   - Solution: Intercept network requests, parse JavaScript variables, extract from DOM

### Testing Strategy

1. Test with Chino Hills SP (PlaceId: 627)
2. Test with San Onofre (PlaceId: 712) - known working park
3. Verify facilities are found
4. Verify availability data is extracted
5. Test error handling

### Next Steps

1. ✅ Create investigation scripts (DONE)
2. ⏳ Install Puppeteer
3. ⏳ Create browser scraper script
4. ⏳ Create PHP wrapper
5. ⏳ Rewrite ReserveCaliforniaScraper
6. ⏳ Test with real parks
7. ⏳ Deploy and monitor

