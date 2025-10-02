#!/bin/bash
# Complete deployment script for all fixes

set -e

SERVER="mark@campsiteagent.com"
APP_ROOT="/var/www/campsite-agent"

echo "=== Deploying Weekend Detection Fixes ==="
echo ""

# Copy all files to server
echo "1. Copying files to server..."
scp campsiteagent/app/src/Services/WeekendDetector.php \
    campsiteagent/app/src/Services/ReserveCaliforniaScraper.php \
    campsiteagent/app/src/Services/ScraperService.php \
    campsiteagent/app/src/Services/MetadataSyncService.php \
    campsiteagent/app/src/Templates/EmailTemplates.php \
    campsiteagent/app/bin/sync-metadata.php \
    campsiteagent/www/index.php \
    $SERVER:/tmp/

echo ""
echo "2. Moving files to correct locations..."
ssh $SERVER << 'ENDSSH'
sudo mv /tmp/WeekendDetector.php /var/www/campsite-agent/app/src/Services/
sudo mv /tmp/ReserveCaliforniaScraper.php /var/www/campsite-agent/app/src/Services/
sudo mv /tmp/ScraperService.php /var/www/campsite-agent/app/src/Services/
sudo mv /tmp/MetadataSyncService.php /var/www/campsite-agent/app/src/Services/
sudo mv /tmp/EmailTemplates.php /var/www/campsite-agent/app/src/Templates/
sudo mv /tmp/sync-metadata.php /var/www/campsite-agent/app/bin/
sudo mv /tmp/index.php /var/www/campsite-agent/www/

echo "3. Fixing permissions..."
sudo bash /var/www/campsite-agent/fix-permissions.sh

echo ""
echo "✅ Deployment complete!"
echo ""
echo "Next steps:"
echo "  1. Clear old data:"
echo "     mysql -u campsitechecker -p campsitechecker -e \"DELETE FROM site_availability; DELETE FROM sites; DELETE FROM facilities;\""
echo ""
echo "  2. Run fresh scrape:"
echo "     cd /var/www/campsite-agent/app && php bin/check-now.php"
echo ""
echo "  3. Check results in dashboard:"
echo "     http://campsiteagent.com/dashboard.html"
ENDSSH

echo ""
echo "🎉 All done!"



