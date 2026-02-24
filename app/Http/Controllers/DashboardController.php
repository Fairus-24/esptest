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
        // Ambil summary statistik
        $summary = $this->statisticsService->getSummary();
        $reliability = $this->statisticsService->getReliability();

        // Ambil data untuk grafik
        $mqttData = $this->statisticsService->getMqttData();
        $httpData = $this->statisticsService->getHttpData();

        // Prepare data untuk Chart.js - Latency Comparison
        $latencyChartData = [
            'labels' => [],
            'mqtt' => [],
            'http' => [],
        ];

        // Group by device dan compare latency
        $devices = $mqttData->pluck('device_id')->merge($httpData->pluck('device_id'))->unique();
        
        foreach ($devices as $deviceId) {
            $mqttAvg = $mqttData->where('device_id', $deviceId)->avg('latency_ms');
            $httpAvg = $httpData->where('device_id', $deviceId)->avg('latency_ms');
            
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
            $mqttAvg = $mqttData->where('device_id', $deviceId)->avg('daya_mw');
            $httpAvg = $httpData->where('device_id', $deviceId)->avg('daya_mw');
            
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
