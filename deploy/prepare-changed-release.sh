#!/usr/bin/env bash
# Siapkan folder release/ hanya dari file yang berubah di commit/range git.
# Layout server = public_html (isi public/ dinaikkan ke root).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RELEASE_DIR="$ROOT/release"
INCLUDE_VENDOR="${INCLUDE_VENDOR:-0}"
RANGE="${DEPLOY_RANGE:-HEAD~1..HEAD}"

rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

cd "$ROOT"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "::error::Bukan git repository"
  exit 1
fi

mapfile -t CHANGED < <(git diff --name-only --diff-filter=ACMR "$RANGE" | sed '/^$/d' || true)

if [ "${#CHANGED[@]}" -eq 0 ]; then
  echo "Tidak ada file berubah di range: $RANGE"
  if [ -n "${GITHUB_OUTPUT:-}" ]; then
    {
      echo "need_deploy=0"
      echo "need_frontend=0"
      echo "copied=0"
    } >> "$GITHUB_OUTPUT"
  fi
  exit 0
fi

echo "File berubah (${#CHANGED[@]}):"
printf ' - %s\n' "${CHANGED[@]}"

NEED_FRONTEND=0
NEED_DEPLOY=0
COPIED=0

should_skip() {
  local f="$1"
  case "$f" in
    .github/*|deploy/*|tests/*|mobile/*|node_modules/*|release/*|*.md|.gitignore|.gitattributes|.editorconfig)
      return 0
      ;;
    .env|.env.*|storage/logs/*|storage/framework/cache/*|storage/framework/sessions/*|storage/framework/views/*)
      return 0
      ;;
    vendor/*)
      [ "$INCLUDE_VENDOR" = "1" ] && return 1 || return 0
      ;;
  esac
  return 1
}

needs_frontend_build() {
  case "$1" in
    resources/css/*|resources/js/*|vite.config.*|package.json|package-lock.json|postcss.config.*|tailwind.config.*)
      return 0
      ;;
  esac
  return 1
}

copy_mapped() {
  local src="$1"
  local dest_rel="$2"
  local dest="$RELEASE_DIR/$dest_rel"
  mkdir -p "$(dirname "$dest")"
  cp -a "$ROOT/$src" "$dest"
  echo "  + $src  →  $dest_rel"
  COPIED=$((COPIED + 1))
  NEED_DEPLOY=1
}

for f in "${CHANGED[@]}"; do
  if should_skip "$f"; then
    echo "  skip $f"
    continue
  fi

  if [ ! -f "$ROOT/$f" ]; then
    echo "  skip (missing) $f"
    continue
  fi

  if needs_frontend_build "$f"; then
    NEED_FRONTEND=1
  fi

  # public/* → root public_html
  if [[ "$f" == public/* ]]; then
    rel="${f#public/}"
    if [ "$rel" = "index.php" ]; then
      cp -a "$ROOT/deploy/public_html-index.php" "$RELEASE_DIR/index.php"
      echo "  + deploy/public_html-index.php  →  index.php"
      COPIED=$((COPIED + 1))
      NEED_DEPLOY=1
    elif [ "$rel" = ".htaccess" ]; then
      cp -a "$ROOT/deploy/public_html.htaccess" "$RELEASE_DIR/.htaccess"
      echo "  + deploy/public_html.htaccess  →  .htaccess"
      COPIED=$((COPIED + 1))
      NEED_DEPLOY=1
    else
      copy_mapped "$f" "$rel"
    fi
    continue
  fi

  copy_mapped "$f" "$f"
done

if [ "$INCLUDE_VENDOR" = "1" ] && [ -d "$ROOT/vendor" ]; then
  echo "INCLUDE_VENDOR=1 → salin vendor/"
  mkdir -p "$RELEASE_DIR/vendor"
  cp -a "$ROOT/vendor/." "$RELEASE_DIR/vendor/"
  NEED_DEPLOY=1
fi

# Selalu kirim package discovery aman (hindari Sanctum / require-dev yang absen di vendor shared hosting)
mkdir -p "$RELEASE_DIR/bootstrap/cache"
cp -a "$ROOT/deploy/bootstrap-cache/packages.php" "$RELEASE_DIR/bootstrap/cache/packages.php"
# Marker: upload script akan hapus services.php & config.php di server
touch "$RELEASE_DIR/bootstrap/cache/.clear-optimize-cache"
# Pastikan index public_html punya stub Sanctum
cp -a "$ROOT/deploy/public_html-index.php" "$RELEASE_DIR/index.php"
NEED_DEPLOY=1
echo "  + deploy/bootstrap-cache/packages.php → bootstrap/cache/packages.php"
echo "  + deploy/public_html-index.php → index.php"
COPIED=$((COPIED + 2))

# Frontend build di step workflow berikutnya; tetap perlu upload.
if [ "$NEED_FRONTEND" = "1" ]; then
  NEED_DEPLOY=1
fi

echo "Copied files: $COPIED"
echo "need_frontend: $NEED_FRONTEND"
echo "need_deploy: $NEED_DEPLOY"
echo "Release size: $(du -sh "$RELEASE_DIR" 2>/dev/null | cut -f1 || echo 0)"

if [ -n "${GITHUB_OUTPUT:-}" ]; then
  {
    echo "need_deploy=$NEED_DEPLOY"
    echo "need_frontend=$NEED_FRONTEND"
    echo "copied=$COPIED"
  } >> "$GITHUB_OUTPUT"
fi

if [ "$NEED_DEPLOY" != "1" ]; then
  echo "Tidak ada file aplikasi untuk di-upload."
fi
