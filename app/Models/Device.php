<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'nama_device',
        'lokasi',
    ];

    public function eksperimens()
    {
        return $this->hasMany(Eksperimen::class);
    }
}
