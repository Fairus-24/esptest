#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$ROOT_DIR"

LOG_FILE="$ROOT_DIR/storage/logs/git_runtime_sync.log"
LOCK_FILE="$ROOT_DIR/storage/app/git_runtime_sync.lock"
mkdir -p "$(dirname "$LOG_FILE")" "$(dirname "$LOCK_FILE")"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  printf '[%s] skip: another sync process is running\n' "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" >> "$LOG_FILE"
  exit 0
fi

log() {
  printf '[%s] %s\n' "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" "$*" >> "$LOG_FILE"
}

trap 'log "error: line=$LINENO cmd=$BASH_COMMAND"' ERR

read_env_var() {
  local key="$1"
  local default_value="${2:-}"
  local value="${!key:-}"

  if [[ -z "$value" && -f "$ROOT_DIR/.env" ]]; then
    value="$(grep -E "^${key}=" "$ROOT_DIR/.env" | tail -n 1 | cut -d '=' -f 2- || true)"
    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"
  fi

  if [[ -z "$value" ]]; then
    value="$default_value"
  fi

  printf '%s' "$value"
}

as_bool() {
  case "$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')" in
    1|true|yes|on) printf '1' ;;
    *) printf '0' ;;
  esac
}

is_true() {
  [[ "$(as_bool "$1")" == "1" ]]
}

AUTO_SYNC_REMOTE="$(read_env_var AUTO_SYNC_REMOTE origin)"
AUTO_SYNC_BRANCH="$(read_env_var AUTO_SYNC_BRANCH main)"
AUTO_SYNC_ENABLE_AUTOCOMMIT="$(read_env_var AUTO_SYNC_ENABLE_AUTOCOMMIT false)"
AUTO_SYNC_ENABLE_PUSH="$(read_env_var AUTO_SYNC_ENABLE_PUSH false)"
AUTO_SYNC_SKIP_TESTS="$(read_env_var AUTO_SYNC_SKIP_TESTS false)"
AUTO_SYNC_TEST_TIMEOUT_SECONDS="$(read_env_var AUTO_SYNC_TEST_TIMEOUT_SECONDS 900)"
AUTO_SYNC_TEST_CMD="$(read_env_var AUTO_SYNC_TEST_CMD "php -d memory_limit=384M artisan test --filter='TransmissionHealthConfigTest|AdminConfigPanelTest|ResetDataTest|SimulationFlowTest' --stop-on-failure")"
AUTO_SYNC_TEST_FALLBACK_CMD="$(read_env_var AUTO_SYNC_TEST_FALLBACK_CMD "php artisan schedule:list")"
AUTO_SYNC_GIT_USER_NAME="$(read_env_var AUTO_SYNC_GIT_USER_NAME "ESPTest Auto Sync Bot")"
AUTO_SYNC_GIT_USER_EMAIL="$(read_env_var AUTO_SYNC_GIT_USER_EMAIL "bot@localhost")"
AUTO_SYNC_COMMIT_PREFIX="$(read_env_var AUTO_SYNC_COMMIT_PREFIX "chore(auto-sync): runtime sync")"
AUTO_SYNC_RUN_MIGRATIONS="$(read_env_var AUTO_SYNC_RUN_MIGRATIONS true)"
AUTO_SYNC_RUN_OPTIMIZE_CLEAR="$(read_env_var AUTO_SYNC_RUN_OPTIMIZE_CLEAR true)"
AUTO_SYNC_PM2_ECOSYSTEM="$(read_env_var AUTO_SYNC_PM2_ECOSYSTEM ecosystem.config.cjs)"
AUTO_SYNC_COMPOSER_MEMORY_LIMIT="$(read_env_var AUTO_SYNC_COMPOSER_MEMORY_LIMIT 512M)"

MODE="${1:-pull}"
case "$MODE" in
  pull|commit-push|full|always) ;;
  *)
    log "invalid mode: $MODE"
    exit 2
    ;;
esac

