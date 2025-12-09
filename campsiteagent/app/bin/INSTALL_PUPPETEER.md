# Installing Puppeteer on the Server

## Quick Install

Run these commands on your server:

```bash
cd /var/www/campsite-agent/app/bin

# Install Puppeteer (this will download Chromium, ~300MB)
npm install
```

## If you get permission errors:

```bash
# Option 1: Fix npm cache permissions
sudo chown -R $(whoami) ~/.npm
npm install

# Option 2: Use a different cache location
npm install --cache /tmp/npm-cache

# Option 3: Install as root (if you're already root)
npm install --unsafe-perm=true --allow-root
```

## Verify Installation

After installation, test it:

```bash
node scrape-via-browser.js facilities 627
```

You should see JSON output with facilities for Chino Hills SP.

## Troubleshooting

### "npm: command not found"
Install Node.js first:
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install nodejs npm

# CentOS/RHEL
sudo yum install nodejs npm
```

### "Permission denied" errors
Make sure you have write permissions to the directory:
```bash
sudo chown -R $(whoami) /var/www/campsite-agent/app/bin
```

### Installation takes too long
Puppeteer downloads Chromium (~300MB) on first install. This is normal and only happens once.

### Out of disk space
Puppeteer needs ~500MB free space. Check with:
```bash
df -h
```

