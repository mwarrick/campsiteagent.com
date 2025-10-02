# Deployment: Weekend Detection Fix

## The Bug

The scraper was incorrectly reporting weekend availability because:

1. **WeekendDetector bug**: Used `!empty()` which returns `true` for both `true` AND `false` boolean values
2. **Empty slices issue**: API returns empty slices when no availability exists for a facility (this is correct behavior)
3. **InSeasonOnly filter**: Was filtering out "out of season" dates

## Files to Deploy

### Fixed Files:
1. `campsiteagent/app/src/Services/WeekendDetector.php` → `/var/www/campsite-agent/app/src/Services/`
   - Fixed `!empty()` bug to use `=== true` comparison

2. `campsiteagent/app/src/Services/ReserveCaliforniaScraper.php` → `/var/www/campsite-agent/app/src/Services/`
   - Added `InSeasonOnly => false` parameter
   - Changed from multi-night to single-night requests

3. `campsiteagent/app/src/Services/ScraperService.php` → `/var/www/campsite-agent/app/src/Services/`
   - Calculate actual date range for emails
   - Track earliest/latest available dates

4. `campsiteagent/app/src/Templates/EmailTemplates.php` → `/var/www/campsite-agent/app/src/Templates/`
   - Show actual date ranges in subject
   - Display site names and facility names

### New Files (Metadata Sync):
5. `campsiteagent/app/src/Services/MetadataSyncService.php` → `/var/www/campsite-agent/app/src/Services/`
6. `campsiteagent/app/bin/sync-metadata.php` → `/var/www/campsite-agent/app/bin/`
7. `campsiteagent/www/index.php` → `/var/www/campsite-agent/www/`
   - Added `/api/admin/sync-metadata` endpoint

## Deployment Commands

```bash
# From local machine
scp campsiteagent/app/src/Services/WeekendDetector.php \
    campsiteagent/app/src/Services/ReserveCaliforniaScraper.php \
    campsiteagent/app/src/Services/ScraperService.php \
    campsiteagent/app/src/Services/MetadataSyncService.php \
    campsiteagent/app/src/Templates/EmailTemplates.php \
    campsiteagent/app/bin/sync-metadata.php \
    campsiteagent/www/index.php \
    mark@campsiteagent.com:/tmp/

# On server as root
sudo mv /tmp/WeekendDetector.php /var/www/campsite-agent/app/src/Services/
sudo mv /tmp/ReserveCaliforniaScraper.php /var/www/campsite-agent/app/src/Services/
sudo mv /tmp/ScraperService.php /var/www/campsite-agent/app/src/Services/
sudo mv /tmp/MetadataSyncService.php /var/www/campsite-agent/app/src/Services/
sudo mv /tmp/EmailTemplates.php /var/www/campsite-agent/app/src/Templates/
sudo mv /tmp/sync-metadata.php /var/www/campsite-agent/app/bin/
sudo mv /tmp/index.php /var/www/campsite-agent/www/

# Fix permissions
sudo bash /var/www/campsite-agent/fix-permissions.sh
```

## Testing After Deployment

### 1. Clear old (incorrect) data:
```sql
DELETE FROM campsitechecker.site_availability;
DELETE FROM campsitechecker.sites;
DELETE FROM campsitechecker.facilities;
```

### 2. Run a fresh scrape:
```bash
cd /var/www/campsite-agent/app
php bin/check-now.php
```

### 3. Verify weekend detection:
```bash
# Check a site that was in the false-positive email
mysql -u campsitechecker -p campsitechecker << 'SQL'
SELECT 
    s.site_number,
    COUNT(CASE WHEN DAYNAME(sa.date) = 'Friday' AND sa.is_available = 1 THEN 1 END) as fridays,
    COUNT(CASE WHEN DAYNAME(sa.date) = 'Saturday' AND sa.is_available = 1 THEN 1 END) as saturdays
FROM sites s
JOIN site_availability sa ON sa.site_id = s.id
WHERE s.site_number = 'S101'
    AND sa.date >= CURDATE()
GROUP BY s.site_number;
SQL
```

### 4. Test with known good dates:
Check the dashboard or run:
```bash
php << 'PHPCODE'
<?php
require '/var/www/campsite-agent/app/bootstrap.php';

// Test weekend detector with real data
$detector = new \CampsiteAgent\Services\WeekendDetector();

// Should be TRUE (consecutive Fri+Sat)
$goodData = [
    '2025-12-05' => true,  // Friday
    '2025-12-06' => true,  // Saturday
    '2025-12-07' => false
];
echo "Good data (Fri+Sat): " . ($detector->hasWeekend($goodData) ? 'PASS' : 'FAIL') . PHP_EOL;

// Should be FALSE (no consecutive weekend)
$badData = [
    '2025-12-05' => false, // Friday
    '2025-12-06' => true,  // Saturday
    '2025-12-07' => true   // Sunday
];
echo "Bad data (no Fri+Sat): " . ($detector->hasWeekend($badData) ? 'FAIL' : 'PASS') . PHP_EOL;
PHPCODE
```

## What's Fixed

✅ **WeekendDetector** now correctly identifies only sites with BOTH Friday AND Saturday available  
✅ **Email alerts** show actual date ranges and site/facility names  
✅ **API requests** include out-of-season dates  
✅ **Metadata sync** available via admin endpoint  

## Expected Behavior Now

- Alerts will ONLY be sent when a site has **consecutive Friday + Saturday** availability
- Email subject: `Weekend availability found for 12/5-3/15/2026: San Onofre SB`
- Email body shows site names and facilities
- Far fewer false positives!



