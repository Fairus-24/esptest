<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Eksperimen;
use App\Services\StatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransmissionHealthConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_transmission_health_uses_configurable_targets_and_weights(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        $basePayload = [
            'device_id' => $device->id,
            'suhu' => 28.8,
            'kelembapan' => 60.1,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'daya_mw' => 77.3,
            'packet_seq' => 1,
            'rssi_dbm' => -55,
            'tx_duration_ms' => 200.0,
            'payload_bytes' => 200,
            'uptime_s' => 444,
            'free_heap_bytes' => 221000,
            'sensor_age_ms' => 11,
            'sensor_read_seq' => 45,
            'send_tick_ms' => 6060,
        ];

        Eksperimen::query()->create(array_merge($basePayload, [
            'protokol' => 'MQTT',
            'latency_ms' => 200.0,
        ]));

        Eksperimen::query()->create(array_merge($basePayload, [
            'protokol' => 'HTTP',
            'latency_ms' => 200.0,
            'packet_seq' => 2,
        ]));

        config([
            'dashboard.transmission_health.mqtt.latency_target_ms' => 100,
            'dashboard.transmission_health.mqtt.tx_target_ms' => 100,
            'dashboard.transmission_health.http.latency_target_ms' => 100,
            'dashboard.transmission_health.http.tx_target_ms' => 100,
            'dashboard.transmission_health.weights.latency' => 0,
            'dashboard.transmission_health.weights.tx_duration' => 0,
            'dashboard.transmission_health.weights.payload' => 1,
        ]);

        $service = app(StatisticsService::class);
        $payloadDominant = $service->getReliability();

        $this->assertSame(100.0, (float) $payloadDominant['mqtt_transmission_health']);
        $this->assertSame(100.0, (float) $payloadDominant['http_transmission_health']);
        $this->assertSame(100.0, (float) $payloadDominant['mqtt_reliability']);
        $this->assertSame(100.0, (float) $payloadDominant['http_reliability']);

        config([
            'dashboard.transmission_health.weights.latency' => 1,
            'dashboard.transmission_health.weights.tx_duration' => 0,
            'dashboard.transmission_health.weights.payload' => 0,
        ]);

        $latencyDominant = $service->getReliability();

        $this->assertSame(0.0, (float) $latencyDominant['mqtt_transmission_health']);
        $this->assertSame(0.0, (float) $latencyDominant['http_transmission_health']);
        $this->assertSame(80.0, (float) $latencyDominant['mqtt_reliability']);
        $this->assertSame(80.0, (float) $latencyDominant['http_reliability']);
    }

    public function test_sequence_reliability_treats_reboot_jump_as_new_segment(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        $basePayload = [
            'device_id' => $device->id,
            'protokol' => 'MQTT',
            'suhu' => 28.8,
            'kelembapan' => 60.1,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'latency_ms' => 120.0,
            'daya_mw' => 77.3,
            'rssi_dbm' => -55,
            'tx_duration_ms' => 200.0,
            'payload_bytes' => 200,
            'free_heap_bytes' => 221000,
            'sensor_age_ms' => 11,
            'sensor_read_seq' => 45,
            'send_tick_ms' => 6060,
        ];

        Eksperimen::query()->create(array_merge($basePayload, [
            'packet_seq' => 1500,
            'uptime_s' => 500,
        ]));
        Eksperimen::query()->create(array_merge($basePayload, [
            'packet_seq' => 1501,
            'uptime_s' => 510,
        ]));
        Eksperimen::query()->create(array_merge($basePayload, [
            'packet_seq' => 987654,
            'uptime_s' => 4,
        ]));

        $service = app(StatisticsService::class);
        $reliability = $service->getReliability();

        $this->assertSame(0, (int) $reliability['mqtt_missing_packets']);
        $this->assertSame(100.0, (float) $reliability['mqtt_sequence_reliability']);
    }

    public function test_sequence_reliability_counts_small_forward_gaps_as_loss(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        $basePayload = [
            'device_id' => $device->id,
            'protokol' => 'MQTT',
            'suhu' => 28.8,
            'kelembapan' => 60.1,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'latency_ms' => 120.0,
            'daya_mw' => 77.3,
            'rssi_dbm' => -55,
            'tx_duration_ms' => 200.0,
            'payload_bytes' => 200,
            'free_heap_bytes' => 221000,
            'sensor_age_ms' => 11,
            'sensor_read_seq' => 45,
            'send_tick_ms' => 6060,
            'uptime_s' => 500,
        ];

        Eksperimen::query()->create(array_merge($basePayload, [
            'packet_seq' => 10,
        ]));
        Eksperimen::query()->create(array_merge($basePayload, [
            'packet_seq' => 13,
            'uptime_s' => 520,
        ]));

        $service = app(StatisticsService::class);
        $reliability = $service->getReliability();

        $this->assertSame(2, (int) $reliability['mqtt_missing_packets']);
        $this->assertSame(50.0, (float) $reliability['mqtt_sequence_reliability']);
    }
}
