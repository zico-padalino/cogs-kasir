#!/usr/bin/env bash
# Upload semua file di release/ ke FTP (hanya file yang ada di folder itu).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RELEASE_DIR="${RELEASE_DIR:-$ROOT/release}"
SERVER="${FTP_SERVER:?FTP_SERVER required}"
USER="${FTP_USERNAME:?FTP_USERNAME required}"
PASS="${FTP_PASSWORD:?FTP_PASSWORD required}"
REMOTE_DIR="${FTP_SERVER_DIR:-./}"

if [ ! -d "$RELEASE_DIR" ]; then
  echo "Folder release tidak ada"
  exit 1
fi

mapfile -t FILES < <(find "$RELEASE_DIR" -type f | sed "s|^$RELEASE_DIR/||" | sort)
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

uploaded=0
for rel in "${FILES[@]}"; do
  local_path="$RELEASE_DIR/$rel"
  if [ -n "$REMOTE_BASE" ]; then
    remote_path="$REMOTE_BASE/$rel"
  else
    remote_path="$rel"
  fi
  remote_dir=$(dirname "$remote_path")
  if [ "$remote_dir" = "." ]; then
    remote_dir=""
  fi

  url="ftp://$SERVER/"
  if [ -n "$remote_dir" ]; then
    # curl --ftp-create-dirs butuh trailing path
    dest="ftp://$SERVER/$remote_path"
  else
    dest="ftp://$SERVER/$remote_path"
  fi

  echo "→ $rel"
  curl --silent --show-error --fail \
    --ftp-create-dirs \
    --user "$USER:$PASS" \
    -T "$local_path" \
    "$dest"
  uploaded=$((uploaded + 1))
done

echo "Upload selesai: $uploaded file"
