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
        'dht_pin',
        'dht_model',
        'extra_build_flags',
    ];

    protected $casts = [
        'mqtt_port' => 'integer',
        'http_tls_insecure' => 'boolean',
        'dht_pin' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
