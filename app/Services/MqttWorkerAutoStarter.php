<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MqttWorkerAutoStarter
{
    public function ensureRunning(): void
    {
        if (!config('mqtt.auto_start', false)) {
            return;
        }

        if ($this->isWorkerRunning()) {
            return;
        }

        if (!$this->passesCooldown()) {
            return;
        }

        $this->startWorkerProcess();
    }

    private function isWorkerRunning(): bool
    {
        $lockFile = storage_path('app/mqtt_worker.lock');
        $handle = @fopen($lockFile, 'c+');

        if ($handle === false) {
            return false;
        }

        $canLock = @flock($handle, LOCK_EX | LOCK_NB);
        if ($canLock) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
            return false;
        }

        @fclose($handle);
        return true;
    }

    private function passesCooldown(): bool
    {
        $cooldown = max(5, (int) config('mqtt.auto_start_cooldown', 20));
        $stampFile = storage_path('app/mqtt_worker.last_start');
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

    private function startWorkerProcess(): void
    {
        $php = $this->quoteForShell(PHP_BINARY);
        $workerScript = $this->quoteForShell(base_path('mqtt_worker.php'));
        $logFile = $this->quoteForShell(storage_path('logs/mqtt_worker.log'));
        $command = "{$php} {$workerScript} >> {$logFile} 2>&1";

        if (DIRECTORY_SEPARATOR === '\\') {
            @pclose(@popen("start \"\" /B {$command}", 'r'));
        } else {
            @exec("nohup {$command} >/dev/null 2>&1 &");
        }

        Log::info('MQTT worker auto-start triggered.', [
            'command' => $command,
        ]);
    }

    private function quoteForShell(string $value): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return escapeshellarg($value);
    }
}
