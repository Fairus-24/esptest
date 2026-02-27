<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Eksperimen;
use Carbon\Carbon;

class ApplicationSimulationService
{
    private const STATE_FILE = 'app/simulation_state.json';
    private const DEVICE_NAME = 'SIMULATOR-APP';
    private const DEVICE_LOCATION = 'Virtual Lab';
    private const PROFILE_STABLE = 'stable';
    private const PROFILE_NORMAL = 'normal';
    private const PROFILE_STRESS = 'stress';
    private const MODE_STEADY = 'steady';
    private const MODE_RECOVERING = 'recovering';
    private const MODE_CONGESTED = 'congested';

    public function status(): array
    {
        $state = $this->loadState();
        $deviceId = $this->resolveSimulatorDeviceId(isset($state['device_id']) ? (int) $state['device_id'] : null);

        $mqttRows = $deviceId > 0
            ? Eksperimen::query()->where('device_id', $deviceId)->where('protokol', 'MQTT')->count()
            : 0;
        $httpRows = $deviceId > 0
            ? Eksperimen::query()->where('device_id', $deviceId)->where('protokol', 'HTTP')->count()
            : 0;

        return [
            'running' => (bool) ($state['running'] ?? false),
            'device_id' => $deviceId > 0 ? $deviceId : null,
            'device_name' => self::DEVICE_NAME,
            'interval_seconds' => (int) ($state['interval_seconds'] ?? 5),
            'http_fail_rate' => (float) ($state['http_fail_rate'] ?? 0.08),
            'mqtt_fail_rate' => (float) ($state['mqtt_fail_rate'] ?? 0.12),
            'tick_count' => (int) ($state['tick_count'] ?? 0),
            'esp_uptime_s' => (int) ($state['esp_uptime_s'] ?? 0),
            'started_at' => $state['started_at'] ?? null,
            'last_tick_at' => $state['last_tick_at'] ?? null,
            'http_packet_seq' => (int) ($state['http_packet_seq'] ?? 0),
            'mqtt_packet_seq' => (int) ($state['mqtt_packet_seq'] ?? 0),
            'sensor_read_seq' => (int) ($state['sensor_read_seq'] ?? 0),
            'base_temp' => round((float) ($state['base_temp'] ?? 28.0), 3),
            'base_humidity' => round((float) ($state['base_humidity'] ?? 60.0), 3),
            'network_profile' => $this->sanitizeNetworkProfile((string) ($state['network_profile'] ?? self::PROFILE_NORMAL)),
            'network_mode' => $this->sanitizeNetworkMode((string) ($state['network_mode'] ?? self::MODE_STEADY)),
            'network_mode_ticks_left' => max(0, (int) ($state['network_mode_ticks_left'] ?? 0)),
            'network_health' => round($this->clampValue((float) ($state['network_health'] ?? 0.86), 0.0, 1.0) * 100.0, 2),
            'mqtt_total_rows' => $mqttRows,
            'http_total_rows' => $httpRows,
            'total_rows' => $mqttRows + $httpRows,
        ];
    }

    public function start(array $options = []): array
    {
        $state = $this->loadState();

        $state['device_id'] = $this->resolveSimulatorDeviceId(isset($state['device_id']) ? (int) $state['device_id'] : null);
        $state['running'] = true;
        $state['started_at'] = $state['started_at'] ?? Carbon::now('UTC')->toIso8601String();
        $state['interval_seconds'] = max(1, (int) ($options['interval_seconds'] ?? $state['interval_seconds'] ?? 5));
        $state['http_fail_rate'] = $this->clampRate((float) ($options['http_fail_rate'] ?? $state['http_fail_rate'] ?? 0.08));
        $state['mqtt_fail_rate'] = $this->clampRate((float) ($options['mqtt_fail_rate'] ?? $state['mqtt_fail_rate'] ?? 0.12));
        $state['network_profile'] = $this->sanitizeNetworkProfile((string) ($options['network_profile'] ?? $state['network_profile'] ?? self::PROFILE_NORMAL));
        $state['network_mode'] = $this->sanitizeNetworkMode((string) ($state['network_mode'] ?? self::MODE_STEADY));

        if (($options['reset_before_start'] ?? false) === true) {
            $this->deleteSimulatorRows((int) $state['device_id']);
            $state['http_packet_seq'] = 0;
            $state['mqtt_packet_seq'] = 0;
            $state['sensor_read_seq'] = 0;
            $state['tick_count'] = 0;
            $state['esp_uptime_s'] = 0;
            $state['last_tick_at'] = null;
            $state['base_temp'] = 28.0;
            $state['base_humidity'] = 60.0;
            $state['network_mode'] = self::MODE_STEADY;
            $state['network_mode_ticks_left'] = 0;
            $state['network_health'] = 0.86;
        }

        $this->saveState($state);
        $this->tick();

        return $this->status();
    }

