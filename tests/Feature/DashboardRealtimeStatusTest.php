<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceFirmwareProfile;
use App\Models\Eksperimen;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRealtimeStatusTest extends TestCase
{
    use RefreshDatabase;

    private string $simulationStatePath;
    private string $heartbeatPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->simulationStatePath = storage_path('app/simulation_state.json');
        $this->heartbeatPath = storage_path('app/esp32_debug_heartbeat.json');
        @unlink($this->simulationStatePath);
        @unlink($this->heartbeatPath);

        config()->set('dashboard.connection.protocol_freshness_seconds', 30);
        config()->set('dashboard.connection.esp32_freshness_seconds', 30);
        config()->set('dashboard.connection.ignore_simulator_when_stopped', true);
    }

    protected function tearDown(): void
    {
        @unlink($this->simulationStatePath);
        @unlink($this->heartbeatPath);
        parent::tearDown();
    }

    public function test_dashboard_shows_not_found_when_no_data_exists(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 OFF')
            ->assertSee('MQTT Not Found')
            ->assertSee('HTTP Not Found');
    }

    public function test_dashboard_marks_protocol_disconnected_when_data_is_stale(): void
    {
        $device = $this->createDevice('ESP32-REAL');
        $staleTimestamp = now()->subSeconds(95);

        $this->insertTelemetry($device, 'MQTT', $staleTimestamp, 1001);
        $this->insertTelemetry($device, 'HTTP', $staleTimestamp, 2001);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 OFF')
            ->assertSee('MQTT Disconnected')
            ->assertSee('HTTP Disconnected');
    }

    public function test_dashboard_shows_connected_when_fresh_data_arrives(): void
    {
        $device = $this->createDevice('ESP32-REAL');
        $freshTimestamp = now()->subSeconds(6);

        $this->insertTelemetry($device, 'MQTT', $freshTimestamp, 3001);
        $this->insertTelemetry($device, 'HTTP', $freshTimestamp, 4001);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 ON')
            ->assertSee('MQTT Connected')
            ->assertSee('HTTP Connected');
    }

    public function test_dashboard_marks_filtered_when_only_simulator_data_exists_and_simulation_is_stopped(): void
    {
        $simDevice = $this->createDevice('SIMULATOR-APP');
        $freshTimestamp = now()->subSeconds(4);

        $this->insertSimulationStateFile(false, $simDevice->id);
        $this->insertTelemetry($simDevice, 'MQTT', $freshTimestamp, 5001);
        $this->insertTelemetry($simDevice, 'HTTP', $freshTimestamp, 6001);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 OFF')
            ->assertSee('MQTT Filtered')
            ->assertSee('HTTP Filtered');
    }

    public function test_dashboard_does_not_filter_simulator_label_when_state_file_is_missing(): void
    {
        $simNamedDevice = $this->createDevice('SIMULATOR-APP');
        $freshTimestamp = now()->subSeconds(3);

        $this->insertTelemetry($simNamedDevice, 'MQTT', $freshTimestamp, 6051);
        $this->insertTelemetry($simNamedDevice, 'HTTP', $freshTimestamp, 6052);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 ON')
            ->assertSee('MQTT Connected')
            ->assertSee('HTTP Connected')
            ->assertDontSee('MQTT Filtered')
            ->assertDontSee('HTTP Filtered');
    }

    public function test_dashboard_does_not_filter_provisioned_device_even_if_simulator_state_points_to_it(): void
    {
        $simNamedDevice = $this->createDevice('SIMULATOR-APP');
        $freshTimestamp = now()->subSeconds(3);

        DeviceFirmwareProfile::query()->create([
            'device_id' => $simNamedDevice->id,
        ]);

        $this->insertSimulationStateFile(false, $simNamedDevice->id);
        $this->insertTelemetry($simNamedDevice, 'MQTT', $freshTimestamp, 6061);
        $this->insertTelemetry($simNamedDevice, 'HTTP', $freshTimestamp, 6062);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 ON')
            ->assertSee('MQTT Connected')
            ->assertSee('HTTP Connected')
            ->assertDontSee('MQTT Filtered')
            ->assertDontSee('HTTP Filtered');
    }

    public function test_dashboard_includes_simulator_data_when_simulation_is_running(): void
    {
        $simDevice = $this->createDevice('SIMULATOR-APP');
        $freshTimestamp = now()->subSeconds(3);

        $this->insertSimulationStateFile(true);
        $this->insertTelemetry($simDevice, 'MQTT', $freshTimestamp, 7001);
        $this->insertTelemetry($simDevice, 'HTTP', $freshTimestamp, 8001);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 ON')
            ->assertSee('MQTT Connected')
            ->assertSee('HTTP Connected');
    }

    public function test_dashboard_marks_esp32_on_from_fresh_debug_heartbeat_even_without_fresh_telemetry(): void
    {
        $device = $this->createDevice('ESP32-REAL-HEARTBEAT');
        $this->insertTelemetry($device, 'MQTT', now()->subSeconds(120), 9001);
        $this->insertTelemetry($device, 'HTTP', now()->subSeconds(120), 9002);

        $this->writeHeartbeatFile([
            'updated_at_utc' => now()->toIso8601String(),
            'last_seen_utc' => now()->toIso8601String(),
            'source_topic' => 'iot/esp32/debug',
            'devices' => [
                (string) $device->id => [
                    'device_id' => $device->id,
                    'last_seen_utc' => now()->subSeconds(4)->toIso8601String(),
                    'source_topic' => 'iot/esp32/debug',
                    'last_message' => 't=123 level=WARN dev=' . $device->id . ' msg=Sensor checksum',
                ],
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 ON')
            ->assertSee('MQTT Disconnected')
            ->assertSee('HTTP Disconnected');
    }

    public function test_dashboard_does_not_filter_real_device_when_simulation_state_points_to_non_simulator_id(): void
    {
        $realDevice = $this->createDevice('ESP32-REAL');
        $this->createDevice('SIMULATOR-APP');
        $freshTimestamp = now()->subSeconds(4);

        $this->insertSimulationStateFile(false, $realDevice->id);
        $this->insertTelemetry($realDevice, 'MQTT', $freshTimestamp, 9101);
        $this->insertTelemetry($realDevice, 'HTTP', $freshTimestamp, 9102);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 ON')
            ->assertSee('MQTT Connected')
            ->assertSee('HTTP Connected')
            ->assertDontSee('MQTT Filtered')
            ->assertDontSee('HTTP Filtered');
    }

    public function test_dashboard_treats_stale_simulator_fallback_as_disconnected_not_filtered(): void
    {
        $simDevice = $this->createDevice('SIMULATOR-APP');
        $staleTimestamp = now()->subSeconds(95);

        $this->insertSimulationStateFile(false, $simDevice->id);
        $this->insertTelemetry($simDevice, 'MQTT', $staleTimestamp, 9201);
        $this->insertTelemetry($simDevice, 'HTTP', $staleTimestamp, 9202);

        $this->get('/')
            ->assertOk()
            ->assertSee('ESP32 OFF')
            ->assertSee('MQTT Disconnected')
            ->assertSee('HTTP Disconnected')
            ->assertDontSee('MQTT Filtered')
            ->assertDontSee('HTTP Filtered');
    }

    private function createDevice(string $name): Device
    {
        return Device::query()->create([
            'nama_device' => $name,
            'lokasi' => 'Lab Test',
        ]);
    }

    private function insertTelemetry(Device $device, string $protocol, Carbon $timestampServer, int $packetSeq): void
    {
        $isHttp = strtoupper($protocol) === 'HTTP';

        Eksperimen::query()->create([
            'device_id' => $device->id,
            'protokol' => strtoupper($protocol),
            'suhu' => $isHttp ? 29.1 : 28.8,
            'kelembapan' => $isHttp ? 61.4 : 60.7,
            'timestamp_esp' => $timestampServer->copy()->subMilliseconds(120),
            'timestamp_server' => $timestampServer->copy(),
            'latency_ms' => $isHttp ? 430.0 : 140.0,
            'daya_mw' => $isHttp ? 980.4 : 812.7,
            'packet_seq' => $packetSeq,
            'rssi_dbm' => -58,
            'tx_duration_ms' => $isHttp ? 690.0 : 14.0,
            'payload_bytes' => $isHttp ? 420 : 388,
            'uptime_s' => 9000,
            'free_heap_bytes' => 235000,
            'sensor_age_ms' => 250,
            'sensor_read_seq' => $packetSeq + 11,
            'send_tick_ms' => ($packetSeq * 3) + 123,
        ]);
    }

    private function insertSimulationStateFile(bool $running, ?int $deviceId = null): void
    {
        $dir = dirname($this->simulationStatePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->simulationStatePath, json_encode([
            'running' => $running,
            'device_id' => $deviceId,
            'interval_seconds' => 5,
        ], JSON_PRETTY_PRINT));
    }

    private function writeHeartbeatFile(array $payload): void
    {
        $dir = dirname($this->heartbeatPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->heartbeatPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
