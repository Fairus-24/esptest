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
        // Buat beberapa test devices
        Device::create([
            'nama_device' => 'ESP32 #1 - Ruang Lab A',
            'lokasi' => 'Laboratorium Penelitian A',
        ]);

        Device::create([
            'nama_device' => 'ESP32 #2 - Ruang Lab B',
            'lokasi' => 'Laboratorium Penelitian B',
        ]);

        Device::create([
            'nama_device' => 'ESP32 #3 - Server Room',
            'lokasi' => 'Ruang Server',
