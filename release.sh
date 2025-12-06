#!/bin/bash

# Marketing Analytics MCP - Release Script
# Bumps version, updates files, builds distribution, and optionally creates git tag

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Plugin details
PLUGIN_SLUG="marketing-analytics-chat"
PLUGIN_FILE="marketing-analytics-chat.php"
README_FILE="readme.txt"
MAIN_FILE="marketing-analytics-chat.php"

# Get current version from plugin file
CURRENT_VERSION=$(grep -m 1 "Version:" "$MAIN_FILE" | awk '{print $3}' | tr -d '\r')

echo -e "${BLUE}📦 Marketing Analytics MCP - Release Script${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "Current version: ${YELLOW}$CURRENT_VERSION${NC}"
echo ""

# Function to validate semantic version
validate_version() {
    if [[ ! $1 =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo -e "${RED}❌ Invalid version format. Use semantic versioning (e.g., 1.2.3)${NC}"
        exit 1
    fi
}

# Function to compare versions
version_gt() {
    test "$(printf '%s\n' "$@" | sort -V | head -n 1)" != "$1"
}

SKIP_GIT=false
SKIP_BUILD=false
DRY_RUN=false
FORCE=false
VERSION_ARG=""

# Parse all arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-git)
            SKIP_GIT=true
            shift
            ;;
        --skip-build)
            SKIP_BUILD=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --*)
            echo -e "${RED}❌ Unknown option: $1${NC}"
            exit 1
            ;;
        *)
            # This is the version argument
            if [ -z "$VERSION_ARG" ]; then
                VERSION_ARG=$1
            else
                echo -e "${RED}❌ Multiple version arguments provided${NC}"
                exit 1
            fi
            shift
            ;;
    esac
done

# Check if version argument was provided
if [ -z "$VERSION_ARG" ]; then
    echo -e "${YELLOW}Usage:${NC}"
    echo "  $0 <version> [options]    # Set specific version (e.g., 1.2.3)"
    echo "  $0 patch [options]        # Bump patch version (1.0.0 -> 1.0.1)"
    echo "  $0 minor [options]        # Bump minor version (1.0.0 -> 1.1.0)"
    echo "  $0 major [options]        # Bump major version (1.0.0 -> 2.0.0)"
    echo ""
    echo -e "${YELLOW}Options:${NC}"
    echo "  --skip-git                # Skip git commit and tag"
    echo "  --skip-build              # Skip building the plugin"
    echo "  --dry-run                 # Show what would be done without making changes"
    echo "  --force                   # Allow version downgrades (use with caution)"
    echo ""
    echo -e "${YELLOW}Examples:${NC}"
    echo "  $0 1.2.3                  # Release version 1.2.3"
    echo "  $0 patch                  # Bump to next patch version"
    echo "  $0 0.1.0 --force --dry-run # Dry run downgrade to 0.1.0"
    echo "  $0 minor --skip-git       # Bump minor version without git operations"
    exit 1
fi

# Calculate new version
if [[ "$VERSION_ARG" == "patch" ]] || [[ "$VERSION_ARG" == "minor" ]] || [[ "$VERSION_ARG" == "major" ]]; then
    # Parse current version
    IFS='.' read -ra VERSION_PARTS <<< "$CURRENT_VERSION"
    MAJOR="${VERSION_PARTS[0]}"
    MINOR="${VERSION_PARTS[1]}"
    PATCH="${VERSION_PARTS[2]}"

    case $VERSION_ARG in
        patch)
            PATCH=$((PATCH + 1))
            ;;
        minor)
            MINOR=$((MINOR + 1))
            PATCH=0
            ;;
        major)
            MAJOR=$((MAJOR + 1))
            MINOR=0
            PATCH=0
            ;;
    esac

    NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"
else
    # Use provided version
    NEW_VERSION=$VERSION_ARG
    validate_version "$NEW_VERSION"
fi

# Validate that new version is greater than current (unless --force is used)
if ! version_gt "$NEW_VERSION" "$CURRENT_VERSION" && [ "$FORCE" = false ]; then
    echo -e "${RED}❌ New version ($NEW_VERSION) must be greater than current version ($CURRENT_VERSION)${NC}"
    echo -e "${YELLOW}   Use --force to allow version downgrades${NC}"
    exit 1
fi

# Warn about downgrade if forced
if [ "$FORCE" = true ] && ! version_gt "$NEW_VERSION" "$CURRENT_VERSION"; then
    echo -e "${YELLOW}⚠️  WARNING: Downgrading version from $CURRENT_VERSION to $NEW_VERSION${NC}"
fi

echo -e "New version: ${GREEN}$NEW_VERSION${NC}"
echo ""

if [ "$DRY_RUN" = true ]; then
    echo -e "${YELLOW}🔍 DRY RUN MODE - No changes will be made${NC}"
    echo ""
fi

# Function to update version in file
update_version_in_file() {
    local file=$1
    local pattern=$2
    local replacement=$3

    if [ "$DRY_RUN" = true ]; then
        echo -e "${BLUE}Would update:${NC} $file"
        return
    fi

    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "$pattern" "$file"
    else
        # Linux
        sed -i "$pattern" "$file"
    fi

    echo -e "${GREEN}✓${NC} Updated $file"
}