    public function stop(): array
    {
        $state = $this->loadState();
        $state['running'] = false;
        $this->saveState($state);

        return $this->status();
    }

    public function reset(): array
    {
        $state = $this->loadState();
        $deviceId = $this->resolveSimulatorDeviceId(isset($state['device_id']) ? (int) $state['device_id'] : null);
        $this->deleteSimulatorRows($deviceId);

        $newState = $this->defaultState();
        $newState['device_id'] = $deviceId;
        $this->saveState($newState);

        return $this->status();
    }

    public function tick(): array
    {
        $state = $this->loadState();
        if (!(bool) ($state['running'] ?? false)) {
            return [
                'ran' => false,
                'reason' => 'simulation_not_running',
                'status' => $this->status(),
            ];
        }

        $state['device_id'] = $this->resolveSimulatorDeviceId(isset($state['device_id']) ? (int) $state['device_id'] : null);
        $intervalSeconds = max(1, (int) ($state['interval_seconds'] ?? 5));
        $now = Carbon::now('UTC');

        $lastTick = isset($state['last_tick_at']) && is_string($state['last_tick_at']) && $state['last_tick_at'] !== ''
            ? Carbon::parse($state['last_tick_at'], 'UTC')
            : null;
        $elapsedSinceLastTick = $lastTick ? $lastTick->diffInSeconds($now, true) : $intervalSeconds;
        if ($lastTick && $elapsedSinceLastTick < $intervalSeconds) {
            return [
                'ran' => false,
                'reason' => 'interval_not_reached',
                'status' => $this->status(),
            ];
        }

        $state['esp_uptime_s'] = (int) ($state['esp_uptime_s'] ?? 0) + $intervalSeconds;
        $state['tick_count'] = (int) ($state['tick_count'] ?? 0) + 1;
        $this->advanceNetworkState($state);
        $networkMode = $this->sanitizeNetworkMode((string) ($state['network_mode'] ?? self::MODE_STEADY));
        $driftScale = match ($networkMode) {
            self::MODE_CONGESTED => 1.25,
            self::MODE_RECOVERING => 1.05,
            default => 0.9,
        };

        $state['base_temp'] = $this->clampValue(
            (float) ($state['base_temp'] ?? 28.0) + ($this->randomFloat(-0.08, 0.08) * $driftScale),
            24.0,
            36.0
        );
        $state['base_humidity'] = $this->clampValue(
            (float) ($state['base_humidity'] ?? 60.0) + ($this->randomFloat(-0.35, 0.35) * $driftScale),
            30.0,
            90.0
        );

        $httpResult = $this->simulateProtocolTick('HTTP', $state, $now);
        $mqttResult = $this->simulateProtocolTick('MQTT', $state, $now);

        $state['last_tick_at'] = $now->toIso8601String();
        $this->saveState($state);

        return [
            'ran' => true,
            'http' => $httpResult,
            'mqtt' => $mqttResult,
            'status' => $this->status(),
        ];
    }

