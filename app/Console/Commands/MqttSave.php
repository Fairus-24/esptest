<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Eksperimen;
use Carbon\Carbon;

class MqttSave extends Command
{
    protected $signature = 'mqtt:save {payload}';
    protected $description = 'Save MQTT data from worker to eksperimens table';

    public function handle()
    {
        $data = json_decode($this->argument('payload'), true);
        if (!$data) {
            $this->error('Invalid JSON payload');
            return 1;
        }
        // Validasi minimal
        if (!isset($data['device_id'], $data['suhu'], $data['timestamp_esp'], $data['daya'])) {
            $this->error('Missing required fields');
            return 1;
        }
        Eksperimen::create([
            'device_id' => $data['device_id'],
            'protokol' => 'MQTT',
            'suhu' => $data['suhu'],
            'timestamp_esp' => Carbon::createFromTimestamp($data['timestamp_esp']),
            'timestamp_server' => now(),
            'latency_ms' => 0, // Optional: hitung jika ingin
            'daya_mw' => $data['daya'],
        ]);
        $this->info('MQTT data saved');
        return 0;
    }
}
