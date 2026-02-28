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

$primaryHost = trim((string) config('mqtt.host', '127.0.0.1'));
$fallbackHosts = config('mqtt.fallback_hosts', []);
if (!is_array($fallbackHosts)) {
    $fallbackHosts = [];
}
$port = (int) config('mqtt.port', 1883);
$topic = (string) config('mqtt.topic', 'iot/esp32/suhu');
$debugTopic = trim((string) config('mqtt.debug_topic', 'iot/esp32/debug'));
$clientIdBase = (string) config('mqtt.client_id', 'laravel-mqtt-worker');
$username = (string) config('mqtt.username', 'esp32');
$password = (string) config('mqtt.password', 'esp32');
$qos = max(0, min(2, (int) config('mqtt.qos', 0)));
$connectTimeout = max(1, (int) config('mqtt.connect_timeout', 5));
$socketTimeout = max(1, (int) config('mqtt.socket_timeout', 5));
$keepAlive = max(1, (int) config('mqtt.keep_alive', 30));
$reconnectDelay = max(1, (int) config('mqtt.reconnect_delay', 3));

$brokerHosts = [];
$seenHosts = [];
$hostCandidates = array_merge([$primaryHost], $fallbackHosts, ['127.0.0.1', 'localhost']);

foreach ($hostCandidates as $candidate) {
    $host = trim((string) $candidate);
    if ($host === '') {
        continue;
    }

    $normalized = strtolower($host);
    if (isset($seenHosts[$normalized])) {
        continue;
    }

    $seenHosts[$normalized] = true;
    $brokerHosts[] = $host;
}

if ($brokerHosts === []) {
    $brokerHosts[] = '127.0.0.1';
}

$hostIndex = 0;

$settings = (new ConnectionSettings())
    ->setUsername($username !== '' ? $username : null)
    ->setPassword($password !== '' ? $password : null)
    ->setConnectTimeout($connectTimeout)
    ->setSocketTimeout($socketTimeout)
    ->setKeepAliveInterval($keepAlive);

$knownDeviceIds = [];
$heartbeatPath = storage_path('app/esp32_debug_heartbeat.json');

