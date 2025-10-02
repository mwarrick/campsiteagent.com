# Multi-Park Support Deployment Guide

## Summary
Added support for multiple parks with facility-level filtering. You can now monitor multiple CA state parks, and for parks like Crystal Cove, you can specify which facilities (campgrounds) to monitor.

## New Features
1. **Multiple Active Parks** - Monitor as many parks as you want
2. **Facility Filtering** - Restrict scraping to specific campgrounds within a park (e.g., only Moro Campground at Crystal Cove)
3. **User-Selected Parks** - Added your requested parks:
   - San Clemente State Beach (707)
   - Chino Hills State Park (627)
   - Crystal Cove State Park (635) - **Only Facility #447 (Moro Campground)**

## Files Changed

### Database Migration
- `app/migrations/013_add_user_selected_parks.sql` - Adds facility_filter column and your 3 parks

### Backend Changes
- `app/src/Services/ReserveCaliforniaScraper.php` - Added facility filtering support
- `app/src/Services/ScraperService.php` - Passes facility filters to scraper
- `app/src/Services/MetadataSyncService.php` - Respects facility filters during sync

### Discovery/Testing Scripts
- `app/bin/discover-parks.php` - Discover all available CA state parks from API
- `app/bin/test-new-park.php` - Test scraping for newly added parks

## Deployment Steps

### 1. Deploy Files to Server
```bash
# From your local machine
cd /Users/markwarrick/Projects/CA-State-Park-Campsite-Monitor/CA-State-Park-Campsite-Monitor

# Copy files to server
scp campsiteagent/app/migrations/013_add_user_selected_parks.sql root@mark-MacPro.local:/var/www/campsite-agent/app/migrations/
scp campsiteagent/app/src/Services/ReserveCaliforniaScraper.php root@mark-MacPro.local:/var/www/campsite-agent/app/src/Services/
scp campsiteagent/app/src/Services/ScraperService.php root@mark-MacPro.local:/var/www/campsite-agent/app/src/Services/
scp campsiteagent/app/src/Services/MetadataSyncService.php root@mark-MacPro.local:/var/www/campsite-agent/app/src/Services/
scp campsiteagent/app/bin/test-new-park.php root@mark-MacPro.local:/var/www/campsite-agent/app/bin/
scp campsiteagent/app/bin/discover-parks.php root@mark-MacPro.local:/var/www/campsite-agent/app/bin/

# Set permissions
ssh root@mark-MacPro.local "chmod +x /var/www/campsite-agent/app/bin/*.php && chown -R www-data:www-data /var/www/campsite-agent/app"
```

### 2. Run Database Migration
```bash
ssh root@mark-MacPro.local
cd /var/www/campsite-agent

# Run migration
mysql -u campsitechecker -p campsitechecker < app/migrations/013_add_user_selected_parks.sql

# Verify parks were added
mysql -u campsitechecker -p campsitechecker -e "SELECT id, name, park_number, active, facility_filter FROM parks ORDER BY name;"
```

Expected output:
```
+----+-------------------+--------------+--------+-----------------+
| id | name              | park_number  | active | facility_filter |
+----+-------------------+--------------+--------+-----------------+
|  1 | San Onofre SB     | 712          |      1 | NULL            |
|  2 | San Clemente SB   | 707          |      1 | NULL            |
|  3 | Chino Hills SP    | 627          |      1 | NULL            |
|  4 | Crystal Cove SP   | 635          |      1 | ["447"]         |
+----+-------------------+--------------+--------+-----------------+
```

### 3. Test One of the New Parks (Optional but Recommended)
```bash
cd /var/www/campsite-agent/app
php bin/test-new-park.php

# Select option 1, 2, or 3 to test
# This will verify the park has facilities and can be scraped
```

### 4. Sync Metadata for New Parks
```bash
cd /var/www/campsite-agent/app
php bin/sync-metadata.php
```

This will fetch all facilities and sites for the new parks without doing a full availability scrape.

### 5. Verify in Dashboard
1. Go to http://campsiteagent.com
2. Click the **Park** dropdown
3. You should now see **4 parks**:
   - San Onofre SB
   - San Clemente SB
   - Chino Hills SP
   - Crystal Cove SP

### 6. Test Manual Scrape
1. In the dashboard, select one of the new parks
2. Click **Check Now**
3. Watch the progress - it should scrape only that park
4. For Crystal Cove, it should only scrape **Moro Campground (Facility 447)**

## How Facility Filtering Works

### Parks WITHOUT Filter
- **San Onofre**, **San Clemente**, **Chino Hills**: `facility_filter = NULL`
- Scrapes **all campgrounds** at these parks

### Parks WITH Filter
- **Crystal Cove**: `facility_filter = ["447"]`
- Only scrapes **Moro Campground** (facility 447)
- Ignores all other campgrounds at Crystal Cove

## Adding More Parks Later

### To add a new park (all facilities):
```sql
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Park Name', 'park_slug', '123', 1);
```

### To add a park with facility filter:
```sql
INSERT INTO parks (name, external_id, park_number, active, facility_filter) 
VALUES ('Park Name', 'park_slug', '123', 1, '["facility_id_1", "facility_id_2"]');
```

### To discover more parks:
```bash
cd /var/www/campsite-agent/app
php bin/discover-parks.php
```

This will show all CA state parks available in the ReserveCalifornia API.

## Troubleshooting

### Park not showing in dropdown?
```sql
-- Check if park is active
SELECT name, active FROM parks WHERE park_number = '707';

-- Activate it
UPDATE parks SET active = 1 WHERE park_number = '707';
```

### Want to test only a specific facility?
```sql
-- Set facility filter
UPDATE parks 
SET facility_filter = '["facility_id"]' 
WHERE park_number = '635';

-- Remove facility filter (scrape all)
UPDATE parks 
SET facility_filter = NULL 
WHERE park_number = '635';
```

### Check what facilities a park has:
```bash
cd /var/www/campsite-agent/app
mysql -u campsitechecker -p campsitechecker -e "
SELECT f.id, f.name, f.external_id 
FROM facilities f 
JOIN parks p ON f.park_id = p.id 
WHERE p.park_number = '635';
"
```

## Architecture Notes

- **Database**: `parks.facility_filter` column stores JSON array of allowed facility IDs
- **Scraper**: `ReserveCaliforniaScraper::fetchParkFacilities()` filters facilities if provided
- **Service Layer**: `ScraperService` and `MetadataSyncService` read facility_filter and pass to scraper
- **UI**: No changes needed - automatically picks up new active parks

## Next Steps

After deployment:
1. ✅ Run "Check Now" for each new park to verify scraping works
2. ✅ Set up scheduled scraping via cron (separate task)
3. ✅ Monitor logs for any errors
4. 📧 Users with alert preferences will automatically receive notifications for all active parks (unless they've specified a specific park in their preferences)

## Support

If you encounter issues:
1. Check Apache error log: `tail -f /var/log/apache2/error.log`
2. Check application logs (if you've set them up)
3. Test individual park scraping: `php bin/test-new-park.php`
4. Verify database: Check `parks`, `facilities`, and `sites` tables

