<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConfigPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_requires_login(): void
    {
        config([
            'admin.panel_token' => 'secret-admin-token',
            'admin.allow_without_token' => false,
        ]);

        $this->get('/admin/config')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_login_and_save_runtime_override(): void
    {
        config([
            'admin.panel_token' => 'secret-admin-token',
            'admin.allow_without_token' => false,
        ]);

        $this->post('/admin/login', [
            'token' => 'secret-admin-token',
        ])->assertRedirect('/admin/config');

        $this->post('/admin/config/runtime', [
            'APP_URL' => 'https://iot.lab.example.com',
            'MQTT_HOST' => 'mqtt.lab.example.com',
            'MQTT_PORT' => '1883',
            'HTTP_INGEST_KEY' => 'new-ingest-key',
            'RESET_ALLOW_WITHOUT_TOKEN' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'APP_URL',
            'setting_value' => 'https://iot.lab.example.com',
        ]);

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'MQTT_HOST',
            'setting_value' => 'mqtt.lab.example.com',
        ]);

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'RESET_ALLOW_WITHOUT_TOKEN',
            'setting_value' => '0',
        ]);
    }

    public function test_admin_firmware_download_contains_selected_device_id(): void
    {
        config([
            'admin.panel_token' => 'secret-admin-token',
            'admin.allow_without_token' => false,
        ]);

        $device = Device::query()->create([
            'nama_device' => 'ESP32-NEW',
            'lokasi' => 'Lab',
        ]);

        $this->post('/admin/login', [
            'token' => 'secret-admin-token',
        ])->assertRedirect('/admin/config');

        $response = $this->get('/admin/config/devices/' . $device->id . '/firmware/main.cpp')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $this->assertStringContainsString(
            'const int DEVICE_ID = ' . $device->id . ';',
            $response->streamedContent()
        );
    }
}
