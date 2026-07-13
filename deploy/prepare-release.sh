#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RELEASE_DIR="$ROOT/release"
INCLUDE_VENDOR="${INCLUDE_VENDOR:-0}"

rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

RSYNC_EXCLUDES=(
  --exclude '.git'
  --exclude '.github'
  --exclude 'node_modules'
  --exclude 'tests'
  --exclude 'mobile'
  --exclude 'release'
  --exclude 'deploy-upload.zip'
  --exclude 'build-upload.zip'
  --exclude '.env'
  --exclude '.env.*'
  --exclude '.phpunit.cache'
  --exclude 'storage/logs/*'
  --exclude 'storage/framework/cache/data/*'
  --exclude 'storage/framework/sessions/*'
  --exclude 'storage/framework/views/*'
  --exclude 'storage/pail/*'
)

if [ "$INCLUDE_VENDOR" != "1" ]; then
  RSYNC_EXCLUDES+=(--exclude 'vendor')
fi

rsync -a "${RSYNC_EXCLUDES[@]}" "$ROOT/" "$RELEASE_DIR/"

cp "$ROOT/deploy/public_html-index.php" "$RELEASE_DIR/index.php"
cp "$ROOT/deploy/public_html.htaccess" "$RELEASE_DIR/.htaccess"

cp -r "$ROOT/public/build" "$RELEASE_DIR/build"
cp -r "$ROOT/public/icons" "$RELEASE_DIR/icons"
cp -r "$ROOT/public/images" "$RELEASE_DIR/images"
cp "$ROOT/public/sw.js" "$RELEASE_DIR/sw.js"
cp "$ROOT/public/robots.txt" "$RELEASE_DIR/robots.txt"
cp "$ROOT/public/favicon.png" "$RELEASE_DIR/favicon.png"

mkdir -p \
  "$RELEASE_DIR/storage/framework/cache/data" \
  "$RELEASE_DIR/storage/framework/sessions" \
  "$RELEASE_DIR/storage/framework/views" \
  "$RELEASE_DIR/storage/logs" \
  "$RELEASE_DIR/bootstrap/cache"

if [ "$INCLUDE_VENDOR" = "1" ]; then
  echo "Release ready (dengan vendor): $(du -sh "$RELEASE_DIR" | cut -f1)"
else
  echo "Release ready (skip vendor): $(du -sh "$RELEASE_DIR" | cut -f1)"
fi
