<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    protected $fillable = [
        'nama_device',
        'lokasi',
    ];

    public function eksperimens(): HasMany
    {
        return $this->hasMany(Eksperimen::class);
    }

    public function firmwareProfile(): HasOne
    {
        return $this->hasOne(DeviceFirmwareProfile::class);
    }
}
