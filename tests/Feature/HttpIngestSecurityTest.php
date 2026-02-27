<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HttpIngestSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_ingest_requires_valid_ingest_key_when_configured(): void
    {
        Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        config([
            'http_server.ingest_key' => 'top-secret-key',
            'http_server.allow_ingest_without_key' => false,
            'http_server.ingest_rate_limit_per_minute' => 600,
        ]);

        $payload = $this->validPayload(1);

        $this->postJson('/api/http-data', $payload)
            ->assertStatus(401);

        $this->withHeaders(['X-Ingest-Key' => 'wrong-key'])
            ->postJson('/api/http-data', $payload)
            ->assertStatus(401);

        $this->withHeaders(['X-Ingest-Key' => 'top-secret-key'])
            ->postJson('/api/http-data', $payload)
            ->assertStatus(201);
    }

    public function test_http_ingest_is_idempotent_for_same_packet_sequence(): void
    {
        Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        config([
            'http_server.ingest_key' => 'top-secret-key',
            'http_server.allow_ingest_without_key' => false,
            'http_server.ingest_rate_limit_per_minute' => 600,
        ]);

        $payload = $this->validPayload(7);

        $this->withHeaders(['X-Ingest-Key' => 'top-secret-key'])
            ->postJson('/api/http-data', $payload)
            ->assertStatus(201);

        $this->withHeaders(['X-Ingest-Key' => 'top-secret-key'])
            ->postJson('/api/http-data', $payload)
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data HTTP duplikat diperbarui (idempotent upsert)',
            ]);

        $this->assertDatabaseCount('eksperimens', 1);
    }

    public function test_http_ingest_internal_error_response_is_sanitized(): void
    {
        Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        config([
            'http_server.ingest_key' => 'top-secret-key',
            'http_server.allow_ingest_without_key' => false,
            'http_server.ingest_rate_limit_per_minute' => 600,
        ]);

        Schema::drop('eksperimens');

        $response = $this->withHeaders(['X-Ingest-Key' => 'top-secret-key'])
            ->postJson('/api/http-data', $this->validPayload(11));

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server saat menyimpan data HTTP.',
            ]);
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->json('message'));
    }

    private function validPayload(int $packetSeq): array
    {
        return [
            'device_id' => 1,
            'suhu' => 28.79999924,
            'kelembapan' => 61.0,
            'timestamp_esp' => time(),
            'daya' => 788.11,
            'packet_seq' => $packetSeq,
            'rssi_dbm' => -57,
            'tx_duration_ms' => 21.9,
            'payload_bytes' => 342,
            'uptime_s' => 6196,
            'free_heap_bytes' => 244236,
            'sensor_age_ms' => 1150,
            'sensor_read_seq' => 1225,
            'send_tick_ms' => 4177671,
        ];
    }
}
