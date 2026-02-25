<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\Eksperimen;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * Log helper untuk output worker dengan timestamp lokal.
 */
$log = static function (string $message): void {
    $time = Carbon::now(config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
    echo "[{$time}] {$message}" . PHP_EOL;
};

/**
 * Lock file agar worker tidak berjalan ganda.
 */
$lockPath = storage_path('app/mqtt_worker.lock');
$lockHandle = fopen($lockPath, 'c+');
if ($lockHandle === false) {
    $log('[ERROR] Gagal membuat lock file worker.');
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $log('[MQTT] Worker sudah berjalan, proses baru dibatalkan.');
    fclose($lockHandle);
    exit(0);
}

ftruncate($lockHandle, 0);
fwrite($lockHandle, (string) getmypid());
fflush($lockHandle);

register_shutdown_function(static function () use ($lockHandle): void {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
});

$server = (string) config('mqtt.host', '192.168.0.100');
$port = (int) config('mqtt.port', 1883);
$topic = (string) config('mqtt.topic', 'iot/esp32/suhu');
$clientIdBase = (string) config('mqtt.client_id', 'laravel-mqtt-worker');
$username = (string) config('mqtt.username', 'esp32');
$password = (string) config('mqtt.password', 'esp32');
$qos = max(0, min(2, (int) config('mqtt.qos', 0)));
$connectTimeout = max(1, (int) config('mqtt.connect_timeout', 5));
$socketTimeout = max(1, (int) config('mqtt.socket_timeout', 5));
$keepAlive = max(1, (int) config('mqtt.keep_alive', 30));
$reconnectDelay = max(1, (int) config('mqtt.reconnect_delay', 3));

$settings = (new ConnectionSettings())
    ->setUsername($username !== '' ? $username : null)
    ->setPassword($password !== '' ? $password : null)
    ->setConnectTimeout($connectTimeout)
    ->setSocketTimeout($socketTimeout)
    ->setKeepAliveInterval($keepAlive);

$knownDeviceIds = [];

$savePayload = static function (string $message) use (&$knownDeviceIds, $log): void {
    $data = json_decode($message, true);
    if (!is_array($data)) {
        $log('[ERROR] Payload MQTT bukan JSON valid.');
        return;
    }

    if (!isset($data['device_id'], $data['suhu'], $data['timestamp_esp'], $data['daya'])) {
        $log('[ERROR] Payload MQTT kurang field wajib (device_id, suhu, timestamp_esp, daya).');
        return;
    }

    $deviceId = (int) $data['device_id'];
    if (!array_key_exists($deviceId, $knownDeviceIds)) {
        $knownDeviceIds[$deviceId] = Device::query()->whereKey($deviceId)->exists();
    }

    if ($knownDeviceIds[$deviceId] !== true) {
        $log("[ERROR] Device ID {$deviceId} tidak ditemukan.");
        return;
    }

    $timestampServer = Carbon::now('UTC');
    $timestampEsp = Carbon::createFromTimestampUTC((int) $data['timestamp_esp']);
    $latencyMs = abs((float) $timestampServer->floatDiffInMilliseconds($timestampEsp));

    Eksperimen::query()->create([
        'device_id' => $deviceId,
        'protokol' => 'MQTT',
        'suhu' => (float) $data['suhu'],
        'kelembapan' => isset($data['kelembapan']) ? (float) $data['kelembapan'] : null,
        'timestamp_esp' => $timestampEsp,
        'timestamp_server' => $timestampServer,
        'latency_ms' => $latencyMs,
        'daya_mw' => (float) $data['daya'],
    ]);

    $log('[DB] MQTT data saved.');
};

$log("[MQTT] Worker starting. Broker={$server}:{$port}, Topic={$topic}");

while (true) {
    $mqtt = null;
    $clientId = "{$clientIdBase}-" . getmypid() . '-' . substr(md5((string) microtime(true)), 0, 6);

    try {
        $mqtt = new MqttClient($server, $port, $clientId);
        $mqtt->connect($settings, true);
        $log('[MQTT] Connected. Listening for messages...');

        $mqtt->subscribe($topic, static function (string $incomingTopic, string $message) use ($log, $savePayload): void {
            $log("[MQTT] Message received on {$incomingTopic}: {$message}");
            try {
                $savePayload($message);
            } catch (\Throwable $e) {
                $log('[ERROR] Gagal simpan payload: ' . $e->getMessage());
            }
        }, $qos);

        $mqtt->loop(true);
    } catch (\Throwable $e) {
        $log('[ERROR] MQTT disconnected: ' . $e->getMessage());
    } finally {
        if ($mqtt !== null) {
            try {
                $mqtt->disconnect();
            } catch (\Throwable) {
                // Ignore disconnection cleanup errors.
            }
        }
    }

    $log("[MQTT] Reconnecting in {$reconnectDelay}s...");
    sleep($reconnectDelay);
}
