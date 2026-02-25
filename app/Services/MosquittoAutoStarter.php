<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MosquittoAutoStarter
{
    public function ensureRunning(): void
    {
        if (!config('mosquitto.auto_start', false)) {
            return;
        }

        $lockHandle = @fopen(storage_path('app/mosquitto_autostart.lock'), 'c+');
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
        $port = max(1, (int) config('mqtt.port', 1883));
        $hosts = $this->resolveBrokerHosts();

        foreach ($hosts as $candidateHost) {
            if ($this->isBrokerReachable($candidateHost, $port)) {
                return;
            }
        }

        $startupHost = $hosts[0] ?? '127.0.0.1';
        foreach ($hosts as $candidateHost) {
            if ($this->isLocalHost($candidateHost)) {
                $startupHost = $candidateHost;
                break;
            }
        }

        if ((bool) config('mosquitto.only_for_local_host', true) && !$this->isLocalHost($startupHost)) {
            return;
        }

        if (!$this->passesCooldown()) {
            return;
        }

        if (!$this->startBrokerProcess()) {
            return;
        }

        $waitSeconds = max(1, (int) config('mosquitto.wait_seconds', 8));
        if (!$this->waitUntilReachable($startupHost, $port, $waitSeconds)) {
            Log::warning('Mosquitto auto-start attempted but broker is still unreachable.', [
                'host' => $startupHost,
                'port' => $port,
            ]);
        }
    }

    private function resolveBrokerHosts(): array
    {
        $primaryHost = trim((string) config('mqtt.host', '127.0.0.1'));
        $fallbackHosts = config('mqtt.fallback_hosts', []);
        if (!is_array($fallbackHosts)) {
            $fallbackHosts = [];
        }

        $candidates = array_merge([$primaryHost], $fallbackHosts, ['127.0.0.1', 'localhost']);
        $hosts = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $host = trim((string) $candidate);
            if ($host === '') {
                continue;
            }

            $normalized = strtolower($host);
            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $hosts[] = $host;
        }

        if ($hosts === []) {
            $hosts[] = '127.0.0.1';
        }

        return $hosts;
    }

    private function isBrokerReachable(string $host, int $port): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if ($socket !== false) {
            @fclose($socket);
            return true;
        }

        return false;
    }

    private function waitUntilReachable(string $host, int $port, int $seconds): bool
    {
        $deadline = microtime(true) + $seconds;

        while (microtime(true) < $deadline) {
            if ($this->isBrokerReachable($host, $port)) {
                return true;
            }

            usleep(250000);
        }

        return false;
    }

    private function isLocalHost(string $host): bool
    {
        $normalized = strtolower(trim($host));
        if (in_array($normalized, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        $resolved = filter_var($normalized, FILTER_VALIDATE_IP) ? $normalized : gethostbyname($normalized);
        if (!filter_var($resolved, FILTER_VALIDATE_IP)) {
            return false;
        }

        $localIps = ['127.0.0.1', '::1'];
        $hostIps = @gethostbynamel(gethostname());
        if (is_array($hostIps)) {
            $localIps = array_merge($localIps, $hostIps);
        }

        $serverAddr = $_SERVER['SERVER_ADDR'] ?? null;
        if (is_string($serverAddr) && $serverAddr !== '') {
            $localIps[] = $serverAddr;
        }

        return in_array($resolved, array_unique($localIps), true);
    }

    private function passesCooldown(): bool
    {
        $cooldown = max(5, (int) config('mosquitto.start_cooldown', 20));
        $stampFile = storage_path('app/mosquitto.last_start');
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

    private function startBrokerProcess(): bool
    {
        $binary = trim((string) config('mosquitto.binary', DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\Program Files\\mosquitto\\mosquitto.exe'
            : 'mosquitto'));

        $configPath = trim((string) config('mosquitto.config_path', DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\Program Files\\mosquitto\\mosquitto.conf'
            : '/etc/mosquitto/mosquitto.conf'));

        if ($this->looksLikePath($binary) && !is_file($binary)) {
            Log::warning('Mosquitto binary path not found, auto-start skipped.', ['binary' => $binary]);
            return false;
        }

        $arguments = [];
        if ((bool) config('mosquitto.verbose', true)) {
            $arguments[] = '-v';
        }

        if ($configPath !== '') {
            if (is_file($configPath)) {
                $arguments[] = '-c';
                $arguments[] = $configPath;
            } else {
                Log::warning('Mosquitto config path not found, auto-start skipped.', ['config_path' => $configPath]);
                return false;
            }
        }

        $command = $this->quoteForShell($binary);
        foreach ($arguments as $argument) {
            $command .= ' ' . $this->quoteForShell($argument);
        }

        $logFile = $this->quoteForShell(storage_path('logs/mosquitto.log'));
        $command .= " >> {$logFile} 2>&1";

        if (DIRECTORY_SEPARATOR === '\\') {
            @pclose(@popen("start \"\" /B {$command}", 'r'));
        } else {
            @exec("nohup {$command} >/dev/null 2>&1 &");
        }

        Log::info('Mosquitto auto-start triggered.', [
            'binary' => $binary,
            'config_path' => $configPath,
        ]);

        return true;
    }

    private function looksLikePath(string $value): bool
    {
        return str_contains($value, '/') || str_contains($value, '\\');
    }

    private function quoteForShell(string $value): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return escapeshellarg($value);
    }
}