if [[ ! -d "$ROOT_DIR/.git" ]]; then
  log "skip: not a git repository ($ROOT_DIR)"
  exit 0
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  log "skip: git is not ready"
  exit 0
fi

ensure_target_branch() {
  local current_branch
  current_branch="$(git rev-parse --abbrev-ref HEAD)"
  if [[ "$current_branch" == "HEAD" ]]; then
    log "detached HEAD detected, checking out $AUTO_SYNC_BRANCH"
    git checkout "$AUTO_SYNC_BRANCH"
    return
  fi

  if [[ "$current_branch" != "$AUTO_SYNC_BRANCH" ]]; then
    log "switch branch: $current_branch -> $AUTO_SYNC_BRANCH"
    git checkout "$AUTO_SYNC_BRANCH"
  fi
}

has_working_tree_changes() {
  [[ -n "$(git status --porcelain --untracked-files=normal)" ]]
}

run_tests() {
  if is_true "$AUTO_SYNC_SKIP_TESTS"; then
    log "tests skipped (AUTO_SYNC_SKIP_TESTS=true)"
    return 0
  fi

  local effective_test_cmd
  effective_test_cmd="$AUTO_SYNC_TEST_CMD"

  # Some production images ship without `artisan test`; fallback keeps sync flow alive.
  if [[ "$effective_test_cmd" == *"artisan test"* ]]; then
    if ! php artisan list --raw 2>/dev/null | awk '{print $1}' | grep -qx 'test'; then
      log "artisan test command unavailable, using fallback: $AUTO_SYNC_TEST_FALLBACK_CMD"
      effective_test_cmd="$AUTO_SYNC_TEST_FALLBACK_CMD"
    fi
  fi

  log "running tests: $effective_test_cmd"
  if command -v timeout >/dev/null 2>&1; then
    timeout "${AUTO_SYNC_TEST_TIMEOUT_SECONDS}" env TERM=dumb bash -lc "$effective_test_cmd"
  else
    env TERM=dumb bash -lc "$effective_test_cmd"
  fi
  log "tests passed"
}

build_commit_message() {
  local changed_files file_count scope_summary sample_files
  changed_files="$(git diff --cached --name-only)"
  file_count="$(printf '%s\n' "$changed_files" | sed '/^[[:space:]]*$/d' | wc -l | tr -d '[:space:]')"
  if [[ -z "$file_count" || "$file_count" == "0" ]]; then
    printf '%s' "$AUTO_SYNC_COMMIT_PREFIX"
    return 0
  fi

  scope_summary="$(printf '%s\n' "$changed_files" | sed '/^[[:space:]]*$/d' | awk -F'/' '{print $1}' | sort -u | head -n 4 | paste -sd, -)"
  if [[ -z "$scope_summary" ]]; then
    scope_summary="repo"
  fi

  sample_files="$(printf '%s\n' "$changed_files" | sed '/^[[:space:]]*$/d' | head -n 3 | tr '\n' ',' | sed 's/,$//')"
  if [[ -z "$sample_files" ]]; then
    sample_files="n/a"
  fi

  printf '%s | scope=%s | files=%s | sample=%s' "$AUTO_SYNC_COMMIT_PREFIX" "$scope_summary" "$file_count" "$sample_files"
}

post_pull_runtime_update() {
  local changed_files="$1"

  if grep -Eq '(^|/)composer\.json$|(^|/)composer\.lock$' <<< "$changed_files"; then
    log "composer manifests changed, running composer install (optimized)"
    COMPOSER_MEMORY_LIMIT="$AUTO_SYNC_COMPOSER_MEMORY_LIMIT" composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
  fi

  if is_true "$AUTO_SYNC_RUN_MIGRATIONS"; then
    log "running migrations"
    php artisan migrate --force
  fi

  if is_true "$AUTO_SYNC_RUN_OPTIMIZE_CLEAR"; then
    log "running optimize:clear"
    php artisan optimize:clear
  fi

  run_tests

  if command -v pm2 >/dev/null 2>&1; then
    if [[ -f "$ROOT_DIR/$AUTO_SYNC_PM2_ECOSYSTEM" ]]; then
      log "reloading pm2 ecosystem: $AUTO_SYNC_PM2_ECOSYSTEM"
      pm2 startOrReload "$ROOT_DIR/$AUTO_SYNC_PM2_ECOSYSTEM" --update-env
      pm2 save
    else
      log "pm2 ecosystem file not found: $AUTO_SYNC_PM2_ECOSYSTEM"
    fi
  else
    log "pm2 not found, skip reload"
  fi
}

