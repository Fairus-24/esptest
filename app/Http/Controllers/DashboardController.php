<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use App\Models\Eksperimen;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Throwable;


class DashboardController extends Controller
{
    protected $statisticsService;

    // Reset all eksperimen data
    public function resetData()
    {
        try {
            DB::transaction(function () {
                // Gunakan delete agar aman saat ada foreign key constraint.
                Eksperimen::query()->delete();
            });

            return redirect()
                ->route('dashboard')
                ->with('status', 'Data eksperimen berhasil direset!');
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'Gagal reset data eksperimen: ' . $e->getMessage());
        }
    }

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

        // Status koneksi: jika data baru dalam 30 detik terakhir dianggap connected
        $now = now();
        $mqttLast = $mqttData->max('created_at');
        $httpLast = $httpData->max('created_at');
        $mqttConnected = $mqttLast && $now->diffInSeconds($mqttLast) < 30;
        $httpConnected = $httpLast && $now->diffInSeconds($httpLast) < 30;

        // Statistik suhu & kelembapan
        $mqttAvgSuhu = $mqttData->avg('suhu') ?? 0;
        $mqttAvgKelembapan = $mqttData->avg('kelembapan') ?? 0;
        $httpAvgSuhu = $httpData->avg('suhu') ?? 0;
        $httpAvgKelembapan = $httpData->avg('kelembapan') ?? 0;

        // Prepare data untuk Chart.js - Latency Comparison (per data point, line chart)
        // X-axis dibuat berdasarkan urutan data supaya jumlah titik selalu sama dengan total data point.
        $latencyChartData = [
            'labels' => [], // index 1..N (N = total data points)
            'time_labels' => [], // short time label per index
            'full_time_labels' => [], // full time label per index
            'datasets' => [],
            'total_points' => 0,
            'display_timezone' => 'Asia/Jakarta',
        ];

        $displayTimezone = 'Asia/Jakarta'; // Surabaya timezone (WIB)
        $deviceNames = Device::pluck('nama_device', 'id');
        $latencyPoints = $mqttData
            ->merge($httpData)
            ->map(function ($point) use ($deviceNames, $displayTimezone) {
                $timestampRaw = $point->timestamp_server ?? $point->created_at;
                $timestampSort = $timestampRaw ? (clone $timestampRaw) : null;
                $timestampDisplay = $timestampRaw ? (clone $timestampRaw)->setTimezone($displayTimezone) : null;
                return [
                    'id' => (int) $point->id,
                    'protocol' => strtoupper((string) $point->protokol),
                    'device_id' => (int) $point->device_id,
                    'device_name' => $deviceNames[$point->device_id] ?? ('Device ' . $point->device_id),
                    'latency_ms' => $point->latency_ms !== null ? (float) $point->latency_ms : null,
                    'timestamp_sort' => $timestampSort,
                    'timestamp_display' => $timestampDisplay,
                ];
            })
            ->sortBy(function ($point) {
                $sortTs = $point['timestamp_sort'] ? $point['timestamp_sort']->format('U.u') : '';
                $sortId = str_pad((string) $point['id'], 10, '0', STR_PAD_LEFT);
                return $sortTs . '|' . $sortId;
            })
            ->values();

        $datasetsByKey = [];
        $sequence = 0;
        foreach ($latencyPoints as $point) {
            $sequence++;
            $isMqtt = $point['protocol'] === 'MQTT';
            $protocol = $isMqtt ? 'MQTT' : 'HTTP';
            $datasetKey = $protocol . '|' . $point['device_id'];
            $timestampDisplay = $point['timestamp_display'];
            $shortTime = $timestampDisplay ? $timestampDisplay->format('H:i:s') . ' WIB' : '-';
            $fullTime = $timestampDisplay ? $timestampDisplay->format('d-m-Y H:i:s') . ' WIB' : '-';

            $latencyChartData['labels'][] = $sequence;
            $latencyChartData['time_labels'][] = $shortTime;
            $latencyChartData['full_time_labels'][] = $fullTime;

            if (!isset($datasetsByKey[$datasetKey])) {
                $datasetsByKey[$datasetKey] = [
                    'label' => $protocol . ' - ' . $point['device_name'],
                    'data' => [],
                    'borderColor' => $isMqtt ? '#0066ff' : '#00cc88',
                    'backgroundColor' => $isMqtt ? 'rgba(0, 102, 255, 0.15)' : 'rgba(0, 204, 136, 0.15)',
                    'pointBackgroundColor' => $isMqtt ? '#0066ff' : '#00cc88',
                    'pointBorderColor' => $isMqtt ? '#0066ff' : '#00cc88',
                ];
            }

            $datasetsByKey[$datasetKey]['data'][] = [
                'x' => $sequence,
                'y' => $point['latency_ms'],
                'device' => $point['device_name'],
                'timestamp' => $fullTime,
                'point_index' => $sequence,
            ];
        }

        $latencyChartData['datasets'] = array_values($datasetsByKey);
        $latencyChartData['total_points'] = $sequence;
        // Prepare data untuk Chart.js - Power Consumption Comparison (per device, rata-rata)
        $devices = Device::all();
        $powerChartData = [
            'labels' => [],
            'mqtt' => [],
            'http' => [],
        ];
        foreach ($devices as $device) {
            $powerChartData['labels'][] = $device->nama_device;
            // Rata-rata daya per device untuk masing-masing protokol
            $mqttAvg = Eksperimen::where('device_id', $device->id)->where('protokol', 'MQTT')->avg('daya_mw');
            $httpAvg = Eksperimen::where('device_id', $device->id)->where('protokol', 'HTTP')->avg('daya_mw');
            $powerChartData['mqtt'][] = $mqttAvg !== null ? round($mqttAvg, 2) : 0;
            $powerChartData['http'][] = $httpAvg !== null ? round($httpAvg, 2) : 0;
        }
        $mqttTotal = $mqttData->count();
        $httpTotal = $httpData->count();
        return view('dashboard', compact(
            'summary', 'reliability', 'latencyChartData', 'powerChartData', 'mqttTotal', 'httpTotal',
            'mqttConnected', 'httpConnected', 'mqttAvgSuhu', 'mqttAvgKelembapan', 'httpAvgSuhu', 'httpAvgKelembapan'
        ));
    }
}
