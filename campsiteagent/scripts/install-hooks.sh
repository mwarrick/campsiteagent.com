#!/bin/bash

# Install Git hooks for security checking
# Run this script after cloning the repository

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
HOOKS_DIR="$REPO_ROOT/.git/hooks"

echo "Installing Git pre-commit hook..."

if [ ! -d "$HOOKS_DIR" ]; then
    echo "Error: .git/hooks directory not found. Are you in a git repository?"
    exit 1
fi

# Copy the pre-commit hook
if [ -f "$SCRIPT_DIR/pre-commit-hook" ]; then
    cp "$SCRIPT_DIR/pre-commit-hook" "$HOOKS_DIR/pre-commit"
    chmod +x "$HOOKS_DIR/pre-commit"
    echo "✅ Pre-commit hook installed successfully!"
    echo ""
    echo "The hook will now run automatically before each commit to check for sensitive information."
else
    echo "Error: pre-commit-hook file not found in scripts directory"
    exit 1
fi

