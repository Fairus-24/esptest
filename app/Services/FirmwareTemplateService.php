<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceFirmwareProfile;
use Illuminate\Support\Str;

class FirmwareTemplateService
{
    public function findProfileByDeviceId(int $deviceId): ?DeviceFirmwareProfile
    {
        return DeviceFirmwareProfile::query()
            ->where('device_id', $deviceId)
            ->first();
    }

    public function ensureProfile(Device $device): DeviceFirmwareProfile
    {
        $appUrl = trim((string) config('app.url', 'http://127.0.0.1'));
        $appScheme = (string) (parse_url($appUrl, PHP_URL_SCHEME) ?: 'http');
        $serverHost = (string) (parse_url($appUrl, PHP_URL_HOST) ?: '127.0.0.1');
        $appPort = parse_url($appUrl, PHP_URL_PORT);
        $httpBaseUrl = $appScheme . '://' . $serverHost;
        if ($appPort !== null) {
            $httpBaseUrl .= ':' . (int) $appPort;
        }

        $appPath = trim((string) (parse_url($appUrl, PHP_URL_PATH) ?: ''), '/');
        $httpEndpoint = $appPath !== ''
            ? '/' . $appPath . '/api/http-data'
            : '/api/http-data';

        $mqttHost = trim((string) config('mqtt.host', ''));
        if ($mqttHost === '') {
            $mqttHost = $serverHost;
        }

        $profile = DeviceFirmwareProfile::query()->firstOrCreate(
            ['device_id' => $device->id],
            [
                'board' => 'esp32doit-devkit-v1',
                'wifi_ssid' => 'Free',
                'wifi_password' => 'gratiskok',
                'server_host' => $serverHost,
                'http_base_url' => $httpBaseUrl,
                'http_endpoint' => $httpEndpoint,
                'mqtt_broker' => $mqttHost,
                'mqtt_host' => $mqttHost,
                'mqtt_port' => 1883,
                'mqtt_topic' => 'iot/esp32/suhu',
                'mqtt_user' => 'esp32',
                'mqtt_password' => 'esp32',
                'http_tls_insecure' => true,
                'dht_pin' => 4,
                'dht_model' => 'DHT11',
            ]
        );

        $backfill = [];
        if (trim((string) $profile->server_host) === '') {
            $backfill['server_host'] = $serverHost;
        }
        if (trim((string) $profile->http_base_url) === '') {
            $backfill['http_base_url'] = $httpBaseUrl;
        }
        if (trim((string) $profile->mqtt_broker) === '') {
            $backfill['mqtt_broker'] = $mqttHost;
        }
        if (trim((string) $profile->mqtt_host) === '') {
            $backfill['mqtt_host'] = $mqttHost;
        }
        if ($profile->http_tls_insecure === null) {
            $backfill['http_tls_insecure'] = true;
        }

        if ($backfill !== []) {
            $profile->fill($backfill);
            $profile->save();
        }

        return $profile;
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

        $httpBaseUrl = rtrim(trim((string) ($profile->http_base_url ?: '')), '/');
        if ($httpBaseUrl === '') {
            $httpBaseUrl = trim((string) config('app.url', 'http://127.0.0.1'));
        }

        $mqttBroker = trim((string) ($profile->mqtt_broker ?: ''));
        if ($mqttBroker === '') {
            $mqttBroker = trim((string) ($profile->mqtt_host ?: $profile->server_host));
        }

        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_HTTP_INGEST_KEY', $httpIngestKey);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_HTTP_BASE_URL', $httpBaseUrl);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_MQTT_BROKER', $mqttBroker);
        $iniRendered = $this->upsertPlatformioPlainFlag(
            $iniRendered,
            'ESP_HTTP_TLS_INSECURE',
            ((bool) $profile->http_tls_insecure) ? '1' : '0'
        );

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

    private function upsertPlatformioQuotedFlag(string $content, string $flag, string $value): string
    {
        $escapedValue = $this->escapePlatformio($value);
        $line = '    -D' . $flag . '=\\"' . $escapedValue . '\\"';
        $pattern = '/^\s*-D' . preg_quote($flag, '/') . '=\\\\\".*?\\\\\"$/m';

        if (preg_match($pattern, $content) === 1) {
            return preg_replace($pattern, $line, $content, 1) ?: $content;
        }

        return $this->appendPlatformioBuildFlag($content, $line);
    }

    private function upsertPlatformioPlainFlag(string $content, string $flag, string $value): string
    {
        $line = '    -D' . $flag . '=' . $value;
        $pattern = '/^\s*-D' . preg_quote($flag, '/') . '=[^\r\n]+$/m';

        if (preg_match($pattern, $content) === 1) {
            return preg_replace($pattern, $line, $content, 1) ?: $content;
        }

        return $this->appendPlatformioBuildFlag($content, $line);
    }

    private function appendPlatformioBuildFlag(string $content, string $line): string
    {
        if (preg_match('/^build_flags\s*=\s*$/m', $content) === 1) {
            return preg_replace(
                '/^build_flags\s*=\s*$/m',
                "build_flags =\n" . $line,
                $content,
                1
            ) ?: $content;
        }

        return rtrim($content) . "\n" . $line . "\n";
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
