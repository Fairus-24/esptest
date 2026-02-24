<?php

namespace App\Http\Controllers;

use App\Models\Eksperimen;
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
     *   "timestamp_esp": 1708884000,
     *   "daya": 100
     * }
     */
    public function storeHttp(Request $request)
    {
        try {
            $validated = $request->validate([
                'device_id' => 'required|integer|exists:devices,id',
                'suhu' => 'required|numeric',
                'timestamp_esp' => 'required|integer',
                'daya' => 'required|numeric',
            ]);

            $timestampServer = now();
            $timestampEsp = \DateTime::createFromFormat('U', $validated['timestamp_esp'])->setTimezone(new \DateTimeZone('UTC'));
            
            // Hitung latency dalam milliseconds
            $latencyMs = $timestampServer->diffInMilliseconds($timestampEsp);

            $eksperimen = Eksperimen::create([
                'device_id' => $validated['device_id'],
                'protokol' => 'HTTP',
                'suhu' => $validated['suhu'],
                'timestamp_esp' => $timestampEsp,
                'timestamp_server' => $timestampServer,
                'latency_ms' => $latencyMs,
                'daya_mw' => $validated['daya'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data HTTP berhasil disimpan',
                'data' => $eksperimen,
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
