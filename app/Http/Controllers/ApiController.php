<?php

namespace App\Http\Controllers;

use App\Models\Eksperimen;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    /**
     * Menerima data HTTP dari ESP32
     * 
     * POST /api/http-data
     * 
     * Request body:
     * {
     *   "device_id": 1,
     *   "suhu": 25.5,
     *   "kelembapan": 60.2,
     *   "timestamp_esp": 1708884000,
     *   "daya": 100,
     *   "packet_seq": 1,
     *   "rssi_dbm": -58,
     *   "tx_duration_ms": 95.2,
     *   "payload_bytes": 212,
     *   "uptime_s": 8451,
     *   "free_heap_bytes": 271232,
     *   "sensor_age_ms": 1530,          // optional telemetry detail
     *   "sensor_read_seq": 991,         // optional telemetry detail
     *   "send_tick_ms": 9876543         // optional telemetry detail
     * }
     */
    public function storeHttp(Request $request)
    {
        try {
            $validated = $request->validate([
                'device_id' => 'required|integer|exists:devices,id',
                'suhu' => 'required|numeric|between:-50,150',
                'kelembapan' => 'required|numeric|between:0,100',
                'timestamp_esp' => 'required|integer|min:1000000000|max:4102444800',
                'daya' => 'required|numeric|min:0',
                'packet_seq' => 'required|integer|min:1',
                'rssi_dbm' => 'required|integer|between:-120,0',
                'tx_duration_ms' => 'required|numeric|min:0',
                'payload_bytes' => 'required|integer|min:1',
                'uptime_s' => 'required|integer|min:0',
                'free_heap_bytes' => 'required|integer|min:0',
                'sensor_age_ms' => 'nullable|integer|min:0',
                'sensor_read_seq' => 'nullable|integer|min:0',
                'send_tick_ms' => 'nullable|integer|min:0',
            ]);

            $timestampServer = Carbon::now('UTC');
            $timestampEsp = Carbon::createFromTimestampUTC((int) $validated['timestamp_esp']);
            
            // Hitung latency dalam milliseconds
            $latencyMs = abs((float) $timestampServer->floatDiffInMilliseconds($timestampEsp));

            $eksperimen = Eksperimen::create([
                'device_id' => $validated['device_id'],
                'protokol' => 'HTTP',
                'suhu' => $validated['suhu'],
                'kelembapan' => $validated['kelembapan'],
                'timestamp_esp' => $timestampEsp,
                'timestamp_server' => $timestampServer,
                'latency_ms' => $latencyMs,
                'daya_mw' => $validated['daya'],
                'packet_seq' => $validated['packet_seq'],
                'rssi_dbm' => $validated['rssi_dbm'],
                'tx_duration_ms' => $validated['tx_duration_ms'],
                'payload_bytes' => $validated['payload_bytes'],
                'uptime_s' => $validated['uptime_s'],
                'free_heap_bytes' => $validated['free_heap_bytes'],
                'sensor_age_ms' => array_key_exists('sensor_age_ms', $validated) ? $validated['sensor_age_ms'] : null,
                'sensor_read_seq' => array_key_exists('sensor_read_seq', $validated) ? $validated['sensor_read_seq'] : null,
                'send_tick_ms' => array_key_exists('send_tick_ms', $validated) ? $validated['send_tick_ms'] : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data HTTP berhasil disimpan',
                'data' => $eksperimen,
                'required_fields_received' => [
                    'device_id' => $validated['device_id'],
                    'suhu' => $validated['suhu'],
                    'kelembapan' => $validated['kelembapan'],
                    'timestamp_esp' => $validated['timestamp_esp'],
                    'daya' => $validated['daya'],
                    'packet_seq' => $validated['packet_seq'],
                    'rssi_dbm' => $validated['rssi_dbm'],
                    'tx_duration_ms' => $validated['tx_duration_ms'],
                    'payload_bytes' => $validated['payload_bytes'],
                    'uptime_s' => $validated['uptime_s'],
                    'free_heap_bytes' => $validated['free_heap_bytes'],
                    'sensor_age_ms' => $validated['sensor_age_ms'] ?? null,
                    'sensor_read_seq' => $validated['sensor_read_seq'] ?? null,
                    'send_tick_ms' => $validated['send_tick_ms'] ?? null,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
