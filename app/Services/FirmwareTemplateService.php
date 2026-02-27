<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceFirmwareProfile;
use Illuminate\Support\Str;

class FirmwareTemplateService
{
    public function ensureProfile(Device $device): DeviceFirmwareProfile
    {
        return DeviceFirmwareProfile::query()->firstOrCreate(
            ['device_id' => $device->id],
            [
                'board' => 'esp32doit-devkit-v1',
                'wifi_ssid' => 'Free',
                'wifi_password' => 'gratiskok',
                'server_host' => '192.168.0.104',
                'http_endpoint' => '/esptest/public/api/http-data',
                'mqtt_host' => '192.168.0.104',
                'mqtt_port' => 1883,
                'mqtt_topic' => 'iot/esp32/suhu',
                'mqtt_user' => 'esp32',
                'mqtt_password' => 'esp32',
                'dht_pin' => 4,
                'dht_model' => 'DHT11',
            ]
        );
    }

    /**
     * @param array<string, array<string, mixed>> $runtimeState
     * @return array<string, string>
     */
    public function render(Device $device, DeviceFirmwareProfile $profile, array $runtimeState): array
    {
        $mainTemplate = file_get_contents(base_path('ESP32_Firmware/src/main.cpp')) ?: '';
        $iniTemplate = file_get_contents(base_path('ESP32_Firmware/platformio.ini')) ?: '';

        $httpIngestKey = (string) ($runtimeState['HTTP_INGEST_KEY']['current_value'] ?? config('http_server.ingest_key', ''));

        $mainRendered = $mainTemplate;
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* WIFI_SSID = ".*?";/', 'const char* WIFI_SSID = "' . $this->escapeCpp((string) $profile->wifi_ssid) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* WIFI_PASSWORD = ".*?";/', 'const char* WIFI_PASSWORD = "' . $this->escapeCpp((string) $profile->wifi_password) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/#define SERVER_HOST ".*?"/', '#define SERVER_HOST "' . $this->escapeCpp((string) $profile->server_host) . '"');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* HTTP_ENDPOINT = ".*?";/', 'const char* HTTP_ENDPOINT = "' . $this->escapeCpp((string) $profile->http_endpoint) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* MQTT_SERVER = .*?;/', 'const char* MQTT_SERVER = "' . $this->escapeCpp((string) $profile->mqtt_host) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const int MQTT_PORT = \d+;/', 'const int MQTT_PORT = ' . max(1, (int) $profile->mqtt_port) . ';');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* MQTT_TOPIC = ".*?";/', 'const char* MQTT_TOPIC = "' . $this->escapeCpp((string) $profile->mqtt_topic) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* MQTT_USER = ".*?";/', 'const char* MQTT_USER = "' . $this->escapeCpp((string) $profile->mqtt_user) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* MQTT_PASSWORD = ".*?";/', 'const char* MQTT_PASSWORD = "' . $this->escapeCpp((string) $profile->mqtt_password) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const int DEVICE_ID = \d+;/', 'const int DEVICE_ID = ' . (int) $device->id . ';');
        $mainRendered = $this->replaceOne($mainRendered, '/#define DHTPIN \d+/', '#define DHTPIN ' . max(0, (int) $profile->dht_pin));
        $mainRendered = $this->replaceOne(
            $mainRendered,
            '/const DHTesp::DHT_MODEL_t DHT_MODEL_PREFERRED = DHTesp::[A-Z0-9_]+;/',
            'const DHTesp::DHT_MODEL_t DHT_MODEL_PREFERRED = DHTesp::' . $this->normalizeDhtModel((string) $profile->dht_model) . ';'
        );

        $iniRendered = $iniTemplate;
        $iniRendered = $this->replaceOne($iniRendered, '/^board\s*=\s*.+$/m', 'board = ' . trim((string) $profile->board));

        $escapedIngestKey = $this->escapePlatformio($httpIngestKey);
        if (preg_match('/-DESP_HTTP_INGEST_KEY=\\\\\".*?\\\\\"/m', $iniRendered) === 1) {
            $iniRendered = preg_replace(
                '/-DESP_HTTP_INGEST_KEY=\\\\\".*?\\\\\"/m',
                '-DESP_HTTP_INGEST_KEY=\\"' . $escapedIngestKey . '\\"',
                $iniRendered,
                1
            ) ?: $iniRendered;
        } elseif (preg_match('/^build_flags\s*=\s*$/m', $iniRendered) === 1) {
            $iniRendered = preg_replace(
                '/^build_flags\s*=\s*$/m',
                "build_flags =\n    -DESP_HTTP_INGEST_KEY=\\\"" . $escapedIngestKey . "\\\"",
                $iniRendered,
                1
            ) ?: $iniRendered;
        }

        $extraFlags = trim((string) $profile->extra_build_flags);
        if ($extraFlags !== '') {
            $extraLines = collect(preg_split('/\r\n|\r|\n/', $extraFlags) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter(fn (string $line): bool => $line !== '')
                ->map(fn (string $line): string => '    ' . $line)
                ->implode("\n");

            if ($extraLines !== '') {
                $iniRendered .= "\n" . $extraLines . "\n";
            }
        }

        return [
            'main_cpp' => $mainRendered,
            'platformio_ini' => $iniRendered,
            'instructions' => implode(PHP_EOL, [
                '1) Simpan generated `main.cpp` ke: ESP32_Firmware/src/main.cpp',
                '2) Simpan generated `platformio.ini` ke: ESP32_Firmware/platformio.ini',
                '3) Upload firmware:',
                '   cd ESP32_Firmware',
                '   pio run -t upload',
                '4) Monitor serial:',
                '   pio device monitor',
            ]),
        ];
    }

    /**
     * @param array<string, string> $rendered
     * @return array<string, string>
     */
    public function applyToWorkspace(Device $device, array $rendered): array
    {
        $timestamp = now()->format('Ymd_His');
        $safeDevice = Str::slug($device->nama_device ?: ('device-' . $device->id));
        $backupDir = storage_path('app/firmware_backups/' . $timestamp . '_' . $safeDevice);
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }

        $mainPath = base_path('ESP32_Firmware/src/main.cpp');
        $iniPath = base_path('ESP32_Firmware/platformio.ini');

        @copy($mainPath, $backupDir . '/main.cpp.bak');
        @copy($iniPath, $backupDir . '/platformio.ini.bak');

        file_put_contents($mainPath, (string) ($rendered['main_cpp'] ?? ''));
        file_put_contents($iniPath, (string) ($rendered['platformio_ini'] ?? ''));

        return [
            'backup_dir' => $backupDir,
            'main_path' => $mainPath,
            'platformio_path' => $iniPath,
        ];
    }

    private function replaceOne(string $content, string $pattern, string $replacement): string
    {
        return preg_replace($pattern, $replacement, $content, 1) ?: $content;
    }

    private function escapeCpp(string $value): string
    {
        return addcslashes($value, "\\\"");
    }

    private function escapePlatformio(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function normalizeDhtModel(string $model): string
    {
        $normalized = strtoupper(trim($model));

        return match ($normalized) {
            'DHT22' => 'DHT22',
            'AM2302' => 'AM2302',
            'AUTO_DETECT' => 'AUTO_DETECT',
            default => 'DHT11',
        };
    }
}