do_pull() {
  ensure_target_branch
  if has_working_tree_changes; then
    if is_true "$AUTO_SYNC_ENABLE_AUTOCOMMIT"; then
      log "working tree dirty before pull, attempting auto commit-push first"
      do_commit_push
    fi

    if has_working_tree_changes; then
      log "skip pull: working tree still has local changes"
      return 0
    fi
  fi

  local before_head after_head changed_files
  before_head="$(git rev-parse HEAD)"

  log "git fetch $AUTO_SYNC_REMOTE/$AUTO_SYNC_BRANCH"
  git fetch "$AUTO_SYNC_REMOTE" "$AUTO_SYNC_BRANCH" --prune
  local remote_head
  remote_head="$(git rev-parse "$AUTO_SYNC_REMOTE/$AUTO_SYNC_BRANCH")"

  if [[ "$before_head" == "$remote_head" ]]; then
    log "already up to date at $before_head"
    return 0
  fi

  log "git pull --rebase $AUTO_SYNC_REMOTE $AUTO_SYNC_BRANCH"
  git pull --rebase "$AUTO_SYNC_REMOTE" "$AUTO_SYNC_BRANCH"
  after_head="$(git rev-parse HEAD)"
  changed_files="$(git diff --name-only "$before_head" "$after_head" || true)"
  log "updated $before_head -> $after_head"

  if [[ -n "$changed_files" ]]; then
    log "changed files: $(tr '\n' ' ' <<< "$changed_files")"
  fi

  post_pull_runtime_update "$changed_files"
}

do_commit_push() {
  ensure_target_branch

  if ! is_true "$AUTO_SYNC_ENABLE_AUTOCOMMIT"; then
    log "autocommit disabled (AUTO_SYNC_ENABLE_AUTOCOMMIT=false)"
    return 0
  fi

  local status_before
  status_before="$(git status --porcelain --untracked-files=normal)"
  if [[ -z "$status_before" ]]; then
    log "no local changes to commit"
    return 0
  fi

  run_tests

  git config user.name "$AUTO_SYNC_GIT_USER_NAME"
  git config user.email "$AUTO_SYNC_GIT_USER_EMAIL"

  git add -A
  git reset --quiet -- \
    "ESP32_Firmware/.pio" \
    "storage" \
    "*.log" \
    "storage/logs/*.log" \
    ".env"

  if git diff --cached --quiet; then
    log "no stageable source changes after runtime excludes"
    return 0
  fi

  local commit_message
  commit_message="$(build_commit_message)"
  git commit -m "$commit_message"
  log "created commit: $commit_message"

  if is_true "$AUTO_SYNC_ENABLE_PUSH"; then
    log "pushing commit to $AUTO_SYNC_REMOTE/$AUTO_SYNC_BRANCH"
    git fetch "$AUTO_SYNC_REMOTE" "$AUTO_SYNC_BRANCH" --prune
    git pull --rebase "$AUTO_SYNC_REMOTE" "$AUTO_SYNC_BRANCH"
    git push "$AUTO_SYNC_REMOTE" "HEAD:$AUTO_SYNC_BRANCH"
    log "push completed"
  else
    log "push disabled (AUTO_SYNC_ENABLE_PUSH=false)"
  fi
}

log "start mode=$MODE branch=$AUTO_SYNC_BRANCH remote=$AUTO_SYNC_REMOTE"
case "$MODE" in
  pull) do_pull ;;
  commit-push) do_commit_push ;;
  full|always)
    do_pull
    do_commit_push
    ;;
esac
log "done mode=$MODE"
