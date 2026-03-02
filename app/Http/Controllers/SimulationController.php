<?php

namespace App\Http\Controllers;

use App\Services\ApplicationSimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SimulationController extends Controller
{
    public function __construct(
        private readonly ApplicationSimulationService $simulationService
    ) {
    }

    public function index()
    {
        try {
            $simulationStatus = $this->simulationService->status();
        } catch (Throwable $e) {
            Log::warning('Simulation page fallback triggered.', [
                'error' => $e->getMessage(),
            ]);
            $simulationStatus = $this->fallbackStatus('Storage simulasi tidak siap. Periksa migrasi dan koneksi database server.');
        }

        return view('simulation', [
            'simulationStatus' => $simulationStatus,
        ]);
    }

    public function status(): JsonResponse
    {
        try {
            $status = $this->simulationService->status();
        } catch (Throwable $e) {
            Log::warning('Simulation status endpoint fallback triggered.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Storage simulasi tidak siap. Periksa migrasi dan koneksi database server.',
                'data' => $this->fallbackStatus('Storage simulasi tidak siap. Periksa migrasi dan koneksi database server.'),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interval_seconds' => ['nullable', 'integer', 'min:1', 'max:30'],
            'http_fail_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'mqtt_fail_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'network_profile' => ['nullable', 'string', 'in:stable,normal,stress'],
            'reset_before_start' => ['nullable', 'boolean'],
        ]);

        try {
            $status = $this->simulationService->start([
                'interval_seconds' => isset($validated['interval_seconds']) ? (int) $validated['interval_seconds'] : null,
                'http_fail_rate' => isset($validated['http_fail_rate']) ? (float) $validated['http_fail_rate'] : null,
                'mqtt_fail_rate' => isset($validated['mqtt_fail_rate']) ? (float) $validated['mqtt_fail_rate'] : null,
                'network_profile' => isset($validated['network_profile']) ? (string) $validated['network_profile'] : null,
                'reset_before_start' => (bool) ($validated['reset_before_start'] ?? false),
            ]);
        } catch (Throwable $e) {
            Log::warning('Simulation start endpoint failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai simulasi. Periksa storage simulasi dan database server.',
                'data' => $this->fallbackStatus('Storage simulasi tidak siap. Periksa migrasi dan koneksi database server.'),
            ], 500);
        }

        if (($status['storage_ready'] ?? true) !== true) {
            return response()->json([
                'success' => false,
                'message' => (string) ($status['storage_error'] ?? 'Storage simulasi tidak siap.'),
                'data' => $status,
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Simulasi aplikasi berhasil dimulai.',
            'data' => $status,
        ]);
    }

    public function stop(): JsonResponse
    {
        try {
            $status = $this->simulationService->stop();
        } catch (Throwable $e) {
            Log::warning('Simulation stop endpoint failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghentikan simulasi. Periksa koneksi database server.',
                'data' => $this->fallbackStatus('Storage simulasi tidak siap. Periksa migrasi dan koneksi database server.'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Simulasi aplikasi dihentikan.',
            'data' => $status,
        ]);
    }

    public function reset(): JsonResponse
    {
        try {
            $status = $this->simulationService->reset();
        } catch (Throwable $e) {
            Log::warning('Simulation reset endpoint failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal reset data simulasi. Periksa storage simulasi dan database server.',
                'data' => $this->fallbackStatus('Storage simulasi tidak siap. Periksa migrasi dan koneksi database server.'),
            ], 500);
        }

        if (($status['storage_ready'] ?? true) !== true) {
            return response()->json([
                'success' => false,
                'message' => (string) ($status['storage_error'] ?? 'Storage simulasi tidak siap.'),
                'data' => $status,
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data simulasi berhasil direset.',
            'data' => $status,
        ]);
    }

    public function tick(): JsonResponse
    {
        try {
            $result = $this->simulationService->tick();

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::warning('Simulation tick endpoint failed.', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjalankan tick simulasi.',
                'data' => [
                    'ran' => false,
                    'reason' => 'tick_exception',
                    'status' => $this->fallbackStatus('Storage simulasi tidak siap. Periksa migrasi dan koneksi database server.'),
                ],
            ], 500);
        }
    }

    private function fallbackStatus(string $storageError): array
    {
        return [
            'running' => false,
            'device_id' => null,
            'device_name' => 'SIMULATOR-APP',
            'interval_seconds' => 5,
            'http_fail_rate' => 0.08,
            'mqtt_fail_rate' => 0.12,
            'tick_count' => 0,
            'esp_uptime_s' => 0,
            'started_at' => null,
            'last_tick_at' => null,
            'http_packet_seq' => 0,
            'mqtt_packet_seq' => 0,
            'sensor_read_seq' => 0,
            'base_temp' => 28.0,
            'base_humidity' => 60.0,
            'network_profile' => 'normal',
            'network_mode' => 'steady',
            'network_mode_ticks_left' => 0,
            'network_health' => 86.0,
            'mqtt_total_rows' => 0,
            'http_total_rows' => 0,
            'total_rows' => 0,
            'storage_ready' => false,
            'storage_error' => $storageError,
        ];
    }
}
