#!/usr/bin/env bash
# Upload semua file di release/ ke FTP (hanya file yang ada di folder itu).
# vendor-deploy.zip di-upload sebagai 1 file, lalu di-extract via HTTP.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RELEASE_DIR="${RELEASE_DIR:-$ROOT/release}"
SERVER="${FTP_SERVER:?FTP_SERVER required}"
USER="${FTP_USERNAME:?FTP_USERNAME required}"
PASS="${FTP_PASSWORD:?FTP_PASSWORD required}"
REMOTE_DIR="${FTP_SERVER_DIR:-./}"
SITE_URL="${DEPLOY_SITE_URL:-https://kedaitjoan.online}"

if [ ! -d "$RELEASE_DIR" ]; then
  echo "Folder release tidak ada"
  exit 1
fi

mapfile -t FILES < <(find "$RELEASE_DIR" -type f ! -name '.vendor-extract-token' | sed "s|^$RELEASE_DIR/||" | sort)
if [ "${#FILES[@]}" -eq 0 ]; then
  echo "Tidak ada file di release/"
  exit 0
fi

echo "Upload ${#FILES[@]} file ke ftp://$SERVER/$REMOTE_DIR"

# Normalisasi remote dir
REMOTE_DIR="${REMOTE_DIR%/}"
if [ "$REMOTE_DIR" = "." ] || [ -z "$REMOTE_DIR" ]; then
  REMOTE_BASE=""
else
  REMOTE_BASE="$REMOTE_DIR"
fi

upload_one() {
  local rel="$1"
  local local_path="$RELEASE_DIR/$rel"
  local remote_path
  if [ -n "$REMOTE_BASE" ]; then
    remote_path="$REMOTE_BASE/$rel"
  else
    remote_path="$rel"
  fi
  local dest="ftp://$SERVER/$remote_path"
  echo "→ $rel ($(du -h "$local_path" | cut -f1))"
  curl --silent --show-error --fail \
    --ftp-create-dirs \
    --user "$USER:$PASS" \
    -T "$local_path" \
    "$dest"
}

uploaded=0

# Upload zip vendor dulu (file besar), baru file lain
if [ -f "$RELEASE_DIR/vendor-deploy.zip" ]; then
  upload_one "vendor-deploy.zip"
  uploaded=$((uploaded + 1))
fi
if [ -f "$RELEASE_DIR/extract-vendor.php" ]; then
  upload_one "extract-vendor.php"
  uploaded=$((uploaded + 1))
fi

for rel in "${FILES[@]}"; do
  case "$rel" in
    vendor-deploy.zip|extract-vendor.php|.vendor-extract-token)
      continue
      ;;
  esac
  upload_one "$rel"
  uploaded=$((uploaded + 1))
done

echo "Upload selesai: $uploaded file"

# Extract vendor zip di server (1 request HTTP, bukan ribuan FTP)
if [ -f "$RELEASE_DIR/.vendor-extract-token" ] && [ -f "$RELEASE_DIR/vendor-deploy.zip" ]; then
  TOKEN="$(cat "$RELEASE_DIR/.vendor-extract-token")"
  EXTRACT_URL="${SITE_URL%/}/extract-vendor.php?token=${TOKEN}"
  echo "Extract vendor via $SITE_URL/extract-vendor.php ..."
  ok=0
  for i in 1 2 3 4 5; do
    if OUT=$(curl --silent --show-error --fail --max-time 300 "$EXTRACT_URL"); then
      echo "$OUT"
      ok=1
      break
    fi
    echo "Extract attempt $i gagal, tunggu 5s..."
    sleep 5
  done
  if [ "$ok" != "1" ]; then
    echo "::warning::Gagal extract vendor otomatis. Buka manual: $EXTRACT_URL"
    echo "::warning::Atau extract vendor-deploy.zip lewat File Manager hosting."
  fi
fi

# Hapus cache optimize di server yang sering merujuk paket absen (Sanctum, dll.) → penyebab HTTP 500
if [ -f "$RELEASE_DIR/bootstrap/cache/.clear-optimize-cache" ]; then
  for rel in \
    "bootstrap/cache/services.php" \
    "bootstrap/cache/config.php" \
    "bootstrap/cache/routes-v7.php" \
    "bootstrap/cache/events.php"
  do
    echo "× DELE $rel"
    if [ -n "$REMOTE_BASE" ]; then
      curl --silent --show-error --user "$USER:$PASS" "ftp://$SERVER/" \
        -Q "CWD $REMOTE_BASE" \
        -Q "DELE $rel" || true
    else
      curl --silent --show-error --user "$USER:$PASS" "ftp://$SERVER/" \
        -Q "DELE $rel" || true
    fi
  done
fi

echo "FTP selesai."
