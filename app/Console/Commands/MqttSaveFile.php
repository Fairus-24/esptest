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
        if (!isset(
            $data['device_id'],
            $data['suhu'],
            $data['kelembapan'],
            $data['timestamp_esp'],
            $data['daya'],
            $data['packet_seq'],
            $data['rssi_dbm'],
            $data['tx_duration_ms'],
            $data['payload_bytes'],
            $data['uptime_s'],
            $data['free_heap_bytes']
        )) {
            $this->error('Missing required fields');
            return 1;
        }
        if (
            !is_numeric($data['suhu']) ||
            !is_numeric($data['kelembapan']) ||
            !is_numeric($data['timestamp_esp']) ||
            !is_numeric($data['daya']) ||
            !is_numeric($data['packet_seq']) ||
            !is_numeric($data['rssi_dbm']) ||
            !is_numeric($data['tx_duration_ms']) ||
            !is_numeric($data['payload_bytes']) ||
            !is_numeric($data['uptime_s']) ||
            !is_numeric($data['free_heap_bytes'])
        ) {
            $this->error('Invalid field type: required numeric fields are not numeric');
            return 1;
        }
        if ((float) $data['kelembapan'] < 0 || (float) $data['kelembapan'] > 100) {
            $this->error('Invalid kelembapan range (0-100)');
            return 1;
        }
        if ((float) $data['daya'] < 0) {
            $this->error('Invalid daya value (must be >= 0)');
            return 1;
        }
        if ((int) $data['packet_seq'] < 1 || (int) $data['rssi_dbm'] < -120 || (int) $data['rssi_dbm'] > 0 || (float) $data['tx_duration_ms'] < 0 || (int) $data['payload_bytes'] < 1 || (int) $data['uptime_s'] < 0 || (int) $data['free_heap_bytes'] < 0) {
            $this->error('Invalid telemetry values (packet_seq/rssi_dbm/tx_duration_ms/payload_bytes/uptime_s/free_heap_bytes)');
            return 1;
        }
        $timestampServer = now();
        $timestampEsp = Carbon::createFromTimestamp($data['timestamp_esp']);
        $latencyMs = abs((float) $timestampServer->floatDiffInMilliseconds($timestampEsp));
        Eksperimen::create([
            'device_id' => $data['device_id'],
            'protokol' => 'MQTT',
            'suhu' => $data['suhu'],
            'kelembapan' => $data['kelembapan'],
            'timestamp_esp' => $timestampEsp,
            'timestamp_server' => $timestampServer,
            'latency_ms' => $latencyMs,
            'daya_mw' => $data['daya'],
            'packet_seq' => (int) $data['packet_seq'],
            'rssi_dbm' => (int) $data['rssi_dbm'],
            'tx_duration_ms' => (float) $data['tx_duration_ms'],
            'payload_bytes' => (int) $data['payload_bytes'],
            'uptime_s' => (int) $data['uptime_s'],
            'free_heap_bytes' => (int) $data['free_heap_bytes'],
        ]);
        $this->info('MQTT data saved');
        return 0;
    }
}
