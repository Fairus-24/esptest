<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Eksperimen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class AdminConfigPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.google_allowed_email' => 'mufaza2408@gmail.com',
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'https://espdht.mufaza.my.id/admin/login/api/auth/google/callback',
        ]);
    }

    public function test_admin_panel_requires_login(): void
    {

        $this->get('/admin/config')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_google_callback_rejects_unlisted_email(): void
    {
        $this->mockGoogleCallback('intruder@example.com');

        $this->get('/admin/login/api/auth/google/callback')
            ->assertRedirect('/admin/login')
            ->assertSessionHas('admin_error');
    }

    public function test_admin_can_login_and_save_runtime_override(): void
    {
        $this->mockGoogleCallback('mufaza2408@gmail.com');

        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $this->post('/admin/config/runtime', [
            'APP_URL' => 'https://iot.lab.example.com',
            'MQTT_HOST' => 'mqtt.lab.example.com',
            'MQTT_PORT' => '1883',
            'HTTP_INGEST_KEY' => 'new-ingest-key',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'APP_URL',
            'setting_value' => 'https://iot.lab.example.com',
        ]);

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'MQTT_HOST',
            'setting_value' => 'mqtt.lab.example.com',
        ]);

    }

    public function test_admin_firmware_download_contains_selected_device_id(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-NEW',
            'lokasi' => 'Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $response = $this->get('/admin/config/devices/' . $device->id . '/firmware/main.cpp')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $this->assertStringContainsString(
            'const int DEVICE_ID = ' . $device->id . ';',
            $response->streamedContent()
        );
    }

    public function test_admin_can_update_and_delete_device_with_purge_option(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-LAB-A',
            'lokasi' => 'Room A',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $this->patch('/admin/config/devices/' . $device->id, [
            'nama_device' => 'ESP32-LAB-A-UPDATED',
            'lokasi' => 'Room B',
        ])->assertRedirect('/admin/config?device_id=' . $device->id);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'nama_device' => 'ESP32-LAB-A-UPDATED',
            'lokasi' => 'Room B',
        ]);

        Eksperimen::query()->create([
            'device_id' => $device->id,
            'protokol' => 'HTTP',
            'suhu' => 28.4,
            'kelembapan' => 62.1,
            'timestamp_esp' => now(),
            'latency_ms' => 120,
            'daya_mw' => 930,
            'packet_seq' => 10001,
        ]);

        $this->delete('/admin/config/devices/' . $device->id, [
            'confirm_delete' => 'DELETE',
        ])->assertSessionHasErrors('device_delete');

        $this->assertDatabaseHas('devices', ['id' => $device->id]);
        $this->assertDatabaseHas('eksperimens', ['device_id' => $device->id]);

        $this->delete('/admin/config/devices/' . $device->id, [
            'confirm_delete' => 'DELETE',
            'purge_experiments' => '1',
        ])->assertRedirect('/admin/config');

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
        $this->assertDatabaseMissing('eksperimens', ['device_id' => $device->id]);
    }

    public function test_admin_platformio_download_contains_runtime_and_profile_network_flags(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-PROD',
            'lokasi' => 'Production Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $this->post('/admin/config/runtime', [
            'HTTP_INGEST_KEY' => 'runtime-ingest-key',
        ])->assertRedirect();

        $this->post('/admin/config/devices/' . $device->id . '/profile', [
            'board' => 'esp32doit-devkit-v1',
            'wifi_ssid' => 'LAB-WIFI',
            'wifi_password' => 'password-lab',
            'server_host' => '',
            'http_base_url' => 'https://espdht.mufaza.my.id',
            'http_endpoint' => '/api/http-data',
            'mqtt_broker' => '202.154.58.51',
            'mqtt_host' => '',
            'mqtt_port' => 1883,
            'mqtt_topic' => 'iot/esp32/suhu',
            'mqtt_user' => 'esp32',
            'mqtt_password' => 'esp32',
            'http_tls_insecure' => '0',
            'dht_pin' => 4,
            'dht_model' => 'DHT11',
            'extra_build_flags' => '-DESP_SENSOR_INTERVAL_MS=10000UL',
        ])->assertRedirect('/admin/config?device_id=' . $device->id);

        $platformioResponse = $this->get('/admin/config/devices/' . $device->id . '/firmware/platformio.ini')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $platformioContent = $platformioResponse->streamedContent();
        $this->assertStringContainsString('-DESP_HTTP_INGEST_KEY=\\"runtime-ingest-key\\"', $platformioContent);
        $this->assertStringContainsString('-DESP_HTTP_BASE_URL=\\"https://espdht.mufaza.my.id\\"', $platformioContent);
        $this->assertStringContainsString('-DESP_MQTT_BROKER=\\"202.154.58.51\\"', $platformioContent);
        $this->assertStringContainsString('-DESP_HTTP_TLS_INSECURE=0', $platformioContent);
    }

    private function mockGoogleCallback(string $email): void
    {
        $provider = Mockery::mock();
        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn('Admin Google');

        $provider->shouldReceive('user')->once()->andReturn($socialUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