    private function simulateProtocolTick(string $protocol, array &$state, Carbon $now): array
    {
        $isHttp = strtoupper($protocol) === 'HTTP';
        $packetKey = $isHttp ? 'http_packet_seq' : 'mqtt_packet_seq';
        $failRateKey = $isHttp ? 'http_fail_rate' : 'mqtt_fail_rate';

        $state[$packetKey] = (int) ($state[$packetKey] ?? 0) + 1;
        $packetSeq = (int) $state[$packetKey];
        $state['sensor_read_seq'] = (int) ($state['sensor_read_seq'] ?? 0) + 1;
        $sensorReadSeq = (int) $state['sensor_read_seq'];

        $baseTemp = (float) ($state['base_temp'] ?? 28.0);
        $baseHumidity = (float) ($state['base_humidity'] ?? 60.0);
        $network = $this->resolveProtocolNetwork($state, $isHttp);
        $sensor = $this->buildSensorSnapshot($baseTemp, $baseHumidity, $state, $isHttp);

        $suhu = (float) $sensor['suhu'];
        $kelembapan = (float) $sensor['kelembapan'];
        $rssi = (int) $network['rssi_dbm'];
        $latencyMs = (float) $network['latency_ms'];
        $txDurationMs = (float) $network['tx_duration_ms'];
        $payloadBytes = (int) $network['payload_bytes'];
        $sensorAgeMs = (int) $sensor['sensor_age_ms'];
        $sendTickMs = (int) $network['send_tick_ms'];
        $freeHeap = (int) $network['free_heap_bytes'];
        $dayaMw = $this->estimatePower($isHttp, $txDurationMs, $rssi, $payloadBytes);

        $baseFailRate = $this->clampRate((float) ($state[$failRateKey] ?? 0.0));
        $effectiveFailRate = $baseFailRate <= 0.0
            ? 0.0
            : $this->clampRate(($baseFailRate * (float) $network['fail_multiplier']) + (float) $network['fail_offset']);
        $failed = $this->isFailure($effectiveFailRate);
        if ($failed) {
            return [
                'packet_seq' => $packetSeq,
                'stored' => false,
                'failed' => true,
                'effective_fail_rate' => round($effectiveFailRate, 4),
            ];
        }

        $timestampEsp = $now->copy()->subMilliseconds((int) round($latencyMs));
        Eksperimen::query()->updateOrCreate(
            [
                'device_id' => (int) $state['device_id'],
                'protokol' => strtoupper($protocol),
                'packet_seq' => $packetSeq,
            ],
            [
                'suhu' => round($suhu, 8),
                'kelembapan' => round($kelembapan, 8),
                'timestamp_esp' => $timestampEsp,
                'timestamp_server' => $now->copy(),
                'latency_ms' => round($latencyMs, 3),
                'daya_mw' => round($dayaMw, 2),
                'rssi_dbm' => $rssi,
                'tx_duration_ms' => round($txDurationMs, 3),
                'payload_bytes' => $payloadBytes,
                'uptime_s' => (int) $state['esp_uptime_s'],
                'free_heap_bytes' => $freeHeap,
                'sensor_age_ms' => $sensorAgeMs,
                'sensor_read_seq' => $sensorReadSeq,
                'send_tick_ms' => $sendTickMs,
            ]
        );

        return [
            'packet_seq' => $packetSeq,
            'stored' => true,
            'failed' => false,
            'effective_fail_rate' => round($effectiveFailRate, 4),
        ];
    }

    private function estimatePower(bool $isHttp, float $txDurationMs, int $rssiDbm, int $payloadBytes): float
    {
        $base = $isHttp ? 805.0 : 780.0;
        $txComponent = ($txDurationMs / ($isHttp ? 220.0 : 12.0)) * 3.5;
        $signalComponent = abs($rssiDbm + 60) * 1.4;
        $payloadComponent = ($payloadBytes / 120.0) * 2.0;
        $noise = $this->randomFloat(-6.0, 6.0);

        return max(0.0, $base + $txComponent + $signalComponent + $payloadComponent + $noise);
    }

