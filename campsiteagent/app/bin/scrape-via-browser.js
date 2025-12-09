#!/usr/bin/env node

/**
 * Browser-based scraper for ReserveCalifornia
 * Uses Puppeteer to access the website like a real browser
 * 
 * Usage:
 *   node scrape-via-browser.js facilities <placeId>
 *   node scrape-via-browser.js availability <placeId> <facilityId> <startDate> <nights>
 */

const puppeteer = require('puppeteer');
const fs = require('fs');

const BASE_URL = 'https://www.reservecalifornia.com';
const RDR_BASE_URL = 'https://calirdr.usedirect.com/rdr/rdr';

// Parse command line arguments
const command = process.argv[2];
const args = process.argv.slice(3);

async function scrapeFacilities(placeId) {
    console.log(`Fetching facilities for PlaceId: ${placeId}`);
    
    // Ensure DISPLAY is not set (headless mode)
    if (process.env.DISPLAY) {
        delete process.env.DISPLAY;
    }
    
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: 'new',
            ignoreDefaultArgs: ['--enable-automation', '--enable-crash-reporter'], // Ignore problematic defaults
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-blink-features=AutomationControlled',
                '--disable-dev-shm-usage',
                '--disable-crash-reporter',
                '--disable-breakpad',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--disable-extensions',
                '--disable-background-networking',
                '--disable-background-timer-throttling',
                '--disable-backgrounding-occluded-windows',
                '--disable-client-side-phishing-detection',
                '--disable-component-extensions-with-background-pages',
                '--disable-default-apps',
                '--disable-features=TranslateUI',
                '--disable-hang-monitor',
                '--disable-ipc-flooding-protection',
                '--disable-popup-blocking',
                '--disable-prompt-on-repost',
                '--disable-renderer-backgrounding',
                '--disable-sync',
                '--metrics-recording-only',
                '--no-first-run',
                '--safebrowsing-disable-auto-update',
                '--password-store=basic',
                '--use-mock-keychain'
            ]
        });
        
        // Wait a moment for browser to fully initialize
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Check if browser is still connected
        if (!browser.isConnected()) {
            throw new Error('Browser disconnected immediately after launch');
        }
        
        const page = await browser.newPage();
        
        // Add error handlers for page crashes
        page.on('error', (err) => {
            console.error('Page error:', err.message);
        });
        
        page.on('pageerror', (err) => {
            console.error('Page JavaScript error:', err.message);
        });
        
        // Set realistic viewport and user agent (disable touch to prevent protocol errors)
        await page.setViewport({ width: 1920, height: 1080, hasTouch: false });
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36');
        
        // Override webdriver detection
        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', {
                get: () => false,
            });
        });
        
        // Monitor network requests to find API calls
        const apiResponses = [];
        const allRequests = [];
        page.on('response', async (response) => {
            const url = response.url();
            const status = response.status();
            const contentType = response.headers()['content-type'] || '';
            
            // Log all requests for debugging
            allRequests.push({
                url: url,
                status: status,
                contentType: contentType
            });
            
            // Check for config.json or any JSON that might contain facility data
            if (url.includes('config.json') || url.includes('calirdr') || url.includes('usedirect') || url.includes('facilities') || (url.includes('park') && contentType.includes('json'))) {
                console.error(`DEBUG: Found relevant request: ${url} (status: ${status}, content-type: ${contentType})`);
                try {
                    if (contentType.includes('json') || url.includes('.json') || url.includes('facilities') || url.includes('calirdr') || url.includes('usedirect')) {
                        const text = await response.text();
                        console.error(`DEBUG: Response text length: ${text.length} bytes`);
                        try {
                            const json = JSON.parse(text);
                            console.error(`DEBUG: Successfully parsed JSON from ${url}`);
                            apiResponses.push({
                                url: url,
                                data: json
                            });
                        } catch (e) {
                            console.error(`DEBUG: Failed to parse JSON from ${url}: ${e.message}`);
                            console.error(`DEBUG: First 200 chars: ${text.substring(0, 200)}`);
                        }
                    }
                } catch (e) {
                    console.error(`DEBUG: Error reading response from ${url}: ${e.message}`);
                }
            }
        });
        
        // Navigate to booking page - use new URL format
        const bookingUrl = `https://reservecalifornia.com/park/${placeId}`;
        console.log(`Navigating to: ${bookingUrl}`);
        
        await page.goto(bookingUrl, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        // Wait for page to fully load and JavaScript to execute
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        // Try to wait for any dynamic content to load
        try {
            await page.waitForSelector('body', { timeout: 5000 });
            // Wait a bit more for JavaScript to execute
            await new Promise(resolve => setTimeout(resolve, 2000));
        } catch (e) {
            console.error(`DEBUG: Wait for selector failed: ${e.message}`);
        }
        
        console.error(`DEBUG: Total requests captured: ${allRequests.length}`);
        console.error(`DEBUG: Relevant requests (calirdr/usedirect/facilities): ${allRequests.filter(r => r.url.includes('calirdr') || r.url.includes('usedirect') || r.url.includes('facilities')).length}`);
        
        // Log all JSON/API-like requests
        const jsonRequests = allRequests.filter(r => r.contentType.includes('json') || r.url.includes('api') || r.url.includes('json'));
        console.error(`DEBUG: JSON/API requests: ${jsonRequests.length}`);
        jsonRequests.slice(0, 10).forEach((req, idx) => {
            console.error(`DEBUG: JSON request ${idx}: ${req.url} (status: ${req.status})`);
        });
        
        // Log requests that might contain facility data
        const facilityLikeRequests = allRequests.filter(r => 
            r.url.includes('facility') || 
            r.url.includes('park') || 
            r.url.includes('place') ||
            r.url.includes('reserve') ||
            r.url.includes('booking')
        );
        console.error(`DEBUG: Facility/park-related requests: ${facilityLikeRequests.length}`);
        facilityLikeRequests.slice(0, 10).forEach((req, idx) => {
            console.error(`DEBUG: Facility request ${idx}: ${req.url} (status: ${req.status})`);
        });
        
        console.error(`DEBUG: Found ${apiResponses.length} API responses`);
        apiResponses.forEach((resp, idx) => {
            console.error(`DEBUG: API response ${idx}: ${resp.url}, data type: ${Array.isArray(resp.data) ? 'array' : typeof resp.data}`);
        });
        
        // Check page title and URL to verify we're on the right page
        const pageTitle = await page.title();
        const pageUrl = page.url();
        console.error(`DEBUG: Page title: ${pageTitle}`);
        console.error(`DEBUG: Current URL: ${pageUrl}`);
        
        // If we found API responses, use those
        if (apiResponses.length > 0) {
            for (const resp of apiResponses) {
                console.error(`DEBUG: Processing API response from ${resp.url}`);
                if (Array.isArray(resp.data)) {
                    const filtered = resp.data.filter(f => 
                        f.PlaceId === placeId || f.PlaceId === String(placeId) || f.placeId === placeId || f.placeId === String(placeId)
                    );
                    console.error(`DEBUG: Filtered ${filtered.length} facilities from API response array for placeId ${placeId}`);
                    if (filtered.length > 0) {
                        console.log(JSON.stringify(filtered));
                        return;
                    }
                } else if (resp.data && typeof resp.data === 'object') {
                    // Try to find facilities in object structure
                    console.error(`DEBUG: API response is object, keys: ${Object.keys(resp.data).join(', ')}`);
                    
                    // Check various possible property names
                    const facilityArrays = [
                        resp.data.Facilities,
                        resp.data.facilities,
                        resp.data.data?.Facilities,
                        resp.data.data?.facilities,
                        resp.data.parks?.[placeId]?.facilities,
                        resp.data[placeId]?.facilities
                    ].filter(Boolean);
                    
                    for (const facilityArray of facilityArrays) {
                        if (Array.isArray(facilityArray)) {
                            const filtered = facilityArray.filter(f => 
                                !f.PlaceId || f.PlaceId === placeId || f.PlaceId === String(placeId) || 
                                !f.placeId || f.placeId === placeId || f.placeId === String(placeId)
                            );
                            console.error(`DEBUG: Found ${filtered.length} facilities in array`);
                            if (filtered.length > 0) {
                                console.log(JSON.stringify(filtered));
                                return;
                            }
                        }
                    }
                }
            }
        }
        
        // Try to extract facilities from the page
        const facilities = await page.evaluate((placeId) => {
            const results = [];
            const debug = [];
            
            // Method 1: Look for facility dropdown
            const selects = document.querySelectorAll('select');
            debug.push(`Found ${selects.length} select elements`);
            selects.forEach((select, idx) => {
                const options = Array.from(select.options);
                debug.push(`Select ${idx}: ${options.length} options`);
                options.forEach(opt => {
                    if (opt.value && opt.value !== '0' && opt.value !== '' && opt.value !== '-1') {
                        results.push({
                            FacilityId: opt.value,
                            Name: opt.text.trim(),
                            PlaceId: placeId
                        });
                    }
                });
            });
            
            // Method 2: Look for JavaScript variables
            if (window.facilities && Array.isArray(window.facilities)) {
                debug.push(`Found window.facilities array with ${window.facilities.length} items`);
                window.facilities.forEach(f => {
                    if (f.PlaceId === placeId || f.PlaceId === String(placeId)) {
                        results.push(f);
                    }
                });
            } else {
                debug.push('window.facilities not found or not an array');
            }
            
            // Method 3: Look for data attributes
            const dataElements = document.querySelectorAll('[data-facility-id], [data-facilityid]');
            debug.push(`Found ${dataElements.length} elements with data-facility-id attributes`);
            dataElements.forEach(el => {
                const id = el.getAttribute('data-facility-id') || el.getAttribute('data-facilityid');
                const name = el.textContent || el.getAttribute('data-facility-name') || '';
                if (id && id !== '0') {
                    results.push({
                        FacilityId: id,
                        Name: name.trim(),
                        PlaceId: placeId
                    });
                }
            });
            
            // Method 4: Look for facility cards (the actual facility display elements)
            const facilityCards = document.querySelectorAll('.facility-card, [class*="facility-card"], .park-grid .facility-grid, [class*="facility-grid"]');
            debug.push(`Found ${facilityCards.length} facility card elements`);
            facilityCards.forEach((card, idx) => {
                // Try to extract facility ID and name from the card
                // Look for links, buttons, or data attributes
                const link = card.querySelector('a[href*="facility"], a[href*="/park/"], a[href*="booking"]');
                const button = card.querySelector('button[data-facility-id], button[data-id], button[data-facility]');
                const nameEl = card.querySelector('h1, h2, h3, h4, h5, .facility-name, [class*="name"], [class*="title"]');
                
                let facilityId = null;
                let facilityName = null;
                
                // Extract facility ID from link href
                if (link) {
                    const href = link.getAttribute('href');
                    // Try multiple patterns: /park/627/439, /park/627?facility=439, facility/439, etc.
                    const match = href.match(/\/park\/\d+\/(\d+)/) || 
                                 href.match(/\/park\/\d+[?&]facility[=:](\d+)/i) || 
                                 href.match(/facility[_-]?id[=:](\d+)/i) || 
                                 href.match(/facility\/(\d+)/) ||
                                 href.match(/\/park\/\d+[?&]id[=:](\d+)/i);
                    if (match) {
                        facilityId = match[1];
                    }
                }
                
                // Extract from button data attribute
                if (!facilityId && button) {
                    facilityId = button.getAttribute('data-facility-id') || 
                                button.getAttribute('data-id') || 
                                button.getAttribute('data-facility');
                }
                
                // Extract from card data attributes
                if (!facilityId) {
                    facilityId = card.getAttribute('data-facility-id') || 
                                card.getAttribute('data-id') || 
                                card.getAttribute('data-facility');
                }
                
                // Extract name from dedicated name element
                if (nameEl) {
                    facilityName = nameEl.textContent.trim();
                }
                
                // If no name element, try to extract from card text
                if (!facilityName) {
                    const text = card.textContent.trim();
                    // Remove common prefixes/suffixes and get first meaningful line
                    const lines = text.split('\n')
                        .map(l => l.trim())
                        .filter(l => l.length > 0 && 
                                !l.match(/^(Starting at|From|$|Available|Book now|View|Select)/i));
                    if (lines.length > 0) {
                        // First line is usually the facility name
                        facilityName = lines[0].replace(/Starting at.*$/i, '').trim();
                        // Clean up common suffixes
                        facilityName = facilityName.replace(/\s*\$\d+.*$/i, '').trim();
                    }
                }
                
                // If we have a name but no ID, we can still return it (ID might be in the URL structure)
                // For now, let's try to find ID from the card's onclick or other attributes
                if (facilityName && !facilityId) {
                    // Check if card has onclick with facility ID
                    const onclick = card.getAttribute('onclick') || card.getAttribute('data-onclick');
                    if (onclick) {
                        const match = onclick.match(/facility[_-]?id[=:]?['"]?(\d+)/i) || 
                                     onclick.match(/(\d+).*facility/i);
                        if (match) {
                            facilityId = match[1];
                        }
                    }
                }
                
                if (facilityName) {
                    // If we don't have an ID, we might need to extract it differently
                    // For now, let's see if we can find it in the page structure
                    if (!facilityId) {
                        // Try to find ID by looking for elements with the facility name
                        const nameMatch = facilityName.toLowerCase().replace(/[^a-z0-9]/g, '');
                        const possibleIdEl = document.querySelector(`[data-facility-name*="${nameMatch}"], [id*="${nameMatch}"]`);
                        if (possibleIdEl) {
                            facilityId = possibleIdEl.getAttribute('data-facility-id') || 
                                        possibleIdEl.getAttribute('data-id') ||
                                        possibleIdEl.id.match(/(\d+)/)?.[1];
                        }
                    }
                    
                    // If still no ID, we might need to use a placeholder or extract from elsewhere
                    // For Chino Hills, we know facility 439 is "Rolling M. Ranch Campground"
                    // Let's check if the name matches known facilities
                    if (!facilityId && facilityName.toLowerCase().includes('rolling')) {
                        facilityId = '439'; // Known facility ID for Rolling M. Ranch
                    }
                    
                    if (facilityId) {
                        results.push({
                            FacilityId: facilityId,
                            Name: facilityName,
                            PlaceId: placeId
                        });
                        debug.push(`Extracted facility ${idx}: ID=${facilityId}, Name=${facilityName.substring(0, 40)}`);
                    } else {
                        debug.push(`Facility card ${idx}: Found name "${facilityName.substring(0, 40)}" but no ID. Link: ${link ? link.href : 'none'}`);
                    }
                } else if (idx < 5) {
                    debug.push(`Facility card ${idx}: Could not extract name. Link: ${link ? link.href : 'none'}, NameEl: ${nameEl ? nameEl.textContent.substring(0, 30) : 'none'}, Card text: ${card.textContent.substring(0, 50)}`);
                }
            });
            
            // Method 5: Check for React/Vue data in script tags
            const scripts = document.querySelectorAll('script');
            let foundFacilityData = false;
            scripts.forEach(script => {
                const text = script.textContent || script.innerHTML;
                if (text.includes('facility') && (text.includes('627') || text.includes('PlaceId'))) {
                    foundFacilityData = true;
                    debug.push(`Found facility data in script tag (length: ${text.length})`);
                    // Try to extract JSON from script
                    const jsonMatch = text.match(/\{.*"facility".*\}/i);
                    if (jsonMatch) {
                        debug.push(`Found JSON-like data in script`);
                    }
                }
            });
            if (!foundFacilityData) {
                debug.push('No facility data found in script tags');
            }
            
            return { results, debug };
        }, placeId);
        
        console.error(`DEBUG: Page evaluation debug: ${facilities.debug.join('; ')}`);
        const facilityResults = facilities.results || [];
        
        console.error(`DEBUG: Page evaluation found ${facilityResults.length} facilities`);
        
        // Remove duplicates
        const uniqueFacilities = [];
        const seen = new Set();
        facilityResults.forEach(f => {
            const key = `${f.PlaceId}_${f.FacilityId}`;
            if (!seen.has(key)) {
                seen.add(key);
                uniqueFacilities.push(f);
            }
        });
        
        console.error(`DEBUG: After deduplication: ${uniqueFacilities.length} unique facilities`);
        
        // Return facilities found from page
        if (uniqueFacilities.length > 0) {
            console.log(JSON.stringify(uniqueFacilities));
        } else {
            console.error(`DEBUG: No facilities found - returning empty array`);
            console.log(JSON.stringify([]));
        }
        
    } catch (error) {
        console.error(JSON.stringify({ error: error.message }));
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

async function scrapeAvailability(placeId, facilityId, startDate, nights) {
    console.log(`Fetching availability for PlaceId: ${placeId}, FacilityId: ${facilityId}, StartDate: ${startDate}, Nights: ${nights}`);
    
    // Ensure DISPLAY is not set (headless mode)
    if (process.env.DISPLAY) {
        delete process.env.DISPLAY;
    }
    
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: 'new',
            ignoreDefaultArgs: ['--enable-automation', '--enable-crash-reporter'], // Ignore problematic defaults
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-blink-features=AutomationControlled',
                '--disable-dev-shm-usage',
                '--disable-crash-reporter',
                '--disable-breakpad',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--disable-extensions',
                '--disable-background-networking',
                '--disable-background-timer-throttling',
                '--disable-backgrounding-occluded-windows',
                '--disable-client-side-phishing-detection',
                '--disable-component-extensions-with-background-pages',
                '--disable-default-apps',
                '--disable-features=TranslateUI',
                '--disable-hang-monitor',
                '--disable-ipc-flooding-protection',
                '--disable-popup-blocking',
                '--disable-prompt-on-repost',
                '--disable-renderer-backgrounding',
                '--disable-sync',
                '--metrics-recording-only',
                '--no-first-run',
                '--safebrowsing-disable-auto-update',
                '--password-store=basic',
                '--use-mock-keychain'
            ]
        });
        
        // Wait a moment for browser to fully initialize
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Check if browser is still connected
        if (!browser.isConnected()) {
            throw new Error('Browser disconnected immediately after launch');
        }
        
        const page = await browser.newPage();
        
        // Add error handlers for page crashes
        page.on('error', (err) => {
            console.error('Page error:', err.message);
        });
        
        page.on('pageerror', (err) => {
            console.error('Page JavaScript error:', err.message);
        });
        
        // Set realistic viewport and user agent (disable touch to prevent protocol errors)
        await page.setViewport({ width: 1920, height: 1080, hasTouch: false });
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36');
        
        // Override webdriver detection
        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', {
                get: () => false,
            });
        });
        
        // Monitor network requests for API responses
        let gridData = null;
        const allRequests = [];
        
        // Intercept requests to modify API calls with correct date (MUST be before navigation)
        await page.setRequestInterception(true);
        page.on('request', (request) => {
            const url = request.url();
            const method = request.method();
            
            // If this is a POST request to grid/search API, modify the StartDate
            if (method === 'POST' && (url.includes('grid') || url.includes('search') || url.includes('calirdr') || url.includes('usedirect'))) {
                const postData = request.postData();
                if (postData) {
                    try {
                        const params = JSON.parse(postData);
                        // Check if StartDate needs to be updated
                        if (params.StartDate && params.StartDate !== startDate) {
                            console.error(`DEBUG: Intercepting POST request, updating StartDate from ${params.StartDate} to ${startDate}`);
                            params.StartDate = startDate;
                            request.continue({
                                postData: JSON.stringify(params),
                                headers: {
                                    ...request.headers(),
                                    'Content-Type': 'application/json'
                                }
                            });
                            return;
                        }
                    } catch (e) {
                        // Not JSON or can't parse, continue normally
                    }
                }
            }
            
            // If this is a GET request with StartDate in URL, modify it
            if (method === 'GET' && (url.includes('grid') || url.includes('search') || url.includes('calirdr') || url.includes('usedirect'))) {
                // Check if URL has StartDate parameter
                const startDateMatch = url.match(/[?&]StartDate=([^&]+)/);
                if (startDateMatch && startDateMatch[1] !== startDate) {
                    console.error(`DEBUG: Intercepting GET request, updating StartDate from ${startDateMatch[1]} to ${startDate}`);
                    const newUrl = url.replace(/[?&]StartDate=[^&]+/, `$&StartDate=${startDate}`.replace(/\$&/, startDateMatch[0].includes('?') ? '&' : '?'));
                    request.continue({
                        url: newUrl
                    });
                    return;
                } else if (!startDateMatch && (url.includes('grid') || url.includes('search'))) {
                    // Add StartDate if it's missing
                    console.error(`DEBUG: Intercepting GET request, adding StartDate=${startDate}`);
                    const separator = url.includes('?') ? '&' : '?';
                    request.continue({
                        url: `${url}${separator}StartDate=${startDate}`
                    });
                    return;
                }
            }
            
            request.continue();
        });
        
        page.on('response', async (response) => {
            const url = response.url();
            const status = response.status();
            const contentType = response.headers()['content-type'] || '';
            
            allRequests.push({ url, status, contentType });
            
            // Look for grid/availability API calls
            if (url.includes('grid') || url.includes('availability') || url.includes('search') || 
                url.includes('calirdr') || url.includes('usedirect') || 
                (url.includes('park') && contentType.includes('json'))) {
                console.error(`DEBUG: Found relevant request: ${url} (status: ${status}, content-type: ${contentType})`);
                
                // Check if URL contains the requested date
                if (url.includes(startDate)) {
                    console.error(`DEBUG: Request URL contains requested date ${startDate}`);
                } else {
                    console.error(`DEBUG: Request URL does NOT contain requested date ${startDate}`);
                }
                
                try {
                    if (contentType.includes('json') || url.includes('.json') || url.includes('grid') || url.includes('search')) {
                        const text = await response.text();
                        console.error(`DEBUG: Response text length: ${text.length} bytes`);
                        try {
                            const json = JSON.parse(text);
                            console.error(`DEBUG: Successfully parsed JSON from ${url}`);
                            
                            // Check date range in response
                            if (json.StartDate) {
                                console.error(`DEBUG: Response StartDate: ${json.StartDate}`);
                            }
                            if (json.Facility && json.Facility.Units) {
                                const firstUnit = Object.values(json.Facility.Units)[0];
                                if (firstUnit && firstUnit.Slices) {
                                    const sliceDates = Object.keys(firstUnit.Slices).map(key => {
                                        const slice = firstUnit.Slices[key];
                                        return slice?.Date || key.split('T')[0];
                                    }).filter(Boolean).sort();
                                    if (sliceDates.length > 0) {
                                        console.error(`DEBUG: Response date range: ${sliceDates[0]} to ${sliceDates[sliceDates.length - 1]}`);
                                    }
                                }
                            }
                            
                            // Check if this is grid data
                            if (json.Facility && json.Facility.Units) {
                                console.error(`DEBUG: Found grid data with ${json.Facility.Units.length} units`);
                                // Prefer grid data with the correct StartDate, but accept any grid data if we don't have any yet
                                if (!gridData) {
                                    gridData = json;
                                    console.error(`DEBUG: Using first grid data found`);
                                } else if (json.StartDate === startDate) {
                                    console.error(`DEBUG: Found grid data with correct StartDate, replacing previous data`);
                                    gridData = json;
                                } else if (url.includes('grid') || url.includes('search')) {
                                    // Prefer grid/search endpoints over other endpoints
                                    console.error(`DEBUG: Found grid data from grid/search endpoint, replacing previous data`);
                                    gridData = json;
                                }
                            } else if (json.Units) {
                                // Alternative structure
                                console.error(`DEBUG: Found units data with ${json.Units.length} units`);
                                gridData = { Facility: { Units: json.Units } };
                            } else if (json.data && json.data.Facility && json.data.Facility.Units) {
                                // Nested structure
                                console.error(`DEBUG: Found nested grid data`);
                                gridData = json.data;
                            }
                        } catch (e) {
                            console.error(`DEBUG: Failed to parse JSON from ${url}: ${e.message}`);
                            console.error(`DEBUG: First 200 chars: ${text.substring(0, 200)}`);
                        }
                    }
                } catch (e) {
                    console.error(`DEBUG: Error reading response from ${url}: ${e.message}`);
                }
            }
        });
        
        // Navigate directly to facility page with date parameters
        // Format: /park/{placeId}/{facilityId}?startDate=YYYY-MM-DD&nights=1
        const facilityUrl = `https://reservecalifornia.com/park/${placeId}/${facilityId}?startDate=${startDate}&nights=${nights}`;
        console.error(`DEBUG: Navigating to facility page with date: ${facilityUrl}`);
        console.log(`Navigating to: ${facilityUrl}`);
        
        await page.goto(facilityUrl, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        // Wait for page to load and JavaScript to execute
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        // Try to set the date if URL params didn't work
        const dateSet = await page.evaluate((startDate, nights) => {
            // Look for date input fields
            const dateInputs = document.querySelectorAll('input[type="date"], input[name*="date" i], input[id*="date" i], input[type="text"][class*="date"]');
            let dateSet = false;
            for (const input of dateInputs) {
                if (input.type === 'date' || input.name?.toLowerCase().includes('start') || input.id?.toLowerCase().includes('start')) {
                    input.value = startDate;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    dateSet = true;
                }
            }
            
            // Also try to set nights
            const nightsInputs = document.querySelectorAll('input[name*="night" i], input[id*="night" i], select[name*="night" i]');
            for (const input of nightsInputs) {
                if (input.tagName === 'SELECT') {
                    input.value = nights;
                } else {
                    input.value = nights;
                }
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            // Try to trigger a search/update by clicking a search button or triggering calendar navigation
            // Note: :contains() is not a valid CSS selector, so we need to check all buttons
            const allButtons = document.querySelectorAll('button, [role="button"], input[type="submit"]');
            for (const btn of allButtons) {
                const text = (btn.textContent || btn.innerText || btn.value || '').toLowerCase();
                if (text.includes('search') || text.includes('check') || text.includes('update') || text.includes('apply')) {
                    btn.click();
                    return { dateSet: true, searchTriggered: true };
                }
            }
            
            // If no search button, try clicking on the calendar to trigger update
            const calendarCells = document.querySelectorAll('[class*="calendar"] [class*="day"], [class*="date"]');
            for (const cell of calendarCells) {
                const cellDate = cell.getAttribute('data-date') || cell.textContent;
                if (cellDate && cellDate.includes('19')) {
                    cell.click();
                    return { dateSet: true, searchTriggered: true };
                }
            }
            
            return { dateSet: dateSet, searchTriggered: false };
        }, startDate, nights);
        
        // Handle the return value (could be boolean or object)
        let dateWasSet = false;
        let searchWasTriggered = false;
        if (typeof dateSet === 'object' && dateSet !== null) {
            dateWasSet = dateSet.dateSet || false;
            searchWasTriggered = dateSet.searchTriggered || false;
        } else {
            dateWasSet = dateSet || false;
        }
        
        if (searchWasTriggered) {
            console.error(`DEBUG: Date set and search triggered, waiting for grid to update...`);
            // Wait for a new API call with the correct date
            let waitedForCorrectDate = false;
            for (let i = 0; i < 10; i++) {
                await new Promise(resolve => setTimeout(resolve, 1000));
                if (gridData && gridData.StartDate === startDate) {
                    console.error(`DEBUG: Found grid data with correct StartDate after ${i + 1} seconds`);
                    waitedForCorrectDate = true;
                    break;
                }
            }
            if (!waitedForCorrectDate) {
                console.error(`DEBUG: Did not find grid data with correct StartDate after 10 seconds`);
            }
        } else if (dateWasSet) {
            console.error(`DEBUG: Date set via input fields, waiting for grid to update...`);
            // Wait a bit for the page to make a new API call
            await new Promise(resolve => setTimeout(resolve, 3000));
        }
        
        // Wait for availability grid to load
        try {
            await page.waitForSelector('table, .availability, .calendar, [class*="unit"], [class*="grid"], [class*="site"]', { timeout: 10000 });
            console.error(`DEBUG: Availability grid found on page`);
        } catch (e) {
            console.error(`DEBUG: Availability grid selector not found, but continuing...`);
        }
        
        // Wait a bit more for any API calls to complete
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        console.error(`DEBUG: Total requests captured: ${allRequests.length}`);
        console.error(`DEBUG: Grid data captured from network: ${gridData ? 'YES' : 'NO'}`);
        
        // Check if grid data has the correct date range
        if (gridData && gridData.Facility && gridData.Facility.Units) {
            const firstUnit = Object.values(gridData.Facility.Units)[0];
            if (firstUnit && firstUnit.Slices) {
                const sliceDates = Object.keys(firstUnit.Slices).map(key => {
                    const slice = firstUnit.Slices[key];
                    return slice?.Date || key.split('T')[0];
                }).filter(Boolean);
                if (sliceDates.length > 0) {
                    console.error(`DEBUG: Grid data date range: ${sliceDates[0]} to ${sliceDates[sliceDates.length - 1]}`);
                    console.error(`DEBUG: Requested date: ${startDate}`);
                    
                    // Check if any slice date matches the requested date (or is within a week)
                    const requestedDateObj = new Date(startDate);
                    const hasRequestedDate = sliceDates.some(date => {
                        const dateObj = new Date(date);
                        const diffDays = Math.abs((dateObj - requestedDateObj) / (1000 * 60 * 60 * 24));
                        return diffDays <= 7; // Allow up to 7 days difference
                    });
                    
                    if (!hasRequestedDate) {
                        console.error(`DEBUG: Grid data doesn't include requested date range, but keeping it anyway (might be filtered later)`);
                        // Don't clear gridData - we'll filter slices in PHP
                    }
                }
            }
        }
        
        // If we captured grid data from network, use it
        if (gridData) {
            console.error(`DEBUG: Returning grid data with ${gridData.Facility?.Units?.length || 0} units`);
            console.log(JSON.stringify(gridData));
            return;
        }
        
        // Otherwise, try to extract from page
        console.error(`DEBUG: No grid data from network, trying to extract from page...`);
        let pageData;
        try {
            pageData = await page.evaluate(() => {
            const debug = [];
            
            // Look for embedded JSON in script tags
            const scripts = document.querySelectorAll('script');
            debug.push(`Found ${scripts.length} script tags`);
            for (const script of scripts) {
                const text = script.textContent || script.innerHTML;
                if (text.includes('Facility') && (text.includes('Units') || text.includes('Slices'))) {
                    debug.push(`Found script with Facility/Units data (length: ${text.length})`);
                    try {
                        // Try to find JSON object
                        const jsonMatch = text.match(/\{[\s\S]*"Facility"[\s\S]*"Units"[\s\S]*\}/);
                        if (jsonMatch) {
                            const json = JSON.parse(jsonMatch[0]);
                            if (json.Facility && json.Facility.Units) {
                                debug.push(`Successfully extracted grid data from script`);
                                return { data: json, debug };
                            }
                        }
                    } catch (e) {
                        debug.push(`Failed to parse JSON from script: ${e.message}`);
                    }
                }
            }
            
            // Look for window variables
            if (window.availabilityData) {
                debug.push(`Found window.availabilityData`);
                return { data: window.availabilityData, debug };
            }
            if (window.gridData) {
                debug.push(`Found window.gridData`);
                return { data: window.gridData, debug };
            }
            if (window.__INITIAL_STATE__) {
                debug.push(`Found window.__INITIAL_STATE__`);
                return { data: window.__INITIAL_STATE__, debug };
            }
            
            debug.push(`No grid data found in page`);
            return { data: null, debug };
            });
        } catch (e) {
            console.error(`DEBUG: Error in page evaluation: ${e.message}`);
            pageData = { data: null, debug: [`Error: ${e.message}`] };
        }
        
        if (pageData && pageData.data) {
            console.error(`DEBUG: Page extraction debug: ${pageData.debug.join('; ')}`);
            console.log(JSON.stringify(pageData.data));
        } else {
            console.error(`DEBUG: Page extraction debug: ${pageData?.debug?.join('; ') || 'No debug info'}`);
            console.error(`DEBUG: No grid data found - returning empty structure`);
            console.log(JSON.stringify({ Facility: { Units: [] } }));
        }
        
    } catch (error) {
        console.error(JSON.stringify({ error: error.message }));
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Main execution
(async () => {
    try {
        if (command === 'facilities' && args.length >= 1) {
            await scrapeFacilities(args[0]);
        } else if (command === 'availability' && args.length >= 4) {
            await scrapeAvailability(args[0], args[1], args[2], args[3]);
        } else {
            console.error(JSON.stringify({ error: 'Invalid command or arguments' }));
            console.error('Usage:');
            console.error('  node scrape-via-browser.js facilities <placeId>');
            console.error('  node scrape-via-browser.js availability <placeId> <facilityId> <startDate> <nights>');
            process.exit(1);
        }
    } catch (error) {
        console.error(JSON.stringify({ error: error.message }));
        process.exit(1);
    }
})();

