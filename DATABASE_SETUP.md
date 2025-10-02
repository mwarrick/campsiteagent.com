# Database Setup Guide

This guide provides step-by-step instructions for setting up the CampsiteAgent.com database from scratch.

## Prerequisites

- MySQL 8.0 or higher
- Database user with CREATE, INSERT, UPDATE, DELETE privileges
- Command-line access to MySQL

## Quick Setup

### 1. Create Database

```sql
CREATE DATABASE campsitechecker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campsitechecker;
```

### 2. Run All Migrations

Execute the following commands in order:

```bash
# Navigate to the project directory
cd campsiteagent/app

# Run all migrations in order
mysql -u root -p campsitechecker < migrations/001_create_notifications.sql
mysql -u root -p campsitechecker < migrations/002_create_users.sql
mysql -u root -p campsitechecker < migrations/003_create_login_tokens.sql
mysql -u root -p campsitechecker < migrations/004_create_parks.sql
mysql -u root -p campsitechecker < migrations/005_create_sites_and_availability.sql
mysql -u root -p campsitechecker < migrations/006_create_availability_runs.sql
mysql -u root -p campsitechecker < migrations/007_create_settings.sql
mysql -u root -p campsitechecker < migrations/008_add_park_number.sql
mysql -u root -p campsitechecker < migrations/009_create_facilities.sql
mysql -u root -p campsitechecker < migrations/010_enhance_sites_metadata_fixed.sql
mysql -u root -p campsitechecker < migrations/011_create_user_alert_preferences.sql
mysql -u root -p campsitechecker < migrations/012_add_popular_socal_parks.sql
mysql -u root -p campsitechecker < migrations/013_add_user_selected_parks.sql
```

### 3. Verify Installation

```sql
-- Check that all tables were created
SHOW TABLES;

-- Expected tables:
-- availability_runs
-- facilities
-- login_tokens
-- notifications
-- parks
-- site_availability
-- sites
-- user_alert_preferences
-- user_selected_parks
-- users
-- settings
```

## Migration Details

### 001_create_notifications.sql
Creates the `notifications` table for storing email notifications.

### 002_create_users.sql
Creates the `users` table for user accounts with email-based authentication.

### 003_create_login_tokens.sql
Creates the `login_tokens` table for passwordless login system.

### 004_create_parks.sql
Creates the `parks` table for storing park information.

### 005_create_sites_and_availability.sql
Creates the `sites` and `site_availability` tables for campsite data.

### 006_create_availability_runs.sql
Creates the `availability_runs` table for tracking scraping operations.

### 007_create_settings.sql
Creates the `settings` table for application configuration.

### 008_add_park_number.sql
Adds `park_number` column to the `parks` table.

### 009_create_facilities.sql
Creates the `facilities` table and adds `facility_id` to `sites` table.

### 010_enhance_sites_metadata_fixed.sql
Adds additional metadata columns to `sites` and `parks` tables.

### 011_create_user_alert_preferences.sql
Creates the `user_alert_preferences` table for user notification settings.

### 012_add_popular_socal_parks.sql
Inserts initial data for popular Southern California parks.

### 013_add_user_selected_parks.sql
Creates the `user_selected_parks` table for user park preferences.

## Post-Installation

### 1. Create Admin User

```sql
INSERT INTO users (email, first_name, last_name, role, verified_at) 
VALUES ('admin@example.com', 'Admin', 'User', 'admin', NOW());
```

### 2. Set Initial Settings

```sql
INSERT INTO settings (`key`, `value`) VALUES 
('rc_user_agent', 'CampsiteAgent/2.0 (+https://campsiteagent.com)'),
('alert_test_email', 'admin@example.com');
```

### 3. Sync Facilities

Run the facility synchronization script:

```bash
cd campsiteagent/app
php bin/sync-facilities.php
```

## Database Schema Overview

### Core Tables

- **users**: User accounts and authentication
- **parks**: Park information and configuration
- **facilities**: Campground facilities within parks
- **sites**: Individual campsites
- **site_availability**: Daily availability data for sites

### Supporting Tables

- **login_tokens**: Passwordless login system
- **availability_runs**: Scraping operation tracking
- **user_alert_preferences**: User notification settings
- **user_selected_parks**: User park preferences
- **settings**: Application configuration
- **notifications**: Email notification history

## Performance Optimization

### Recommended MySQL Configuration

For production environments, consider these optimizations:

```ini
[mysqld]
# Memory settings for 64GB RAM server
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

### Index Optimization

The database includes optimized indexes for common queries:

- **sites**: `idx_park`, `idx_facility_id`
- **site_availability**: `idx_site`, `uq_site_date`
- **parks**: `idx_active`
- **facilities**: `idx_park_id`, `idx_active`
- **users**: `idx_email`
- **login_tokens**: `idx_token`, `idx_expires`

## Troubleshooting

### Common Issues

1. **Foreign Key Constraint Errors**
   - Ensure migrations are run in the correct order
   - Check that referenced tables exist before creating foreign keys

2. **Character Set Issues**
   - Ensure database is created with `utf8mb4` character set
   - Verify all tables use `utf8mb4_unicode_ci` collation

3. **Permission Errors**
   - Ensure database user has CREATE, INSERT, UPDATE, DELETE privileges
   - Check that user can create tables and indexes

### Verification Queries

```sql
-- Check table structure
DESCRIBE users;
DESCRIBE parks;
DESCRIBE sites;
DESCRIBE site_availability;

-- Check foreign key constraints
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = 'campsitechecker';

-- Check indexes
SHOW INDEX FROM sites;
SHOW INDEX FROM site_availability;
```

## Backup and Recovery

### Create Backup

```bash
mysqldump -u root -p --single-transaction --routines --triggers campsitechecker > campsitechecker_backup.sql
```

### Restore from Backup

```bash
mysql -u root -p campsitechecker < campsitechecker_backup.sql
```

## Maintenance

### Regular Maintenance Tasks

1. **Clean up old login tokens**
   ```sql
   DELETE FROM login_tokens WHERE expires_at < NOW() - INTERVAL 1 DAY;
   ```

2. **Archive old availability data**
   ```sql
   DELETE FROM site_availability WHERE date < CURDATE() - INTERVAL 90 DAY;
   ```

3. **Optimize tables**
   ```sql
   OPTIMIZE TABLE sites, site_availability, parks, facilities;
   ```

---

For additional support, refer to the main README.md file or create an issue on GitHub.
