# Deployment: Enhanced Metadata Support

## Overview
This update adds comprehensive metadata capture from ReserveCalifornia API, including:
- Facility details (campgrounds within parks)
- Site metadata (ADA accessibility, vehicle length, unit types)
- Automatic facility/site syncing during scraping

## Files to Deploy

### 1. Migration
```
campsiteagent/app/migrations/010_enhance_sites_metadata.sql
```
Run on server:
```bash
mysql -u campsitechecker -p campsitechecker < /var/www/campsite-agent/app/migrations/010_enhance_sites_metadata.sql
```

### 2. Updated Repositories
```
campsiteagent/app/src/Repositories/FacilityRepository.php
campsiteagent/app/src/Repositories/SiteRepository.php
```

### 3. Updated Services
```
campsiteagent/app/src/Services/ReserveCaliforniaScraper.php
campsiteagent/app/src/Services/ScraperService.php
```

### 4. Test Scripts
```
campsiteagent/app/bin/test-real-scraper.php
```

## What This Enables

### For Users:
- **Filter by ADA sites**: `?ada=true`
- **Filter by vehicle length**: `?vehicle_length=35`
- **See facility names**: "Bluff Camp (sites 1-23)"
- **Better site details**: Full site names, not just numbers

### For Dashboard:
- Display facility breakdowns
- Show ADA-accessible sites
- Filter by RV length requirements
- More detailed availability views

### Automatic on First Scrape:
When you run `check-now.php`, it will:
1. ✅ Fetch all facilities for San Onofre
2. ✅ Save facility metadata to database
3. ✅ Fetch all sites with metadata
4. ✅ Save site details (name, ADA, vehicle length, etc.)
5. ✅ Save availability as before

## Testing After Deployment

```bash
# 1. Run migration
mysql -u campsitechecker -p campsitechecker < /var/www/campsite-agent/app/migrations/010_enhance_sites_metadata.sql

# 2. Test the scraper
php /var/www/campsite-agent/app/bin/test-real-scraper.php

# Should show:
# - 14 facilities
# - ~295 sites with metadata
# - Sites with availability counts per facility

# 3. Check database
mysql -u campsitechecker -p campsitechecker -e "
SELECT f.name, COUNT(s.id) as site_count 
FROM facilities f 
LEFT JOIN sites s ON s.facility_id = f.id 
WHERE f.park_id = 1 
GROUP BY f.id;
"

# 4. Run full scrape
php /var/www/campsite-agent/app/bin/check-now.php
```

## Database Schema Changes

### parks table (new columns):
- `latitude` DECIMAL(10,7)
- `longitude` DECIMAL(10,7)
- `address1` VARCHAR(255)
- `city` VARCHAR(100)
- `state` VARCHAR(2)
- `zip` VARCHAR(10)
- `phone` VARCHAR(20)

### facilities table (new columns):
- `description` TEXT
- `allow_web_booking` BOOLEAN
- `facility_type` INT

### sites table (new columns):
- `site_name` VARCHAR(255)
- `unit_type_id` INT
- `is_ada` BOOLEAN
- `vehicle_length` INT
- `allow_web_booking` BOOLEAN
- `is_web_viewable` BOOLEAN

## Next Steps

After deployment, you can:
1. Update dashboard to show facility breakdowns
2. Add ADA filter to search
3. Add vehicle length filter
4. Show richer site details in results



