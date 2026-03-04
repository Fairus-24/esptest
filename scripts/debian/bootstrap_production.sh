#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$ROOT_DIR"

if ! command -v php >/dev/null 2>&1; then
  echo "error: php command not found" >&2
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "error: composer command not found" >&2
  exit 1
fi

if ! command -v pm2 >/dev/null 2>&1; then
  echo "error: pm2 command not found" >&2
  exit 1
fi

echo "[1/6] composer install --no-dev --optimize-autoloader"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "[2/6] php artisan migrate --force"
php artisan migrate --force

echo "[3/6] php artisan optimize:clear"
php artisan optimize:clear

echo "[4/6] pm2 start ecosystem.config.cjs"
pm2 start ecosystem.config.cjs

echo "[5/6] pm2 save"
pm2 save

echo "[6/6] install runtime sync cron"
bash "$SCRIPT_DIR/install_runtime_sync_cron.sh" "$ROOT_DIR"

echo "bootstrap completed"
