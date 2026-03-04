#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
TARGET_DIR="${1:-$ROOT_DIR}"
PROCESS_NAME="${2:-esptest-git-watch}"

if [[ ! -d "$TARGET_DIR/.git" ]]; then
  echo "error: target is not a git repository: $TARGET_DIR" >&2
  exit 1
fi

WATCH_SCRIPT="$TARGET_DIR/scripts/debian/git_runtime_watch.sh"
if [[ ! -f "$WATCH_SCRIPT" ]]; then
  echo "error: watcher script not found: $WATCH_SCRIPT" >&2
  exit 1
fi

if ! command -v pm2 >/dev/null 2>&1; then
  echo "error: pm2 command not found" >&2
  exit 1
fi

if ! command -v inotifywait >/dev/null 2>&1; then
  echo "error: inotifywait command not found. install package: inotify-tools" >&2
  exit 1
fi

chmod +x "$WATCH_SCRIPT" "$TARGET_DIR/scripts/debian/git_runtime_sync.sh"

if pm2 describe "$PROCESS_NAME" >/dev/null 2>&1; then
  pm2 delete "$PROCESS_NAME" >/dev/null 2>&1 || true
fi

pm2 start "$WATCH_SCRIPT" --name "$PROCESS_NAME" --cwd "$TARGET_DIR" --interpreter bash
pm2 save

echo "event-driven git sync watcher installed"
echo "  process : $PROCESS_NAME"
echo "  command : $WATCH_SCRIPT"
echo "  logs    : $TARGET_DIR/storage/logs/git_runtime_watch.log"
