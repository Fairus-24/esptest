<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use App\Models\Eksperimen;

class DashboardController extends Controller
{
    protected $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function index()
    {
        // Ambil data MQTT dan HTTP sekali saja
        $mqttData = $this->statisticsService->getMqttData();
        $httpData = $this->statisticsService->getHttpData();

        // Ambil summary statistik
        $summary = $this->statisticsService->getSummary();
        $reliability = $this->statisticsService->getReliability();

        // Prepare data untuk Chart.js - Latency Comparison
        $latencyChartData = [
            'labels' => [],
            'mqtt' => [],
            'http' => [],
        ];

        // Group by device dan compare latency - gunakan unique devices saja
        $devices = collect()
            ->merge($mqttData->pluck('device_id')->unique())
            ->merge($httpData->pluck('device_id')->unique())
            ->unique()
            ->sort()
            ->values();
        
        foreach ($devices as $deviceId) {
            $mqttAvg = $mqttData->where('device_id', $deviceId)->avg('latency_ms') ?? 0;
            $httpAvg = $httpData->where('device_id', $deviceId)->avg('latency_ms') ?? 0;
            
            // Hanya tambah ke chart jika ada minimal satu data
            if ($mqttAvg > 0 || $httpAvg > 0) {
                $latencyChartData['labels'][] = 'Device ' . $deviceId;
                $latencyChartData['mqtt'][] = round($mqttAvg, 2);
                $latencyChartData['http'][] = round($httpAvg, 2);
            }
        }

        // Prepare data untuk Chart.js - Power Consumption Comparison
        $powerChartData = [
            'labels' => [],
            'mqtt' => [],
            'http' => [],
        ];

        foreach ($devices as $deviceId) {
            $mqttAvg = $mqttData->where('device_id', $deviceId)->avg('daya_mw') ?? 0;
            $httpAvg = $httpData->where('device_id', $deviceId)->avg('daya_mw') ?? 0;
            
            // Hanya tambah ke chart jika ada minimal satu data
            if ($mqttAvg > 0 || $httpAvg > 0) {
                $powerChartData['labels'][] = 'Device ' . $deviceId;
                $powerChartData['mqtt'][] = round($mqttAvg, 2);
                $powerChartData['http'][] = round($httpAvg, 2);
            }
        }

        // Data timeline untuk analisis trend
        $timelineData = [
            'mqtt' => $mqttData->groupBy(function($item) {
                return $item->created_at->format('H:00');
            })->map(function($items) {
                return round($items->avg('latency_ms'), 2);
            }),
            'http' => $httpData->groupBy(function($item) {
                return $item->created_at->format('H:00');
            })->map(function($items) {
                return round($items->avg('latency_ms'), 2);
            }),
        ];

        return view('dashboard', [
            'summary' => $summary,
            'reliability' => $reliability,
            'latencyChartData' => $latencyChartData,
            'powerChartData' => $powerChartData,
            'timelineData' => $timelineData,
            'mqttTotal' => $mqttData->count(),
            'httpTotal' => $httpData->count(),
        ]);
    }
}
