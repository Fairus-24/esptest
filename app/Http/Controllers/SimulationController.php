<?php

namespace App\Http\Controllers;

use App\Services\ApplicationSimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SimulationController extends Controller
{
    public function __construct(
        private readonly ApplicationSimulationService $simulationService
    ) {
    }

    public function index()
    {
        return view('simulation', [
            'simulationStatus' => $this->simulationService->status(),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->simulationService->status(),
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

        $status = $this->simulationService->start([
            'interval_seconds' => isset($validated['interval_seconds']) ? (int) $validated['interval_seconds'] : null,
            'http_fail_rate' => isset($validated['http_fail_rate']) ? (float) $validated['http_fail_rate'] : null,
            'mqtt_fail_rate' => isset($validated['mqtt_fail_rate']) ? (float) $validated['mqtt_fail_rate'] : null,
            'network_profile' => isset($validated['network_profile']) ? (string) $validated['network_profile'] : null,
            'reset_before_start' => (bool) ($validated['reset_before_start'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Simulasi aplikasi berhasil dimulai.',
            'data' => $status,
        ]);
    }

    public function stop(): JsonResponse
    {
        $status = $this->simulationService->stop();

        return response()->json([
            'success' => true,
            'message' => 'Simulasi aplikasi dihentikan.',
            'data' => $status,
        ]);
    }

    public function reset(): JsonResponse
    {
        $status = $this->simulationService->reset();

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
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjalankan tick simulasi.',
            ], 500);
        }
    }
}
