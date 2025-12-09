#!/usr/bin/env node

/**
 * Browser-based investigation of ReserveCalifornia website
 * Uses Puppeteer to access the site like a real browser
 */

const puppeteer = require('puppeteer');

async function investigate() {
    console.log('=== BROWSER-BASED INVESTIGATION ===\n');
    
    let browser;
    try {
        console.log('Launching browser...');
        browser = await puppeteer.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        
        const page = await browser.newPage();
        
        // Set a realistic viewport
        await page.setViewport({ width: 1920, height: 1080 });
        
        // Set user agent
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36');
        
        // Monitor network requests
        const networkRequests = [];
        page.on('request', request => {
            const url = request.url();
            if (url.includes('calirdr') || url.includes('usedirect') || url.includes('reservecalifornia')) {
                networkRequests.push({
                    url: url,
                    method: request.method(),
                    headers: request.headers()
                });
            }
        });
        
        // Monitor network responses
        const networkResponses = [];
        page.on('response', async response => {
            const url = response.url();
            if (url.includes('calirdr') || url.includes('usedirect')) {
                try {
                    const contentType = response.headers()['content-type'] || '';
                    if (contentType.includes('json') || url.includes('facilities') || url.includes('grid') || url.includes('availability')) {
                        const text = await response.text();
                        networkResponses.push({
                            url: url,
                            status: response.status(),
                            contentType: contentType,
                            body: text.substring(0, 2000) // First 2000 chars
                        });
                    }
                } catch (e) {
                    // Ignore
                }
            }
        });
        
        console.log('\n1. Navigating to ReserveCalifornia homepage...');
        await page.goto('https://www.reservecalifornia.com/', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        console.log('   ✅ Page loaded');
        
        // Wait a bit for JavaScript to execute
        await page.waitForTimeout(3000);
        
        // Check for embedded data
        console.log('\n2. Checking for embedded JavaScript data...');
        const embeddedData = await page.evaluate(() => {
            const data = {
                windowVars: {},
                scriptTags: [],
                apiEndpoints: []
            };
            
            // Check window variables
            if (window.facilities) data.windowVars.facilities = window.facilities;
            if (window.parks) data.windowVars.parks = window.parks;
            if (window.apiBaseUrl) data.windowVars.apiBaseUrl = window.apiBaseUrl;
            
            // Find script tags with data
            const scripts = document.querySelectorAll('script');
            scripts.forEach(script => {
                const text = script.textContent || script.innerHTML;
                if (text.includes('calirdr') || text.includes('facilities') || text.includes('PlaceId')) {
                    data.scriptTags.push(text.substring(0, 500));
                }
            });
            
            // Look for API endpoints in JavaScript
            const allText = document.documentElement.outerHTML;
            const apiMatches = allText.match(/https?:\/\/[^\s'"]*calirdr[^\s'"]*/gi);
            if (apiMatches) {
                data.apiEndpoints = [...new Set(apiMatches)];
            }
            
            return data;
        });
        
        if (Object.keys(embeddedData.windowVars).length > 0) {
            console.log('   ✅ Found window variables:', Object.keys(embeddedData.windowVars));
        }
        if (embeddedData.apiEndpoints.length > 0) {
            console.log('   ✅ Found API endpoints:', embeddedData.apiEndpoints.length);
            embeddedData.apiEndpoints.forEach(url => console.log('      -', url));
        }
        
        // Try to navigate to a specific park's booking page
        console.log('\n3. Testing park-specific page (Chino Hills SP - PlaceId 627)...');
        try {
            // Try common booking page patterns
            const bookingUrls = [
                'https://www.reservecalifornia.com/CaliforniaWebHome/Facilities/MapView.aspx?PlaceId=627',
                'https://www.reservecalifornia.com/CaliforniaWebHome/Facilities/Details.aspx?PlaceId=627',
                'https://www.reservecalifornia.com/CaliforniaWebHome/Facilities/Search.aspx?PlaceId=627'
            ];
            
            for (const url of bookingUrls) {
                console.log(`   Trying: ${url}`);
                try {
                    await page.goto(url, { waitUntil: 'networkidle2', timeout: 15000 });
                    await page.waitForTimeout(2000);
                    
                    // Check if page loaded successfully
                    const title = await page.title();
                    console.log(`      Title: ${title}`);
                    
                    // Check for facilities data
                    const facilitiesData = await page.evaluate(() => {
                        // Look for facility dropdowns
                        const selects = Array.from(document.querySelectorAll('select'));
                        const facilities = [];
                        selects.forEach(select => {
                            const options = Array.from(select.options);
                            options.forEach(opt => {
                                if (opt.value && opt.value !== '0' && opt.value !== '') {
                                    facilities.push({
                                        id: opt.value,
                                        name: opt.text
                                    });
                                }
                            });
                        });
                        return facilities;
                    });
                    
                    if (facilitiesData.length > 0) {
                        console.log(`      ✅ Found ${facilitiesData.length} facilities in dropdowns`);
                        facilitiesData.slice(0, 5).forEach(f => {
                            console.log(`         - ${f.name} (ID: ${f.id})`);
                        });
                    }
                    
                    break; // Stop on first successful page
                } catch (e) {
                    console.log(`      ⚠️  Error: ${e.message}`);
                }
            }
        } catch (e) {
            console.log(`   ⚠️  Error: ${e.message}`);
        }
        
        // Report network requests
        console.log('\n4. Network Requests to API:');
        if (networkRequests.length > 0) {
            console.log(`   Found ${networkRequests.length} API-related requests:`);
            networkRequests.slice(0, 10).forEach(req => {
                console.log(`   ${req.method} ${req.url}`);
            });
        } else {
            console.log('   ⚠️  No API requests detected');
        }
        
        // Report network responses
        console.log('\n5. Network Responses from API:');
        if (networkResponses.length > 0) {
            console.log(`   Found ${networkResponses.length} API responses:`);
            networkResponses.forEach(resp => {
                console.log(`   ${resp.status} ${resp.url}`);
                console.log(`      Content-Type: ${resp.contentType}`);
                if (resp.body && resp.body.length > 0) {
                    console.log(`      Body preview: ${resp.body.substring(0, 200)}...`);
                }
            });
        } else {
            console.log('   ⚠️  No API responses detected');
        }
        
        // Save page HTML for inspection
        const html = await page.content();
        const fs = require('fs');
        fs.writeFileSync('/tmp/reservecal_browser.html', html);
        console.log('\n6. Saved page HTML to /tmp/reservecal_browser.html');
        
    } catch (error) {
        console.error('Error:', error.message);
        console.error(error.stack);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
    
    console.log('\n=== INVESTIGATION COMPLETE ===\n');
}

investigate().catch(console.error);

