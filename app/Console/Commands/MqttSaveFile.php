<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Eksperimen;
use Carbon\Carbon;

class MqttSaveFile extends Command
{
    protected $signature = 'mqtt:save-file {file}';
    protected $description = 'Save MQTT data from file to eksperimens table';

    public function handle()
    {
        $file = $this->argument('file');
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (!$data) {
            $this->error('Invalid JSON payload');
            return 1;
        }
        if (!isset($data['device_id'], $data['suhu'], $data['timestamp_esp'], $data['daya'])) {
            $this->error('Missing required fields');
            return 1;
        }
        $timestampServer = now();
        $timestampEsp = Carbon::createFromTimestamp($data['timestamp_esp']);
        $latencyMs = abs((float) $timestampServer->floatDiffInMilliseconds($timestampEsp));
        Eksperimen::create([
            'device_id' => $data['device_id'],
            'protokol' => 'MQTT',
            'suhu' => $data['suhu'],
            'timestamp_esp' => $timestampEsp,
            'timestamp_server' => $timestampServer,
            'latency_ms' => $latencyMs,
            'daya_mw' => $data['daya'],
        ]);
        $this->info('MQTT data saved');
        return 0;
    }
}
