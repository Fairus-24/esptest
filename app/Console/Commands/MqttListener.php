<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Eksperimen;
use App\Models\Device;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listener {--broker=127.0.0.1} {--port=1883} {--client_id=laravel-mqtt-listener}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to MQTT messages from ESP32 and save data to database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $broker = $this->option('broker');
        $port = $this->option('port');
        $clientId = $this->option('client_id');

        $this->info("========================================");
        $this->info("MQTT Listener - IoT Research");
        $this->info("========================================");
        $this->info("Connecting to MQTT broker at {$broker}:{$port}...");
        $this->info("Client ID: {$clientId}");
        $this->info("Subscribe Topic: iot/esp32/suhu");
        $this->info("");

        try {
            $connectionSettings = (new ConnectionSettings())
                ->setUseTls(false)
                ->setConnectTimeout(3)
                ->setKeepAliveInterval(60);

            $client = new MqttClient($broker, $port, $clientId);
            $client->connect($connectionSettings);

            $this->info('✓ Connected to MQTT broker successfully!');
            $this->info("");
            $this->info("Listening for messages... Press Ctrl+C to stop");
            $this->info("========================================");
            $this->info("");

            // Subscribe ke topic dan listen
            $client->subscribe('iot/esp32/suhu', function ($topic, $message) {
                $this->processMqttMessage($topic, $message);
            });

            // Block dan listen for messages
            $client->loop(true);

        } catch (\Exception $e) {
            $this->error('✗ Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Process MQTT message dan simpan ke database
     */
    private function processMqttMessage($topic, $message)
    {
        try {
            $data = json_decode($message, true);
            if (!is_array($data)) {
                $this->warn("⚠ Invalid JSON message from {$topic}: {$message}");
                return;
            }
            // Validasi required fields
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
                $this->warn("⚠ Missing required fields (device_id, suhu, kelembapan, timestamp_esp, daya, packet_seq, rssi_dbm, tx_duration_ms, payload_bytes, uptime_s, free_heap_bytes)");
                return;
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
                $this->warn("⚠ Invalid payload type on required numeric fields");
                return;
            }
            $deviceId = $data['device_id'];
            $suhu = $data['suhu'];
            $kelembapan = $data['kelembapan'];
            $daya = $data['daya'];
            $packetSeq = (int) $data['packet_seq'];
            $rssiDbm = (int) $data['rssi_dbm'];
            $txDurationMs = (float) $data['tx_duration_ms'];
            $payloadBytes = (int) $data['payload_bytes'];
            $uptimeSeconds = (int) $data['uptime_s'];
            $freeHeapBytes = (int) $data['free_heap_bytes'];
            $timestampEsp = \DateTime::createFromFormat('U', $data['timestamp_esp']);
            if ($timestampEsp === false) {
                $this->warn("⚠ Invalid timestamp_esp in payload");
                return;
            }
            if ((float) $kelembapan < 0 || (float) $kelembapan > 100) {
                $this->warn("⚠ Invalid kelembapan range (0-100)");
                return;
            }
            if ((float) $daya < 0) {
                $this->warn("⚠ Invalid daya value (must be >= 0)");
                return;
            }
            if ($packetSeq < 1 || $rssiDbm < -120 || $rssiDbm > 0 || $txDurationMs < 0 || $payloadBytes < 1 || $uptimeSeconds < 0 || $freeHeapBytes < 0) {
                $this->warn("⚠ Invalid telemetry values (packet_seq/rssi_dbm/tx_duration_ms/payload_bytes/uptime_s/free_heap_bytes)");
                return;
            }
            // Verify device exists
            $device = Device::find($deviceId);
            if (!$device) {
                $this->warn("⚠ Device not found (ID: {$deviceId})");
                return;
            }
            $timestampServer = now();
            // Hitung latency dalam milliseconds
            $latencyMs = abs((float) $timestampServer->diffInMilliseconds($timestampEsp));
            // Create eksperimen record
            $eksperimen = Eksperimen::create([
                'device_id' => $deviceId,
                'protokol' => 'MQTT',
                'suhu' => $suhu,
                'kelembapan' => $kelembapan,
                'timestamp_esp' => $timestampEsp,
                'timestamp_server' => $timestampServer,
                'latency_ms' => $latencyMs,
                'daya_mw' => $daya,
                'packet_seq' => $packetSeq,
                'rssi_dbm' => $rssiDbm,
                'tx_duration_ms' => $txDurationMs,
                'payload_bytes' => $payloadBytes,
                'uptime_s' => $uptimeSeconds,
                'free_heap_bytes' => $freeHeapBytes,
            ]);
            $timeStr = $timestampServer->format('H:i:s');
            $this->line("[{$timeStr}] ✓ MQTT Data Received - Device: {$deviceId} | Seq: {$packetSeq} | Suhu: {$suhu}°C | Kelembapan: {$kelembapan}% | Daya: {$daya}mW | RSSI: {$rssiDbm}dBm | TX: {$txDurationMs}ms | Latency: {$latencyMs}ms");
        } catch (\Exception $e) {
            $this->error("✗ Error processing message: " . $e->getMessage());
        }
    }
}
