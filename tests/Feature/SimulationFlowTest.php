<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Eksperimen;
use App\Services\ApplicationSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_page_renders_controls(): void
    {
        $this->get('/simulation')
            ->assertOk()
            ->assertSee('Network Profile')
            ->assertSee('Start Simulasi');
    }

    public function test_simulation_start_tick_and_stop_flow(): void
    {
        $this->post('/simulation/start', [
            'interval_seconds' => 1,
            'http_fail_rate' => 0,
            'mqtt_fail_rate' => 0,
            'network_profile' => 'stress',
            'reset_before_start' => true,
        ])->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.network_profile', 'stress');

        $this->post('/simulation/tick')->assertOk()->assertJson([
            'success' => true,
        ]);

        $this->get('/simulation/status')
            ->assertOk()
            ->assertJsonPath('data.network_profile', 'stress')
            ->assertJsonPath('data.running', true);

        $simDeviceId = Device::query()->where('nama_device', 'SIMULATOR-APP')->value('id');
        $this->assertNotNull($simDeviceId);

        $this->assertGreaterThanOrEqual(
            1,
            Eksperimen::query()->where('device_id', $simDeviceId)->where('protokol', 'HTTP')->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            Eksperimen::query()->where('device_id', $simDeviceId)->where('protokol', 'MQTT')->count()
        );

        $this->post('/simulation/stop')
            ->assertOk()
            ->assertJsonPath('data.running', false);
    }

    public function test_simulation_tick_runs_after_interval_elapsed(): void
    {
        $this->post('/simulation/start', [
            'interval_seconds' => 1,
            'http_fail_rate' => 0,
            'mqtt_fail_rate' => 0,
            'network_profile' => 'normal',
            'reset_before_start' => true,
        ])->assertOk();

        $beforeTickCount = app(ApplicationSimulationService::class)->status()['tick_count'] ?? 0;
        $ran = false;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            sleep(1);
            $response = $this->post('/simulation/tick')->assertOk();
            $ran = (bool) ($response->json('data.ran') ?? false);
            if ($ran) {
                break;
            }
        }

        $this->assertTrue($ran);

        $afterTickCount = app(ApplicationSimulationService::class)->status()['tick_count'] ?? 0;
        $this->assertGreaterThan($beforeTickCount, $afterTickCount);
    }

    public function test_simulation_reset_only_clears_simulator_device_rows(): void
    {
        $realDevice = Device::query()->create([
            'nama_device' => 'ESP32-REAL',
            'lokasi' => 'Lab',
        ]);

        Eksperimen::query()->create([
            'device_id' => $realDevice->id,
            'protokol' => 'HTTP',
            'suhu' => 28.8,
            'kelembapan' => 60.1,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'latency_ms' => 120.0,
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
        ]);

        $this->post('/simulation/start', [
            'interval_seconds' => 1,
            'http_fail_rate' => 0,
            'mqtt_fail_rate' => 0,
            'reset_before_start' => true,
        ])->assertOk();

        $this->post('/simulation/tick')->assertOk();
        $simDeviceId = Device::query()->where('nama_device', 'SIMULATOR-APP')->value('id');
        $this->assertNotNull($simDeviceId);

        $this->post('/simulation/reset')
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('eksperimens', [
            'device_id' => $realDevice->id,
            'protokol' => 'HTTP',
            'packet_seq' => 1,
        ]);
        $this->assertDatabaseMissing('eksperimens', [
            'device_id' => $simDeviceId,
            'protokol' => 'HTTP',
        ]);
        $this->assertDatabaseMissing('eksperimens', [
            'device_id' => $simDeviceId,
            'protokol' => 'MQTT',
        ]);
    }

    public function test_simulation_reset_ignores_stale_non_simulator_state_device_id(): void
    {
        $realDevice = Device::query()->create([
            'nama_device' => 'ESP32-REAL-STATE',
            'lokasi' => 'Lab',
        ]);
        $simDevice = Device::query()->create([
            'nama_device' => 'SIMULATOR-APP',
            'lokasi' => 'Virtual Lab',
        ]);

        Eksperimen::query()->create([
            'device_id' => $realDevice->id,
            'protokol' => 'HTTP',
            'suhu' => 29.1,
            'kelembapan' => 61.2,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'latency_ms' => 125.0,
            'daya_mw' => 79.4,
            'packet_seq' => 11,
            'rssi_dbm' => -57,
            'tx_duration_ms' => 210.0,
            'payload_bytes' => 210,
            'uptime_s' => 500,
            'free_heap_bytes' => 219500,
            'sensor_age_ms' => 13,
            'sensor_read_seq' => 51,
            'send_tick_ms' => 7070,
        ]);
        Eksperimen::query()->create([
            'device_id' => $simDevice->id,
            'protokol' => 'MQTT',
            'suhu' => 27.9,
            'kelembapan' => 58.6,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'latency_ms' => 95.0,
            'daya_mw' => 74.8,
            'packet_seq' => 22,
            'rssi_dbm' => -54,
            'tx_duration_ms' => 18.0,
            'payload_bytes' => 188,
            'uptime_s' => 505,
            'free_heap_bytes' => 220100,
            'sensor_age_ms' => 9,
            'sensor_read_seq' => 52,
            'send_tick_ms' => 7110,
        ]);

        $statePath = storage_path('app/simulation_state.json');
        if (!is_dir(dirname($statePath))) {
            mkdir(dirname($statePath), 0775, true);
        }
        file_put_contents($statePath, json_encode([
            'running' => false,
            'device_id' => $realDevice->id,
            'interval_seconds' => 5,
            'http_fail_rate' => 0.08,
            'mqtt_fail_rate' => 0.12,
        ], JSON_PRETTY_PRINT));

        $this->post('/simulation/reset')
            ->assertOk()
            ->assertJsonPath('data.device_id', $simDevice->id);

        $this->assertDatabaseHas('eksperimens', [
            'device_id' => $realDevice->id,
            'protokol' => 'HTTP',
            'packet_seq' => 11,
        ]);
        $this->assertDatabaseMissing('eksperimens', [
            'device_id' => $simDevice->id,
            'protokol' => 'MQTT',
            'packet_seq' => 22,
        ]);
    }
}