$updateHeartbeatFromDebug = static function (string $incomingTopic, string $message) use ($heartbeatPath, $log): void {
    $now = Carbon::now('UTC');
    $deviceId = null;

    if (preg_match('/\bdev=(\d+)\b/i', $message, $matches) === 1) {
        $deviceId = (int) $matches[1];
    }

    if ($deviceId === null) {
        $decoded = json_decode($message, true);
        if (is_array($decoded) && isset($decoded['device_id']) && is_numeric($decoded['device_id'])) {
            $deviceId = (int) $decoded['device_id'];
        }
    }

    if (!is_dir(dirname($heartbeatPath))) {
        @mkdir(dirname($heartbeatPath), 0775, true);
    }

    $state = [
        'updated_at_utc' => $now->toIso8601String(),
        'last_seen_utc' => $now->toIso8601String(),
        'source_topic' => $incomingTopic,
        'devices' => [],
    ];

    if (is_file($heartbeatPath)) {
        $raw = @file_get_contents($heartbeatPath);
        $parsed = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($parsed)) {
            $state = array_merge($state, $parsed);
            if (!isset($state['devices']) || !is_array($state['devices'])) {
                $state['devices'] = [];
            }
        }
    }

    $state['updated_at_utc'] = $now->toIso8601String();
    $state['last_seen_utc'] = $now->toIso8601String();
    $state['source_topic'] = $incomingTopic;

    if ($deviceId !== null && $deviceId > 0) {
        $deviceKey = (string) $deviceId;
        $state['devices'][$deviceKey] = [
            'device_id' => $deviceId,
            'last_seen_utc' => $now->toIso8601String(),
            'source_topic' => $incomingTopic,
            'last_message' => substr($message, 0, 280),
        ];
    }

    $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || @file_put_contents($heartbeatPath, $encoded, LOCK_EX) === false) {
        $log('[WARN] Gagal memperbarui file heartbeat debug ESP32.');
    }
};

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
        $data['free_heap_bytes'],
        $data['sensor_age_ms'],
        $data['sensor_read_seq'],
        $data['send_tick_ms']
    )) {
        $log('[ERROR] Payload MQTT kurang field wajib (device_id, suhu, kelembapan, timestamp_esp, daya, packet_seq, rssi_dbm, tx_duration_ms, payload_bytes, uptime_s, free_heap_bytes, sensor_age_ms, sensor_read_seq, send_tick_ms).');
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
        !is_numeric($data['free_heap_bytes']) ||
        !is_numeric($data['sensor_age_ms']) ||
        !is_numeric($data['sensor_read_seq']) ||
        !is_numeric($data['send_tick_ms'])
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
    $sensorAgeMs = (int) $data['sensor_age_ms'];
    $sensorReadSeq = (int) $data['sensor_read_seq'];
    $sendTickMs = (int) $data['send_tick_ms'];

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

    if ($sensorAgeMs < 0 || $sensorReadSeq < 0 || $sendTickMs < 0) {
        $log('[ERROR] Payload MQTT sensor_age_ms/sensor_read_seq/send_tick_ms harus >= 0.');
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

    Eksperimen::query()->updateOrCreate(
        [
            'device_id' => $deviceId,
            'protokol' => 'MQTT',
            'packet_seq' => $packetSeq,
        ],
        [
            'suhu' => $suhu,
            'kelembapan' => $kelembapan,
            'timestamp_esp' => $timestampEsp,
            'timestamp_server' => $timestampServer,
            'latency_ms' => $latencyMs,
            'daya_mw' => $daya,
            'rssi_dbm' => $rssiDbm,
            'tx_duration_ms' => $txDurationMs,
            'payload_bytes' => $payloadBytes,
            'uptime_s' => $uptimeSeconds,
            'free_heap_bytes' => $freeHeapBytes,
            'sensor_age_ms' => $sensorAgeMs,
            'sensor_read_seq' => $sensorReadSeq,
            'send_tick_ms' => $sendTickMs,
        ]
    );

    $log("[DB] MQTT data saved. device_id={$deviceId}, packet_seq={$packetSeq}, suhu={$suhu}, kelembapan={$kelembapan}, daya={$daya}, latency_ms={$latencyMs}, rssi_dbm={$rssiDbm}, tx_duration_ms={$txDurationMs}, payload_bytes={$payloadBytes}, sensor_age_ms=" . ($sensorAgeMs ?? '-') . ", sensor_read_seq=" . ($sensorReadSeq ?? '-') . ", send_tick_ms=" . ($sendTickMs ?? '-'));
};

$log('[MQTT] Worker starting. Broker candidates=' . implode(', ', $brokerHosts) . ", Port={$port}, Topic={$topic}, DebugTopic=" . ($debugTopic !== '' ? $debugTopic : '-'));

while (true) {
    $mqtt = null;
    $server = $brokerHosts[$hostIndex] ?? $brokerHosts[0];
    $clientId = "{$clientIdBase}-" . getmypid() . '-' . substr(md5((string) microtime(true)), 0, 6);

    try {
        $log("[MQTT] Connecting to broker {$server}:{$port} ...");
        $mqtt = new MqttClient($server, $port, $clientId);
        $mqtt->connect($settings, true);
        $log("[MQTT] Connected to {$server}:{$port}. Listening for messages...");

        $mqtt->subscribe($topic, static function (string $incomingTopic, string $message) use ($log, $savePayload): void {
            $log("[MQTT] Message received on {$incomingTopic}: {$message}");
            try {
                $savePayload($message);
            } catch (\Throwable $e) {
                $log('[ERROR] Gagal simpan payload: ' . $e->getMessage());
            }
        }, $qos);

        if ($debugTopic !== '') {
            $mqtt->subscribe($debugTopic, static function (string $incomingTopic, string $message) use ($updateHeartbeatFromDebug): void {
                try {
                    $updateHeartbeatFromDebug($incomingTopic, $message);
                } catch (\Throwable) {
                    // Ignore debug heartbeat parsing errors to keep worker telemetry path stable.
                }
            }, $qos);
        }

        $mqtt->loop(true);
    } catch (\Throwable $e) {
        $log("[ERROR] MQTT disconnected from {$server}: " . $e->getMessage());

        if (count($brokerHosts) > 1) {
            $hostIndex = ($hostIndex + 1) % count($brokerHosts);
            $nextHost = $brokerHosts[$hostIndex];
            $log("[MQTT] Switching broker candidate to {$nextHost}:{$port}");
        }
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
