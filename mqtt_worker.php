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

    if (!isset(
        $data['device_id'],
        $data['suhu'],
        $data['kelembapan'],
        $data['timestamp_esp'],
        $data['daya'],
        $data['packet_seq'],
        $data['rssi_dbm'],
        $data['tx_duration_ms'],
        $data['payload_bytes'],
        $data['uptime_s'],
        $data['free_heap_bytes']
    )) {
        $log('[ERROR] Payload MQTT kurang field wajib (device_id, suhu, kelembapan, timestamp_esp, daya, packet_seq, rssi_dbm, tx_duration_ms, payload_bytes, uptime_s, free_heap_bytes).');
        return;
    }

    if (
        !is_numeric($data['suhu']) ||
        !is_numeric($data['kelembapan']) ||
        !is_numeric($data['daya']) ||
        !is_numeric($data['timestamp_esp']) ||
        !is_numeric($data['packet_seq']) ||
        !is_numeric($data['rssi_dbm']) ||
        !is_numeric($data['tx_duration_ms']) ||
        !is_numeric($data['payload_bytes']) ||
        !is_numeric($data['uptime_s']) ||
        !is_numeric($data['free_heap_bytes'])
    ) {
        $log('[ERROR] Payload MQTT memiliki tipe field tidak valid (harus numerik).');
        return;
    }

    $suhu = (float) $data['suhu'];
    $kelembapan = (float) $data['kelembapan'];
    $daya = (float) $data['daya'];
    $timestampEspRaw = (int) $data['timestamp_esp'];
    $packetSeq = (int) $data['packet_seq'];
    $rssiDbm = (int) $data['rssi_dbm'];
    $txDurationMs = (float) $data['tx_duration_ms'];
    $payloadBytes = (int) $data['payload_bytes'];
    $uptimeSeconds = (int) $data['uptime_s'];
    $freeHeapBytes = (int) $data['free_heap_bytes'];

    if ($kelembapan < 0 || $kelembapan > 100) {
        $log('[ERROR] Payload MQTT kelembapan di luar rentang 0-100%.');
        return;
    }

    if ($daya < 0) {
        $log('[ERROR] Payload MQTT daya tidak boleh negatif.');
        return;
    }

    if ($timestampEspRaw < 1000000000 || $timestampEspRaw > 4102444800) {
        $log('[ERROR] Payload MQTT timestamp_esp di luar rentang valid.');
        return;
    }

    if ($packetSeq < 1) {
        $log('[ERROR] Payload MQTT packet_seq harus >= 1.');
        return;
    }

    if ($rssiDbm < -120 || $rssiDbm > 0) {
        $log('[ERROR] Payload MQTT rssi_dbm di luar rentang -120..0.');
        return;
    }

    if ($txDurationMs < 0) {
        $log('[ERROR] Payload MQTT tx_duration_ms tidak boleh negatif.');
        return;
    }

    if ($payloadBytes < 1) {
        $log('[ERROR] Payload MQTT payload_bytes harus >= 1.');
        return;
    }

    if ($uptimeSeconds < 0 || $freeHeapBytes < 0) {
        $log('[ERROR] Payload MQTT uptime_s/free_heap_bytes tidak boleh negatif.');
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
    $timestampEsp = Carbon::createFromTimestampUTC($timestampEspRaw);
    $latencyMs = abs((float) $timestampServer->floatDiffInMilliseconds($timestampEsp));

    Eksperimen::query()->create([
        'device_id' => $deviceId,
        'protokol' => 'MQTT',
        'suhu' => $suhu,
        'kelembapan' => $kelembapan,
        'timestamp_esp' => $timestampEsp,
        'timestamp_server' => $timestampServer,
        'latency_ms' => $latencyMs,
        'daya_mw' => $daya,
        'packet_seq' => $packetSeq,
        'rssi_dbm' => $rssiDbm,
        'tx_duration_ms' => $txDurationMs,
        'payload_bytes' => $payloadBytes,
        'uptime_s' => $uptimeSeconds,
        'free_heap_bytes' => $freeHeapBytes,
    ]);

    $log("[DB] MQTT data saved. device_id={$deviceId}, packet_seq={$packetSeq}, suhu={$suhu}, kelembapan={$kelembapan}, daya={$daya}, latency_ms={$latencyMs}, rssi_dbm={$rssiDbm}, tx_duration_ms={$txDurationMs}, payload_bytes={$payloadBytes}");
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
