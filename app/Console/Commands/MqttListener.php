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
            if (!isset($data['device_id']) || !isset($data['suhu'])) {
                $this->warn("⚠ Missing required fields (device_id, suhu)");
                return;
            }
            $deviceId = $data['device_id'];
            $suhu = $data['suhu'];
            $kelembapan = $data['kelembapan'] ?? null;
            $daya = $data['daya'] ?? 0;
            $timestampEsp = isset($data['timestamp_esp']) 
                ? \DateTime::createFromFormat('U', $data['timestamp_esp']) 
                : now();
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
            ]);
            $timeStr = $timestampServer->format('H:i:s');
            $this->line("[{$timeStr}] ✓ MQTT Data Received - Device: {$deviceId} | Suhu: {$suhu}°C | Kelembapan: {$kelembapan}% | Daya: {$daya}mW | Latency: {$latencyMs}ms");
        } catch (\Exception $e) {
            $this->error("✗ Error processing message: " . $e->getMessage());
        }
    }
}
