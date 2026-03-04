<?php

return [
    'webhook_enabled' => filter_var(env('GIT_SYNC_WEBHOOK_ENABLED', false), FILTER_VALIDATE_BOOL),
    'webhook_secret' => trim((string) env('GIT_SYNC_WEBHOOK_SECRET', '')),
    'webhook_repo_full_name' => trim((string) env('GIT_SYNC_WEBHOOK_REPO_FULL_NAME', '')),
    'auto_sync_branch' => trim((string) env('AUTO_SYNC_BRANCH', 'main')),
    'sync_script' => trim((string) env('GIT_SYNC_SCRIPT_PATH', 'scripts/debian/git_runtime_sync.sh')),
];

