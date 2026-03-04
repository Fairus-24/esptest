#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
SYNC_SCRIPT="$ROOT_DIR/scripts/debian/git_runtime_sync.sh"
LOG_FILE="$ROOT_DIR/storage/logs/git_runtime_watch.log"
DEBOUNCE_SECONDS="${AUTO_SYNC_WATCH_DEBOUNCE_SECONDS:-2}"

mkdir -p "$ROOT_DIR/storage/logs"

log() {
  printf '[%s] %s\n' "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" "$*" >> "$LOG_FILE"
}

if [[ ! -x "$SYNC_SCRIPT" ]]; then
  log "error: sync script not executable: $SYNC_SCRIPT"
  exit 1
fi

if ! command -v inotifywait >/dev/null 2>&1; then
  log "error: inotifywait command not found. Install inotify-tools first."
  exit 1
fi

WATCH_EXCLUDE='(^|/)(\.git|vendor|node_modules|storage|ESP32_Firmware/\.pio)(/|$)'

log "watcher started (root=$ROOT_DIR debounce=${DEBOUNCE_SECONDS}s)"

last_sync_at=0

inotifywait \
  --monitor \
  --recursive \
  --event close_write,create,delete,move \
  --format '%w%f' \
  --exclude "$WATCH_EXCLUDE" \
  "$ROOT_DIR" |
while IFS= read -r changed_path; do
  now="$(date +%s)"
  if (( now - last_sync_at < DEBOUNCE_SECONDS )); then
    continue
  fi

  last_sync_at="$now"
  log "change detected: $changed_path"
  log "trigger: git_runtime_sync.sh commit-push"
  /usr/bin/env bash "$SYNC_SCRIPT" commit-push >> "$LOG_FILE" 2>&1 || log "commit-push run failed"
done
