<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Sinkronisasi dengan konfigurasi ESP (device_id = 1 dan 2)
        \App\Models\Device::firstOrCreate([
            'id' => 1
        ], [
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab'
        ]);
        \App\Models\Device::firstOrCreate([
            'id' => 2
        ], [
            'nama_device' => 'ESP32-2',
            'lokasi' => 'Lab 2'
        ]);

        // Tambah data eksperimen dummy untuk 2 device dan 2 protokol
        $dummyData = [
            // Device 1
            [1, 'MQTT', 27.5, 45.2, 120, 80],
            [1, 'HTTP', 27.7, 46.1, 150, 82],
            [1, 'MQTT', 28.0, 44.8, 110, 78],
            [1, 'HTTP', 28.2, 47.0, 140, 85],
            // Device 2
            [2, 'MQTT', 26.9, 43.5, 100, 75],
            [2, 'HTTP', 27.1, 44.0, 130, 79],
            [2, 'MQTT', 27.2, 42.9, 105, 77],
            [2, 'HTTP', 27.4, 45.5, 135, 81],
        ];
        foreach ($dummyData as $row) {
            \App\Models\Eksperimen::create([
                'device_id' => $row[0],
                'protokol' => $row[1],
                'suhu' => $row[2],
                'kelembapan' => $row[3],
                'latency_ms' => $row[4],
                'daya_mw' => $row[5],
                'timestamp_esp' => now(),
                'timestamp_server' => now(),
            ]);
        }
    }
}
