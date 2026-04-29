#!/usr/bin/env sh
set -eu

SQLITE_DIR="${SQLITE_DIR:-/var/www/storage/app/sqlite}"
APP_DB="${APP_DB:-$SQLITE_DIR/app.sqlite}"
TEST_DB="${TEST_DB:-$SQLITE_DIR/testing.sqlite}"
LEGACY_DB="${LEGACY_DB:-/var/www/database/database.sqlite}"

mkdir -p "$SQLITE_DIR"
mkdir -p "$(dirname "$LEGACY_DB")"

# Create files if missing (Laravel requires the file to exist)
touch "$APP_DB" "$TEST_DB" "$LEGACY_DB"

# Best-effort permissions for shared volumes
chmod 664 "$APP_DB" "$TEST_DB" "$LEGACY_DB" 2>/dev/null || true
chmod 775 "$SQLITE_DIR" 2>/dev/null || true

