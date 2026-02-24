<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Eksperimen extends Model
{
    protected $fillable = [
        'device_id',
        'protokol',
        'suhu',
        'timestamp_esp',
        'timestamp_server',
        'latency_ms',
        'daya_mw',
    ];

    protected $casts = [
        'timestamp_esp' => 'datetime',
        'timestamp_server' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
