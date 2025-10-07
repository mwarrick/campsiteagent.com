-- Diagnostic queries to check for orphaned records in the database
-- Run these queries in MySQL to identify why we're seeing "Unknown Facility" on the dashboard

-- 1. Check for sites with facility_id that don't exist in facilities table (orphaned sites)
SELECT s.id, s.site_number, s.facility_id, s.park_id, p.name as park_name
FROM sites s
LEFT JOIN facilities f ON s.facility_id = f.id
LEFT JOIN parks p ON s.park_id = p.id
WHERE s.facility_id IS NOT NULL AND f.id IS NULL
ORDER BY s.park_id, s.site_number;

-- 2. Check for sites with NULL facility_id
SELECT s.id, s.site_number, s.park_id, p.name as park_name
FROM sites s
LEFT JOIN parks p ON s.park_id = p.id
WHERE s.facility_id IS NULL
ORDER BY s.park_id, s.site_number;

-- 3. Check for facilities that exist but have no sites
SELECT f.id, f.name, f.park_id, p.name as park_name
FROM facilities f
LEFT JOIN parks p ON f.park_id = p.id
LEFT JOIN sites s ON f.id = s.facility_id
WHERE s.id IS NULL
ORDER BY f.park_id, f.name;

-- 4. Check for sites with invalid park_id (orphaned from parks)
SELECT s.id, s.site_number, s.park_id, s.facility_id
FROM sites s
LEFT JOIN parks p ON s.park_id = p.id
WHERE p.id IS NULL
ORDER BY s.park_id, s.site_number;

-- 5. Summary statistics
SELECT 
    (SELECT COUNT(*) FROM sites) as total_sites,
    (SELECT COUNT(*) FROM sites WHERE facility_id IS NOT NULL) as sites_with_facility,
    (SELECT COUNT(*) FROM sites WHERE facility_id IS NULL) as sites_without_facility,
    (SELECT COUNT(*) FROM facilities) as total_facilities,
    (SELECT COUNT(*) FROM parks) as total_parks;

-- 6. Recent site_availability records with facility info (last 20)
SELECT sa.id, sa.site_id, s.site_number, s.facility_id, f.name as facility_name, 
       sa.available, sa.date, p.name as park_name
FROM site_availability sa
LEFT JOIN sites s ON sa.site_id = s.id
LEFT JOIN facilities f ON s.facility_id = f.id
LEFT JOIN parks p ON s.park_id = p.id
ORDER BY sa.id DESC
LIMIT 20;

-- 7. Check for sites that have availability but no facility name
SELECT sa.id, sa.site_id, s.site_number, s.facility_id, f.name as facility_name, 
       sa.available, sa.date, p.name as park_name
FROM site_availability sa
LEFT JOIN sites s ON sa.site_id = s.id
LEFT JOIN facilities f ON s.facility_id = f.id
LEFT JOIN parks p ON s.park_id = p.id
WHERE sa.available = 1 AND (f.name IS NULL OR f.name = '')
ORDER BY sa.date DESC, sa.id DESC
LIMIT 50;

-- 8. Count of sites by facility status
SELECT 
    CASE 
        WHEN s.facility_id IS NULL THEN 'NULL facility_id'
        WHEN f.id IS NULL THEN 'Invalid facility_id'
        WHEN f.name IS NULL OR f.name = '' THEN 'Empty facility name'
        ELSE 'Valid facility'
    END as facility_status,
    COUNT(*) as count
FROM sites s
LEFT JOIN facilities f ON s.facility_id = f.id
GROUP BY facility_status
ORDER BY count DESC;
