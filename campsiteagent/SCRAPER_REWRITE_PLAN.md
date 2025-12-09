# ScraperService Rewrite Plan

## Current Situation

**Problem**: The UseDirect API (`calirdr.usedirect.com/rdr/rdr`) is returning HTML instead of JSON, suggesting:
- API endpoint structure has changed
- Bot detection/blocking is active
- Authentication/headers requirements changed
- API may have been deprecated

**Impact**: 
- No facilities are being found for any parks
- Scraper returns 0 sites for all parks
- System is completely non-functional

## Investigation Phase

### Step 1: Test Current API Endpoints
**Goal**: Determine if ANY endpoint still works

**Actions**:
1. Test alternative endpoint patterns:
   - `fd/places/{placeId}/facilities` (from discover-parks.php)
   - `fd/place/{placeId}/facilities`
   - `places/{placeId}/facilities`
   - `search/grid` (POST endpoint)
   - `fd/search/grid` (POST endpoint)

2. Test with different headers:
   - Add Referer header
   - Add Origin header
   - Test with cookies/session
   - Test with different Accept headers

3. Test HTML scraping approach:
   - Scrape `www.reservecalifornia.com` directly
   - Parse embedded JSON from booking pages
   - Extract data from JavaScript variables

**Deliverable**: Test script that identifies working endpoints/approaches

### Step 2: Analyze ReserveCalifornia Website
**Goal**: Understand how the public website works

**Actions**:
1. Visit `www.reservecalifornia.com` manually
2. Inspect network requests in browser DevTools
3. Identify:
   - What API endpoints the website actually uses
   - What headers/authentication it sends
   - How it fetches availability data
   - What JavaScript frameworks/libraries it uses

**Deliverable**: Documentation of actual API calls made by the website

## Architecture Options

### Option A: Fix Current API Approach
**If**: API endpoints still work with different headers/format

**Changes**:
- Update endpoint URLs
- Add required headers (Referer, Origin, etc.)
- Implement cookie/session handling if needed
- Add retry logic with exponential backoff

**Pros**: Minimal changes, fast implementation
**Cons**: May break again if API changes

### Option B: HTML Scraping Approach
**If**: API is blocked but website is accessible

**Changes**:
- Scrape `www.reservecalifornia.com` booking pages
- Parse embedded JSON from page source
- Extract data from JavaScript variables
- Use headless browser (Puppeteer/Playwright) if needed

**Pros**: More resilient to API changes
**Cons**: More fragile (HTML structure changes), slower, more complex

### Option C: Hybrid Approach
**If**: Some endpoints work, some don't

**Changes**:
- Try API first, fallback to HTML scraping
- Cache successful API patterns
- Use HTML scraping as backup

**Pros**: Best of both worlds
**Cons**: Most complex to maintain

### Option D: Reverse Engineer New API
**If**: Website uses completely different API structure

**Changes**:
- Monitor browser network traffic
- Identify new API endpoints
- Reverse engineer request format
- Implement new scraper based on findings

**Pros**: Uses official API (if found)
**Cons**: Time-consuming, may break if they change it

## Recommended Approach

**Phase 1: Investigation (1-2 hours)**
1. Create comprehensive test script to try all endpoint patterns
2. Manually inspect ReserveCalifornia website in browser
3. Document actual network requests
4. Determine which approach is viable

**Phase 2: Prototype (2-4 hours)**
1. Implement working approach (A, B, C, or D)
2. Test with one park (San Onofre - known to work before)
3. Verify data structure matches existing database schema
4. Test with Chino Hills SP

**Phase 3: Integration (2-3 hours)**
1. Integrate new scraper into ScraperService
2. Maintain backward compatibility with existing data
3. Add comprehensive error handling
4. Add logging/monitoring

**Phase 4: Testing (1-2 hours)**
1. Test with all active parks
2. Verify weekend detection still works
3. Test reconciliation logic
4. Verify notifications still work

## New Architecture Design

### Proposed Structure

```
ReserveCaliforniaScraper (Interface/Abstract)
├── ReserveCaliforniaApiScraper (if API works)
│   ├── fetchParkFacilities()
│   ├── fetchFacilityAvailability()
│   └── fetchMonthlyAvailability()
│
├── ReserveCaliforniaHtmlScraper (if HTML needed)
│   ├── scrapeParkPage()
│   ├── parseFacilitiesFromHtml()
│   ├── scrapeAvailabilityFromHtml()
│   └── extractJsonFromPage()
│
└── ReserveCaliforniaHybridScraper (fallback)
    ├── Try API first
    ├── Fallback to HTML if API fails
    └── Cache successful methods
```

### Key Design Principles

1. **Separation of Concerns**
   - Scraper only fetches data
   - ScraperService handles business logic
   - Repositories handle data persistence

2. **Error Handling**
   - Graceful degradation
   - Detailed error logging
   - Retry logic with backoff

3. **Extensibility**
   - Easy to swap scraping methods
   - Support multiple data sources
   - Plugin architecture for new parks

4. **Observability**
   - Comprehensive logging
   - Progress callbacks
   - Debug mode

## Implementation Steps

### Step 1: Create Investigation Script
**File**: `app/bin/investigate-api.php`
- Test all known endpoint patterns
- Test with different headers
- Save responses for analysis
- Generate report of findings

### Step 2: Create New Scraper Interface
**File**: `app/src/Services/ScraperInterface.php`
- Define common methods
- Allow multiple implementations

### Step 3: Implement Working Scraper
**File**: `app/src/Services/ReserveCaliforniaScraperV2.php`
- Based on investigation findings
- Implement interface
- Add comprehensive error handling

### Step 4: Update ScraperService
**File**: `app/src/Services/ScraperService.php`
- Use new scraper implementation
- Maintain existing business logic
- Add fallback mechanisms

### Step 5: Testing & Validation
- Test with all parks
- Verify data accuracy
- Performance testing
- Error scenario testing

## Questions to Answer

1. **What endpoints actually work?**
   - Need to test all patterns
   - Document working endpoints

2. **What headers are required?**
   - User-Agent (we have this)
   - Referer? Origin? Cookies?

3. **Is HTML scraping viable?**
   - Can we parse booking pages?
   - Is data embedded in JavaScript?

4. **Should we use a headless browser?**
   - Only if JavaScript rendering is needed
   - Adds complexity and slowness

5. **How do we handle rate limiting?**
   - Add delays between requests
   - Implement exponential backoff
   - Respect robots.txt

## Next Steps

1. **You**: Run investigation script, share results
2. **Me**: Analyze results, determine best approach
3. **Together**: Implement chosen approach
4. **Test**: Verify with real data
5. **Deploy**: Roll out gradually

## Success Criteria

- ✅ Scraper finds facilities for all parks
- ✅ Scraper retrieves availability data
- ✅ Data structure matches existing schema
- ✅ Weekend detection works correctly
- ✅ Notifications still function
- ✅ Performance is acceptable (< 10 min for 6 months)
- ✅ Error handling is robust

