<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LaravelHttpAutoStarter
{
    public function ensureRunning(): void
    {
        if (!config('http_server.auto_start', false)) {
            return;
        }

        if ($this->isCurrentRequestServedByTargetServer()) {
            return;
        }

        $lockHandle = @fopen(storage_path('app/http_server_autostart.lock'), 'c+');
        if ($lockHandle === false) {
            return;
        }

        if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
            @fclose($lockHandle);
            return;
        }

        try {
            $this->ensureRunningUnlocked();
        } finally {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
        }
    }

    private function ensureRunningUnlocked(): void
    {
        if ($this->isHealthy()) {
            return;
        }

        if ($this->isPortOpen()) {
            // Hindari spawn proses ganda jika server sudah listen namun health check belum stabil.
            return;
        }

        if (!$this->passesCooldown()) {
            return;
        }

        if (!$this->startServerProcess()) {
            return;
        }

        if (!$this->waitUntilHealthy()) {
            Log::warning('Laravel HTTP auto-start attempted, but health check still failed.', [
                'host' => $this->healthHost(),
                'port' => $this->port(),
                'health_path' => $this->healthPath(),
            ]);
        }
    }

    private function isHealthy(): bool
    {
        $socket = @fsockopen($this->healthHost(), $this->port(), $errno, $errstr, 1.0);
        if ($socket === false) {
            return false;
        }

        @fclose($socket);

        $healthPath = $this->healthPath();
        if ($healthPath === '') {
            return true;
        }

        $url = 'http://' . $this->healthHost() . ':' . $this->port() . $healthPath;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $context);
        $status = $this->extractStatusCode($http_response_header ?? []);

        return $status >= 200 && $status < 500;
    }

    private function isPortOpen(): bool
    {
        $socket = @fsockopen($this->healthHost(), $this->port(), $errno, $errstr, 1.0);
        if ($socket === false) {
            return false;
        }

        @fclose($socket);
        return true;
    }

    private function waitUntilHealthy(): bool
    {
        $deadline = microtime(true) + max(1, (int) config('http_server.wait_seconds', 8));

        while (microtime(true) < $deadline) {
            if ($this->isHealthy()) {
                return true;
            }

            usleep(250000);
        }

        return false;
    }

    private function passesCooldown(): bool
    {
        $cooldown = max(5, (int) config('http_server.start_cooldown', 15));
        $stampFile = storage_path('app/http_server.last_start');
        $now = time();

        $last = 0;
        if (is_file($stampFile)) {
            $last = (int) trim((string) @file_get_contents($stampFile));
        }

        if (($now - $last) < $cooldown) {
            return false;
        }

        @file_put_contents($stampFile, (string) $now, LOCK_EX);
        return true;
    }

    private function startServerProcess(): bool
    {
        $phpBinary = trim((string) config('http_server.php_binary', PHP_BINARY));
        if ($phpBinary === '') {
            return false;
        }

        if ($this->looksLikePath($phpBinary) && !is_file($phpBinary)) {
            Log::warning('Laravel HTTP auto-start skipped: php binary not found.', [
                'php_binary' => $phpBinary,
            ]);
            return false;
        }

        $host = trim((string) config('http_server.host', '0.0.0.0'));
        $port = $this->port();

        $command = $this->quoteForShell($phpBinary)
            . ' '
            . $this->quoteForShell(base_path('artisan'))
            . ' serve'
            . ' --host=' . $host
            . ' --port=' . $port;

        $logFile = $this->quoteForShell(storage_path('logs/laravel_http_server.log'));
        $command .= " >> {$logFile} 2>&1";

        if (DIRECTORY_SEPARATOR === '\\') {
            @pclose(@popen("start \"\" /B {$command}", 'r'));
        } else {
            @exec("nohup {$command} >/dev/null 2>&1 &");
        }

        Log::info('Laravel HTTP auto-start triggered.', [
            'host' => $host,
            'port' => $port,
            'php_binary' => $phpBinary,
        ]);

        return true;
    }

    private function isCurrentRequestServedByTargetServer(): bool
    {
        if (PHP_SAPI !== 'cli-server') {
            return false;
        }

        $currentPort = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 0;
        return $currentPort > 0 && $currentPort === $this->port();
    }

    private function healthHost(): string
    {
        return trim((string) config('http_server.health_host', '127.0.0.1'));
    }

    private function healthPath(): string
    {
        $path = trim((string) config('http_server.health_path', '/up'));
        if ($path === '') {
            return '';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    private function port(): int
    {
        return max(1, (int) config('http_server.port', 8000));
    }

    private function extractStatusCode(array $headers): int
    {
        foreach (array_reverse($headers) as $headerLine) {
            if (!is_string($headerLine)) {
                continue;
            }

            if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/i', trim($headerLine), $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function looksLikePath(string $value): bool
    {
        return str_contains($value, '\\') || str_contains($value, '/');
    }

    private function quoteForShell(string $value): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return escapeshellarg($value);
    }
}
