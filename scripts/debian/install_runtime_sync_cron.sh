#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
TARGET_DIR="${1:-$ROOT_DIR}"

if [[ ! -d "$TARGET_DIR/.git" ]]; then
  echo "error: target is not a git repository: $TARGET_DIR" >&2
  exit 1
fi

mkdir -p "$TARGET_DIR/storage/logs"

SYNC_SCRIPT="$TARGET_DIR/scripts/debian/git_runtime_sync.sh"
CRON_LOG="$TARGET_DIR/storage/logs/git_runtime_sync.cron.log"

if [[ ! -f "$SYNC_SCRIPT" ]]; then
  echo "error: sync script not found: $SYNC_SCRIPT" >&2
  exit 1
fi

TARGET_ESCAPED="$(printf '%q' "$TARGET_DIR")"
SYNC_ESCAPED="$(printf '%q' "$SYNC_SCRIPT")"
LOG_ESCAPED="$(printf '%q' "$CRON_LOG")"

PULL_LINE="*/2 * * * * cd $TARGET_ESCAPED && /usr/bin/env bash $SYNC_ESCAPED pull >> $LOG_ESCAPED 2>&1"
COMMIT_PUSH_LINE="*/10 * * * * cd $TARGET_ESCAPED && /usr/bin/env bash $SYNC_ESCAPED commit-push >> $LOG_ESCAPED 2>&1"

TMP_CRON="$(mktemp)"
trap 'rm -f "$TMP_CRON"' EXIT

crontab -l 2>/dev/null | grep -v 'git_runtime_sync.sh pull' | grep -v 'git_runtime_sync.sh commit-push' > "$TMP_CRON" || true
{
  cat "$TMP_CRON"
  echo "$PULL_LINE"
  echo "$COMMIT_PUSH_LINE"
} | crontab -

echo "runtime sync cron installed for: $TARGET_DIR"
echo "  - pull every 2 minutes"
echo "  - commit-push every 10 minutes"
echo "log file: $CRON_LOG"
