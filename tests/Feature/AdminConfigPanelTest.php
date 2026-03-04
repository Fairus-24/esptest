<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceFirmwareProfile;
use App\Models\Eksperimen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;
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

    public function test_admin_firmware_action_buttons_are_disabled_when_workspace_is_in_sync(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-SYNC',
            'lokasi' => 'Sync Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $mainPath = base_path('ESP32_Firmware/src/main.cpp');
        $iniPath = base_path('ESP32_Firmware/platformio.ini');
        $mainBackup = File::exists($mainPath) ? (string) File::get($mainPath) : null;
        $iniBackup = File::exists($iniPath) ? (string) File::get($iniPath) : null;

        try {
            $generatedMain = $this->get('/admin/config/devices/' . $device->id . '/firmware/main.cpp')
                ->assertOk()
                ->streamedContent();
            $generatedIni = $this->get('/admin/config/devices/' . $device->id . '/firmware/platformio.ini')
                ->assertOk()
                ->streamedContent();

            File::put($mainPath, $generatedMain);
            File::put($iniPath, $generatedIni);

            $html = $this->get('/admin/config?device_id=' . $device->id)
                ->assertOk()
                ->getContent();

            $this->assertIsString($html);
            $this->assertStringContainsString('data-workspace-sync="1"', $html);
            $this->assertMatchesRegularExpression('/id="firmware-profile-save-btn"[^>]*disabled/', $html);
            $this->assertMatchesRegularExpression('/id="firmware-apply-btn"[^>]*disabled/', $html);
        } finally {
            if ($mainBackup !== null) {
                File::put($mainPath, $mainBackup);
            }
            if ($iniBackup !== null) {
                File::put($iniPath, $iniBackup);
            }
        }
    }

    public function test_admin_runtime_device_actions_are_guarded_until_input_changes_or_requirements_met(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-GUARD',
            'lokasi' => 'Guard Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $html = $this->get('/admin/config?device_id=' . $device->id)
            ->assertOk()
            ->getContent();

        $this->assertIsString($html);
        $this->assertMatchesRegularExpression('/id="quick-runtime-save-btn"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/id="update-device-btn"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/id="delete-device-btn"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/<button class="btn" type="button" disabled>Selected<\/button>/', $html);
    }

    public function test_admin_page_exposes_web_serial_monitor_controls(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-SERIAL',
            'lokasi' => 'Serial Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $response = $this->get('/admin/config?device_id=' . $device->id)
            ->assertOk()
            ->assertSee('Start Serial Monitor')
            ->assertSee('Serial Baud')
            ->assertSee('Serial Monitor: idle')
            ->assertSee('id="serial-monitor-baud"', false)
            ->assertSee('<option value="9600"', false)
            ->assertSee('<option value="115200"', false)
            ->assertSee('<option value="921600"', false);

        $this->assertStringNotContainsString(
            '<input id="serial-monitor-baud" type="number"',
            (string) $response->getContent()
        );
    }

    public function test_admin_profile_extra_build_flags_cannot_override_selected_device_id(): void
    {
        Device::query()->create([
            'nama_device' => 'ESP32-BASE',
            'lokasi' => 'Lab 0',
        ]);
        $device = Device::query()->create([
            'nama_device' => 'ESP32-TARGET',
            'lokasi' => 'Lab 1',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $this->post('/admin/config/devices/' . $device->id . '/profile', [
            'board' => 'esp32doit-devkit-v1',
            'wifi_ssid' => 'LAB-WIFI',
            'wifi_password' => 'password-lab',
            'server_host' => '192.168.1.100',
            'http_base_url' => 'https://iot.example.com',
            'http_endpoint' => '/api/http-data',
            'mqtt_broker' => 'mqtt.example.com',
            'mqtt_host' => 'mqtt.example.com',
            'mqtt_port' => 1883,
            'mqtt_topic' => 'iot/esp32/suhu',
            'mqtt_user' => 'esp32',
            'mqtt_password' => 'esp32',
            'http_tls_insecure' => '1',
            'http_read_timeout_ms' => 5000,
            'dht_pin' => 4,
            'dht_model' => 'DHT11',
            'sensor_interval_ms' => 5000,
            'http_interval_ms' => 10000,
            'mqtt_interval_ms' => 10000,
            'dht_min_read_interval_ms' => 1500,
            'core_debug_level' => 0,
            'mqtt_max_packet_size' => 2048,
            'monitor_speed' => 115200,
            'monitor_port' => '',
            'upload_port' => '',
            'extra_build_flags' => "-DESP_DEVICE_ID=1\n-DESP_CUSTOM_SAMPLE=1",
        ])->assertRedirect('/admin/config?device_id=' . $device->id)
            ->assertSessionHas('admin_status');

        $profile = DeviceFirmwareProfile::query()->where('device_id', $device->id)->firstOrFail();
        $this->assertStringNotContainsString('ESP_DEVICE_ID', (string) $profile->extra_build_flags);
        $this->assertStringContainsString('-DESP_CUSTOM_SAMPLE=1', (string) $profile->extra_build_flags);

        $mainResponse = $this->get('/admin/config/devices/' . $device->id . '/firmware/main.cpp')->assertOk();
        $this->assertStringContainsString(
            'const int DEVICE_ID = ' . $device->id . ';',
            $mainResponse->streamedContent()
        );

        $iniResponse = $this->get('/admin/config/devices/' . $device->id . '/firmware/platformio.ini')->assertOk();
        $iniContent = $iniResponse->streamedContent();
        $this->assertStringContainsString('-DESP_DEVICE_ID=' . $device->id, $iniContent);
        $this->assertStringNotContainsString('-DESP_DEVICE_ID=1', $iniContent);
        $this->assertStringContainsString('-DESP_CUSTOM_SAMPLE=1', $iniContent);
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
        $this->assertStringContainsString('lib_archive = false', $platformioContent);
        $this->assertStringContainsString('extra_scripts = pre:scripts/pio_env_fix.py', $platformioContent);
        $this->assertStringNotContainsString('DHT sensor library for ESPx', $platformioContent);
        $this->assertStringNotContainsString('ArduinoJson@6.21.3adafruit/DHT sensor library', $platformioContent);
        $this->assertStringContainsString('-DESP_HTTP_INGEST_KEY=\\"runtime-ingest-key\\"', $platformioContent);
        $this->assertStringContainsString('-DESP_HTTP_BASE_URL=\\"https://espdht.mufaza.my.id\\"', $platformioContent);
        $this->assertStringContainsString('-DESP_MQTT_BROKER=\\"202.154.58.51\\"', $platformioContent);
        $this->assertStringContainsString('-DESP_HTTP_TLS_INSECURE=0', $platformioContent);
    }

    public function test_admin_firmware_profile_advanced_tuning_is_reflected_in_generated_sources(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-TUNE',
            'lokasi' => 'Advanced Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $this->post('/admin/config/devices/' . $device->id . '/profile', [
            'board' => 'esp32doit-devkit-v1',
            'wifi_ssid' => 'LAB-WIFI-ADV',
            'wifi_password' => 'lab-pass-adv',
            'server_host' => '192.168.1.111',
            'http_base_url' => 'https://iot.example.com',
            'http_endpoint' => '/api/http-data',
            'mqtt_broker' => 'mqtt.example.com',
            'mqtt_host' => 'mqtt.example.com',
            'mqtt_port' => 1884,
            'mqtt_topic' => 'iot/esp32/advanced',
            'mqtt_user' => 'adv-user',
            'mqtt_password' => 'adv-pass',
            'http_tls_insecure' => '0',
            'http_read_timeout_ms' => 7000,
            'dht_pin' => 5,
            'dht_model' => 'AM2302',
            'sensor_interval_ms' => 12000,
            'http_interval_ms' => 17000,
            'mqtt_interval_ms' => 15000,
            'dht_min_read_interval_ms' => 2500,
            'core_debug_level' => 3,
            'mqtt_max_packet_size' => 4096,
            'monitor_speed' => 230400,
            'monitor_port' => 'COM7',
            'upload_port' => 'COM8',
            'extra_build_flags' => '-DESP_EXTRA_SAMPLE=1',
        ])->assertRedirect('/admin/config?device_id=' . $device->id);

        $mainResponse = $this->get('/admin/config/devices/' . $device->id . '/firmware/main.cpp')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $mainContent = $mainResponse->streamedContent();
        $this->assertStringContainsString('#define DHTTYPE DHT22', $mainContent);
        $this->assertStringContainsString('const unsigned long INTERVAL_SENSOR = 12000UL;', $mainContent);
        $this->assertStringContainsString('const unsigned long INTERVAL_HTTP = 17000UL;', $mainContent);
        $this->assertStringContainsString('const unsigned long INTERVAL_MQTT = 15000UL;', $mainContent);
        $this->assertStringContainsString('const unsigned long DHT_MIN_READ_INTERVAL_MS = 2500UL;', $mainContent);

        $platformioResponse = $this->get('/admin/config/devices/' . $device->id . '/firmware/platformio.ini')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $platformioContent = $platformioResponse->streamedContent();
        $this->assertStringContainsString('monitor_speed = 230400', $platformioContent);
        $this->assertStringContainsString('monitor_port = COM7', $platformioContent);
        $this->assertStringContainsString('upload_port = COM8', $platformioContent);
        $this->assertStringContainsString('-DCORE_DEBUG_LEVEL=3', $platformioContent);
        $this->assertStringContainsString('-DMQTT_MAX_PACKET_SIZE=4096', $platformioContent);
        $this->assertStringContainsString('-DESP_HTTP_READ_TIMEOUT_MS=7000', $platformioContent);
        $this->assertStringContainsString('-DESP_SENSOR_INTERVAL_MS=12000UL', $platformioContent);
        $this->assertStringContainsString('-DESP_HTTP_INTERVAL_MS=17000UL', $platformioContent);
        $this->assertStringContainsString('-DESP_MQTT_INTERVAL_MS=15000UL', $platformioContent);
        $this->assertStringContainsString('-DESP_DHT_MIN_READ_INTERVAL_MS=2500UL', $platformioContent);
    }

    public function test_admin_mqtt_broker_change_updates_effective_generated_mqtt_server(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-MQTT-SWITCH',
            'lokasi' => 'Broker Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $payload = [
            'board' => 'esp32doit-devkit-v1',
            'wifi_ssid' => 'LAB-WIFI',
            'wifi_password' => 'password-lab',
            'server_host' => '192.168.1.120',
            'http_base_url' => 'https://iot.example.com',
            'http_endpoint' => '/api/http-data',
            'mqtt_broker' => 'mqtt-old.example.com',
            'mqtt_host' => 'mqtt-old.example.com',
            'mqtt_port' => 1883,
            'mqtt_topic' => 'iot/esp32/suhu',
            'mqtt_user' => 'esp32',
            'mqtt_password' => 'esp32',
            'http_tls_insecure' => '1',
            'dht_pin' => 4,
            'dht_model' => 'DHT11',
        ];

        $this->post('/admin/config/devices/' . $device->id . '/profile', $payload)
            ->assertRedirect('/admin/config?device_id=' . $device->id);

        $payload['mqtt_broker'] = 'mqtt-new.example.com';
        // Simulate unchanged legacy host input from the page before this fix.
        $payload['mqtt_host'] = 'mqtt-old.example.com';

        $this->post('/admin/config/devices/' . $device->id . '/profile', $payload)
            ->assertRedirect('/admin/config?device_id=' . $device->id);

        $profile = DeviceFirmwareProfile::query()->where('device_id', $device->id)->firstOrFail();
        $this->assertSame('mqtt-new.example.com', $profile->mqtt_broker);
        $this->assertSame('mqtt-new.example.com', $profile->mqtt_host);

        $mainContent = $this->get('/admin/config/devices/' . $device->id . '/firmware/main.cpp')
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('const char* MQTT_SERVER = "mqtt-new.example.com";', $mainContent);

        $platformioContent = $this->get('/admin/config/devices/' . $device->id . '/firmware/platformio.ini')
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('-DESP_MQTT_BROKER=\\"mqtt-new.example.com\\"', $platformioContent);
    }

    public function test_admin_can_trigger_firmware_build_from_panel(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-BUILD',
            'lokasi' => 'Build Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        Process::fake([
            '*' => Process::result('BUILD OK', '', 0),
        ]);

        $this->post('/admin/config/devices/' . $device->id . '/firmware/build')
            ->assertRedirect('/admin/config?device_id=' . $device->id)
            ->assertSessionHas('admin_status')
            ->assertSessionHas('firmware_cli_result');

        Process::assertRan(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : (string) $process->command;

            return str_contains($command, 'run')
                && !str_contains($command, '-t upload');
        });
    }

    public function test_admin_build_uses_fallback_when_primary_platformio_command_is_missing(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-BUILD-FALLBACK',
            'lokasi' => 'Build Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        config([
            'admin.platformio_command' => 'missing-platformio-cli',
        ]);

        Process::fake(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : (string) $process->command;

            if (str_contains($command, 'missing-platformio-cli')) {
                return Process::result('', '/bin/sh: 1: missing-platformio-cli: not found', 127);
            }

            return Process::result('BUILD OK FROM FALLBACK', '', 0);
        });

        $this->post('/admin/config/devices/' . $device->id . '/firmware/build')
            ->assertRedirect('/admin/config?device_id=' . $device->id)
            ->assertSessionHas('admin_status')
            ->assertSessionHas('firmware_cli_result', function ($value) {
                if (!is_array($value)) {
                    return false;
                }

                return (bool) ($value['ok'] ?? false)
                    && str_contains((string) ($value['output'] ?? ''), 'PlatformIO fallback activated')
                    && !str_contains((string) ($value['command'] ?? ''), 'missing-platformio-cli');
            });

        Process::assertRan(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : (string) $process->command;

            return str_contains($command, 'missing-platformio-cli');
        });
    }

    public function test_admin_upload_firmware_failure_is_reported_in_session(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-UPLOAD',
            'lokasi' => 'Upload Lab',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        Process::fake([
            '*' => Process::result('', 'UPLOAD FAILED', 1),
        ]);

        $this->post('/admin/config/devices/' . $device->id . '/firmware/upload')
            ->assertRedirect('/admin/config?device_id=' . $device->id)
            ->assertSessionHasErrors('firmware_cli')
            ->assertSessionHas('firmware_cli_result');

        Process::assertRan(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : (string) $process->command;

            return str_contains($command, '-t upload');
        });
    }

    public function test_admin_prepare_webflash_returns_artifacts_manifest(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-WEBFLASH',
            'lokasi' => 'Remote Browser',
        ]);

        config(['admin.platformio_env' => 'webflash-test']);
        $buildDir = base_path('ESP32_Firmware/.pio/build/webflash-test');
        File::ensureDirectoryExists($buildDir);
        file_put_contents($buildDir . '/bootloader.bin', str_repeat('A', 64));
        file_put_contents($buildDir . '/partitions.bin', str_repeat('B', 64));
        file_put_contents($buildDir . '/firmware.bin', str_repeat('C', 128));

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        Process::fake([
            '*' => Process::result('WEBFLASH BUILD OK', '', 0),
        ]);

        $this->postJson('/admin/config/devices/' . $device->id . '/firmware/webflash/prepare')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'message',
                'device_id',
                'build' => ['ok', 'command', 'exit_code', 'output'],
                'environment',
                'images' => [
                    ['name', 'address', 'size', 'url'],
                ],
            ]);
    }

    public function test_admin_prepare_webflash_reports_git_conflict_marker_in_project_files(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-WEBFLASH-CONFLICT',
            'lokasi' => 'Remote Browser',
        ]);

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $iniPath = base_path('ESP32_Firmware/platformio.ini');
        $iniBackup = File::exists($iniPath) ? (string) File::get($iniPath) : null;

        try {
            File::put($iniPath, implode("\n", [
                '[env:esp32doit-devkit-v1]',
                'platform = espressif32',
                '<<<<<<< Updated upstream',
                'board = esp32doit-devkit-v1',
                '=======',
                'board = esp32-s3-devkitc-1',
                '>>>>>>> Stashed changes',
            ]) . "\n");

            Process::fake();

            $this->postJson('/admin/config/devices/' . $device->id . '/firmware/webflash/prepare')
                ->assertStatus(422)
                ->assertJsonPath('ok', false)
                ->assertJsonPath('build.exit_code', 2)
                ->assertJsonPath('build.command', 'validation')
                ->assertJsonPath('message', 'Build firmware gagal. Cek output build pada response.')
                ->assertJsonPath('build.output', function ($value): bool {
                    if (!is_string($value)) {
                        return false;
                    }

                    return str_contains($value, 'Git conflict marker terdeteksi')
                        && str_contains($value, 'platformio.ini')
                        && str_contains($value, 'Lines:');
                });

            Process::assertNothingRan();
        } finally {
            if ($iniBackup !== null) {
                File::put($iniPath, $iniBackup);
            }
        }
    }

    public function test_admin_webflash_artifact_download_returns_binary_file(): void
    {
        $device = Device::query()->create([
            'nama_device' => 'ESP32-ARTIFACT',
            'lokasi' => 'Binary Download',
        ]);

        config(['admin.platformio_env' => 'webflash-download-test']);
        $buildDir = base_path('ESP32_Firmware/.pio/build/webflash-download-test');
        File::ensureDirectoryExists($buildDir);
        file_put_contents($buildDir . '/firmware.bin', 'FIRMWARE-BINARY-DATA');

        $this->mockGoogleCallback('mufaza2408@gmail.com');
        $this->get('/admin/login/api/auth/google/callback')->assertRedirect('/admin/config');

        $this->get('/admin/config/devices/' . $device->id . '/firmware/webflash/firmware.bin')
            ->assertOk()
            ->assertHeader('content-type', 'application/octet-stream');
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