# Update version in main plugin file
echo -e "${BLUE}📝 Updating version in files...${NC}"
update_version_in_file "$MAIN_FILE" \
    "s/Version: *${CURRENT_VERSION}/Version: ${NEW_VERSION}/" \
    "Version: ${NEW_VERSION}"

update_version_in_file "$MAIN_FILE" \
    "s/define( 'MARKETING_ANALYTICS_MCP_VERSION', '${CURRENT_VERSION}' );/define( 'MARKETING_ANALYTICS_MCP_VERSION', '${NEW_VERSION}' );/" \
    "define( 'MARKETING_ANALYTICS_MCP_VERSION', '${NEW_VERSION}' );"

# Update version in readme.txt
update_version_in_file "$README_FILE" \
    "s/Stable tag: *${CURRENT_VERSION}/Stable tag: ${NEW_VERSION}/" \
    "Stable tag: ${NEW_VERSION}"

# Update changelog in readme.txt with new version
if [ "$DRY_RUN" = false ]; then
    # Get current date
    RELEASE_DATE=$(date +%Y-%m-%d)

    # Check if changelog section exists
    if grep -q "== Changelog ==" "$README_FILE"; then
        # Add new version entry at the top of changelog
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            sed -i '' "/== Changelog ==/a\\
\\
= ${NEW_VERSION} - ${RELEASE_DATE} =\\
* Release version ${NEW_VERSION}\\
" "$README_FILE"
        else
            # Linux
            sed -i "/== Changelog ==/a \\
\\
= ${NEW_VERSION} - ${RELEASE_DATE} =\\
* Release version ${NEW_VERSION}\\
" "$README_FILE"
        fi
        echo -e "${GREEN}✓${NC} Added changelog entry"
    fi
fi

echo ""

# Build the plugin
if [ "$SKIP_BUILD" = false ]; then
    echo -e "${BLUE}🔨 Building plugin distribution...${NC}"

    if [ "$DRY_RUN" = false ]; then
        if [ -f "./build-plugin-optimized.sh" ]; then
            ./build-plugin-optimized.sh
            echo -e "${GREEN}✓${NC} Plugin built successfully"
        else
            echo -e "${RED}❌ build-plugin-optimized.sh not found${NC}"
            exit 1
        fi
    else
        echo -e "${BLUE}Would run:${NC} ./build-plugin-optimized.sh"
    fi

    echo ""
fi

# Git operations
if [ "$SKIP_GIT" = false ]; then
    echo -e "${BLUE}📦 Git operations...${NC}"

    # Check if git repo
    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        echo -e "${YELLOW}⚠️  Not a git repository, skipping git operations${NC}"
    else
        if [ "$DRY_RUN" = false ]; then
            # Check for uncommitted changes
            if [[ -n $(git status -s) ]]; then
                # Stage version changes
                git add "$MAIN_FILE" "$README_FILE"

                # Commit version bump
                git commit -m "chore: bump version to $NEW_VERSION"
                echo -e "${GREEN}✓${NC} Committed version changes"

                # Create git tag
                git tag -a "v$NEW_VERSION" -m "Release version $NEW_VERSION"
                echo -e "${GREEN}✓${NC} Created git tag v$NEW_VERSION"

                echo ""
                echo -e "${YELLOW}📤 To push changes and tags, run:${NC}"
                echo -e "   git push && git push --tags"
            else
                echo -e "${YELLOW}⚠️  No uncommitted changes to commit${NC}"
            fi
        else
            echo -e "${BLUE}Would run:${NC}"
            echo "  git add $MAIN_FILE $README_FILE"
            echo "  git commit -m 'chore: bump version to $NEW_VERSION'"
            echo "  git tag -a 'v$NEW_VERSION' -m 'Release version $NEW_VERSION'"
        fi
    fi

    echo ""
fi

# Summary
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ Release process complete!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BLUE}Version:${NC} $CURRENT_VERSION → ${GREEN}$NEW_VERSION${NC}"

if [ "$SKIP_BUILD" = false ]; then
    echo -e "${BLUE}Package:${NC} dist/${PLUGIN_SLUG}-${NEW_VERSION}.zip"
fi

if [ "$SKIP_GIT" = false ] && git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${BLUE}Git tag:${NC} v$NEW_VERSION"
fi

echo ""

if [ "$DRY_RUN" = true ]; then
    echo -e "${YELLOW}⚠️  This was a dry run. No changes were made.${NC}"
    echo -e "${YELLOW}   Run without --dry-run to apply changes.${NC}"
    echo ""
fi

# Next steps
if [ "$DRY_RUN" = false ]; then
    echo -e "${YELLOW}📋 Next steps:${NC}"

    if [ "$SKIP_GIT" = false ] && git rev-parse --git-dir > /dev/null 2>&1; then
        echo "1. Review the changes: git log -1 --stat"
        echo "2. Push to remote: git push && git push --tags"
    fi

    echo "3. Upload dist/${PLUGIN_SLUG}-${NEW_VERSION}.zip to WordPress.org"
    echo "4. Create a GitHub release with the changelog"
    echo ""
fi
