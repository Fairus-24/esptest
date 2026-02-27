<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AdminEnvironmentService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'key' => 'APP_URL',
                'label' => 'App URL / Subdomain',
                'config_path' => 'app.url',
                'type' => 'string',
                'group' => 'Core',
                'placeholder' => 'https://iot.lab.example.com',
                'help' => 'Base URL aplikasi Laravel (untuk link, redirect, metadata).',
            ],
            [
                'key' => 'MQTT_HOST',
                'label' => 'MQTT Host',
                'config_path' => 'mqtt.host',
                'type' => 'string',
                'group' => 'Broker',
                'placeholder' => '127.0.0.1 / broker.domain',
                'help' => 'Host broker MQTT untuk worker/dashboard.',
            ],
            [
                'key' => 'MQTT_PORT',
                'label' => 'MQTT Port',
                'config_path' => 'mqtt.port',
                'type' => 'integer',
                'group' => 'Broker',
                'placeholder' => '1883',
                'help' => 'Port broker MQTT.',
            ],
            [
                'key' => 'MQTT_TOPIC',
                'label' => 'MQTT Topic',
                'config_path' => 'mqtt.topic',
                'type' => 'string',
                'group' => 'Broker',
                'placeholder' => 'iot/esp32/suhu',
                'help' => 'Topik subscribe/publish telemetry.',
            ],
            [
                'key' => 'MQTT_USERNAME',
                'label' => 'MQTT Username',
                'config_path' => 'mqtt.username',
                'type' => 'string',
                'group' => 'Broker',
                'placeholder' => 'esp32',
                'help' => 'Username autentikasi broker.',
            ],
            [
                'key' => 'MQTT_PASSWORD',
                'label' => 'MQTT Password',
                'config_path' => 'mqtt.password',
                'type' => 'string',
                'group' => 'Broker',
                'placeholder' => '******',
                'secret' => true,
                'help' => 'Password autentikasi broker.',
            ],
            [
                'key' => 'HTTP_INGEST_KEY',
                'label' => 'HTTP Ingest Key',
                'config_path' => 'http_server.ingest_key',
                'type' => 'string',
                'group' => 'Security',
                'placeholder' => 'hex/random secret',
                'secret' => true,
                'help' => 'Token header X-Ingest-Key untuk endpoint HTTP ingest.',
            ],
            [
                'key' => 'HTTP_ALLOW_INGEST_WITHOUT_KEY',
                'label' => 'Allow HTTP Ingest Without Key',
                'config_path' => 'http_server.allow_ingest_without_key',
                'type' => 'boolean',
                'group' => 'Security',
                'help' => 'Set false untuk produksi.',
            ],
            [
                'key' => 'RESET_DATA_TOKEN',
                'label' => 'Reset Data Token',
                'config_path' => 'dashboard.reset.token',
                'type' => 'string',
                'group' => 'Security',
                'placeholder' => 'long random token',
                'secret' => true,
                'help' => 'Token tambahan saat reset data eksperimen.',
            ],
            [
                'key' => 'RESET_ALLOW_WITHOUT_TOKEN',
                'label' => 'Allow Reset Without Token',
                'config_path' => 'dashboard.reset.allow_without_token',
                'type' => 'boolean',
                'group' => 'Security',
                'help' => 'Set false untuk produksi.',
            ],
            [
                'key' => 'LARAVEL_HTTP_HOST',
                'label' => 'Internal PHP Host',
                'config_path' => 'http_server.host',
                'type' => 'string',
                'group' => 'Runtime',
                'placeholder' => '0.0.0.0',
                'help' => 'Host bind untuk `php artisan serve` internal.',
            ],
            [
                'key' => 'LARAVEL_HTTP_PORT',
                'label' => 'Internal PHP Port',
                'config_path' => 'http_server.port',
                'type' => 'integer',
                'group' => 'Runtime',
                'placeholder' => '8010',
                'help' => 'Port internal server Laravel (upstream Nginx).',
            ],
            [
                'key' => 'DATA_RETENTION_DAYS',
                'label' => 'Retention Days',
                'config_path' => 'dashboard.retention_days',
                'type' => 'integer',
                'group' => 'Analytics',
                'placeholder' => '30',
                'help' => 'Jumlah hari data dipertahankan oleh scheduler prune.',
            ],
            [
                'key' => 'DASHBOARD_MQTT_HEALTH_MIN_SCORE',
                'label' => 'MQTT Health Min Score',
                'config_path' => 'dashboard.warnings.mqtt_health_min_score',
                'type' => 'float',
                'group' => 'Analytics',
                'placeholder' => '65',
                'help' => 'Ambang warning health MQTT.',
            ],
            [
                'key' => 'DASHBOARD_HTTP_HEALTH_MIN_SCORE',
                'label' => 'HTTP Health Min Score',
                'config_path' => 'dashboard.warnings.http_health_min_score',
                'type' => 'float',
                'group' => 'Analytics',
                'placeholder' => '70',
                'help' => 'Ambang warning health HTTP.',
            ],
            [
                'key' => 'DASHBOARD_BALANCE_ALLOWED_RATIO',
                'label' => 'Balance Allowed Ratio',
                'config_path' => 'dashboard.warnings.balance_allowed_ratio',
                'type' => 'float',
                'group' => 'Analytics',
                'placeholder' => '0.12',
                'help' => 'Toleransi rasio ketidakseimbangan sampel MQTT/HTTP.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        $rules = [];
        foreach ($this->definitions() as $definition) {
            $key = (string) $definition['key'];
            $type = (string) $definition['type'];
            $rule = match ($type) {
                'integer' => ['nullable', 'integer'],
                'float' => ['nullable', 'numeric'],
                'boolean' => ['nullable', 'in:0,1,true,false,on,off,yes,no'],
                default => ['nullable', 'string', 'max:1000'],
            };
            $rules[$key] = $rule;
        }

        return $rules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFormState(): array
    {
        $definitions = collect($this->definitions())->keyBy('key');
        $stored = $this->getStoredSettings();
        $result = [];

        foreach ($definitions as $key => $definition) {
            $storedRow = $stored->get($key);
            $configPath = (string) $definition['config_path'];
            $defaultValue = config($configPath);
            $effectiveValue = $storedRow !== null
                ? $this->parseByType((string) $storedRow['setting_value'], (string) $definition['type'])
                : $defaultValue;

            $result[$key] = array_merge($definition, [
                'stored' => $storedRow !== null,
                'current_value' => $effectiveValue,
                'input_value' => $this->normalizeInputValue($effectiveValue, (string) $definition['type']),
            ]);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $validatedInput
     */
    public function saveOverrides(array $validatedInput, ?string $ipAddress = null): void
    {
        foreach ($this->definitions() as $definition) {
            $key = (string) $definition['key'];
            if (!array_key_exists($key, $validatedInput)) {
                continue;
            }

            $rawValue = $validatedInput[$key];
            if ($rawValue === null || $rawValue === '') {
                AppSetting::query()->where('setting_key', $key)->delete();
                continue;
            }

            AppSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                [
                    'setting_value' => $this->serializeByType($rawValue, (string) $definition['type']),
                    'value_type' => (string) $definition['type'],
                    'updated_by_ip' => $ipAddress,
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfigOverrides(): array
    {
        $definitions = collect($this->definitions())->keyBy('key');
        $stored = $this->getStoredSettings();
        $overrides = [];

        foreach ($stored as $setting) {
            $key = (string) Arr::get($setting, 'setting_key', '');
            $definition = $definitions->get($key);
            if ($definition === null) {
                continue;
            }

            $configPath = (string) $definition['config_path'];
            $value = $this->parseByType((string) Arr::get($setting, 'setting_value', ''), (string) $definition['type']);
            $overrides[$configPath] = $value;
        }

        return $overrides;
    }

    public function renderEnvSnippet(): string
    {
        $lines = [];
        $state = $this->getFormState();
        foreach ($state as $key => $item) {
            if (!(bool) ($item['stored'] ?? false)) {
                continue;
            }

            $value = (string) ($item['input_value'] ?? '');
            $lines[] = "{$key}={$value}";
        }

        return implode(PHP_EOL, $lines);
    }

    private function getStoredSettings(): Collection
    {
        try {
            $keys = array_map(static fn (array $d): string => (string) $d['key'], $this->definitions());

            return AppSetting::query()
                ->whereIn('setting_key', $keys)
                ->get()
                ->keyBy('setting_key')
                ->map(static fn (AppSetting $setting): array => $setting->toArray());
        } catch (QueryException) {
            return collect();
        }
    }

    private function parseByType(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }

    private function serializeByType(mixed $value, string $type): string
    {
        return match ($type) {
            'integer' => (string) ((int) $value),
            'float' => (string) ((float) $value),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0',
            default => trim((string) $value),
        };
    }

    private function normalizeInputValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return '';
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0',
            default => (string) $value,
        };
    }
}

