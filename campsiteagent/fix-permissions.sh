#!/bin/bash
# Fix permissions for Campsite Agent after deployment
# Run this script after copying files to the server

echo "Fixing ownership and permissions for Campsite Agent..."

# Fix ownership - make everything owned by www-data
echo "Setting ownership to www-data:www-data..."
chown -R www-data:www-data /var/www/campsite-agent/app
chown -R www-data:www-data /var/www/campsite-agent/www

# Fix directory permissions (755 = rwxr-xr-x)
echo "Setting directory permissions..."
find /var/www/campsite-agent/app -type d -exec chmod 755 {} \;
find /var/www/campsite-agent/www -type d -exec chmod 755 {} \;

# Fix file permissions (644 = rw-r--r--)
echo "Setting file permissions..."
find /var/www/campsite-agent/app -type f -exec chmod 644 {} \;
find /var/www/campsite-agent/www -type f -exec chmod 644 {} \;

# Extra security for sensitive files (600 = rw-------)
echo "Securing sensitive files..."
if [ -f /var/www/campsite-agent/app/.env ]; then
    chmod 600 /var/www/campsite-agent/app/.env
    echo "  ✓ .env"
fi

if [ -f /var/www/campsite-agent/app/credentials.json ]; then
    chmod 600 /var/www/campsite-agent/app/credentials.json
    echo "  ✓ credentials.json"
fi

if [ -f /var/www/campsite-agent/app/token.json ]; then
    chmod 600 /var/www/campsite-agent/app/token.json
    echo "  ✓ token.json"
fi

echo "✅ Permissions fixed!"
echo ""
echo "Current ownership:"
ls -la /var/www/campsite-agent/ | head -5

