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
        if ($this->isUnsafeFirmwareTargetHost($mqttHost)) {
            $mqttHost = '';
        }
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
                'monitor_port' => null,
                'upload_port' => null,
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
        if ((int) ($profile->http_read_timeout_ms ?? 0) <= 0) {
            $backfill['http_read_timeout_ms'] = 5000;
        }
        if ((int) ($profile->sensor_interval_ms ?? 0) <= 0) {
            $backfill['sensor_interval_ms'] = 5000;
        }
        if ((int) ($profile->http_interval_ms ?? 0) <= 0) {
            $backfill['http_interval_ms'] = 10000;
        }
        if ((int) ($profile->mqtt_interval_ms ?? 0) <= 0) {
            $backfill['mqtt_interval_ms'] = 10000;
        }
        if ((int) ($profile->dht_min_read_interval_ms ?? 0) <= 0) {
            $backfill['dht_min_read_interval_ms'] = 1500;
        }
        if ((int) ($profile->mqtt_max_packet_size ?? 0) <= 0) {
            $backfill['mqtt_max_packet_size'] = 2048;
        }
        if ((int) ($profile->monitor_speed ?? 0) <= 0) {
            $backfill['monitor_speed'] = 115200;
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
        $rendered = $this->renderStandard($device, $profile, $runtimeState);
        $activeOverrides = [];

        $customMain = $this->resolveCustomSource($profile->custom_main_cpp ?? null);
        if ($customMain !== null) {
            $rendered['main_cpp'] = $customMain;
            $activeOverrides[] = 'main.cpp';
        }

        $customPlatformio = $this->resolveCustomSource($profile->custom_platformio_ini ?? null);
        if ($customPlatformio !== null) {
            $rendered['platformio_ini'] = $customPlatformio;
            $activeOverrides[] = 'platformio.ini';
        }

        if ($activeOverrides !== []) {
            $rendered['instructions'] .= PHP_EOL
                . '5) Custom per-device source override active: ' . implode(', ', $activeOverrides)
                . '. Device lain tetap memakai generated standard template.';
        }

        return $rendered;
    }

    /**
     * @param array<string, array<string, mixed>> $runtimeState
     * @return array<string, string>
     */
    public function renderStandard(Device $device, DeviceFirmwareProfile $profile, array $runtimeState): array
    {
        $mainTemplate = $this->readTemplateFile(
            resource_path('firmware-templates/main.cpp.stub'),
            base_path('ESP32_Firmware/src/main.cpp')
        );
        $iniTemplate = $this->readTemplateFile(
            resource_path('firmware-templates/platformio.ini.stub'),
            base_path('ESP32_Firmware/platformio.ini')
        );

        $wifiSsid = (string) $profile->wifi_ssid;
        $wifiPassword = (string) $profile->wifi_password;
        $serverHost = trim((string) $profile->server_host);
        $httpIngestKey = (string) ($runtimeState['HTTP_INGEST_KEY']['current_value'] ?? config('http_server.ingest_key', ''));
        $httpBaseUrl = rtrim(trim((string) ($profile->http_base_url ?: '')), '/');
        if ($httpBaseUrl === '') {
            $httpBaseUrl = trim((string) config('app.url', 'http://127.0.0.1'));
        }
        $httpEndpoint = trim((string) ($profile->http_endpoint ?: ''));
        if ($httpEndpoint === '') {
            $httpEndpoint = '/api/http-data';
        }
        $httpEndpoint = '/' . ltrim($httpEndpoint, '/');
        $mqttBroker = trim((string) ($profile->mqtt_broker ?: $profile->mqtt_host ?: $serverHost));
        if ($this->isUnsafeFirmwareTargetHost($mqttBroker)) {
            $mqttBroker = $serverHost;
        }
        $mqttHost = $mqttBroker;
        $mqttPort = max(1, (int) $profile->mqtt_port);
        $mqttTopic = trim((string) $profile->mqtt_topic);
        $mqttUser = (string) $profile->mqtt_user;
        $mqttPassword = (string) $profile->mqtt_password;
        $httpTlsInsecure = ((bool) $profile->http_tls_insecure) ? '1' : '0';
        $httpReadTimeout = max(1000, (int) ($profile->http_read_timeout_ms ?? 5000));
        $dhtPin = max(0, (int) $profile->dht_pin);
        $dhtTypeToken = $this->resolveDhtTypeToken((string) $profile->dht_model);
        $sensorInterval = max(500, (int) ($profile->sensor_interval_ms ?? 5000));
        $httpInterval = max(500, (int) ($profile->http_interval_ms ?? 10000));
        $mqttInterval = max(500, (int) ($profile->mqtt_interval_ms ?? 10000));
        $dhtMinReadInterval = max(250, (int) ($profile->dht_min_read_interval_ms ?? 1500));
        $coreDebugLevel = max(0, min(5, (int) ($profile->core_debug_level ?? 0)));
        $mqttMaxPacketSize = max(256, (int) ($profile->mqtt_max_packet_size ?? 2048));
        $monitorSpeed = max(1200, (int) ($profile->monitor_speed ?? 115200));
        $monitorPort = trim((string) ($profile->monitor_port ?? ''));
        $uploadPort = trim((string) ($profile->upload_port ?? ''));

        $mainRendered = $mainTemplate;
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* WIFI_SSID = [^;]+;/', 'const char* WIFI_SSID = "' . $this->escapeCpp($wifiSsid) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* WIFI_PASSWORD = [^;]+;/', 'const char* WIFI_PASSWORD = "' . $this->escapeCpp($wifiPassword) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/#define SERVER_HOST ".*?"/', '#define SERVER_HOST "' . $this->escapeCpp($serverHost) . '"');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* HTTP_ENDPOINT = [^;]+;/', 'const char* HTTP_ENDPOINT = "' . $this->escapeCpp($httpEndpoint) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* MQTT_SERVER = .*?;/', 'const char* MQTT_SERVER = "' . $this->escapeCpp($mqttHost) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const int MQTT_PORT = [^;]+;/', 'const int MQTT_PORT = ' . $mqttPort . ';');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* MQTT_TOPIC = [^;]+;/', 'const char* MQTT_TOPIC = "' . $this->escapeCpp($mqttTopic) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* MQTT_USER = [^;]+;/', 'const char* MQTT_USER = "' . $this->escapeCpp($mqttUser) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const char\* MQTT_PASSWORD = [^;]+;/', 'const char* MQTT_PASSWORD = "' . $this->escapeCpp($mqttPassword) . '";');
        $mainRendered = $this->replaceOne($mainRendered, '/const int DEVICE_ID = [^;]+;/', 'const int DEVICE_ID = ' . (int) $device->id . ';');
        $mainRendered = $this->replaceOne($mainRendered, '/^#define DHTPIN .+$/m', '#define DHTPIN ' . $dhtPin);
        $mainRendered = $this->replaceOne($mainRendered, '/^#define DHTTYPE .+$/m', '#define DHTTYPE ' . $dhtTypeToken);
        $mainRendered = $this->replaceOne($mainRendered, '/const unsigned long INTERVAL_SENSOR = [^;]+;/', 'const unsigned long INTERVAL_SENSOR = ' . $sensorInterval . 'UL;');
        $mainRendered = $this->replaceOne($mainRendered, '/const unsigned long INTERVAL_HTTP = [^;]+;/', 'const unsigned long INTERVAL_HTTP = ' . $httpInterval . 'UL;');
        $mainRendered = $this->replaceOne($mainRendered, '/const unsigned long INTERVAL_MQTT = [^;]+;/', 'const unsigned long INTERVAL_MQTT = ' . $mqttInterval . 'UL;');
        $mainRendered = $this->replaceOne($mainRendered, '/const unsigned long DHT_MIN_READ_INTERVAL_MS = [^;]+;/', 'const unsigned long DHT_MIN_READ_INTERVAL_MS = ' . $dhtMinReadInterval . 'UL;');

        $iniRendered = $iniTemplate;
        $iniRendered = $this->upsertPlatformioSetting($iniRendered, 'board', trim((string) $profile->board));
        $iniRendered = $this->upsertPlatformioSetting($iniRendered, 'monitor_speed', (string) $monitorSpeed);
        // Keep direct object linking to avoid fragile archive steps on some server shells/toolchains.
        $iniRendered = $this->upsertPlatformioSetting($iniRendered, 'lib_archive', 'false');
        $iniRendered = $this->upsertPlatformioSetting($iniRendered, 'extra_scripts', 'pre:scripts/pio_env_fix.py');
        $iniRendered = $this->upsertPlatformioMultilineSetting($iniRendered, 'lib_deps', [
            'adafruit/DHT sensor library@1.4.4',
            'adafruit/Adafruit Unified Sensor@1.1.14',
            'knolleary/PubSubClient@2.8',
            'bblanchon/ArduinoJson@6.21.3',
        ]);
        $iniRendered = $this->upsertOrRemovePlatformioSetting($iniRendered, 'monitor_port', $monitorPort);
        $iniRendered = $this->upsertOrRemovePlatformioSetting($iniRendered, 'upload_port', $uploadPort);

        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'CORE_DEBUG_LEVEL', (string) $coreDebugLevel);
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'HTTP_CLIENT_TIMEOUT', (string) $httpReadTimeout);
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'MQTT_MAX_PACKET_SIZE', (string) $mqttMaxPacketSize);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_WIFI_SSID', $wifiSsid);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_WIFI_PASSWORD', $wifiPassword);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_HTTP_INGEST_KEY', $httpIngestKey);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_HTTP_BASE_URL', $httpBaseUrl);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_HTTP_ENDPOINT', $httpEndpoint);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_MQTT_BROKER', $mqttBroker);
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_MQTT_PORT', (string) $mqttPort);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_MQTT_TOPIC', $mqttTopic);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_MQTT_USER', $mqttUser);
        $iniRendered = $this->upsertPlatformioQuotedFlag($iniRendered, 'ESP_MQTT_PASSWORD', $mqttPassword);
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_HTTP_TLS_INSECURE', $httpTlsInsecure);
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_HTTP_READ_TIMEOUT_MS', (string) $httpReadTimeout);
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_DHT_PIN', (string) $dhtPin);
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_DEVICE_ID', (string) ((int) $device->id));
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_SENSOR_INTERVAL_MS', $sensorInterval . 'UL');
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_HTTP_INTERVAL_MS', $httpInterval . 'UL');
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_MQTT_INTERVAL_MS', $mqttInterval . 'UL');
        $iniRendered = $this->upsertPlatformioPlainFlag($iniRendered, 'ESP_DHT_MIN_READ_INTERVAL_MS', $dhtMinReadInterval . 'UL');

        $extraFlags = trim((string) $profile->extra_build_flags);
        $extraFlags = $this->stripManagedBuildFlagsFromExtra($extraFlags);
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

    /**
     * @param array<string, string> $rendered
     */
    public function isWorkspaceSynchronized(array $rendered): bool
    {
        $mainPath = base_path('ESP32_Firmware/src/main.cpp');
        $iniPath = base_path('ESP32_Firmware/platformio.ini');

        if (!is_file($mainPath) || !is_file($iniPath)) {
            return false;
        }

        $workspaceMain = (string) file_get_contents($mainPath);
        $workspaceIni = (string) file_get_contents($iniPath);
        $renderedMain = (string) ($rendered['main_cpp'] ?? '');
        $renderedIni = (string) ($rendered['platformio_ini'] ?? '');

        return $this->normalizeLineEndings($workspaceMain) === $this->normalizeLineEndings($renderedMain)
            && $this->normalizeLineEndings($workspaceIni) === $this->normalizeLineEndings($renderedIni);
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

    private function upsertPlatformioSetting(string $content, string $key, string $value): string
    {
        $line = $key . ' = ' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*[^\r\n]*\r?$/m';

        if (preg_match($pattern, $content) === 1) {
            $seen = 0;
            $updated = preg_replace_callback($pattern, static function () use (&$seen, $line): string {
                $seen++;
                return $seen === 1 ? $line : '';
            }, $content) ?: $content;

            return preg_replace("/\n{3,}/", "\n\n", $updated) ?: $updated;
        }

        return rtrim($content) . "\n" . $line . "\n";
    }

    private function upsertOrRemovePlatformioSetting(string $content, string $key, string $value): string
    {
        if ($value === '') {
            return $this->removePlatformioSetting($content, $key);
        }

        return $this->upsertPlatformioSetting($content, $key, $value);
    }

    private function removePlatformioSetting(string $content, string $key): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*[^\r\n]*\r?\n?/m';
        $updated = preg_replace($pattern, '', $content) ?: $content;

        return preg_replace("/\n{3,}/", "\n\n", $updated) ?: $updated;
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

    /**
     * @param list<string> $items
     */
    private function upsertPlatformioMultilineSetting(string $content, string $key, array $items): string
    {
        $cleanItems = array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            $items
        ), static fn (string $item): bool => $item !== ''));

        $block = $key . ' =';
        if ($cleanItems !== []) {
            $block .= "\n    " . implode("\n    ", $cleanItems);
        }
        $block .= "\n";

        // Replace the entire multi-line block until next non-indented line.
        $pattern = '/^' . preg_quote($key, '/') . '\s*=.*?(?=^\S|\z)/ms';
        if (preg_match($pattern, $content) === 1) {
            return preg_replace($pattern, $block, $content, 1) ?: $content;
        }

        return rtrim($content) . "\n" . $block;
    }

    private function resolveDhtTypeToken(string $model): string
    {
        $normalized = strtoupper(trim($model));

        return match ($normalized) {
            'DHT22' => 'DHT22',
            'AM2302' => 'DHT22',
            default => 'DHT11',
        };
    }

    private function stripManagedBuildFlagsFromExtra(string $extraFlags): string
    {
        if ($extraFlags === '') {
            return '';
        }

        $reserved = [];
        foreach ($this->reservedManagedBuildFlags() as $flag) {
            $reserved[strtoupper($flag)] = true;
        }

        $filtered = [];
        $lines = preg_split('/\r\n|\r|\n/', $extraFlags) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^-D([A-Za-z_][A-Za-z0-9_]*)(?:=.*)?$/', $trimmed, $matches) === 1) {
                $macro = strtoupper((string) ($matches[1] ?? ''));
                if ($macro !== '' && isset($reserved[$macro])) {
                    continue;
                }
            }

            $filtered[] = $trimmed;
        }

        return implode(PHP_EOL, $filtered);
    }

    private function resolveCustomSource(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = $this->normalizeLineEndings($value);
        if (trim($normalized) === '') {
            return null;
        }

        return $normalized;
    }

    private function readTemplateFile(string $preferredPath, string $fallbackPath): string
    {
        $path = is_file($preferredPath) ? $preferredPath : $fallbackPath;

        return (string) (file_get_contents($path) ?: '');
    }

    /**
     * @return list<string>
     */
    private function reservedManagedBuildFlags(): array
    {
        return [
            'ESP_DEVICE_ID',
            'ESP_DHT_PIN',
            'ESP_WIFI_SSID',
            'ESP_WIFI_PASSWORD',
            'ESP_HTTP_INGEST_KEY',
            'ESP_HTTP_BASE_URL',
            'ESP_HTTP_ENDPOINT',
            'ESP_MQTT_BROKER',
            'ESP_MQTT_PORT',
            'ESP_MQTT_TOPIC',
            'ESP_MQTT_USER',
            'ESP_MQTT_PASSWORD',
            'ESP_HTTP_TLS_INSECURE',
            'ESP_HTTP_READ_TIMEOUT_MS',
            'ESP_SENSOR_INTERVAL_MS',
            'ESP_HTTP_INTERVAL_MS',
            'ESP_MQTT_INTERVAL_MS',
            'ESP_DHT_MIN_READ_INTERVAL_MS',
            'CORE_DEBUG_LEVEL',
            'HTTP_CLIENT_TIMEOUT',
            'MQTT_MAX_PACKET_SIZE',
        ];
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    private function isUnsafeFirmwareTargetHost(string $host): bool
    {
        $candidate = strtolower(trim($host));
        if ($candidate === '') {
            return true;
        }

        if (in_array($candidate, ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'esp_mqtt_broker'], true)) {
            return true;
        }

        if (str_starts_with($candidate, '127.')) {
            return true;
        }

        return false;
    }
}
