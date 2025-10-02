# Deployment: Metadata Sync Feature

## Files to Deploy

### New Files:
1. `campsiteagent/app/src/Services/MetadataSyncService.php` → `/var/www/campsite-agent/app/src/Services/`
2. `campsiteagent/app/bin/sync-metadata.php` → `/var/www/campsite-agent/app/bin/`

### Updated Files:
3. `campsiteagent/www/index.php` → `/var/www/campsite-agent/www/`

## Deployment Commands

From your local machine:
```bash
# Copy new service
scp campsiteagent/app/src/Services/MetadataSyncService.php mark@campsiteagent.com:/tmp/

# Copy CLI script
scp campsiteagent/app/bin/sync-metadata.php mark@campsiteagent.com:/tmp/

# Copy updated router
scp campsiteagent/www/index.php mark@campsiteagent.com:/tmp/
```

On the server (as root):
```bash
# Move files to correct locations
mv /tmp/MetadataSyncService.php /var/www/campsite-agent/app/src/Services/
mv /tmp/sync-metadata.php /var/www/campsite-agent/app/bin/
mv /tmp/index.php /var/www/campsite-agent/www/

# Fix permissions
chmod 644 /var/www/campsite-agent/app/src/Services/MetadataSyncService.php
chmod 755 /var/www/campsite-agent/app/bin/sync-metadata.php
chmod 644 /var/www/campsite-agent/www/index.php
chown www-data:www-data /var/www/campsite-agent/app/src/Services/MetadataSyncService.php
chown www-data:www-data /var/www/campsite-agent/www/index.php
```

## Testing

### Test CLI Script:
```bash
cd /var/www/campsite-agent/app
php bin/sync-metadata.php
```

Expected output:
```
=== Syncing Park Metadata ===
This will update facilities and site metadata for all active parks.

✓ San Onofre SB: 14 facilities, 297 sites

Metadata sync complete!
```

### Test API Endpoint:
```bash
# As an admin user, call:
curl -X POST http://campsiteagent.com/api/admin/sync-metadata \
  -H "Cookie: PHPSESSID=<your-session-id>"
```

Expected response:
```json
{
  "results": [
    {
      "park": "San Onofre SB",
      "success": true,
      "facilities": 14,
      "sites": 297
    }
  ]
}
```

## What This Does

**Purpose**: Sync park/facility/site metadata WITHOUT fetching availability data.

**When to Use**:
- When you add a new park
- When facility names change
- When site numbers or attributes change
- Rarely needed (metadata is relatively static)

**Performance**: Much faster than full scrape (~30 seconds vs 5-10 minutes)

**Data Synced**:
- ✅ Facilities (names, IDs)
- ✅ Sites (numbers, names, ADA, vehicle length, unit types)
- ❌ Availability data (use `/api/check-now` for that)

## Next Steps

After deployment, you can add a "Sync Park Metadata" button to your admin UI that calls:
```javascript
async function syncMetadata() {
  const res = await fetch('/api/admin/sync-metadata', { method: 'POST' });
  const data = await res.json();
  console.log('Metadata synced:', data);
}
```