    private function buildSensorSnapshot(float $baseTemp, float $baseHumidity, array $state, bool $isHttp): array
    {
        $networkMode = $this->sanitizeNetworkMode((string) ($state['network_mode'] ?? self::MODE_STEADY));
        $qualityPenalty = 1.0 - $this->clampValue((float) ($state['network_health'] ?? 0.86), 0.2, 1.0);

        $tempSpread = $isHttp ? 0.22 : 0.18;
        $humiditySpread = $isHttp ? 1.15 : 0.92;
        $suhu = $this->clampValue($baseTemp + $this->randomFloat(-$tempSpread, $tempSpread), 20.0, 45.0);
        $kelembapan = $this->clampValue($baseHumidity + $this->randomFloat(-$humiditySpread, $humiditySpread), 20.0, 95.0);

        $sensorAgeBase = $isHttp
            ? $this->randomFloat(180, 2200)
            : $this->randomFloat(70, 1400);
        $sensorAgeMs = $sensorAgeBase + ($qualityPenalty * ($isHttp ? 5400.0 : 3800.0));

        if ($networkMode === self::MODE_CONGESTED && $this->isFailure(0.18)) {
            $sensorAgeMs += $this->randomFloat(1800, 7600);
        }

        return [
            'suhu' => $suhu,
            'kelembapan' => $kelembapan,
            'sensor_age_ms' => (int) round($sensorAgeMs),
        ];
    }

    private function resolveProtocolNetwork(array $state, bool $isHttp): array
    {
        $profile = $this->resolveProfileConfig((string) ($state['network_profile'] ?? self::PROFILE_NORMAL));
        $networkMode = $this->sanitizeNetworkMode((string) ($state['network_mode'] ?? self::MODE_STEADY));
        $quality = $this->clampValue((float) ($state['network_health'] ?? 0.86), 0.2, 1.0);
        $qualityPenalty = 1.0 - $quality;
        $modePenalty = match ($networkMode) {
            self::MODE_CONGESTED => 1.0,
            self::MODE_RECOVERING => 0.45,
            default => 0.15,
        };

        $jitter = (float) $profile['jitter'];
        if ($isHttp) {
            $latencyMs = 620.0 + (($qualityPenalty + $modePenalty) * 1800.0) + ($this->randomFloat(30, 260) * $jitter);
            $txDurationMs = 1100.0 + (($qualityPenalty + ($modePenalty * 0.8)) * 2750.0) + ($this->randomFloat(70, 720) * $jitter);
        } else {
            $latencyMs = 230.0 + (($qualityPenalty + $modePenalty) * 970.0) + ($this->randomFloat(10, 160) * $jitter);
            $txDurationMs = 16.0 + (($qualityPenalty + ($modePenalty * 0.9)) * 120.0) + ($this->randomFloat(1.5, 24.0) * $jitter);
        }

        if ($networkMode === self::MODE_CONGESTED && $this->isFailure($isHttp ? 0.18 : 0.14)) {
            $latencyMs *= $this->randomFloat(1.2, 1.95);
            $txDurationMs *= $this->randomFloat(1.12, 1.7);
        }

        $payloadBase = $this->randomFloat($isHttp ? 392 : 368, $isHttp ? 432 : 418);
        $payloadPenalty = $qualityPenalty * ($isHttp ? 26.0 : 20.0);
        $payloadBytes = (int) round($this->clampValue($payloadBase - $payloadPenalty, 220.0, 520.0));

        $rssiDbm = (int) round($this->clampValue(
            -54.0 - ($qualityPenalty * 21.0) - ($modePenalty * 8.0) + $this->randomFloat(-3.0, 3.0),
            -96.0,
            -44.0
        ));

        $heapPressure = ($qualityPenalty * 17000.0) + ($modePenalty * 6500.0) + $this->randomFloat(-2200.0, 1800.0);
        $freeHeapBytes = (int) round($this->clampValue(260000.0 - $heapPressure, 210000.0, 280000.0));

        $sendTickMs = max(1, ((int) ($state['esp_uptime_s'] ?? 0) * 1000) + (int) round($this->randomFloat(10.0, 950.0)));

        $failMultiplier = (float) $profile['fail_multiplier']
            * (1.0 + ($qualityPenalty * 1.45) + ($modePenalty * 0.8))
            * ($isHttp ? 1.06 : 1.0);
        $failOffset = $this->isFailure(0.16) ? $this->randomFloat(0.0, 0.015) : 0.0;

        return [
            'latency_ms' => $latencyMs,
            'tx_duration_ms' => $txDurationMs,
            'payload_bytes' => $payloadBytes,
            'rssi_dbm' => $rssiDbm,
            'free_heap_bytes' => $freeHeapBytes,
            'send_tick_ms' => $sendTickMs,
            'fail_multiplier' => max(0.05, $failMultiplier),
            'fail_offset' => $failOffset,
        ];
    }

