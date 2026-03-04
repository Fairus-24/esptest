<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class GitSyncWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!config('git_sync.webhook_enabled', false)) {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook sync disabled.',
            ], 404);
        }

        $secret = trim((string) config('git_sync.webhook_secret', ''));
        if ($secret === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook secret not configured.',
            ], 503);
        }

        if (!$this->isAuthorized($request, $secret)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid webhook signature/token.',
            ], 401);
        }

        $event = strtolower(trim((string) $request->header('X-GitHub-Event', 'push')));
        if ($event === 'ping') {
            return response()->json([
                'ok' => true,
                'message' => 'pong',
            ]);
        }

        if ($event !== 'push') {
            return response()->json([
                'ok' => true,
                'message' => 'Event ignored.',
            ]);
        }

        $payload = $request->json()->all();
        $branch = trim((string) config('git_sync.auto_sync_branch', 'main'));
        $expectedRef = 'refs/heads/' . $branch;
        $ref = trim((string) ($payload['ref'] ?? ''));
        if ($ref !== $expectedRef) {
            return response()->json([
                'ok' => true,
                'message' => 'Branch ignored.',
                'expected_ref' => $expectedRef,
                'received_ref' => $ref,
            ]);
        }

        $expectedRepo = trim((string) config('git_sync.webhook_repo_full_name', ''));
        $receivedRepo = trim((string) data_get($payload, 'repository.full_name', ''));
        if ($expectedRepo !== '' && strcasecmp($expectedRepo, $receivedRepo) !== 0) {
            return response()->json([
                'ok' => true,
                'message' => 'Repository ignored.',
                'expected_repo' => $expectedRepo,
                'received_repo' => $receivedRepo,
            ]);
        }

        $script = trim((string) config('git_sync.sync_script', 'scripts/debian/git_runtime_sync.sh'));
        $scriptPath = base_path($script);
        if ($script === '' || !is_file($scriptPath)) {
            return response()->json([
                'ok' => false,
                'message' => 'Sync script not found.',
            ], 500);
        }

        try {
            Process::path(base_path())
                ->env([
                    'AUTO_SYNC_ENABLE_AUTOCOMMIT' => 'true',
                    'AUTO_SYNC_ENABLE_PUSH' => 'true',
                ])
                ->start([
                    '/usr/bin/env',
                    'bash',
                    $script,
                    'pull',
                ]);
        } catch (Throwable $e) {
            Log::error('Git sync webhook failed to start pull process.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Failed to trigger pull sync.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Pull sync triggered.',
        ], 202);
    }

    private function isAuthorized(Request $request, string $secret): bool
    {
        $token = trim((string) $request->header('X-Git-Sync-Token', ''));
        if ($token !== '' && hash_equals($secret, $token)) {
            return true;
        }

        $signatureHeader = trim((string) $request->header('X-Hub-Signature-256', ''));
        if ($signatureHeader === '' || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $providedSignature = substr($signatureHeader, 7);
        if ($providedSignature === '') {
            return false;
        }

        $computedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($computedSignature, $providedSignature);
    }
}

