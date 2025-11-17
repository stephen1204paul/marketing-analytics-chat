#!/bin/bash

# Optimized WordPress Plugin Build Script
# Minimizes package size by excluding unnecessary vendor files

set -e

PLUGIN_SLUG="marketing-analytics-mcp"
VERSION=$(grep "Version:" marketing-analytics-mcp.php | awk '{print $3}')
BUILD_DIR="build"
DIST_DIR="dist"

echo "📦 Building optimized ${PLUGIN_SLUG} v${VERSION}..."

# Clean previous builds
rm -rf ${BUILD_DIR}
rm -rf ${DIST_DIR}
mkdir -p ${BUILD_DIR}/${PLUGIN_SLUG}
mkdir -p ${DIST_DIR}

echo "📋 Copying plugin files..."

# Copy plugin files (exclude dev files)
rsync -av --progress . ${BUILD_DIR}/${PLUGIN_SLUG} \
  --exclude .git \
  --exclude .gitignore \
  --exclude .DS_Store \
  --exclude .claude \
  --exclude .serena \
  --exclude node_modules \
  --exclude vendor \
  --exclude tests \
  --exclude build \
  --exclude dist \
  --exclude composer.lock \
  --exclude '*.log' \
  --exclude build-plugin.sh \
  --exclude build-plugin-optimized.sh \
  --exclude phpunit.xml \
  --exclude .phpcs.xml \
  --exclude phpstan.neon \
  --exclude plan.md \
  --exclude PRESENTATION_PLAN_ENHANCED.md \
  --exclude presentation.md \
  --exclude LIGHTNING_TALK.md \
  --exclude docs

echo "🎼 Installing production dependencies..."

# Install production dependencies only
cd ${BUILD_DIR}/${PLUGIN_SLUG}
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "🗑️  Removing unnecessary vendor files..."

# Remove test files and docs from vendor packages
find vendor -type d -name "tests" -exec rm -rf {} + 2>/dev/null || true
find vendor -type d -name "Tests" -exec rm -rf {} + 2>/dev/null || true
find vendor -type d -name "test" -exec rm -rf {} + 2>/dev/null || true
find vendor -type d -name "docs" -exec rm -rf {} + 2>/dev/null || true
find vendor -type d -name "examples" -exec rm -rf {} + 2>/dev/null || true
find vendor -type d -name "benchmarks" -exec rm -rf {} + 2>/dev/null || true

# Remove documentation files
find vendor -type f -name "*.md" -delete 2>/dev/null || true
find vendor -type f -name "*.rst" -delete 2>/dev/null || true
find vendor -type f -name "*.txt" -delete 2>/dev/null || true
find vendor -type f -name "LICENSE*" -delete 2>/dev/null || true
find vendor -type f -name "CHANGELOG*" -delete 2>/dev/null || true
find vendor -type f -name "CONTRIBUTING*" -delete 2>/dev/null || true
find vendor -type f -name ".travis.yml" -delete 2>/dev/null || true
find vendor -type f -name ".gitignore" -delete 2>/dev/null || true
find vendor -type f -name "phpunit.xml*" -delete 2>/dev/null || true
find vendor -type f -name "phpstan.neon*" -delete 2>/dev/null || true

# Remove Google API services we don't use (keep only Analytics and Search Console)
if [ -d "vendor/google/apiclient-services/src" ]; then
  echo "🎯 Trimming Google API services (keeping only GA4 & Search Console)..."
  cd vendor/google/apiclient-services/src

  # Keep only these services
  KEEP_SERVICES=("AnalyticsData" "SearchConsole" "Analytics")

  # Remove all except kept services
  for dir in */; do
    dir_name="${dir%/}"
    should_keep=false
    for keep in "${KEEP_SERVICES[@]}"; do
      if [ "$dir_name" == "$keep" ]; then
        should_keep=true
        break
      fi
    done

    if [ "$should_keep" = false ]; then
      rm -rf "$dir_name"
    fi
  done

  cd ../../../..
fi

# Remove composer files
rm -f composer.json composer.lock

echo "📊 Optimized vendor size:"
du -sh vendor/ 2>/dev/null || echo "N/A"

cd ../..

echo "🗜️  Creating ZIP archive..."

# Create ZIP file
cd ${BUILD_DIR}
zip -r ../${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip ${PLUGIN_SLUG} -q
cd ..

echo "✅ Plugin packaged successfully!"
echo "📍 Location: ${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
echo "📊 Size: $(du -h ${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip | awk '{print $1}')"

# Show size comparison
ORIGINAL_SIZE=$(du -sh . | awk '{print $1}')
PACKAGE_SIZE=$(du -sh ${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip | awk '{print $1}')
echo "📉 Original: ${ORIGINAL_SIZE} → Package: ${PACKAGE_SIZE}"

# Cleanup
rm -rf ${BUILD_DIR}

echo ""
echo "🚀 Ready to distribute or upload to WordPress!"
