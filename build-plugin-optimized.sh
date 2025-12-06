#!/bin/bash

# Optimized WordPress Plugin Build Script
# Minimizes package size by excluding unnecessary vendor files

set -e

PLUGIN_SLUG="marketing-analytics-chat"
VERSION=$(grep "Version:" marketing-analytics-chat.php | awk '{print $3}')
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
  --exclude '*.sh' \
  --exclude phpunit.xml \
  --exclude .phpcs.xml \
  --exclude .phpunit.result.cache \
  --exclude phpstan.neon \
  --exclude plan.md \
  --exclude PRESENTATION_PLAN_ENHANCED.md \
  --exclude presentation.md \
  --exclude LIGHTNING_TALK.md \
  --exclude docs \
  --exclude presentation \
  --exclude CLAUDE.md \
  --exclude README.md \
  --exclude deploy-to-test.sh \
  --exclude copy-plugin-to-clean-repo.sh \
  --exclude assets

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

# Remove Google API services we don't use (read from composer.json)
if [ -d "vendor/google/apiclient-services/src" ]; then
  echo "🎯 Trimming Google API services..."

  # Read services to keep from composer.json and map to directory names (requires PHP)
  # Note: Some services have different directory names (e.g., AnalyticsAdmin -> GoogleAnalyticsAdmin)
  KEEP_SERVICES=($(php -r '
    $composer = json_decode(file_get_contents("composer.json"), true);
    $services = $composer["extra"]["google/apiclient-services"] ?? [];
    // Map service names to actual directory names
    $dir_map = [
      "AnalyticsAdmin" => "GoogleAnalyticsAdmin",
    ];
    $dirs = array_map(fn($s) => $dir_map[$s] ?? $s, $services);
    echo implode(" ", $dirs);
  '))

  if [ ${#KEEP_SERVICES[@]} -eq 0 ]; then
    echo "⚠️  No google/apiclient-services defined in composer.json, skipping trim"
  else
    echo "   Keeping: ${KEEP_SERVICES[*]}"
    cd vendor/google/apiclient-services/src

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
