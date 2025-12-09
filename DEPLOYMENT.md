# Deployment Guide

This guide covers deploying CampsiteAgent.com to a production server.

## Prerequisites

- Ubuntu 22.04 LTS server
- Root or sudo access
- Domain name pointed to server IP
- SSL certificate (Let's Encrypt recommended)

## Server Setup

### 1. Update System

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install Required Software

```bash
# Install Apache, PHP, MySQL, and other dependencies
sudo apt install -y apache2 php8.1 php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml php8.1-zip mysql-server composer git

# Enable Apache modules
sudo a2enmod rewrite headers ssl
```

### 3. Configure MySQL

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'your_database_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';
GRANT ALL PRIVILEGES ON your_database_name.* TO 'your_database_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Deploy Application

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/yourusername/campsiteagent.git
sudo chown -R www-data:www-data campsiteagent
sudo chmod -R 755 campsiteagent

# Install PHP dependencies
cd campsiteagent/app
sudo -u www-data composer install --no-dev --optimize-autoloader
```

### 5. Configure Database

```bash
# Run database migrations
cd /var/www/campsiteagent/app
mysql -u campsiteagent -p campsitechecker < migrations/001_create_notifications.sql
mysql -u campsiteagent -p campsitechecker < migrations/002_create_users.sql
mysql -u campsiteagent -p campsitechecker < migrations/003_create_login_tokens.sql
mysql -u campsiteagent -p campsitechecker < migrations/004_create_parks.sql
mysql -u campsiteagent -p campsitechecker < migrations/005_create_sites_and_availability.sql
mysql -u campsiteagent -p campsitechecker < migrations/006_create_availability_runs.sql
mysql -u campsiteagent -p campsitechecker < migrations/007_create_settings.sql
mysql -u campsiteagent -p campsitechecker < migrations/008_add_park_number.sql
mysql -u campsiteagent -p campsitechecker < migrations/009_create_facilities.sql
mysql -u campsiteagent -p campsitechecker < migrations/010_enhance_sites_metadata_fixed.sql
mysql -u campsiteagent -p campsitechecker < migrations/011_create_user_alert_preferences.sql
mysql -u campsiteagent -p campsitechecker < migrations/012_add_popular_socal_parks.sql
mysql -u campsiteagent -p campsitechecker < migrations/013_add_user_selected_parks.sql
```

### 6. Configure Environment

```bash
# Create environment file
sudo -u www-data cp /var/www/campsiteagent/app/.env.example /var/www/campsiteagent/app/.env
sudo -u www-data nano /var/www/campsiteagent/app/.env
```

```bash
# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_secure_password_here

# ReserveCalifornia API
RC_BASE_URL=https://www.reservecalifornia.com
RC_TIMEOUT=15
RC_MAX_RETRIES=3
RC_USER_AGENT=CampsiteAgent/2.0 (+https://campsiteagent.com)

# Gmail API (configure with your credentials)
GMAIL_CLIENT_ID=your_gmail_client_id
GMAIL_CLIENT_SECRET=your_gmail_client_secret
GMAIL_REFRESH_TOKEN=your_gmail_refresh_token

# Optional: Test email for alerts
ALERT_TEST_EMAIL=admin@campsiteagent.com
```

### 7. Configure Apache

```bash
# Create virtual host
sudo nano /etc/apache2/sites-available/campsiteagent.com.conf
```

```apache
<VirtualHost *:80>
    ServerName campsiteagent.com
    ServerAlias www.campsiteagent.com
    DocumentRoot /var/www/campsiteagent/www
    
    <Directory /var/www/campsiteagent/www>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/campsiteagent_error.log
    CustomLog ${APACHE_LOG_DIR}/campsiteagent_access.log combined
</VirtualHost>
```

```bash
# Enable site and restart Apache
sudo a2ensite campsiteagent.com.conf
sudo a2dissite 000-default.conf
sudo systemctl restart apache2
```

### 8. Configure SSL with Let's Encrypt

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-apache

# Obtain SSL certificate
sudo certbot --apache -d campsiteagent.com -d www.campsiteagent.com

# Test auto-renewal
sudo certbot renew --dry-run
```

### 9. Configure MySQL for Production

```bash
# Edit MySQL configuration
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Add these optimizations for a server with 64GB RAM:

```ini
[mysqld]
# Memory settings
innodb_buffer_pool_size = 32G
max_connections = 500
thread_cache_size = 100
table_open_cache = 8000
table_definition_cache = 2000

# InnoDB settings
innodb_redo_log_capacity = 4G
innodb_log_buffer_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_io_capacity = 2000
innodb_io_capacity_max = 4000

# Query optimization
tmp_table_size = 1G
max_heap_table_size = 1G
sort_buffer_size = 8M
join_buffer_size = 8M
read_buffer_size = 2M
read_rnd_buffer_size = 8M

# Network settings
max_allowed_packet = 256M
net_buffer_length = 32K
max_connect_errors = 100000

# Logging
log_error = /var/log/mysql/error.log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 1
log_queries_not_using_indexes = 1
```

```bash
# Restart MySQL
sudo systemctl restart mysql
```

### 10. Set Up Initial Data

```bash
# Create admin user
mysql -u campsiteagent -p campsitechecker
```

```sql
INSERT INTO users (email, first_name, last_name, role, verified_at) 
VALUES ('admin@campsiteagent.com', 'Admin', 'User', 'admin', NOW());

INSERT INTO settings (`key`, `value`) VALUES 
('rc_user_agent', 'CampsiteAgent/2.0 (+https://campsiteagent.com)'),
('alert_test_email', 'admin@campsiteagent.com');

EXIT;
```

```bash
# Sync facilities
cd /var/www/campsiteagent/app
sudo -u www-data php bin/sync-facilities.php
```

## Security Configuration

### 1. Firewall Setup

```bash
# Configure UFW firewall
sudo ufw allow ssh
sudo ufw allow 'Apache Full'
sudo ufw enable
```

### 2. File Permissions

```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/campsiteagent
sudo chmod -R 755 /var/www/campsiteagent
sudo chmod 600 /var/www/campsiteagent/app/.env
```

### 3. PHP Security

```bash
# Edit PHP configuration
sudo nano /etc/php/8.1/apache2/php.ini
```

```ini
# Security settings
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

# Performance settings
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 50M
upload_max_filesize = 50M
```

### 4. Apache Security

```bash
# Edit Apache security configuration
sudo nano /etc/apache2/conf-available/security.conf
```

```apache
ServerTokens Prod
ServerSignature Off
```

```bash
sudo a2enconf security
sudo systemctl restart apache2
```

## Monitoring and Maintenance

### 1. Set Up Log Rotation

```bash
# Configure log rotation
sudo nano /etc/logrotate.d/campsiteagent
```

```
/var/log/apache2/campsiteagent_*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload apache2
    endscript
}
```

### 2. Set Up Monitoring

```bash
# Install monitoring tools
sudo apt install -y htop iotop nethogs

# Create monitoring script
sudo nano /usr/local/bin/campsiteagent-monitor.sh
```

```bash
#!/bin/bash
# CampsiteAgent Monitoring Script

echo "=== CampsiteAgent System Status ==="
echo "Date: $(date)"
echo

echo "=== Apache Status ==="
systemctl status apache2 --no-pager -l
echo

echo "=== MySQL Status ==="
systemctl status mysql --no-pager -l
echo

echo "=== Disk Usage ==="
df -h
echo

echo "=== Memory Usage ==="
free -h
echo

echo "=== Recent Errors ==="
tail -n 20 /var/log/apache2/campsiteagent_error.log
echo

echo "=== Database Connection Test ==="
# Use environment variable or .env file for password
mysql -u $DB_USER -p$DB_PASS -e "SELECT COUNT(*) as user_count FROM users;" $DB_NAME
```

```bash
sudo chmod +x /usr/local/bin/campsiteagent-monitor.sh
```

### 3. Set Up Automated Backups

```bash
# Create backup script
sudo nano /usr/local/bin/campsiteagent-backup.sh
```

```bash
#!/bin/bash
# CampsiteAgent Backup Script

BACKUP_DIR="/var/backups/campsiteagent"
DATE=$(date +%Y%m%d_%H%M%S)
# Load database credentials from environment or .env file
DB_NAME="${DB_DATABASE:-your_database_name}"
DB_USER="${DB_USERNAME:-your_database_user}"
DB_PASS="${DB_PASSWORD}"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u $DB_USER -p$DB_PASS --single-transaction --routines --triggers $DB_NAME > $BACKUP_DIR/database_$DATE.sql

# Application backup
tar -czf $BACKUP_DIR/application_$DATE.tar.gz -C /var/www campsiteagent

# Clean up old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

```bash
sudo chmod +x /usr/local/bin/campsiteagent-backup.sh

# Add to crontab
sudo crontab -e
```

Add this line for daily backups at 2 AM:
```
0 2 * * * /usr/local/bin/campsiteagent-backup.sh
```

## Performance Optimization

### 1. Enable Apache Caching

```bash
# Enable mod_cache
sudo a2enmod cache cache_disk

# Add to virtual host
sudo nano /etc/apache2/sites-available/campsiteagent.com.conf
```

```apache
# Add inside VirtualHost
<IfModule mod_cache.c>
    CacheDefaultExpire 3600
    CacheMaxExpire 86400
    CacheLastModifiedFactor 0.1
    CacheIgnoreHeaders Set-Cookie
    CacheIgnoreNoLastMod On
    CacheStoreNoStore On
    CacheStorePrivate On
</IfModule>

<IfModule mod_cache_disk.c>
    CacheRoot /var/cache/apache2/mod_cache_disk
    CacheDirLevels 2
    CacheDirLength 1
</IfModule>
```

### 2. Enable Gzip Compression

```bash
# Enable mod_deflate
sudo a2enmod deflate

# Add to virtual host
```

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data /var/www/campsiteagent
   sudo chmod -R 755 /var/www/campsiteagent
   ```

2. **Database Connection Issues**
   ```bash
   # Test database connection
   mysql -u campsiteagent -p campsitechecker -e "SELECT 1;"
   ```

3. **Apache Configuration Issues**
   ```bash
   # Test Apache configuration
   sudo apache2ctl configtest
   
   # Check error logs
   sudo tail -f /var/log/apache2/error.log
   ```

4. **PHP Errors**
   ```bash
   # Check PHP error log
   sudo tail -f /var/log/php_errors.log
   ```

### Health Check Script

```bash
# Create health check endpoint
sudo nano /var/www/campsiteagent/www/health.php
```

```php
<?php
header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Database check
try {
    // Use environment variables - never hardcode credentials
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db = getenv('DB_DATABASE') ?: 'your_database_name';
    $user = getenv('DB_USERNAME') ?: 'your_database_user';
    $pass = getenv('DB_PASSWORD') ?: '';
    
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db}",
        $user,
        $pass
    );
    $health['checks']['database'] = 'ok';
} catch (Exception $e) {
    $health['checks']['database'] = 'error: ' . $e->getMessage();
    $health['status'] = 'error';
}

// Disk space check
$diskFree = disk_free_space('/');
$diskTotal = disk_total_space('/');
$diskPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

if ($diskPercent > 90) {
    $health['checks']['disk'] = 'warning: ' . round($diskPercent, 2) . '% used';
    $health['status'] = 'warning';
} else {
    $health['checks']['disk'] = 'ok: ' . round($diskPercent, 2) . '% used';
}

echo json_encode($health, JSON_PRETTY_PRINT);
?>
```

## Updates and Maintenance

### 1. Application Updates

```bash
# Update application
cd /var/www/campsiteagent
sudo -u www-data git pull origin main

# Update dependencies
cd app
sudo -u www-data composer install --no-dev --optimize-autoloader

# Run any new migrations
# (Check for new migration files and run them)
```

### 2. System Updates

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Restart services
sudo systemctl restart apache2 mysql
```

---

For additional support, refer to the main README.md file or create an issue on GitHub.