    private function advanceNetworkState(array &$state): void
    {
        $profile = $this->resolveProfileConfig((string) ($state['network_profile'] ?? self::PROFILE_NORMAL));
        $currentMode = $this->sanitizeNetworkMode((string) ($state['network_mode'] ?? self::MODE_STEADY));
        $ticksLeft = max(0, (int) ($state['network_mode_ticks_left'] ?? 0));

        if ($ticksLeft <= 0) {
            $currentMode = $this->chooseNextNetworkMode($currentMode, $profile);
            $ticksLeft = $this->rollModeDuration($currentMode, $profile);
        } else {
            $ticksLeft -= 1;
        }

        $sampleHealth = match ($currentMode) {
            self::MODE_CONGESTED => $this->randomFloat(0.32, 0.72),
            self::MODE_RECOVERING => $this->randomFloat(0.6, 0.88),
            default => $this->randomFloat(0.84, 0.99),
        };
        $sampleHealth = $this->clampValue($sampleHealth * (float) $profile['health_bias'], 0.22, 1.0);
        $previousHealth = $this->clampValue((float) ($state['network_health'] ?? 0.86), 0.22, 1.0);
        $blended = ($previousHealth * 0.62) + ($sampleHealth * 0.38);

        $state['network_mode'] = $currentMode;
        $state['network_mode_ticks_left'] = $ticksLeft;
        $state['network_health'] = $this->clampValue($blended, 0.22, 1.0);
    }

    private function chooseNextNetworkMode(string $currentMode, array $profile): string
    {
        if ($currentMode === self::MODE_CONGESTED && $this->isFailure(0.68)) {
            return self::MODE_RECOVERING;
        }
        if ($currentMode === self::MODE_RECOVERING && $this->isFailure(0.58)) {
            return self::MODE_STEADY;
        }
        if ($currentMode === self::MODE_STEADY && $this->isFailure((float) $profile['micro_congestion_chance'])) {
            return self::MODE_CONGESTED;
        }

        return $this->pickWeightedMode((array) $profile['mode_weights']);
    }

    private function pickWeightedMode(array $weights): string
    {
        $steady = max(0.0, (float) ($weights[self::MODE_STEADY] ?? 0.0));
        $recovering = max(0.0, (float) ($weights[self::MODE_RECOVERING] ?? 0.0));
        $congested = max(0.0, (float) ($weights[self::MODE_CONGESTED] ?? 0.0));
        $total = $steady + $recovering + $congested;

        if ($total <= 0.0) {
            return self::MODE_STEADY;
        }

        $draw = $this->randomFloat(0.0, $total);
        if ($draw <= $steady) {
            return self::MODE_STEADY;
        }

        if ($draw <= ($steady + $recovering)) {
            return self::MODE_RECOVERING;
        }

        return self::MODE_CONGESTED;
    }

    private function rollModeDuration(string $mode, array $profile): int
    {
        $durationBias = max(0.6, (float) ($profile['duration_bias'] ?? 1.0));
        [$min, $max] = match ($mode) {
            self::MODE_CONGESTED => [2.0, 8.0],
            self::MODE_RECOVERING => [2.0, 7.0],
            default => [5.0, 13.0],
        };

        $duration = (int) round($this->randomFloat($min, $max) * $durationBias);
        return max(1, $duration);
    }

