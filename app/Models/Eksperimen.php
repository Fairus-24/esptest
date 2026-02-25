<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Eksperimen extends Model
{
    protected $fillable = [
        'device_id',
        'protokol',
        'suhu',
        'kelembapan',
        'timestamp_esp',
        'timestamp_server',
        'latency_ms',
        'daya_mw',
        'packet_seq',
        'rssi_dbm',
        'tx_duration_ms',
        'payload_bytes',
        'uptime_s',
        'free_heap_bytes',
    ];

    protected $casts = [
        'timestamp_esp' => 'datetime',
        'timestamp_server' => 'datetime',
        'packet_seq' => 'integer',
        'rssi_dbm' => 'integer',
        'tx_duration_ms' => 'float',
        'payload_bytes' => 'integer',
        'uptime_s' => 'integer',
        'free_heap_bytes' => 'integer',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function getLatencyMsAttribute($value): float
    {
        return abs((float) $value);
    }
}
