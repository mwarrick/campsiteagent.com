# CampsiteAgent.com

**Version 2.1.0** - October 2025 (STABLE)

Automated monitoring system for California State Park campground availability, focusing on weekend availability at popular Southern California parks.

## 🏕️ Project Overview

CampsiteAgent.com is a web application that automatically monitors ReserveCalifornia.com for weekend campsite availability and provides real-time updates to users. The system uses the UseDirect API (ReserveCalifornia's backend) to fetch real-time availability data and presents it through an intuitive web interface.

## ✨ Features

### ✅ Core Functionality (v2.1)

**🆕 New in v2.1:**
- **🚨 Park Alerts System**: Real-time park closure and restriction notifications
  - Automatic scraping of park safety alerts and restrictions
  - Critical alerts for bear activity, water quality, and facility closures
  - Color-coded alert display (Critical/Warning/Info) in dashboard
  - Admin interface for managing and viewing all park alerts
- **🔧 Unified Admin Panel**: Consolidated all admin functions into one interface
  - Parks management, facilities management, user management, and data tools
  - Modal-based user management integrated into main admin panel
  - Improved button styling and consistent UI design
- **📋 Enhanced Alert Parsing**: Better detection of park safety information
  - Support for multiple park website formats and HTML structures
  - Specific parsing for Silverwood Lake SRA safety alerts
  - Flexible regex patterns for various alert types
- **🗓️ ReserveCalifornia Booking Assistant**: Smart modal for booking guidance
  - Automatic date range detection for weekend vs daily listings
  - Professional modal with booking instructions and date reminders
  - Weekend listings: Shows 3-day date range for 2-night stays
  - Daily listings: Shows single start date for 1-night stays
  - Clear instructions for entering dates on ReserveCalifornia.com

**Previous Features (v2.0):**
- **"Disable All Alerts" links** in digest emails for easy opt-out
- **"Email Digest Now" button** on user dashboard for on-demand emails
- **Separate scraping and emailing** processes for better reliability
- **Comprehensive test scripts** for debugging and validation
- **Enhanced email templates** with dual disable links
- **Fixed database status issues** in daily scraping

- **🔐 User Authentication**: Email-based registration and passwordless login via Gmail API
- **🌐 Real API Integration**: Direct integration with UseDirect API (ReserveCalifornia backend)
- **📅 Weekend Detection**: Accurately identifies sites with Friday AND Saturday night availability
- **📧 Daily Email Notifications**: 
  - HTML/text emails grouped by weekend with facility details
  - Personalized digests based on user alert preferences
  - "Email Digest Now" button for on-demand notifications
  - "Disable All Alerts" links for easy opt-out
- **📊 Interactive Dashboard**: 
  - Weekend-grouped view with facility breakdown
  - Date range filters (30/60/90/180 days)
  - Real-time availability checking (SSE)
  - CSV export functionality
- **⭐ Favorites (per user)**:
  - Global “Manage Favorites” modal (Park → Facility → Sites)
  - Toggle stars to favorite/unfavorite sites; persists per user
  - Favorites prioritized in results; “Favorites only” filter supported
  - Colored star displayed next to favorites in results
  - Facilities/weekends with zero favorite matches are hidden in favorites-only mode
- **♻️ Availability Reconciliation**:
  - Stale availability cleared within scraped window
  - `found_at` timestamp reflects latest scrape via `updated_at`
- **🏗️ Metadata Management**: Full park, facility, and site metadata tracking
- **⚙️ Admin Controls**: Park activation, facility management, metadata sync
- **👥 User Preferences**: Customizable alert preferences per user
- **🔧 Admin Scraping Interface**: Dedicated admin interface for data collection
- **⏰ Daily Automated Scraping**: Automated daily scraping with CLI script and cron job support
- **📧 Email Digest System**: Separate CLI script for sending daily digest emails to all users
- **🔧 Test Scripts**: Comprehensive testing tools for scraping and email functionality
- **📱 Mobile Responsive**: Optimized for mobile devices (390px+ width)

### 🚀 Technical Features

- **Real-time Updates**: Server-Sent Events (SSE) for live scraping progress
- **Responsive Design**: Modern, mobile-friendly interface
- **Database Optimization**: Efficient queries with proper indexing
- **Error Handling**: Comprehensive error logging and user feedback
- **Security**: Role-based access control and secure authentication

## 🛠️ Technical Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Email**: Gmail API (OAuth2)
- **External API**: UseDirect API (calirdr.usedirect.com)
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Deployment**: Apache 2.4, Ubuntu 22.04

## 📁 Project Structure

```
campsiteagent/
├── app/
│   ├── bin/                    # Command-line scripts
│   │   ├── sync-facilities.php # Facility synchronization
│   │   └── ...
│   ├── migrations/             # Database migration files
│   │   ├── 001_create_notifications.sql
│   │   ├── 002_create_users.sql
│   │   └── ...
│   └── src/
│       ├── Infrastructure/     # Database and HTTP clients
│       ├── Repositories/       # Data access layer
│       ├── Services/          # Business logic
│       └── Templates/         # Email templates
├── www/                       # Web application files
│   ├── index.php             # Main API router
│   ├── dashboard.html        # User dashboard
│   ├── admin-scraping.html   # Admin scraping interface
│   └── ...
├── plan/                     # Planning documents
└── README.md
```

## 🚀 Getting Started

### Prerequisites

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache 2.4 or higher
- Composer (for PHP dependencies)
- Gmail API credentials (for email functionality)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/campsiteagent.git
   cd campsiteagent
   ```

2. **Install PHP dependencies**
   ```bash
   cd campsiteagent/app
   composer install
   ```

3. **Set up the database**
   
   Create a MySQL database and run the migration files in order:
   ```bash
   mysql -u root -p your_database_name < migrations/001_create_notifications.sql
   mysql -u root -p your_database_name < migrations/002_create_users.sql
   mysql -u root -p your_database_name < migrations/003_create_login_tokens.sql
   mysql -u root -p your_database_name < migrations/004_create_parks.sql
   mysql -u root -p your_database_name < migrations/005_create_sites_and_availability.sql
   mysql -u root -p your_database_name < migrations/006_create_availability_runs.sql
   mysql -u root -p your_database_name < migrations/007_create_settings.sql
   mysql -u root -p your_database_name < migrations/008_add_park_number.sql
   mysql -u root -p your_database_name < migrations/009_create_facilities.sql
   mysql -u root -p your_database_name < migrations/010_enhance_sites_metadata_fixed.sql
   mysql -u root -p your_database_name < migrations/011_create_user_alert_preferences.sql
   mysql -u root -p your_database_name < migrations/012_add_popular_socal_parks.sql
   mysql -u root -p your_database_name < migrations/013_add_user_selected_parks.sql
   # New in v2.1
   mysql -u root -p your_database_name < migrations/014_add_updated_at_to_site_availability.sql
   mysql -u root -p your_database_name < migrations/015_create_user_favorites.sql
   # New in v2.3
   mysql -u root -p your_database_name < migrations/016_add_park_websites.sql
   ```

4. **Configure environment variables**
   
   Create a `.env` file in the `campsiteagent/app` directory:
   ```bash
   # Database Configuration
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=campsitechecker
   DB_USERNAME=root
   DB_PASSWORD=your_password
   
   # ReserveCalifornia API
   RC_BASE_URL=https://www.reservecalifornia.com
   RC_TIMEOUT=15
   RC_MAX_RETRIES=3
   RC_USER_AGENT=CampsiteAgent/2.0 (+https://campsiteagent.com)
   
   # Gmail API (for email notifications)
   GMAIL_CLIENT_ID=your_gmail_client_id
   GMAIL_CLIENT_SECRET=your_gmail_client_secret
   GMAIL_REFRESH_TOKEN=your_gmail_refresh_token
   
   # Optional: Test email for alerts
   ALERT_TEST_EMAIL=you@example.com
   ```

5. **Set up Gmail API**
   
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create a new project or select existing one
   - Enable Gmail API
   - Create OAuth2 credentials
   - Download the credentials and configure the environment variables

6. **Configure Apache**
   
   Point your Apache virtual host to the `campsiteagent/www` directory:
   ```apache
   <VirtualHost *:80>
       ServerName campsiteagent.com
       DocumentRoot /path/to/campsiteagent/www
       
       <Directory /path/to/campsiteagent/www>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

7. **Set up initial data**
   
   Run the facility synchronization script:
   ```bash
   cd campsiteagent/app
   php bin/sync-facilities.php
   ```

### Development Setup

For local development:

```bash
cd campsiteagent/app
composer install
php -S 127.0.0.1:8080 -t ../www
```

Access the application at `http://127.0.0.1:8080`

## 📖 API Documentation

### Authentication Endpoints

- `POST /api/register` - Register a new user
- `POST /api/login` - Send login email
- `GET /api/auth/callback?token=...` - Complete login
- `GET /api/me` - Get current user info
- `POST /api/logout` - Logout user

### Availability Endpoints

- `GET /api/availability/latest` - Get latest availability data
- `GET /api/availability/export.csv` - Export availability as CSV
- `POST /api/check-now` - Trigger manual availability check (admin)

### Favorites & Metadata Endpoints

- `GET /api/favorites` — List current user's favorite site IDs
- `POST /api/favorites/{siteId}/toggle` — Toggle favorite for a site
- `GET /api/parks/{parkId}/facilities` — List active facilities for a park
- `GET /api/facilities/{facilityId}/sites` — List all sites for a facility with `favorite` flag

### Admin Endpoints

- `GET /api/admin/parks` - List all parks
- `POST /api/admin/parks` - Create/update park
- `GET /api/admin/parks/{id}/facilities` - Get park facilities
- `POST /api/admin/facilities/{id}/toggle` - Toggle facility status
- `POST /api/admin/sync-facilities` - Sync facilities from API
- `POST /api/admin/sync-metadata` - Sync park metadata
- `POST /api/admin/notifications/daily-test` - Send test digest email (admin only)
- `POST /api/admin/notifications/daily-run` - Send daily digest to all users
- `POST /api/admin/notifications/scrape-results` - Send email with scrape results
- `POST /api/user/send-digest` - Send personal digest email (user only)
- `GET /api/user/disable-alerts/{token}` - Disable all user alerts via email link
- `PUT /api/admin/parks/{id}/website-url` - Update park website URL (admin only)

## 🎯 Usage

### For Users

1. **Register**: Visit the homepage and register with your email
2. **Login**: Check your email for the login link
3. **Browse**: Use the dashboard to view available campsites
4. **Filter**: Use date range and park filters to find specific availability
5. **Favorites**: 
   - Click "★ Manage Favorites" to mark favorite sites
   - Select Park → Facility → Sites to manage favorites
   - Use "Favorites only" filter to see only your favorite sites
   - Favorite sites appear with colored stars and are prioritized in results
6. **Preferences**: Set up email alert preferences for specific parks and date ranges
7. **Export**: Download CSV files for offline analysis

### For Administrators

1. **Access Admin Tools**: Login with an admin account
2. **Manage Parks**: Activate/deactivate parks for monitoring
3. **Manage Park Websites**: Edit and test park website URLs in the parks management interface
4. **Sync Facilities**: Update facility data from ReserveCalifornia
5. **Monitor Scraping**: Use the admin scraping interface for data collection
6. **Email Testing**: Use "Send Test Digest" to test email notifications
7. **Daily Notifications**: Set up cron job for automated daily email digests
8. **Manage Users**: View user preferences and activity

### Command Line Interface

The system includes CLI scripts for automated operations:

```bash
# Full scraping with email notifications
/usr/bin/php /var/www/campsite-agent/app/bin/check-now.php

# Email-only mode (no scraping, uses existing data)
/usr/bin/php /var/www/campsite-agent/app/bin/check-now.php --dry-run

# Daily automated scraping
/usr/bin/php /var/www/campsite-agent/app/bin/daily-scrape.php --verbose

# Send daily digest emails to all users
/usr/bin/php /var/www/campsite-agent/app/bin/send-daily-digest.php --verbose

# Test email digest (dry run)
/usr/bin/php /var/www/campsite-agent/app/bin/send-daily-digest.php --dry-run --verbose

# Test scraping functionality (limited scope)
/usr/bin/php /var/www/campsite-agent/app/bin/test-daily-scrape.php --parks=1,2 --months=1 --verbose

# Test scraping with emails
/usr/bin/php /var/www/campsite-agent/app/bin/test-daily-scrape.php --parks=1,2 --months=1 --send-emails --verbose

# Test Gmail API configuration
/usr/bin/php /var/www/campsite-agent/app/bin/test-gmail-config.php

# Populate park website URLs (one-time setup)
/usr/bin/php /var/www/campsite-agent/app/bin/scrape-park-websites.php --verbose
```

**Cron Job Setup** (recommended separate scraping and emailing):
```bash
# Daily scraping at 5:00 AM
0 5 * * * php /var/www/campsite-agent/app/bin/daily-scrape.php --verbose >> /var/www/campsite-agent/logs/scrape.log 2>&1

# Daily digest emails at 6:00 AM (1 hour after scraping)
0 6 * * * php /var/www/campsite-agent/app/bin/send-daily-digest.php --verbose >> /var/www/campsite-agent/logs/digest.log 2>&1
```

### Maintenance Scripts

**Fix "Unknown Facility" Issue:**
```bash
# Test run (see what would be changed)
php fix-unknown-facilities.php --dry-run --verbose

# Apply the fix
php fix-unknown-facilities.php --verbose

# Quick fix (minimal output)
php fix-unknown-facilities.php
```

This script addresses the common issue where sites have NULL facility_id, causing the dashboard to display "Unknown Facility" instead of actual facility names. Run this after scraping new parks or if you see "Unknown Facility" on the dashboard.

### Park Website Management

**Initial Setup:**
```bash
# Populate park website URLs (run once after migration)
php scrape-park-websites.php --verbose
```

**Admin Interface:**
- Access the admin scraping page and click "🏞️ Manage Parks"
- Edit website URLs directly in the interface
- Use "Test" buttons to verify URLs work
- Save changes immediately to the database

**Features:**
- Inline editing of park website URLs
- URL validation and testing
- Visual feedback for successful saves
- Mobile-responsive interface
- Bulk management capabilities

## 🔧 Configuration

### Database Optimization

For production environments with 64GB RAM, consider these MySQL optimizations:

```ini
[mysqld]
innodb_buffer_pool_size = 32G
max_connections = 500
thread_cache_size = 100
table_open_cache = 8000
innodb_redo_log_capacity = 4G
innodb_log_buffer_size = 256M
```

### Apache Configuration

Add to your `.htaccess` file for optimal performance:

```apache
# PHP Memory & Execution Settings
php_value memory_limit 512M
php_value max_execution_time 300

# Disable buffering for Server-Sent Events
<IfModule mod_headers.c>
    Header always set X-Accel-Buffering "no"
</IfModule>
```

## 🐛 Troubleshooting

### Known Issues

1. **SQL Parameter Errors**: Occasional "SQLSTATE[HY093]: Invalid parameter number" errors during scraping (does not affect functionality)
2. **Email Delivery**: Gmail API rate limits may affect high-volume email sending

### Common Issues

1. **"Unknown Facility" on Dashboard**: Sites have NULL facility_id
   ```bash
   # Fix with maintenance script
   php fix-unknown-facilities.php --verbose
   ```
2. **SQL Parameter Errors**: Ensure all database migrations are run in order
3. **Email Not Working**: Verify Gmail API credentials and permissions
4. **Scraping Failures**: Check network connectivity and API rate limits
5. **Permission Errors**: Ensure Apache has write access to log directories

### Debug Mode

Enable debug logging by setting:
```bash
php_value log_errors On
php_value error_log /var/log/php_errors.log
```

## 📊 Monitoring

### Key Metrics

- **Scraping Success Rate**: Monitor API response times and error rates
- **Database Performance**: Track query execution times
- **User Activity**: Monitor registration and login patterns
- **Email Delivery**: Track notification success rates

### Log Files

- **Apache Error Log**: `/var/log/apache2/error.log`
- **PHP Error Log**: `/var/log/php_errors.log`
- **Scraping Log**: `/var/www/campsite-agent/logs/scrape.log`
- **Application Logs**: Check database `availability_runs` table for scraping history

## 🤝 Contributing

This project is currently in active development. For contributions:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under a custom license. See the [LICENSE.md](LICENSE.md) file for details.

## ⚠️ Support Policy

**NO SUPPORT - AS-IS**

This software is provided "AS-IS" without any warranty or support. Use at your own risk.

- No technical support provided
- No bug fixes or updates guaranteed
- No documentation updates
- No assistance with setup or deployment
- No response to issues or questions

This is a personal project shared for educational purposes only.

## 📋 Changelog

### Version 2.1.1 (November 4, 2025)
- **🔗 ReserveCalifornia URL Fix**: Updated booking links to use new URL format
  - Changed from old format (`/Web/Default.aspx#!park/2`) to new format (`/park/{parkNumber}/{facilityId}`)
  - Fixed 404 errors when clicking booking links
  - Updated both dashboard and email notifications
- **📧 Email Digest Improvements**: Fixed park name display in email digests
  - Changed from generic "Daily Digest" to actual park names (e.g., "Chino Hills SP")
  - Shows "Multiple Parks" when digest contains multiple parks
  - Applied to all digest types: daily, personal, and test digests
- **🗓️ Scraper Date Range Fix**: Fixed date range calculation to ensure 6 months from today
  - Now correctly includes the month that contains "6 months from today"
  - Previously stopped at April when it should include May (Nov + 6 months)
- **🚨 Park Alerts Expiration Filtering**: Added automatic filtering of expired alerts
  - Parses date ranges from alert text (e.g., "June 1-September 30")
  - Automatically filters out alerts where expiration date has passed
  - Filters alerts even if expiration_date is NULL by parsing text
  - Prevents showing outdated alerts like "closed June 1-September 30" in November

### Version 2.1.0 (October 9, 2025)
- **🚨 Park Alerts System**: Real-time park closure and restriction notifications
  - Automatic scraping of park safety alerts and restrictions
  - Critical alerts for bear activity, water quality, and facility closures
  - Color-coded alert display (Critical/Warning/Info) in dashboard
  - Admin interface for managing and viewing all park alerts
- **🔧 Unified Admin Panel**: Consolidated all admin functions into one interface
  - Parks management, facilities management, user management, and data tools
  - Modal-based user management integrated into main admin panel
  - Improved button styling and consistent UI design
- **📋 Enhanced Alert Parsing**: Better detection of park safety information
  - Support for multiple park website formats and HTML structures
  - Specific parsing for Silverwood Lake SRA safety alerts
  - Flexible regex patterns for various alert types
- **🗓️ ReserveCalifornia Booking Assistant**: Smart modal for booking guidance
  - Automatic date range detection for weekend vs daily listings
  - Professional modal with booking instructions and date reminders
  - Weekend listings: Shows 3-day date range for 2-night stays
  - Daily listings: Shows single start date for 1-night stays
  - Clear instructions for entering dates on ReserveCalifornia.com
- **🐛 Bug Fixes**: Fixed admin authentication issues and inconsistent button styling

### Version 2.0.0 (October 2025)
- **🔗 Park Website Links**: Added clickable links to official government park websites in dashboard
- **🏞️ Enhanced Parks Management**: Complete admin interface for managing park website URLs
- **🔧 Admin Tools**: URL validation, testing, and bulk management capabilities
- **📱 Mobile Responsive**: Optimized admin interface for mobile devices

### Version 2.2.0 (October 2025)
- **"Disable All Alerts" links** in digest emails for easy opt-out
- **"Email Digest Now" button** on user dashboard for on-demand emails
- **Separate scraping and emailing** processes for better reliability
- **Comprehensive test scripts** for debugging and validation
- **Enhanced email templates** with dual disable links
- **Fixed database status issues** in daily scraping

## 🎉 Acknowledgments

- ReserveCalifornia.com for providing the API access
- Google for Gmail API integration
- The open-source community for various PHP libraries

---

**Note**: This project is designed for personal use and educational purposes. Please respect ReserveCalifornia's terms of service and rate limits when using this application.