<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceFirmwareProfile extends Model
{
    protected $fillable = [
        'device_id',
        'board',
        'wifi_ssid',
        'wifi_password',
        'server_host',
        'http_base_url',
        'http_endpoint',
        'mqtt_broker',
        'mqtt_host',
        'mqtt_port',
        'mqtt_topic',
        'mqtt_user',
        'mqtt_password',
        'http_tls_insecure',
        'http_read_timeout_ms',
        'dht_pin',
        'dht_model',
        'sensor_interval_ms',
        'http_interval_ms',
        'mqtt_interval_ms',
        'dht_min_read_interval_ms',
        'core_debug_level',
        'mqtt_max_packet_size',
        'monitor_speed',
        'monitor_port',
        'upload_port',
        'extra_build_flags',
    ];

    protected $casts = [
        'mqtt_port' => 'integer',
        'http_tls_insecure' => 'boolean',
        'http_read_timeout_ms' => 'integer',
        'dht_pin' => 'integer',
        'sensor_interval_ms' => 'integer',
        'http_interval_ms' => 'integer',
        'mqtt_interval_ms' => 'integer',
        'dht_min_read_interval_ms' => 'integer',
        'core_debug_level' => 'integer',
        'mqtt_max_packet_size' => 'integer',
        'monitor_speed' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
