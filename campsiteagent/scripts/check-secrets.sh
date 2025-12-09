#!/bin/bash

# Standalone script to check for sensitive information in the codebase
# Can be run manually: ./scripts/check-secrets.sh

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔍 Scanning codebase for sensitive information...${NC}"
echo ""

# Patterns to check for (case-insensitive)
PATTERNS=(
    "password\s*=\s*['\"][^'\"]+['\"]"
    "password\s*=\s*[^[:space:]]+"
    "DB_PASSWORD\s*=\s*['\"][^'\"]+['\"]"
    "DB_PASSWORD\s*=\s*[^[:space:]]+"
    "api[_-]?key\s*=\s*['\"][^'\"]+['\"]"
    "api[_-]?key\s*=\s*[^[:space:]]+"
    "secret[_-]?key\s*=\s*['\"][^'\"]+['\"]"
    "secret[_-]?key\s*=\s*[^[:space:]]+"
    "private[_-]?key\s*=\s*['\"][^'\"]+['\"]"
    "private[_-]?key\s*=\s*[^[:space:]]+"
    "access[_-]?token\s*=\s*['\"][^'\"]+['\"]"
    "access[_-]?token\s*=\s*[^[:space:]]+"
    "mysql://[^[:space:]]+:[^[:space:]]+@"
    "postgres://[^[:space:]]+:[^[:space:]]+@"
    "mongodb://[^[:space:]]+:[^[:space:]]+@"
    "connectionString\s*=\s*['\"][^'\"]*://[^'\"]*:[^'\"]*@"
)

# Files/directories to skip
SKIP_PATTERNS=(
    "vendor/"
    "node_modules/"
    ".git/"
    ".gitignore"
    "package-lock.json"
    "composer.lock"
    "check-secrets.sh"
    "pre-commit"
    ".env"  # .env files are expected to contain secrets, just shouldn't be in git
)

ERRORS=0
WARNINGS=0

# Find all PHP, JS, and config files
FILES=$(find campsiteagent -type f \( -name "*.php" -o -name "*.js" -o -name "*.json" -o -name "*.env*" -o -name "*.config*" -o -name "*.conf" \) 2>/dev/null)

for file in $FILES; do
    # Skip if file matches skip patterns
    SKIP=false
    for skip_pattern in "${SKIP_PATTERNS[@]}"; do
        if [[ "$file" == *"$skip_pattern"* ]]; then
            SKIP=true
            break
        fi
    done
    
    if [ "$SKIP" = true ] || [ ! -f "$file" ]; then
        continue
    fi
    
    # Check each pattern
    for pattern in "${PATTERNS[@]}"; do
        if grep -qiE "$pattern" "$file" 2>/dev/null; then
            # Check if it's using environment variables (acceptable)
            if grep -qiE "(getenv|\\\$_ENV|process\.env)" "$file" 2>/dev/null; then
                # This is likely using env vars, which is OK
                continue
            fi
            
            echo -e "${RED}❌ SECURITY ISSUE:${NC} ${YELLOW}$file${NC}"
            echo -e "   Pattern: ${RED}$pattern${NC}"
            # Show context (2 lines before and after)
            echo -e "   ${BLUE}Context:${NC}"
            grep -iE -B 2 -A 2 "$pattern" "$file" 2>/dev/null | head -5 | while read -r line; do
                echo -e "   ${YELLOW}$line${NC}"
            done
            echo ""
            ERRORS=$((ERRORS + 1))
        fi
    done
done

# Check for .env files that might be tracked
echo -e "${BLUE}🔍 Checking for .env files...${NC}"
ENV_FILES=$(find campsiteagent -name ".env*" -type f 2>/dev/null | grep -v node_modules | grep -v vendor)
if [ -n "$ENV_FILES" ]; then
    echo -e "${YELLOW}⚠️  WARNING: Found .env files:${NC}"
    echo "$ENV_FILES" | while read -r file; do
        echo -e "   ${YELLOW}$file${NC}"
        if git ls-files --error-unmatch "$file" >/dev/null 2>&1; then
            echo -e "   ${RED}   ⚠️  This file is tracked in git!${NC}"
            WARNINGS=$((WARNINGS + 1))
        else
            echo -e "   ${GREEN}   ✅ Not tracked (good)${NC}"
        fi
    done
    echo ""
fi

# Summary
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}❌ Found $ERRORS potential security issue(s)${NC}"
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Found $WARNINGS warning(s)${NC}"
    exit 0
else
    echo -e "${GREEN}✅ No sensitive information detected${NC}"
    exit 0
fi

