<?php

namespace App\Http\Controllers;

use App\Models\Eksperimen;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     *   "sensor_age_ms": 1530,          // required telemetry detail
     *   "sensor_read_seq": 991,         // required telemetry detail
     *   "send_tick_ms": 9876543         // required telemetry detail
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
                'sensor_age_ms' => 'required|integer|min:0',
                'sensor_read_seq' => 'required|integer|min:0',
                'send_tick_ms' => 'required|integer|min:0',
            ]);

            $timestampServer = Carbon::now('UTC');
            $timestampEsp = Carbon::createFromTimestampUTC((int) $validated['timestamp_esp']);
            
            // Hitung latency dalam milliseconds
            $latencyMs = abs((float) $timestampServer->floatDiffInMilliseconds($timestampEsp));

            $eksperimen = $this->upsertHttpRecord($validated, $timestampServer, $timestampEsp, $latencyMs);

            return response()->json([
                'success' => true,
                'message' => $eksperimen->wasRecentlyCreated
                    ? 'Data HTTP berhasil disimpan'
                    : 'Data HTTP duplikat diperbarui (idempotent upsert)',
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
                    'sensor_age_ms' => $validated['sensor_age_ms'],
                    'sensor_read_seq' => $validated['sensor_read_seq'],
                    'send_tick_ms' => $validated['send_tick_ms'],
                ],
            ], $eksperimen->wasRecentlyCreated ? 201 : 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);

        } catch (Throwable $e) {
            Log::error('HTTP ingest failed.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'device_id' => $request->input('device_id'),
                'packet_seq' => $request->input('packet_seq'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server saat menyimpan data HTTP.',
            ], 500);
        }
    }

    private function upsertHttpRecord(array $validated, Carbon $timestampServer, Carbon $timestampEsp, float $latencyMs): Eksperimen
    {
        $identity = [
            'device_id' => (int) $validated['device_id'],
            'protokol' => 'HTTP',
            'packet_seq' => (int) $validated['packet_seq'],
        ];

        $attributes = [
            'suhu' => $validated['suhu'],
            'kelembapan' => $validated['kelembapan'],
            'timestamp_esp' => $timestampEsp,
            'timestamp_server' => $timestampServer,
            'latency_ms' => $latencyMs,
            'daya_mw' => $validated['daya'],
            'rssi_dbm' => $validated['rssi_dbm'],
            'tx_duration_ms' => $validated['tx_duration_ms'],
            'payload_bytes' => $validated['payload_bytes'],
            'uptime_s' => $validated['uptime_s'],
            'free_heap_bytes' => $validated['free_heap_bytes'],
            'sensor_age_ms' => $validated['sensor_age_ms'],
            'sensor_read_seq' => $validated['sensor_read_seq'],
            'send_tick_ms' => $validated['send_tick_ms'],
        ];

        try {
            return Eksperimen::query()->updateOrCreate($identity, $attributes);
        } catch (QueryException $e) {
            if (!$this->isDuplicateKeyException($e)) {
                throw $e;
            }

            // Jika ada race condition pada unique index, ambil baris yang sudah ada lalu update.
            $existing = Eksperimen::query()->where($identity)->first();
            if ($existing === null) {
                throw $e;
            }

            $existing->fill($attributes);
            $existing->save();

            return $existing;
        }
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
    }
}
