# Security Scripts

This directory contains scripts to help prevent committing sensitive information to the repository.

## Pre-commit Hook

A Git pre-commit hook is installed at `.git/hooks/pre-commit` that automatically checks for sensitive information before each commit.

### What it checks for:
- Hardcoded passwords
- Database connection strings with credentials
- API keys and secret keys
- Access tokens
- Private keys

### How it works:
- Runs automatically on `git commit`
- Scans all staged files
- Blocks commit if sensitive information is found
- Skips vendor files and node_modules

### Bypassing (use with caution):
If you get a false positive, you can bypass with:
```bash
git commit --no-verify
```

## Manual Secret Check Script

Run the standalone script to check the entire codebase:

```bash
./campsiteagent/scripts/check-secrets.sh
```

This script:
- Scans all PHP, JS, and config files
- Reports potential security issues
- Checks if .env files are tracked in git
- Provides detailed context for each issue

## Best Practices

1. **Use Environment Variables**: Always use `getenv()` or `$_ENV` for sensitive data
   ```php
   // ✅ Good
   $password = getenv('DB_PASSWORD');
   
   // ❌ Bad
   $password = 'mysecretpassword';
   ```

2. **Keep .env files out of git**: Ensure `.env` files are in `.gitignore`

3. **Use default/fallback values carefully**: Only use safe defaults in code
   ```php
   // ✅ Good - empty string is safe default
   $pass = getenv('DB_PASSWORD') ?: '';
   
   // ❌ Bad - real password as default
   $pass = getenv('DB_PASSWORD') ?: 'productionpassword123';
   ```

4. **Review false positives**: If the hook flags something that's not actually sensitive, consider:
   - Adding it to the skip patterns
   - Using a different variable name
   - Adding a comment explaining why it's safe

## Installing the Pre-commit Hook

The hook is automatically installed when you clone the repository. If you need to reinstall it:

```bash
chmod +x .git/hooks/pre-commit
```

## Updating Patterns

To add new patterns to check for, edit `.git/hooks/pre-commit` and add to the `PATTERNS` array.

