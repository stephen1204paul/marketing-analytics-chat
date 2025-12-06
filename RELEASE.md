# Release Process

This document describes how to create releases for the Marketing Analytics MCP plugin.

## Release Script

The `release.sh` script automates the entire release process:

1. ✅ Updates version numbers in all files
2. ✅ Adds changelog entry
3. ✅ Builds optimized distribution package
4. ✅ Creates git commit and tag
5. ✅ Provides next steps for publishing

## Usage

### Basic Usage

```bash
# Release a specific version
./release.sh 1.2.3

# Bump patch version (0.1.0 → 0.1.1)
./release.sh patch

# Bump minor version (0.1.0 → 0.2.0)
./release.sh minor

# Bump major version (0.1.0 → 1.0.0)
./release.sh major
```

### Options

```bash
--dry-run         # Preview changes without making them
--skip-git        # Skip git commit and tag creation
--skip-build      # Skip building the distribution package
--force           # Allow version downgrades (use with caution)
```

### Examples

```bash
# Dry run to see what would happen
./release.sh 0.2.0 --dry-run

# Release without git operations (useful for testing)
./release.sh 0.2.0 --skip-git

# Just bump version without building
./release.sh patch --skip-build

# Reset to earlier version (use carefully!)
./release.sh 0.1.0 --force
```

## What Gets Updated

The script updates version numbers in:

- ✅ `marketing-analytics-chat.php` - Main plugin file header
- ✅ `marketing-analytics-chat.php` - `MARKETING_ANALYTICS_MCP_VERSION` constant
- ✅ `readme.txt` - Stable tag
- ✅ `readme.txt` - Changelog (automatic entry)

## Files Created

After running the script:

```
dist/
└── marketing-analytics-chat-{VERSION}.zip  # Ready to upload
```

## Git Operations

The script automatically:

1. Creates a commit: `chore: bump version to {VERSION}`
2. Creates a tag: `v{VERSION}`

**Note:** Changes are committed but NOT pushed. You must manually push:

```bash
git push && git push --tags
```

## Complete Workflow Example

```bash
# 1. Make sure you're on main branch with clean working directory
git status

# 2. Run release script (patch version bump: 0.1.0 → 0.1.1)
./release.sh patch

# 3. Review the changes
git log -1 --stat

# 4. Test the distribution package
# unzip and test dist/marketing-analytics-chat-0.1.1.zip locally

# 5. Push to remote
git push && git push --tags

# 6. Upload to WordPress.org
# Upload dist/marketing-analytics-chat-0.1.1.zip

# 7. Create GitHub release
# Go to https://github.com/your-repo/releases/new
# Use tag v0.1.1
# Copy changelog from readme.txt
```

## Version Numbering

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.0.0): Breaking changes, major rewrites
- **MINOR** (0.1.0): New features, backwards compatible
- **PATCH** (0.0.1): Bug fixes, minor improvements

### Examples

- `0.1.0` → `0.1.1`: Bug fix (patch)
- `0.1.1` → `0.2.0`: New feature (minor)
- `0.9.0` → `1.0.0`: First stable release (major)

## Changelog Management

The script automatically adds a changelog entry:

```
= 0.1.1 - 2025-12-06 =
* Release version 0.1.1
```

**Before releasing**, update `readme.txt` to add details:

```
= 0.1.1 - 2025-12-06 =
* Added: OpenAI GPT integration
* Added: Google Gemini integration
* Fixed: Gemini API authentication using headers
* Updated: Model lists for all providers
```

## Troubleshooting

### "New version must be greater than current version"

You're trying to downgrade. Options:
- Use `--force` to allow downgrade (be careful!)
- Use semantic bumps: `patch`, `minor`, `major`

### Build fails

Make sure you have:
- PHP 8.1+ installed
- Composer installed
- All dependencies: `composer install`

### Git operations fail

Check:
- You're in a git repository
- You have uncommitted changes
- You have write permissions

## Safety Features

✅ **Version validation**: Ensures semantic versioning format
✅ **Downgrade protection**: Prevents accidental version downgrades
✅ **Dry run mode**: Preview changes before applying
✅ **Git safety**: Commits locally only, requires manual push

## Quick Reference

```bash
# Most common workflows
./release.sh patch              # Bug fix release
./release.sh minor              # Feature release
./release.sh 1.0.0              # Specific version

# Testing
./release.sh patch --dry-run    # Preview patch release
./release.sh 0.2.0 --skip-git   # Build without git

# Force operations
./release.sh 0.1.0 --force      # Downgrade version
```

## Support

For issues or questions about the release process:
- Check this documentation first
- Review recent commits: `git log --oneline -10`
- Check git tags: `git tag -l`
- Review build artifacts: `ls -lh dist/`
