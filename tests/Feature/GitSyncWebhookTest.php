<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class GitSyncWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'git_sync.webhook_enabled' => true,
            'git_sync.webhook_secret' => 'super-secret',
            'git_sync.webhook_repo_full_name' => 'Fairus-24/esptest',
            'git_sync.auto_sync_branch' => 'main',
            'git_sync.sync_script' => 'scripts/debian/git_runtime_sync.sh',
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->postJson('/api/git-sync/webhook', [
            'ref' => 'refs/heads/main',
            'repository' => [
                'full_name' => 'Fairus-24/esptest',
            ],
        ], [
            'X-Hub-Signature-256' => 'sha256=invalid',
            'X-GitHub-Event' => 'push',
        ])->assertStatus(401);
    }

    public function test_webhook_triggers_pull_for_valid_push_event(): void
    {
        Process::fake();

        $payload = [
            'ref' => 'refs/heads/main',
            'repository' => [
                'full_name' => 'Fairus-24/esptest',
            ],
        ];
        $this->postJson('/api/git-sync/webhook', $payload, [
            'X-GitHub-Event' => 'push',
            'X-Git-Sync-Token' => 'super-secret',
        ])->assertStatus(202);

        Process::assertRan(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : (string) $process->command;

            return str_contains($command, 'git_runtime_sync.sh')
                && str_contains($command, 'pull');
        });
    }

    public function test_webhook_ignores_other_branch(): void
    {
        Process::fake();

        $payload = [
            'ref' => 'refs/heads/dev',
            'repository' => [
                'full_name' => 'Fairus-24/esptest',
            ],
        ];
        $this->postJson('/api/git-sync/webhook', $payload, [
            'X-GitHub-Event' => 'push',
            'X-Git-Sync-Token' => 'super-secret',
        ])->assertOk();

        Process::assertDidntRun(function () {
            return true;
        });
    }
}
