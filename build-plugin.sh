#!/bin/bash

# WordPress Plugin Build Script
# Packages the plugin for distribution

set -e

PLUGIN_SLUG="marketing-analytics-chat"
VERSION=$(grep "Version:" marketing-analytics-chat.php | awk '{print $3}')
BUILD_DIR="build"
DIST_DIR="dist"

echo "📦 Building ${PLUGIN_SLUG} v${VERSION}..."

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
composer install --no-dev --optimize-autoloader --no-interaction

# Remove composer files
rm -f composer.json composer.lock

cd ../..

echo "🗜️  Creating ZIP archive..."

# Create ZIP file
cd ${BUILD_DIR}
zip -r ../${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip ${PLUGIN_SLUG}
cd ..

echo "✅ Plugin packaged successfully!"
echo "📍 Location: ${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
echo "📊 Size: $(du -h ${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip | awk '{print $1}')"

# Cleanup
rm -rf ${BUILD_DIR}

echo ""
echo "🚀 Ready to distribute or upload to WordPress!"
