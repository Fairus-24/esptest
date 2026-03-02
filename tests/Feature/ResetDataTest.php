<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Eksperimen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_requires_explicit_confirmation_without_token_field(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        Eksperimen::query()->create([
            'device_id' => $device->id,
            'protokol' => 'HTTP',
            'suhu' => 28.8,
            'kelembapan' => 61.1,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'latency_ms' => 90.4,
            'daya_mw' => 77.3,
            'packet_seq' => 2,
            'rssi_dbm' => -55,
            'tx_duration_ms' => 9.1,
            'payload_bytes' => 205,
            'uptime_s' => 444,
            'free_heap_bytes' => 221000,
            'sensor_age_ms' => 11,
            'sensor_read_seq' => 45,
            'send_tick_ms' => 6060,
        ]);

        $this->from('/reset-data')->post('/reset-data', [
            'confirm_text' => 'RESET',
        ])->assertRedirect('/reset-data')
            ->assertSessionHasErrors(['confirm_risk']);

        $this->from('/reset-data')->post('/reset-data', [
            'confirm_risk' => 'on',
            'confirm_text' => 'NOPE',
        ])->assertRedirect('/reset-data')
            ->assertSessionHasErrors(['confirm_text']);

        $this->post('/reset-data', [
            'confirm_risk' => 'on',
            'confirm_text' => 'RESET',
        ])->assertOk();

        $this->assertDatabaseCount('eksperimens', 0);
    }

    public function test_reset_requires_explicit_server_side_confirmation(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        Eksperimen::query()->create([
            'device_id' => $device->id,
            'protokol' => 'HTTP',
            'suhu' => 28.7,
            'kelembapan' => 60.2,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'latency_ms' => 120.2,
            'daya_mw' => 88.4,
            'packet_seq' => 1,
            'rssi_dbm' => -58,
            'tx_duration_ms' => 23.2,
            'payload_bytes' => 212,
            'uptime_s' => 333,
            'free_heap_bytes' => 220000,
            'sensor_age_ms' => 10,
            'sensor_read_seq' => 44,
            'send_tick_ms' => 5050,
        ]);

        $response = $this->from('/reset-data')->post('/reset-data', [
            'confirm_risk' => 'on',
            'confirm_text' => 'NOPE',
        ]);

        $response->assertRedirect('/reset-data');
        $response->assertSessionHasErrors(['confirm_text']);
        $this->assertDatabaseCount('eksperimens', 1);
    }

    public function test_reset_deletes_all_rows_when_confirmation_is_valid(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        Eksperimen::query()->create([
            'device_id' => $device->id,
            'protokol' => 'MQTT',
            'suhu' => 28.8,
            'kelembapan' => 61.1,
            'timestamp_esp' => now()->subSecond(),
            'timestamp_server' => now(),
            'latency_ms' => 90.4,
            'daya_mw' => 77.3,
            'packet_seq' => 2,
            'rssi_dbm' => -55,
            'tx_duration_ms' => 9.1,
            'payload_bytes' => 205,
            'uptime_s' => 444,
            'free_heap_bytes' => 221000,
            'sensor_age_ms' => 11,
            'sensor_read_seq' => 45,
            'send_tick_ms' => 6060,
        ]);

        $response = $this->post('/reset-data', [
            'confirm_risk' => 'on',
            'confirm_text' => 'reset',
        ]);

        $response->assertOk();
        $response->assertSee('berhasil direset', false);
        $this->assertDatabaseCount('eksperimens', 0);
    }
}