    private function resolveProfileConfig(string $profile): array
    {
        return match ($this->sanitizeNetworkProfile($profile)) {
            self::PROFILE_STABLE => [
                'fail_multiplier' => 0.68,
                'jitter' => 0.82,
                'health_bias' => 1.05,
                'duration_bias' => 1.12,
                'micro_congestion_chance' => 0.05,
                'mode_weights' => [
                    self::MODE_STEADY => 0.72,
                    self::MODE_RECOVERING => 0.2,
                    self::MODE_CONGESTED => 0.08,
                ],
            ],
            self::PROFILE_STRESS => [
                'fail_multiplier' => 1.35,
                'jitter' => 1.24,
                'health_bias' => 0.9,
                'duration_bias' => 0.84,
                'micro_congestion_chance' => 0.22,
                'mode_weights' => [
                    self::MODE_STEADY => 0.38,
                    self::MODE_RECOVERING => 0.27,
                    self::MODE_CONGESTED => 0.35,
                ],
            ],
            default => [
                'fail_multiplier' => 1.0,
                'jitter' => 1.0,
                'health_bias' => 1.0,
                'duration_bias' => 1.0,
                'micro_congestion_chance' => 0.12,
                'mode_weights' => [
                    self::MODE_STEADY => 0.56,
                    self::MODE_RECOVERING => 0.24,
                    self::MODE_CONGESTED => 0.2,
                ],
            ],
        };
    }

    private function sanitizeNetworkProfile(string $profile): string
    {
        $normalized = strtolower(trim($profile));
        if (in_array($normalized, [self::PROFILE_STABLE, self::PROFILE_NORMAL, self::PROFILE_STRESS], true)) {
            return $normalized;
        }

        return self::PROFILE_NORMAL;
    }

    private function sanitizeNetworkMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        if (in_array($normalized, [self::MODE_STEADY, self::MODE_RECOVERING, self::MODE_CONGESTED], true)) {
            return $normalized;
        }

        return self::MODE_STEADY;
    }

    private function isFailure(float $failRate): bool
    {
        if ($failRate <= 0.0) {
            return false;
        }

        $random = mt_rand(0, 1000000) / 1000000;
        return $random < $failRate;
    }

    private function ensureSimulatorDevice(): Device
    {
        return Device::query()->firstOrCreate(
            ['nama_device' => self::DEVICE_NAME],
            ['lokasi' => self::DEVICE_LOCATION]
        );
    }

    private function resolveSimulatorDeviceId(?int $candidateDeviceId): int
    {
        if ($candidateDeviceId !== null && $candidateDeviceId > 0) {
            $isSimulatorDevice = Device::query()
                ->whereKey($candidateDeviceId)
                ->where('nama_device', self::DEVICE_NAME)
                ->exists();

            if ($isSimulatorDevice) {
                return $candidateDeviceId;
            }
        }

        return $this->ensureSimulatorDevice()->id;
    }

    private function deleteSimulatorRows(int $deviceId): void
    {
        Eksperimen::query()->where('device_id', $deviceId)->delete();
    }

    private function loadState(): array
    {
        $path = storage_path(self::STATE_FILE);
        if (!is_file($path)) {
            return $this->defaultState();
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return $this->defaultState();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->defaultState();
        }

        return array_merge($this->defaultState(), $decoded);
    }

    private function saveState(array $state): void
    {
        $path = storage_path(self::STATE_FILE);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function defaultState(): array
    {
        return [
            'running' => false,
            'device_id' => null,
            'interval_seconds' => 5,
            'http_fail_rate' => 0.08,
            'mqtt_fail_rate' => 0.12,
            'tick_count' => 0,
            'esp_uptime_s' => 0,
            'started_at' => null,
            'last_tick_at' => null,
            'http_packet_seq' => 0,
            'mqtt_packet_seq' => 0,
            'sensor_read_seq' => 0,
            'base_temp' => 28.0,
            'base_humidity' => 60.0,
            'network_profile' => self::PROFILE_NORMAL,
            'network_mode' => self::MODE_STEADY,
            'network_mode_ticks_left' => 0,
            'network_health' => 0.86,
        ];
    }

    private function clampRate(float $value): float
    {
        return $this->clampValue($value, 0.0, 1.0);
    }

    private function clampValue(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function randomFloat(float $min, float $max): float
    {
        if ($max <= $min) {
            return $min;
        }

        return $min + (mt_rand(0, 1000000) / 1000000) * ($max - $min);
    }
}
