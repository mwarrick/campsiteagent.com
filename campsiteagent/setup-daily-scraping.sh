#!/bin/bash

# Setup Daily Scraping Cron Job
# This script helps configure automated daily scraping at 6 AM

echo "🚀 Campsite Agent - Daily Scraping Setup"
echo "========================================"
echo ""

# Get the current directory (should be the project root)
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT_PATH="$PROJECT_ROOT/app/bin/daily-scrape.php"

echo "📁 Project root: $PROJECT_ROOT"
echo "📄 Script path: $SCRIPT_PATH"
echo ""

# Check if the script exists
if [ ! -f "$SCRIPT_PATH" ]; then
    echo "❌ Error: daily-scrape.php not found at $SCRIPT_PATH"
    exit 1
fi

# Check if the script is executable
if [ ! -x "$SCRIPT_PATH" ]; then
    echo "🔧 Making script executable..."
    chmod +x "$SCRIPT_PATH"
fi

echo "✅ Script found and executable"
echo ""

# Test the script
echo "🧪 Testing the script..."
php "$SCRIPT_PATH" --help
echo ""

# Show current cron jobs
echo "📋 Current cron jobs for this user:"
crontab -l 2>/dev/null || echo "No cron jobs found"
echo ""

# Create the cron job entry
CRON_ENTRY="0 6 * * * cd $PROJECT_ROOT && php $SCRIPT_PATH --verbose >> /var/log/campsite-agent-daily.log 2>&1"

echo "📝 Proposed cron job entry:"
echo "$CRON_ENTRY"
echo ""

# Ask for confirmation
read -p "🤔 Do you want to add this cron job? (y/N): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Add the cron job
    echo "➕ Adding cron job..."
    
    # Get current crontab and add new entry
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    
    if [ $? -eq 0 ]; then
        echo "✅ Cron job added successfully!"
        echo ""
        echo "📋 Updated cron jobs:"
        crontab -l
        echo ""
        echo "📝 Log file: /var/log/campsite-agent-daily.log"
        echo "🕕 Schedule: Daily at 6:00 AM"
        echo ""
        echo "🔍 To test the script manually:"
        echo "   php $SCRIPT_PATH --dry-run --verbose"
        echo ""
        echo "📊 To view logs:"
        echo "   tail -f /var/log/campsite-agent-daily.log"
        echo ""
        echo "🗑️  To remove the cron job later:"
        echo "   crontab -e  # then delete the line"
    else
        echo "❌ Failed to add cron job"
        exit 1
    fi
else
    echo "⏭️  Skipping cron job setup"
    echo ""
    echo "🔧 To set up manually later:"
    echo "   1. Run: crontab -e"
    echo "   2. Add this line:"
    echo "      $CRON_ENTRY"
    echo "   3. Save and exit"
fi

echo ""
echo "🎯 Daily scraping setup complete!"
