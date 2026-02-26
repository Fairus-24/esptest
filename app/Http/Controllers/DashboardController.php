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

    public function showResetPage()
    {
        return view('reset-data', $this->buildResetPagePayload());
    }

    // Reset all eksperimen data
    public function resetData()
    {
        $statusType = 'success';
        $statusMessage = 'Data eksperimen berhasil direset!';

        try {
            DB::transaction(function () {
                // Gunakan delete agar aman saat ada foreign key constraint.
                Eksperimen::query()->delete();
            });
        } catch (Throwable $e) {
            $statusType = 'error';
            $statusMessage = 'Gagal reset data eksperimen: ' . $e->getMessage();
        }

        $payload = $this->buildResetPagePayload();
        $payload['statusType'] = $statusType;
        $payload['statusMessage'] = $statusMessage;

        return response()->view('reset-data', $payload);
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
        $mqttAvgSuhu = $mqttData->whereNotNull('suhu')->avg('suhu');
        $mqttAvgKelembapan = $mqttData->whereNotNull('kelembapan')->avg('kelembapan');
        $httpAvgSuhu = $httpData->whereNotNull('suhu')->avg('suhu');
        $httpAvgKelembapan = $httpData->whereNotNull('kelembapan')->avg('kelembapan');

        $avgSuhu = collect([$mqttAvgSuhu, $httpAvgSuhu])
            ->filter(static fn($value) => $value !== null)
            ->avg() ?? 0;

        $avgKelembapan = collect([$mqttAvgKelembapan, $httpAvgKelembapan])
            ->filter(static fn($value) => $value !== null)
            ->avg() ?? 0;

        $displayTimezone = 'Asia/Jakarta'; // Surabaya timezone (WIB)

        $formatToWib = static function ($value) use ($displayTimezone): string {
            if (!$value) {
                return '-';
            }

            try {
                return (clone $value)->setTimezone($displayTimezone)->format('d-m-Y H:i:s') . ' WIB';
            } catch (\Throwable) {
                return '-';
            }
        };

        $latestMqtt = $mqttData->sortByDesc('id')->first();
        $latestHttp = $httpData->sortByDesc('id')->first();

        $buildProtocolDetail = static function ($row, string $protocol) use ($formatToWib) {
            if (!$row) {
                return [
                    'protocol' => $protocol,
                    'available' => false,
                ];
            }

            return [
                'protocol' => $protocol,
                'available' => true,
                'id' => (int) $row->id,
                'packet_seq' => $row->packet_seq !== null ? (int) $row->packet_seq : null,
                'suhu' => $row->suhu !== null ? (float) $row->suhu : null,
                'kelembapan' => $row->kelembapan !== null ? (float) $row->kelembapan : null,
                'latency_ms' => $row->latency_ms !== null ? (float) $row->latency_ms : null,
                'daya_mw' => $row->daya_mw !== null ? (float) $row->daya_mw : null,
                'rssi_dbm' => $row->rssi_dbm !== null ? (int) $row->rssi_dbm : null,
                'tx_duration_ms' => $row->tx_duration_ms !== null ? (float) $row->tx_duration_ms : null,
                'payload_bytes' => $row->payload_bytes !== null ? (int) $row->payload_bytes : null,
                'uptime_s' => $row->uptime_s !== null ? (int) $row->uptime_s : null,
                'free_heap_bytes' => $row->free_heap_bytes !== null ? (int) $row->free_heap_bytes : null,
                'sensor_age_ms' => $row->sensor_age_ms !== null ? (int) $row->sensor_age_ms : null,
                'sensor_read_seq' => $row->sensor_read_seq !== null ? (int) $row->sensor_read_seq : null,
                'send_tick_ms' => $row->send_tick_ms !== null ? (int) $row->send_tick_ms : null,
                'timestamp_esp' => $formatToWib($row->timestamp_esp),
                'timestamp_server' => $formatToWib($row->timestamp_server ?? $row->created_at),
            ];
        };

        $protocolDiagnostics = [
            'mqtt' => $buildProtocolDetail($latestMqtt, 'MQTT'),
            'http' => $buildProtocolDetail($latestHttp, 'HTTP'),
            'delta' => null,
            'pair_available' => false,
            'sensor_sync_note' => null,
        ];

        if ($latestMqtt && $latestHttp) {
            $mqttServerTs = $latestMqtt->timestamp_server ?? $latestMqtt->created_at;
            $httpServerTs = $latestHttp->timestamp_server ?? $latestHttp->created_at;
            $serverGapMs = ($mqttServerTs && $httpServerTs)
                ? abs((float) $mqttServerTs->floatDiffInMilliseconds($httpServerTs))
                : null;

            $deltaSuhu = ((float) $latestMqtt->suhu) - ((float) $latestHttp->suhu);
            $deltaKelembapan = ((float) $latestMqtt->kelembapan) - ((float) $latestHttp->kelembapan);

            $protocolDiagnostics['delta'] = [
                'suhu' => $deltaSuhu,
                'kelembapan' => $deltaKelembapan,
                'latency_ms' => ((float) $latestMqtt->latency_ms) - ((float) $latestHttp->latency_ms),
                'daya_mw' => ((float) $latestMqtt->daya_mw) - ((float) $latestHttp->daya_mw),
                'tx_duration_ms' => ((float) $latestMqtt->tx_duration_ms) - ((float) $latestHttp->tx_duration_ms),
                'payload_bytes' => ((int) $latestMqtt->payload_bytes) - ((int) $latestHttp->payload_bytes),
                'rssi_dbm' => ((int) $latestMqtt->rssi_dbm) - ((int) $latestHttp->rssi_dbm),
                'sensor_read_seq' => ((int) ($latestMqtt->sensor_read_seq ?? 0)) - ((int) ($latestHttp->sensor_read_seq ?? 0)),
                'send_tick_ms' => ((int) ($latestMqtt->send_tick_ms ?? 0)) - ((int) ($latestHttp->send_tick_ms ?? 0)),
                'sensor_age_ms' => ((int) ($latestMqtt->sensor_age_ms ?? 0)) - ((int) ($latestHttp->sensor_age_ms ?? 0)),
                'server_gap_ms' => $serverGapMs !== null ? $serverGapMs : null,
            ];
            $protocolDiagnostics['pair_available'] = true;

            $sameSensorReadSeq = isset($latestMqtt->sensor_read_seq, $latestHttp->sensor_read_seq)
                && ((int) $latestMqtt->sensor_read_seq === (int) $latestHttp->sensor_read_seq);

            if ($sameSensorReadSeq) {
                $protocolDiagnostics['sensor_sync_note'] = 'Peringatan: sensor_read_seq MQTT dan HTTP sama pada sampel terbaru. Ini mengindikasikan kedua payload kemungkinan memakai snapshot sensor yang sama.';
            } elseif (abs($deltaSuhu) < 0.01 && abs($deltaKelembapan) < 0.01) {
                $protocolDiagnostics['sensor_sync_note'] = 'Nilai suhu/kelembapan identik pada sampel terbaru. Data tetap dapat valid jika kondisi lingkungan stabil, namun pastikan sensor_read_seq berbeda antar protokol.';
            } else {
                $protocolDiagnostics['sensor_sync_note'] = 'Nilai suhu/kelembapan berbeda di sampel terbaru. Ini bisa terjadi karena timing kirim protokol tidak persis bersamaan.';
            }
        }

        // Data quality checks: kedua protokol wajib mengirim field lengkap yang sama.
        $requiredFields = [
            'suhu' => 'Suhu',
            'kelembapan' => 'Kelembapan',
            'timestamp_esp' => 'Timestamp ESP',
            'timestamp_server' => 'Timestamp Server',
            'latency_ms' => 'Latency',
            'daya_mw' => 'Daya',
            'packet_seq' => 'Packet Sequence',
            'rssi_dbm' => 'RSSI',
            'tx_duration_ms' => 'TX Duration',
            'payload_bytes' => 'Payload Bytes',
            'uptime_s' => 'Uptime',
            'free_heap_bytes' => 'Free Heap',
            'sensor_age_ms' => 'Sensor Age',
            'sensor_read_seq' => 'Sensor Read Seq',
            'send_tick_ms' => 'Send Tick',
        ];

        $protocolDataMap = [
            'MQTT' => $mqttData,
            'HTTP' => $httpData,
        ];
        $mqttTotal = $mqttData->count();
        $httpTotal = $httpData->count();

        $fieldCompleteness = [];
        $dataWarnings = [];

        foreach ($protocolDataMap as $protocol => $protocolData) {
            $qualityScope = $protocolData->whereNotNull('packet_seq');
            if ($qualityScope->isEmpty()) {
                $qualityScope = $protocolData;
            }
            $qualityScope = $qualityScope->sortByDesc('id')->take(200)->values();

            $total = $qualityScope->count();
            $fieldCompleteness[$protocol] = [
                'total' => $total,
                'fields' => [],
            ];

            if ($total === 0) {
                $dataWarnings[] = "{$protocol}: belum ada data masuk.";
                continue;
            }

            foreach ($requiredFields as $fieldKey => $fieldLabel) {
                $missing = $qualityScope->whereNull($fieldKey)->count();
                $valid = $total - $missing;

                $fieldCompleteness[$protocol]['fields'][$fieldKey] = [
                    'label' => $fieldLabel,
                    'valid' => $valid,
                    'missing' => $missing,
                    'total' => $total,
                ];

                if ($missing > 0) {
                    $dataWarnings[] = "{$protocol} {$fieldLabel}: {$missing}/{$total} data kosong.";
                }
            }
        }

        if ($mqttTotal !== $httpTotal) {
            $dataWarnings[] = "Jumlah data tidak seimbang: MQTT {$mqttTotal} vs HTTP {$httpTotal}.";
        }

        if (($reliability['mqtt_missing_packets'] ?? 0) > 0) {
            $dataWarnings[] = "MQTT packet loss terdeteksi: {$reliability['mqtt_missing_packets']} packet hilang (seq gap).";
        }
        if (($reliability['http_missing_packets'] ?? 0) > 0) {
            $dataWarnings[] = "HTTP packet loss terdeteksi: {$reliability['http_missing_packets']} packet hilang (seq gap).";
        }
        if (($reliability['mqtt_expected_packets'] ?? 0) === 0 && $mqttTotal > 0) {
            $dataWarnings[] = "MQTT belum memiliki telemetry packet_seq. Pastikan firmware ESP32 terbaru sudah terpasang.";
        }
        if (($reliability['http_expected_packets'] ?? 0) === 0 && $httpTotal > 0) {
            $dataWarnings[] = "HTTP belum memiliki telemetry packet_seq. Pastikan firmware ESP32 terbaru sudah terpasang.";
        }
        if (($reliability['mqtt_transmission_health'] ?? 100) < 80 && $mqttTotal > 0) {
            $dataWarnings[] = "MQTT transmission health rendah ({$reliability['mqtt_transmission_health']}%). Cek RSSI, broker, atau kestabilan jaringan.";
        }
        if (($reliability['http_transmission_health'] ?? 100) < 80 && $httpTotal > 0) {
            $dataWarnings[] = "HTTP transmission health rendah ({$reliability['http_transmission_health']}%). Cek server API, endpoint, atau kestabilan jaringan.";
        }
        if (($summary['mqtt']['std_daya'] ?? 0) < 0.5 && ($summary['mqtt']['total_data'] ?? 0) >= 20) {
            $dataWarnings[] = "Variasi daya MQTT sangat rendah (std < 0.5). Data cenderung konstan, cek perhitungan daya firmware.";
        }
        if (($summary['http']['std_daya'] ?? 0) < 0.5 && ($summary['http']['total_data'] ?? 0) >= 20) {
            $dataWarnings[] = "Variasi daya HTTP sangat rendah (std < 0.5). Data cenderung konstan, cek perhitungan daya firmware.";
        }
        if (($protocolDiagnostics['pair_available'] ?? false) && isset($protocolDiagnostics['delta'])) {
            $diagDelta = $protocolDiagnostics['delta'];
            $sensorReadGap = isset($diagDelta['sensor_read_seq']) ? (int) $diagDelta['sensor_read_seq'] : null;
            $sendTickGap = isset($diagDelta['send_tick_ms']) ? (int) $diagDelta['send_tick_ms'] : null;
            $tempGap = isset($diagDelta['suhu']) ? abs((float) $diagDelta['suhu']) : null;
            $humidityGap = isset($diagDelta['kelembapan']) ? abs((float) $diagDelta['kelembapan']) : null;

            if ($sensorReadGap !== null && $sensorReadGap === 0) {
                $dataWarnings[] = "Validasi protokol: sensor_read_seq MQTT dan HTTP terbaru sama. Indikasi snapshot sensor dipakai bersama, bukan pembacaan terpisah.";
            }
            if ($sendTickGap !== null && $sendTickGap === 0) {
                $dataWarnings[] = "Validasi protokol: send_tick_ms MQTT dan HTTP terbaru sama. Cek firmware agar setiap protokol melakukan pembacaan/kirim terpisah.";
            }
            if ($tempGap !== null && $humidityGap !== null && $tempGap < 0.0000005 && $humidityGap < 0.0000005 && $sensorReadGap === 0) {
                $dataWarnings[] = "Suhu dan kelembapan antar protokol identik hingga presisi tinggi dengan sensor_read_seq yang sama. Periksa potensi data duplikat lintas protokol.";
            }
        }

        // Host mismatch checks: tampilkan warning jelas jika konfigurasi host broker bermasalah.
        $mqttHost = trim((string) config('mqtt.host', '127.0.0.1'));
        $mqttPort = max(1, (int) config('mqtt.port', 1883));
        $localhostBrokerReachable = $this->isTcpReachable('127.0.0.1', $mqttPort);
        $configuredBrokerReachable = $this->isTcpReachable($mqttHost, $mqttPort);
        $mqttHostIsLocal = $this->isLocalHost($mqttHost);
        $mosquittoLocalOnly = (bool) config('mosquitto.only_for_local_host', true);

        if (!$configuredBrokerReachable && $localhostBrokerReachable) {
            $dataWarnings[] = "Host mismatch terdeteksi: MQTT_HOST={$mqttHost}:{$mqttPort} tidak bisa diakses, tetapi broker lokal 127.0.0.1:{$mqttPort} aktif. Periksa IP host di .env dan firmware ESP32.";
        } elseif (!$configuredBrokerReachable && !$localhostBrokerReachable && !$mqttConnected) {
            $dataWarnings[] = "Broker MQTT tidak terjangkau pada host konfigurasi ({$mqttHost}:{$mqttPort}). Cek Mosquitto service, firewall, dan IP host.";
        }

        if ($mosquittoLocalOnly && !$mqttHostIsLocal) {
            $dataWarnings[] = "Konfigurasi berpotensi mismatch: MOSQUITTO_ONLY_LOCAL=true, tetapi MQTT_HOST={$mqttHost} bukan host lokal. Gunakan 127.0.0.1/localhost atau ubah konfigurasi.";
        }

        $dataWarnings = array_values(array_unique($dataWarnings));

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
                    'daya_mw' => $point->daya_mw !== null ? (float) $point->daya_mw : null,
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
        // Prepare data untuk Chart.js - Power Comparison (per data point, urutan realtime)
        $powerChartData = [
            'labels' => [],
            'time_labels' => [],
            'full_time_labels' => [],
            'mqtt' => [],
            'http' => [],
            'total_points' => 0,
            'display_timezone' => 'Asia/Jakarta',
        ];
        $powerSequence = 0;
        foreach ($latencyPoints as $point) {
            $powerSequence++;
            $timestampDisplay = $point['timestamp_display'];
            $shortTime = $timestampDisplay ? $timestampDisplay->format('H:i:s') . ' WIB' : '-';
            $fullTime = $timestampDisplay ? $timestampDisplay->format('d-m-Y H:i:s') . ' WIB' : '-';

            $powerChartData['labels'][] = $powerSequence;
            $powerChartData['time_labels'][] = $shortTime;
            $powerChartData['full_time_labels'][] = $fullTime;
            $powerChartData['mqtt'][] = $point['protocol'] === 'MQTT'
                ? ($point['daya_mw'] !== null ? round($point['daya_mw'], 2) : null)
                : null;
            $powerChartData['http'][] = $point['protocol'] === 'HTTP'
                ? ($point['daya_mw'] !== null ? round($point['daya_mw'], 2) : null)
                : null;
        }
        $powerChartData['total_points'] = $powerSequence;

        return view('dashboard', compact(
            'summary', 'reliability', 'latencyChartData', 'powerChartData', 'mqttTotal', 'httpTotal',
            'mqttConnected', 'httpConnected', 'mqttAvgSuhu', 'mqttAvgKelembapan', 'httpAvgSuhu', 'httpAvgKelembapan',
            'avgSuhu', 'avgKelembapan', 'fieldCompleteness', 'dataWarnings', 'protocolDiagnostics'
        ));
    }

    private function isTcpReachable(string $host, int $port, float $timeoutSeconds = 0.35): bool
    {
        $targetHost = trim($host);
        if ($targetHost === '' || $port < 1) {
            return false;
        }

        $socket = @fsockopen($targetHost, $port, $errno, $errstr, $timeoutSeconds);
        if ($socket === false) {
            return false;
        }

        fclose($socket);
        return true;
    }

    private function isLocalHost(string $host): bool
    {
        $normalized = strtolower(trim($host));
        if (in_array($normalized, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        $resolved = filter_var($normalized, FILTER_VALIDATE_IP) ? $normalized : gethostbyname($normalized);
        if (!filter_var($resolved, FILTER_VALIDATE_IP)) {
            return false;
        }

        $localIps = ['127.0.0.1', '::1'];
        $hostnameIps = @gethostbynamel(gethostname());
        if (is_array($hostnameIps)) {
            $localIps = array_merge($localIps, $hostnameIps);
        }

        $serverAddr = request()->server('SERVER_ADDR');
        if (is_string($serverAddr) && $serverAddr !== '') {
            $localIps[] = $serverAddr;
        }

        return in_array($resolved, array_unique($localIps), true);
    }

    private function buildResetPagePayload(): array
    {
        $totalRows = Eksperimen::query()->count();
        $mqttRows = Eksperimen::query()->whereRaw('UPPER(protokol) = ?', ['MQTT'])->count();
        $httpRows = Eksperimen::query()->whereRaw('UPPER(protokol) = ?', ['HTTP'])->count();

        $latestRecord = Eksperimen::query()
            ->select(['id', 'protokol', 'timestamp_server', 'created_at'])
            ->latest('id')
            ->first();

        $latestWib = '-';
        if ($latestRecord) {
            $timestampSource = $latestRecord->timestamp_server ?? $latestRecord->created_at;
            if ($timestampSource) {
                try {
                    $latestWib = (clone $timestampSource)->setTimezone('Asia/Jakarta')->format('d-m-Y H:i:s') . ' WIB';
                } catch (Throwable) {
                    $latestWib = '-';
                }
            }
        }

        return [
            'totalRows' => $totalRows,
            'mqttRows' => $mqttRows,
            'httpRows' => $httpRows,
            'latestWib' => $latestWib,
            'statusType' => null,
            'statusMessage' => null,
        ];
    }
}
