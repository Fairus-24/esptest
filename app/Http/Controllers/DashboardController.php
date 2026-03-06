<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use App\Models\Eksperimen;
use App\Models\SimulatedEksperimen;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Carbon\Carbon;
use Carbon\CarbonInterface;


class DashboardController extends Controller
{
    protected $statisticsService;
    private string $telemetrySource = 'real';
    private string $telemetryModelClass = Eksperimen::class;

    public function showResetPage()
    {
        return $this->renderResetPage($this->buildResetPagePayload());
    }

    // Reset all eksperimen data
    public function resetData(Request $request)
    {
        $rules = [
            'confirm_risk' => ['required', 'accepted'],
            'confirm_text' => ['required', 'string', 'max:16', 'regex:/^reset$/i'],
        ];
        $messages = [
            'confirm_risk.accepted' => 'Checklist konfirmasi risiko wajib diaktifkan sebelum reset.',
            'confirm_text.regex' => 'Konfirmasi teks harus tepat: RESET',
        ];
        $validated = $request->validate($rules, $messages);

        $confirmText = strtoupper(trim((string) ($validated['confirm_text'] ?? '')));
        if ($confirmText !== 'RESET') {
            return back()
                ->withErrors(['confirm_text' => 'Konfirmasi teks harus tepat: RESET'])
                ->withInput();
        }

        $statusType = 'success';
        $statusMessage = 'Data eksperimen berhasil direset!';
        $deletedRows = 0;

        try {
            DB::transaction(function () use (&$deletedRows) {
                // Gunakan delete agar aman saat ada foreign key constraint.
                $deletedRows = Eksperimen::query()->count();
                if ($deletedRows > 0) {
                    Eksperimen::query()->delete();
                }
            });

            if ($deletedRows === 0) {
                $statusMessage = 'Tidak ada data eksperimen untuk direset.';
            } else {
                $statusMessage = "Data eksperimen berhasil direset ({$deletedRows} baris dihapus).";
            }
        } catch (Throwable $e) {
            $statusType = 'error';
            $statusMessage = 'Gagal reset data eksperimen karena kesalahan internal server.';
            Log::error('Reset data eksperimen gagal.', [
                'message' => $e->getMessage(),
            ]);
        }

        $payload = $this->buildResetPagePayload();
        $payload['statusType'] = $statusType;
        $payload['statusMessage'] = $statusMessage;

        return $this->renderResetPage($payload);
    }

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function index(Request $request)
    {
        $this->configureTelemetrySource((string) $request->query('source', 'real'));
        $this->statisticsService->setTelemetrySource($this->telemetrySource);

        if (!$this->isDatabaseReachable()) {
            $payload = $this->buildDashboardFallbackPayload(
                'Database MySQL tidak terhubung. Dashboard menampilkan mode aman. Aktifkan MySQL/XAMPP pada 127.0.0.1:3306 agar data realtime kembali normal.'
            );
            $payload['telemetrySource'] = $this->telemetrySource;

            return $this->renderDashboardPage($payload);
        }

        // Ambil data MQTT dan HTTP sekali saja
        $mqttData = $this->statisticsService->getMqttData();
        $httpData = $this->statisticsService->getHttpData();
        $chartWindowSize = $this->resolveChartWindowSize();
        $mqttChartData = $this->getChartProtocolData('MQTT', $chartWindowSize);
        $httpChartData = $this->getChartProtocolData('HTTP', $chartWindowSize);

        // Ambil summary statistik
        $summary = $this->statisticsService->getSummary($mqttData, $httpData);
        $reliability = $this->statisticsService->getReliability();
        $protocolTotals = $this->resolveProtocolTotals();
        $summary['mqtt']['total_data'] = $protocolTotals['MQTT'];
        $summary['http']['total_data'] = $protocolTotals['HTTP'];

        $now = now();
        $connectionConfig = $this->resolveConnectionConfig();
        $simulationRunning = $this->isSimulationRunningFromStateFile();
        $excludeSimulatorStatusSource = $this->telemetrySource === 'real'
            && $connectionConfig['ignore_simulator_when_stopped']
            && !$simulationRunning;
        $excludedDeviceIds = $this->resolveExcludedDeviceIdsForStatus($excludeSimulatorStatusSource, $now);

        $latestMqttResolution = $this->resolveLatestProtocolRowForStatus('MQTT', $excludedDeviceIds);
        $latestHttpResolution = $this->resolveLatestProtocolRowForStatus('HTTP', $excludedDeviceIds);
        $latestIncomingResolution = $this->resolveLatestIncomingRowForStatus($excludedDeviceIds);
        $latestMqtt = $latestMqttResolution['row'] ?? null;
        $latestHttp = $latestHttpResolution['row'] ?? null;
        $latestEsp32IncomingRow = $latestIncomingResolution['row'] ?? null;
        $latestEsp32DebugHeartbeat = $this->resolveEsp32DebugHeartbeat($excludedDeviceIds, $now);

        $mqttConnectionStatus = $this->buildProtocolConnectionStatus(
            'MQTT',
            $latestMqtt,
            $now,
            $connectionConfig['protocol_freshness_seconds'],
            $latestMqttResolution
        );
        $httpConnectionStatus = $this->buildProtocolConnectionStatus(
            'HTTP',
            $latestHttp,
            $now,
            $connectionConfig['protocol_freshness_seconds'],
            $latestHttpResolution
        );
        $esp32ConnectionStatus = $this->buildEsp32ConnectionStatus(
            $latestEsp32IncomingRow,
            $now,
            $connectionConfig['esp32_freshness_seconds'],
            $latestEsp32DebugHeartbeat,
            $latestIncomingResolution
        );

        $mqttConnected = (bool) ($mqttConnectionStatus['connected'] ?? false);
        $httpConnected = (bool) ($httpConnectionStatus['connected'] ?? false);
        $esp32Connected = (bool) ($esp32ConnectionStatus['connected'] ?? false);

        // Header suhu & kelembapan harus mengikuti telemetry fresh, bukan histori lama.
        $mqttHeaderSuhu = $this->resolveFreshProtocolMetricValue($latestMqtt, $mqttConnectionStatus, 'suhu');
        $mqttHeaderKelembapan = $this->resolveFreshProtocolMetricValue($latestMqtt, $mqttConnectionStatus, 'kelembapan');
        $httpHeaderSuhu = $this->resolveFreshProtocolMetricValue($latestHttp, $httpConnectionStatus, 'suhu');
        $httpHeaderKelembapan = $this->resolveFreshProtocolMetricValue($latestHttp, $httpConnectionStatus, 'kelembapan');

        $mqttAvgSuhu = $mqttHeaderSuhu ?? 0.0;
        $mqttAvgKelembapan = $mqttHeaderKelembapan ?? 0.0;
        $httpAvgSuhu = $httpHeaderSuhu ?? 0.0;
        $httpAvgKelembapan = $httpHeaderKelembapan ?? 0.0;

        $avgSuhu = $this->calculateFreshCombinedMetric([$mqttHeaderSuhu, $httpHeaderSuhu]);
        $avgKelembapan = $this->calculateFreshCombinedMetric([$mqttHeaderKelembapan, $httpHeaderKelembapan]);
        $headerSuhuDelta = ($mqttHeaderSuhu !== null && $httpHeaderSuhu !== null)
            ? $mqttHeaderSuhu - $httpHeaderSuhu
            : null;
        $headerKelembapanDelta = ($mqttHeaderKelembapan !== null && $httpHeaderKelembapan !== null)
            ? $mqttHeaderKelembapan - $httpHeaderKelembapan
            : null;

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
            $sameSendTick = isset($latestMqtt->send_tick_ms, $latestHttp->send_tick_ms)
                && ((int) $latestMqtt->send_tick_ms === (int) $latestHttp->send_tick_ms);

            if ($sameSensorReadSeq && $sameSendTick) {
                $protocolDiagnostics['sensor_sync_note'] = 'Peringatan: sensor_read_seq dan send_tick_ms MQTT/HTTP sama pada sampel terbaru. Indikasi kuat payload memakai snapshot sensor yang sama.';
            } elseif ($sameSensorReadSeq) {
                $protocolDiagnostics['sensor_sync_note'] = 'Perhatian: sensor_read_seq MQTT dan HTTP sama pada sampel terbaru. Ini bisa terjadi saat fallback snapshot aktif, tetapi idealnya pembacaan antar protokol tetap terpisah.';
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

        $mqttTotal = (int) ($summary['mqtt']['total_data'] ?? 0);
        $httpTotal = (int) ($summary['http']['total_data'] ?? 0);
        $warningConfig = $this->resolveWarningConfig();

        $fieldCompleteness = [];
        $dataWarnings = [];

        if (($mqttConnectionStatus['state'] ?? '') === 'filtered') {
            $deviceId = $latestMqttResolution['filtered_device_id'] ?? null;
            $deviceHint = is_int($deviceId) && $deviceId > 0 ? " (device_id {$deviceId})" : '';
            $dataWarnings[] = "MQTT telemetry terdeteksi{$deviceHint}, tetapi saat ini ditandai sebagai sumber simulator yang sedang diabaikan. Status ditampilkan sebagai FILTERED.";
        }
        if (($httpConnectionStatus['state'] ?? '') === 'filtered') {
            $deviceId = $latestHttpResolution['filtered_device_id'] ?? null;
            $deviceHint = is_int($deviceId) && $deviceId > 0 ? " (device_id {$deviceId})" : '';
            $dataWarnings[] = "HTTP telemetry terdeteksi{$deviceHint}, tetapi saat ini ditandai sebagai sumber simulator yang sedang diabaikan. Status ditampilkan sebagai FILTERED.";
        }
        if (($esp32ConnectionStatus['filtered_fallback'] ?? false) === true && ($esp32ConnectionStatus['source'] ?? 'none') === 'telemetry' && !($latestEsp32DebugHeartbeat['available'] ?? false)) {
            $deviceId = $latestIncomingResolution['filtered_device_id'] ?? null;
            $deviceHint = is_int($deviceId) && $deviceId > 0 ? " (device_id {$deviceId})" : '';
            $dataWarnings[] = "ESP32 ON/OFF memakai mode konservatif: telemetry terbaru hanya berasal dari sumber simulator{$deviceHint} yang diabaikan.";
        }

        foreach (['MQTT', 'HTTP'] as $protocol) {
            $protocolMeta = $this->buildFieldCompletenessForProtocol($protocol, $requiredFields);
            $fieldCompleteness[$protocol] = $protocolMeta;
            $total = (int) ($protocolMeta['total'] ?? 0);

            if ($total === 0) {
                $dataWarnings[] = "{$protocol}: belum ada data masuk.";
                continue;
            }

            foreach (($protocolMeta['fields'] ?? []) as $fieldMeta) {
                $fieldLabel = (string) ($fieldMeta['label'] ?? 'Field');
                $missing = (int) ($fieldMeta['missing'] ?? 0);
                if ($missing > 0) {
                    $dataWarnings[] = "{$protocol} {$fieldLabel}: {$missing}/{$total} data kosong.";
                }
            }
        }

        $countDelta = abs($mqttTotal - $httpTotal);
        $maxCount = max($mqttTotal, $httpTotal);
        $allowedDelta = max(
            $warningConfig['balance_allowed_delta'],
            (int) ceil($maxCount * $warningConfig['balance_allowed_ratio'])
        );
        $enoughSamplesForBalanceCheck = $maxCount >= $warningConfig['balance_min_samples'];

        if ($enoughSamplesForBalanceCheck && $countDelta > $allowedDelta) {
            $dataWarnings[] = "Jumlah data tidak seimbang: MQTT {$mqttTotal} vs HTTP {$httpTotal} (delta {$countDelta}, batas {$allowedDelta}).";
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
        if (($reliability['mqtt_transmission_health'] ?? 100) < $warningConfig['mqtt_health_min_score'] && $mqttTotal > 0) {
            $dataWarnings[] = "MQTT transmission health rendah ({$reliability['mqtt_transmission_health']}%). Cek RSSI, broker, atau kestabilan jaringan.";
        }
        if (($reliability['http_transmission_health'] ?? 100) < $warningConfig['http_health_min_score'] && $httpTotal > 0) {
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
            $sameSnapshotSignature = $sensorReadGap !== null
                && $sensorReadGap === 0
                && $sendTickGap !== null
                && abs($sendTickGap) <= 100;

            if ($sameSnapshotSignature) {
                $dataWarnings[] = "Validasi protokol: sensor_read_seq dan send_tick_ms MQTT/HTTP hampir identik. Indikasi snapshot sensor dipakai bersama, bukan pembacaan terpisah.";
            }
            if ($tempGap !== null && $humidityGap !== null && $tempGap < 0.0000005 && $humidityGap < 0.0000005 && $sameSnapshotSignature) {
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

        // Prepare data untuk Chart.js - Latency Comparison.
        // X-axis dibucket per slot waktu (detik WIB) agar MQTT/HTTP pada waktu yang sama berada di posisi x yang sama.
        $latencyChartData = [
            'labels' => [], // index 1..N (N = total slot waktu unik)
            'time_labels' => [], // short time label per slot
            'full_time_labels' => [], // full time label per slot
            'datasets' => [],
            'total_points' => 0,
            'total_records' => 0,
            'display_timezone' => 'Asia/Jakarta',
        ];

        $deviceNames = Device::pluck('nama_device', 'id');
        $latencyPoints = $mqttChartData
            ->merge($httpChartData)
            ->map(function ($point) use ($deviceNames, $displayTimezone, $excludedDeviceIds) {
                $timestampRaw = $point->timestamp_server ?? $point->created_at;
                $timestampSort = $timestampRaw ? (clone $timestampRaw) : null;
                $timestampDisplay = $timestampRaw ? (clone $timestampRaw)->setTimezone($displayTimezone) : null;
                $deviceId = (int) $point->device_id;
                $rawDeviceName = isset($deviceNames[$deviceId]) ? (string) $deviceNames[$deviceId] : null;
                return [
                    'id' => (int) $point->id,
                    'protocol' => strtoupper((string) $point->protokol),
                    'device_id' => $deviceId,
                    'device_name' => $this->resolveDashboardDeviceName($deviceId, $rawDeviceName, $excludedDeviceIds),
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
        $datasetPointIndexBySlot = [];
        $slotIndexByKey = [];
        $slotSequence = 0;
        $resolveTimeSlot = static function (array $point): array {
            $timestampDisplay = $point['timestamp_display'] ?? null;
            if ($timestampDisplay instanceof CarbonInterface) {
                return [
                    'key' => 'ts:' . $timestampDisplay->format('Y-m-d H:i:s'),
                    'short' => $timestampDisplay->format('H:i:s') . ' WIB',
                    'full' => $timestampDisplay->format('d-m-Y H:i:s') . ' WIB',
                ];
            }

            return [
                'key' => 'row:' . (string) ($point['id'] ?? 0),
                'short' => '-',
                'full' => '-',
            ];
        };

        foreach ($latencyPoints as $point) {
            $isMqtt = $point['protocol'] === 'MQTT';
            $protocol = $isMqtt ? 'MQTT' : 'HTTP';
            $datasetKey = $protocol . '|' . $point['device_id'];
            $slotMeta = $resolveTimeSlot($point);
            $slotKey = $slotMeta['key'];
            if (!isset($slotIndexByKey[$slotKey])) {
                $slotSequence++;
                $slotIndexByKey[$slotKey] = $slotSequence;
                $latencyChartData['labels'][] = $slotSequence;
                $latencyChartData['time_labels'][] = $slotMeta['short'];
                $latencyChartData['full_time_labels'][] = $slotMeta['full'];
            }
            $slotIndex = $slotIndexByKey[$slotKey];
            $fullTime = $slotMeta['full'];

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

            if (!isset($datasetPointIndexBySlot[$datasetKey])) {
                $datasetPointIndexBySlot[$datasetKey] = [];
            }

            $pointPayload = [
                'x' => $slotIndex,
                'y' => $point['latency_ms'],
                'device' => $point['device_name'],
                'timestamp' => $fullTime,
                'point_index' => $slotIndex,
            ];

            if (isset($datasetPointIndexBySlot[$datasetKey][$slotIndex])) {
                $existingIndex = $datasetPointIndexBySlot[$datasetKey][$slotIndex];
                $datasetsByKey[$datasetKey]['data'][$existingIndex] = $pointPayload;
            } else {
                $datasetsByKey[$datasetKey]['data'][] = $pointPayload;
                $datasetPointIndexBySlot[$datasetKey][$slotIndex] = count($datasetsByKey[$datasetKey]['data']) - 1;
            }
        }

        $latencyChartData['datasets'] = array_values($datasetsByKey);
        $latencyChartData['total_points'] = $slotSequence;
        $latencyChartData['total_records'] = $latencyPoints->count();
        // Prepare data untuk Chart.js - Power Comparison (mengikuti slot waktu yang sama dengan latency)
        $powerChartData = [
            'labels' => [],
            'time_labels' => [],
            'full_time_labels' => [],
            'mqtt' => [],
            'http' => [],
            'total_points' => 0,
            'total_records' => 0,
            'display_timezone' => 'Asia/Jakarta',
        ];

        $powerChartData['labels'] = $latencyChartData['labels'];
        $powerChartData['time_labels'] = $latencyChartData['time_labels'];
        $powerChartData['full_time_labels'] = $latencyChartData['full_time_labels'];
        $powerChartData['total_points'] = $latencyChartData['total_points'];
        $powerChartData['total_records'] = $latencyChartData['total_records'];

        $powerChartData['mqtt'] = array_fill(0, $powerChartData['total_points'], null);
        $powerChartData['http'] = array_fill(0, $powerChartData['total_points'], null);

        foreach ($latencyPoints as $point) {
            $slotMeta = $resolveTimeSlot($point);
            $slotKey = $slotMeta['key'];
            if (!isset($slotIndexByKey[$slotKey])) {
                continue;
            }

            $slotIndex = (int) $slotIndexByKey[$slotKey];
            if ($slotIndex < 1 || $slotIndex > $powerChartData['total_points']) {
                continue;
            }

            $bucketIndex = $slotIndex - 1;
            $roundedPower = $point['daya_mw'] !== null ? round($point['daya_mw'], 2) : null;

            if ($point['protocol'] === 'MQTT') {
                $powerChartData['mqtt'][$bucketIndex] = $roundedPower;
            } elseif ($point['protocol'] === 'HTTP') {
                $powerChartData['http'][$bucketIndex] = $roundedPower;
            }
        }
        $telemetrySource = $this->telemetrySource;

        return $this->renderDashboardPage(compact(
            'summary', 'reliability', 'latencyChartData', 'powerChartData', 'mqttTotal', 'httpTotal',
            'mqttConnected', 'httpConnected', 'esp32Connected', 'mqttAvgSuhu', 'mqttAvgKelembapan', 'httpAvgSuhu', 'httpAvgKelembapan',
            'avgSuhu', 'avgKelembapan', 'headerSuhuDelta', 'headerKelembapanDelta', 'fieldCompleteness', 'dataWarnings', 'protocolDiagnostics',
            'mqttConnectionStatus', 'httpConnectionStatus', 'esp32ConnectionStatus', 'connectionConfig', 'simulationRunning', 'excludeSimulatorStatusSource',
            'telemetrySource'
        ));
    }

    public function calculator(Request $request): JsonResponse
    {
        $this->configureTelemetrySource((string) $request->query('source', 'real'));
        $this->statisticsService->setTelemetrySource($this->telemetrySource);

        return response()
            ->json([
                'success' => true,
                'data' => $this->buildDashboardCalculatorPayload(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }

    private function getChartProtocolData(string $protocol, int $chartWindowSize): \Illuminate\Support\Collection
    {
        $query = $this->telemetryQuery()
            ->whereRaw('UPPER(protokol) = ?', [strtoupper($protocol)]);

        if ($chartWindowSize > 0) {
            return $query
                ->orderByDesc('id')
                ->limit($chartWindowSize)
                ->get()
                ->sortBy('id')
                ->values();
        }

        return $query
            ->orderBy('id')
            ->get()
            ->values();
    }

    private function resolveChartWindowSize(): int
    {
        return max(0, (int) config('dashboard.chart_window', 0));
    }

    private function buildDashboardCalculatorPayload(): array
    {
        $generatedAt = now();
        $analysisWindow = max(0, (int) config('dashboard.analysis_window', 0));

        if (!$this->isDatabaseReachable()) {
            $fallback = $this->buildDashboardFallbackPayload(
                'Database MySQL tidak terhubung. Kalkulator dashboard real memakai mode aman dengan hasil 0.'
            );

            return [
                'database_ready' => false,
                'source' => $this->telemetrySource,
                'source_label' => strtoupper($this->telemetrySource),
                'generated_at_wib' => $this->formatTimestampToWib($generatedAt),
                'status_message' => 'Database tidak terhubung. Nilai kalkulator ditampilkan sebagai 0 sampai telemetry real tersedia kembali.',
                'protocol_freshness_seconds' => (int) ($fallback['connectionConfig']['protocol_freshness_seconds'] ?? 30),
                'analysis_window' => $analysisWindow,
                'groups' => $this->buildDashboardCalculatorGroups(
                    $fallback['summary'],
                    $fallback['reliability'],
                    $fallback['mqttConnectionStatus'],
                    $fallback['httpConnectionStatus'],
                    $fallback['avgSuhu'] ?? 0.0,
                    $fallback['avgKelembapan'] ?? 0.0,
                    $fallback['mqttAvgSuhu'] ?? 0.0,
                    $fallback['mqttAvgKelembapan'] ?? 0.0,
                    $fallback['httpAvgSuhu'] ?? 0.0,
                    $fallback['httpAvgKelembapan'] ?? 0.0,
                    $fallback['headerSuhuDelta'] ?? null,
                    $fallback['headerKelembapanDelta'] ?? null,
                    0.0,
                    0.0,
                    0,
                    0,
                    0.0,
                    0.0,
                    0.0,
                    0.0,
                    $analysisWindow
                ),
            ];
        }

        $mqttData = $this->statisticsService->getMqttData();
        $httpData = $this->statisticsService->getHttpData();
        $summary = $this->statisticsService->getSummary($mqttData, $httpData);
        $reliability = $this->statisticsService->getReliability();
        $protocolTotals = $this->resolveProtocolTotals();
        $summary['mqtt']['total_data'] = $protocolTotals['MQTT'];
        $summary['http']['total_data'] = $protocolTotals['HTTP'];

        $now = now();
        $connectionConfig = $this->resolveConnectionConfig();
        $simulationRunning = $this->isSimulationRunningFromStateFile();
        $excludeSimulatorStatusSource = $this->telemetrySource === 'real'
            && $connectionConfig['ignore_simulator_when_stopped']
            && !$simulationRunning;
        $excludedDeviceIds = $this->resolveExcludedDeviceIdsForStatus($excludeSimulatorStatusSource, $now);

        $latestMqttResolution = $this->resolveLatestProtocolRowForStatus('MQTT', $excludedDeviceIds);
        $latestHttpResolution = $this->resolveLatestProtocolRowForStatus('HTTP', $excludedDeviceIds);
        $latestMqtt = $latestMqttResolution['row'] ?? null;
        $latestHttp = $latestHttpResolution['row'] ?? null;

        $mqttConnectionStatus = $this->buildProtocolConnectionStatus(
            'MQTT',
            $latestMqtt,
            $now,
            $connectionConfig['protocol_freshness_seconds'],
            $latestMqttResolution
        );
        $httpConnectionStatus = $this->buildProtocolConnectionStatus(
            'HTTP',
            $latestHttp,
            $now,
            $connectionConfig['protocol_freshness_seconds'],
            $latestHttpResolution
        );

        $mqttHeaderSuhu = $this->resolveFreshProtocolMetricValue($latestMqtt, $mqttConnectionStatus, 'suhu');
        $mqttHeaderKelembapan = $this->resolveFreshProtocolMetricValue($latestMqtt, $mqttConnectionStatus, 'kelembapan');
        $httpHeaderSuhu = $this->resolveFreshProtocolMetricValue($latestHttp, $httpConnectionStatus, 'suhu');
        $httpHeaderKelembapan = $this->resolveFreshProtocolMetricValue($latestHttp, $httpConnectionStatus, 'kelembapan');

        $avgSuhu = $this->calculateFreshCombinedMetric([$mqttHeaderSuhu, $httpHeaderSuhu]);
        $avgKelembapan = $this->calculateFreshCombinedMetric([$mqttHeaderKelembapan, $httpHeaderKelembapan]);
        $headerSuhuDelta = ($mqttHeaderSuhu !== null && $httpHeaderSuhu !== null)
            ? $mqttHeaderSuhu - $httpHeaderSuhu
            : null;
        $headerKelembapanDelta = ($mqttHeaderKelembapan !== null && $httpHeaderKelembapan !== null)
            ? $mqttHeaderKelembapan - $httpHeaderKelembapan
            : null;

        $mqttLatencyScope = $mqttData->whereNotNull('latency_ms');
        $httpLatencyScope = $httpData->whereNotNull('latency_ms');
        $mqttPowerScope = $mqttData->whereNotNull('daya_mw');
        $httpPowerScope = $httpData->whereNotNull('daya_mw');

        return [
            'database_ready' => true,
            'source' => $this->telemetrySource,
            'source_label' => strtoupper($this->telemetrySource),
            'generated_at_wib' => $this->formatTimestampToWib($generatedAt),
            'status_message' => 'Kalkulator ini mengikuti data real dashboard terbaru dan refresh otomatis.',
            'protocol_freshness_seconds' => (int) ($connectionConfig['protocol_freshness_seconds'] ?? 30),
            'analysis_window' => $analysisWindow,
            'groups' => $this->buildDashboardCalculatorGroups(
                $summary,
                $reliability,
                $mqttConnectionStatus,
                $httpConnectionStatus,
                $avgSuhu,
                $avgKelembapan,
                $mqttHeaderSuhu ?? 0.0,
                $mqttHeaderKelembapan ?? 0.0,
                $httpHeaderSuhu ?? 0.0,
                $httpHeaderKelembapan ?? 0.0,
                $headerSuhuDelta,
                $headerKelembapanDelta,
                (float) $mqttLatencyScope->sum('latency_ms'),
                (float) $httpLatencyScope->sum('latency_ms'),
                (int) $mqttLatencyScope->count(),
                (int) $httpLatencyScope->count(),
                (float) $mqttPowerScope->sum('daya_mw'),
                (float) $httpPowerScope->sum('daya_mw'),
                (float) $mqttPowerScope->count(),
                (float) $httpPowerScope->count(),
                $analysisWindow
            ),
        ];
    }

    private function buildDashboardCalculatorGroups(
        array $summary,
        array $reliability,
        array $mqttConnectionStatus,
        array $httpConnectionStatus,
        float $avgSuhu,
        float $avgKelembapan,
        float $mqttSuhu,
        float $mqttKelembapan,
        float $httpSuhu,
        float $httpKelembapan,
        ?float $headerSuhuDelta,
        ?float $headerKelembapanDelta,
        float $mqttLatencySum,
        float $httpLatencySum,
        int $mqttLatencyN,
        int $httpLatencyN,
        float $mqttPowerSum,
        float $httpPowerSum,
        float $mqttPowerN,
        float $httpPowerN,
        int $analysisWindow
    ): array {
        $freshCountSuhu = (int) (($mqttConnectionStatus['connected'] ?? false) ? 1 : 0) + (int) (($httpConnectionStatus['connected'] ?? false) ? 1 : 0);
        $freshCountHumidity = $freshCountSuhu;
        $mqttReliabilityHasSequence = ((int) ($reliability['mqtt_expected_packets'] ?? 0) > 0) || ((int) ($reliability['mqtt_received_packets'] ?? 0) > 0);
        $httpReliabilityHasSequence = ((int) ($reliability['http_expected_packets'] ?? 0) > 0) || ((int) ($reliability['http_received_packets'] ?? 0) > 0);

        return [
            [
                'title' => 'Header Realtime',
                'description' => 'Perhitungan card suhu dan kelembapan di header dashboard real. Nilai stale otomatis dianggap 0.',
                'cards' => [
                    [
                        'tone' => $this->resolveHeaderCardTone($freshCountSuhu),
                        'chip' => 'HEADER',
                        'label' => 'Suhu Realtime',
                        'result' => $this->formatCalculatorNumber($avgSuhu) . ' C',
                        'formula' => 'Hasil = average(nilai fresh MQTT/HTTP saja)',
                        'inputs' => [
                            ['label' => 'MQTT fresh', 'value' => $this->formatCalculatorNumber($mqttSuhu) . ' C'],
                            ['label' => 'HTTP fresh', 'value' => $this->formatCalculatorNumber($httpSuhu) . ' C'],
                            ['label' => 'Protokol fresh', 'value' => (string) $freshCountSuhu],
                            ['label' => 'Delta MQTT-HTTP', 'value' => $headerSuhuDelta !== null ? $this->formatSignedCalculatorNumber($headerSuhuDelta) . ' C' : '-'],
                        ],
                    ],
                    [
                        'tone' => $this->resolveHeaderCardTone($freshCountHumidity),
                        'chip' => 'HEADER',
                        'label' => 'Kelembapan Realtime',
                        'result' => $this->formatCalculatorNumber($avgKelembapan) . ' %',
                        'formula' => 'Hasil = average(nilai fresh MQTT/HTTP saja)',
                        'inputs' => [
                            ['label' => 'MQTT fresh', 'value' => $this->formatCalculatorNumber($mqttKelembapan) . ' %'],
                            ['label' => 'HTTP fresh', 'value' => $this->formatCalculatorNumber($httpKelembapan) . ' %'],
                            ['label' => 'Protokol fresh', 'value' => (string) $freshCountHumidity],
                            ['label' => 'Delta MQTT-HTTP', 'value' => $headerKelembapanDelta !== null ? $this->formatSignedCalculatorNumber($headerKelembapanDelta) . ' %' : '-'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Realtime Metrics',
                'description' => 'Perhitungan card utama dashboard real. `Total Data` memakai total tabel aktual, sedangkan rata-rata mengikuti scope analisis dashboard.',
                'cards' => [
                    [
                        'tone' => $this->resolveConnectionTone($mqttConnectionStatus),
                        'chip' => 'MQTT',
                        'label' => 'Total Data',
                        'result' => (string) ((int) ($summary['mqtt']['total_data'] ?? 0)) . ' data',
                        'formula' => 'COUNT(baris real dengan protokol MQTT)',
                        'inputs' => [
                            ['label' => 'Rows MQTT real', 'value' => (string) ((int) ($summary['mqtt']['total_data'] ?? 0))],
                            ['label' => 'Status koneksi', 'value' => (string) ($mqttConnectionStatus['label'] ?? 'Unknown')],
                        ],
                    ],
                    [
                        'tone' => $this->resolveConnectionTone($mqttConnectionStatus),
                        'chip' => 'MQTT',
                        'label' => 'Avg Latency',
                        'result' => $this->formatCalculatorNumber((float) ($summary['mqtt']['avg_latency_ms'] ?? 0)) . ' ms',
                        'formula' => 'SUM(latency_ms valid) / N latency MQTT',
                        'inputs' => [
                            ['label' => 'SUM latency', 'value' => $this->formatCalculatorNumber($mqttLatencySum, 4) . ' ms'],
                            ['label' => 'N latency', 'value' => (string) $mqttLatencyN],
                            ['label' => 'Window analisis', 'value' => $analysisWindow > 0 ? (string) $analysisWindow . ' data terakhir' : 'unlimited'],
                        ],
                    ],
                    [
                        'tone' => $this->resolveConnectionTone($mqttConnectionStatus),
                        'chip' => 'MQTT',
                        'label' => 'Avg Power',
                        'result' => $this->formatCalculatorNumber((float) ($summary['mqtt']['avg_daya_mw'] ?? 0)) . ' mW',
                        'formula' => 'SUM(daya_mw valid) / N daya MQTT',
                        'inputs' => [
                            ['label' => 'SUM daya', 'value' => $this->formatCalculatorNumber($mqttPowerSum, 4) . ' mW'],
                            ['label' => 'N daya', 'value' => (string) ((int) $mqttPowerN)],
                            ['label' => 'Window analisis', 'value' => $analysisWindow > 0 ? (string) $analysisWindow . ' data terakhir' : 'unlimited'],
                        ],
                    ],
                    [
                        'tone' => $this->resolveConnectionTone($mqttConnectionStatus),
                        'chip' => 'MQTT',
                        'label' => 'Reliability',
                        'result' => $this->formatCalculatorNumber((float) ($reliability['mqtt_reliability'] ?? 0)) . ' %',
                        'formula' => $mqttReliabilityHasSequence
                            ? '(0.55 x sequence) + (0.25 x completeness) + (0.20 x transmission)'
                            : '(0.60 x completeness) + (0.40 x transmission)',
                        'inputs' => [
                            ['label' => 'Sequence score', 'value' => $this->formatCalculatorNumber((float) ($reliability['mqtt_sequence_reliability'] ?? 0)) . ' %'],
                            ['label' => 'Completeness', 'value' => $this->formatCalculatorNumber((float) ($reliability['mqtt_data_completeness'] ?? 0)) . ' %'],
                            ['label' => 'Transmission', 'value' => $this->formatCalculatorNumber((float) ($reliability['mqtt_transmission_health'] ?? 0)) . ' %'],
                            ['label' => 'Window reliability', 'value' => (string) ((int) ($reliability['mqtt_window_size'] ?? 0)) . ' data'],
                        ],
                    ],
                    [
                        'tone' => $this->resolveConnectionTone($httpConnectionStatus),
                        'chip' => 'HTTP',
                        'label' => 'Total Data',
                        'result' => (string) ((int) ($summary['http']['total_data'] ?? 0)) . ' data',
                        'formula' => 'COUNT(baris real dengan protokol HTTP)',
                        'inputs' => [
                            ['label' => 'Rows HTTP real', 'value' => (string) ((int) ($summary['http']['total_data'] ?? 0))],
                            ['label' => 'Status koneksi', 'value' => (string) ($httpConnectionStatus['label'] ?? 'Unknown')],
                        ],
                    ],
                    [
                        'tone' => $this->resolveConnectionTone($httpConnectionStatus),
                        'chip' => 'HTTP',
                        'label' => 'Avg Latency',
                        'result' => $this->formatCalculatorNumber((float) ($summary['http']['avg_latency_ms'] ?? 0)) . ' ms',
                        'formula' => 'SUM(latency_ms valid) / N latency HTTP',
                        'inputs' => [
                            ['label' => 'SUM latency', 'value' => $this->formatCalculatorNumber($httpLatencySum, 4) . ' ms'],
                            ['label' => 'N latency', 'value' => (string) $httpLatencyN],
                            ['label' => 'Window analisis', 'value' => $analysisWindow > 0 ? (string) $analysisWindow . ' data terakhir' : 'unlimited'],
                        ],
                    ],
                    [
                        'tone' => $this->resolveConnectionTone($httpConnectionStatus),
                        'chip' => 'HTTP',
                        'label' => 'Avg Power',
                        'result' => $this->formatCalculatorNumber((float) ($summary['http']['avg_daya_mw'] ?? 0)) . ' mW',
                        'formula' => 'SUM(daya_mw valid) / N daya HTTP',
                        'inputs' => [
                            ['label' => 'SUM daya', 'value' => $this->formatCalculatorNumber($httpPowerSum, 4) . ' mW'],
                            ['label' => 'N daya', 'value' => (string) ((int) $httpPowerN)],
                            ['label' => 'Window analisis', 'value' => $analysisWindow > 0 ? (string) $analysisWindow . ' data terakhir' : 'unlimited'],
                        ],
                    ],
                    [
                        'tone' => $this->resolveConnectionTone($httpConnectionStatus),
                        'chip' => 'HTTP',
                        'label' => 'Reliability',
                        'result' => $this->formatCalculatorNumber((float) ($reliability['http_reliability'] ?? 0)) . ' %',
                        'formula' => $httpReliabilityHasSequence
                            ? '(0.55 x sequence) + (0.25 x completeness) + (0.20 x transmission)'
                            : '(0.60 x completeness) + (0.40 x transmission)',
                        'inputs' => [
                            ['label' => 'Sequence score', 'value' => $this->formatCalculatorNumber((float) ($reliability['http_sequence_reliability'] ?? 0)) . ' %'],
                            ['label' => 'Completeness', 'value' => $this->formatCalculatorNumber((float) ($reliability['http_data_completeness'] ?? 0)) . ' %'],
                            ['label' => 'Transmission', 'value' => $this->formatCalculatorNumber((float) ($reliability['http_transmission_health'] ?? 0)) . ' %'],
                            ['label' => 'Window reliability', 'value' => (string) ((int) ($reliability['http_window_size'] ?? 0)) . ' data'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'T-Test Dashboard Real',
                'description' => 'Ringkasan perhitungan statistik yang dipakai card T-Test di dashboard real.',
                'cards' => [
                    $this->buildCalculatorTTestCard('LATENCY', 'Latency T-Test', $summary['ttest_latency'] ?? []),
                    $this->buildCalculatorTTestCard('POWER', 'Power T-Test', $summary['ttest_daya'] ?? []),
                ],
            ],
        ];
    }

    private function buildCalculatorTTestCard(string $chip, string $label, array $ttest): array
    {
        $valid = (bool) ($ttest['valid'] ?? false);
        $tone = $valid ? ((bool) ($ttest['is_significant'] ?? false) ? 'ok' : 'warn') : 'muted';

        return [
            'tone' => $tone,
            'chip' => $chip,
            'label' => $label,
            'result' => $valid
                ? (((bool) ($ttest['is_significant'] ?? false)) ? 'SIGNIFIKAN' : 'TIDAK SIGNIFIKAN')
                : 'Belum cukup data',
            'formula' => 'Welch independent sample t-test antara MQTT dan HTTP',
            'inputs' => [
                ['label' => 'MQTT N', 'value' => (string) ((int) ($ttest['data1']['n'] ?? 0))],
                ['label' => 'MQTT mean', 'value' => $this->formatCalculatorFlexible($ttest['data1']['mean'] ?? null)],
                ['label' => 'MQTT variance', 'value' => $this->formatCalculatorFlexible($ttest['data1']['variance'] ?? null)],
                ['label' => 'HTTP N', 'value' => (string) ((int) ($ttest['data2']['n'] ?? 0))],
                ['label' => 'HTTP mean', 'value' => $this->formatCalculatorFlexible($ttest['data2']['mean'] ?? null)],
                ['label' => 'HTTP variance', 'value' => $this->formatCalculatorFlexible($ttest['data2']['variance'] ?? null)],
                ['label' => 't-value', 'value' => $this->formatCalculatorFlexible($ttest['t_value'] ?? null)],
                ['label' => 'df', 'value' => (string) ((int) ($ttest['df'] ?? 0))],
                ['label' => 'critical', 'value' => '±' . $this->formatCalculatorFlexible($ttest['critical_value'] ?? null)],
                ['label' => 'p-value', 'value' => $this->formatCalculatorFlexible($ttest['p_value_display'] ?? ($ttest['p_value'] ?? null))],
            ],
        ];
    }

    private function resolveConnectionTone(array $status): string
    {
        $state = (string) ($status['state'] ?? '');
        if ($state === 'connected') {
            return 'ok';
        }
        if ($state === 'filtered' || $state === 'stale') {
            return 'warn';
        }

        return 'muted';
    }

    private function resolveHeaderCardTone(int $freshCount): string
    {
        if ($freshCount >= 2) {
            return 'ok';
        }
        if ($freshCount === 1) {
            return 'warn';
        }

        return 'muted';
    }

    private function formatCalculatorNumber(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', '');
    }

    private function formatCalculatorSignedNumber(float $value, int $decimals = 2): string
    {
        return sprintf('%+.' . $decimals . 'f', $value);
    }

    private function formatCalculatorFlexible(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            if (!is_finite($numeric)) {
                return (string) $value;
            }

            $encoded = json_encode($numeric, JSON_PRESERVE_ZERO_FRACTION);
            return is_string($encoded) ? $encoded : (string) $value;
        }

        return (string) $value;
    }

    private function configureTelemetrySource(string $source): void
    {
        $normalized = strtolower(trim($source));
        if (in_array($normalized, ['simulation', 'sim'], true)) {
            $this->telemetrySource = 'simulation';
            $this->telemetryModelClass = SimulatedEksperimen::class;
            return;
        }

        $this->telemetrySource = 'real';
        $this->telemetryModelClass = Eksperimen::class;
    }

    private function telemetryQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $modelClass = $this->telemetryModelClass;

        return $modelClass::query();
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

    private function resolveConnectionConfig(): array
    {
        $protocolFreshness = (int) config('dashboard.connection.protocol_freshness_seconds', 30);
        $esp32Freshness = (int) config('dashboard.connection.esp32_freshness_seconds', $protocolFreshness);
        $esp32DebugFreshness = (int) config('dashboard.connection.esp32_debug_freshness_seconds', 120);
        $ignoreSimulatorWhenStopped = (bool) config('dashboard.connection.ignore_simulator_when_stopped', true);

        return [
            'protocol_freshness_seconds' => max(5, $protocolFreshness),
            'esp32_freshness_seconds' => max(5, $esp32Freshness),
            'esp32_debug_freshness_seconds' => max(15, $esp32DebugFreshness),
            'ignore_simulator_when_stopped' => $ignoreSimulatorWhenStopped,
        ];
    }

    private function isSimulationRunningFromStateFile(): bool
    {
        $decoded = $this->readSimulationStateFromFile();
        if (!is_array($decoded)) {
            return false;
        }

        return (bool) ($decoded['running'] ?? false);
    }

    private function readSimulationStateFromFile(): ?array
    {
        $stateFile = storage_path('app/simulation_state.json');
        if (!is_file($stateFile)) {
            return null;
        }

        $raw = @file_get_contents($stateFile);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function resolveExcludedDeviceIdsForStatus(bool $excludeSimulatorStatusSource, CarbonInterface $now): array
    {
        if (!$excludeSimulatorStatusSource) {
            return [];
        }

        $simulatorDeviceIds = $this->resolveSimulatorRegistryDeviceIds();
        if ($simulatorDeviceIds === []) {
            return [];
        }

        $decoded = $this->readSimulationStateFromFile();
        if (!is_array($decoded)) {
            // Fail-open: without explicit simulation state, do not filter by simulator label only.
            return [];
        }

        $simulationDeviceId = isset($decoded['device_id']) && is_numeric($decoded['device_id'])
            ? (int) $decoded['device_id']
            : 0;
        // Guard against stale/invalid state file that accidentally points to a real ESP32 device id.
        if ($simulationDeviceId <= 0 || !in_array($simulationDeviceId, $simulatorDeviceIds, true)) {
            return [];
        }

        $excluded = [$simulationDeviceId];
        $freshHeartbeatDeviceIds = $this->resolveFreshDebugHeartbeatDeviceIds($now);
        if ($freshHeartbeatDeviceIds !== []) {
            $excluded = array_values(array_diff($excluded, $freshHeartbeatDeviceIds));
        }
        $provisionedDeviceIds = $this->resolveProvisionedDeviceIds($excluded);
        if ($provisionedDeviceIds !== []) {
            $excluded = array_values(array_diff($excluded, $provisionedDeviceIds));
        }

        return $excluded;
    }

    private function resolveFreshDebugHeartbeatDeviceIds(CarbonInterface $now): array
    {
        $heartbeatPath = storage_path('app/esp32_debug_heartbeat.json');
        if (!is_file($heartbeatPath)) {
            return [];
        }

        $raw = @file_get_contents($heartbeatPath);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $freshnessSeconds = max(
            5,
            (int) config('dashboard.connection.esp32_debug_freshness_seconds', 120)
        );
        $freshIds = [];
        $devices = isset($decoded['devices']) && is_array($decoded['devices']) ? $decoded['devices'] : [];

        foreach ($devices as $devicePayload) {
            if (!is_array($devicePayload)) {
                continue;
            }

            $deviceId = isset($devicePayload['device_id']) && is_numeric($devicePayload['device_id'])
                ? (int) $devicePayload['device_id']
                : 0;
            if ($deviceId <= 0) {
                continue;
            }

            $timestampRaw = isset($devicePayload['last_seen_utc']) ? (string) $devicePayload['last_seen_utc'] : '';
            if ($timestampRaw === '') {
                continue;
            }

            try {
                $timestamp = Carbon::parse($timestampRaw, 'UTC');
            } catch (Throwable) {
                continue;
            }

            $ageSeconds = $this->calculateAgeSeconds($timestamp, $now);
            if ($ageSeconds !== null && $ageSeconds <= $freshnessSeconds) {
                $freshIds[$deviceId] = true;
            }
        }

        return array_keys($freshIds);
    }

    private function resolveSimulatorRegistryDeviceIds(): array
    {
        return Device::query()
            ->where('nama_device', 'SIMULATOR-APP')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    private function resolveProvisionedDeviceIds(array $deviceIds): array
    {
        if ($deviceIds === []) {
            return [];
        }

        return Device::query()
            ->whereIn('id', $deviceIds)
            ->whereHas('firmwareProfile')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    private function resolveDashboardDeviceName(int $deviceId, ?string $rawDeviceName, array $excludedDeviceIds): string
    {
        $fallbackName = 'Device ' . $deviceId;
        $name = trim((string) $rawDeviceName);
        if ($name === '') {
            return $fallbackName;
        }

        $isSimulatorLabel = strcasecmp($name, 'SIMULATOR-APP') === 0;
        if ($isSimulatorLabel && !in_array($deviceId, $excludedDeviceIds, true)) {
            return $fallbackName;
        }

        return $name;
    }

    private function resolveLatestProtocolRowForStatus(string $protocol, array $excludedDeviceIds): array
    {
        $row = $this->fetchLatestProtocolRow($protocol, $excludedDeviceIds);
        if ($row !== null) {
            return [
                'row' => $row,
                'filtered_fallback' => false,
                'filtered_device_id' => null,
            ];
        }

        if ($excludedDeviceIds === []) {
            return [
                'row' => null,
                'filtered_fallback' => false,
                'filtered_device_id' => null,
            ];
        }

        $fallbackRow = $this->fetchLatestProtocolRow($protocol, []);
        if ($fallbackRow === null) {
            return [
                'row' => null,
                'filtered_fallback' => false,
                'filtered_device_id' => null,
            ];
        }

        $fallbackDeviceId = is_numeric($fallbackRow->device_id) ? (int) $fallbackRow->device_id : null;
        $isFilteredFallback = $fallbackDeviceId !== null && in_array($fallbackDeviceId, $excludedDeviceIds, true);

        return [
            'row' => $fallbackRow,
            'filtered_fallback' => $isFilteredFallback,
            'filtered_device_id' => $isFilteredFallback ? $fallbackDeviceId : null,
        ];
    }

    private function resolveLatestIncomingRowForStatus(array $excludedDeviceIds): array
    {
        $row = $this->fetchLatestIncomingRow($excludedDeviceIds);
        if ($row !== null) {
            return [
                'row' => $row,
                'filtered_fallback' => false,
                'filtered_device_id' => null,
            ];
        }

        if ($excludedDeviceIds === []) {
            return [
                'row' => null,
                'filtered_fallback' => false,
                'filtered_device_id' => null,
            ];
        }

        $fallbackRow = $this->fetchLatestIncomingRow([]);
        if ($fallbackRow === null) {
            return [
                'row' => null,
                'filtered_fallback' => false,
                'filtered_device_id' => null,
            ];
        }

        $fallbackDeviceId = is_numeric($fallbackRow->device_id) ? (int) $fallbackRow->device_id : null;
        $isFilteredFallback = $fallbackDeviceId !== null && in_array($fallbackDeviceId, $excludedDeviceIds, true);

        return [
            'row' => $fallbackRow,
            'filtered_fallback' => $isFilteredFallback,
            'filtered_device_id' => $isFilteredFallback ? $fallbackDeviceId : null,
        ];
    }

    private function fetchLatestProtocolRow(string $protocol, array $excludedDeviceIds = []): ?\Illuminate\Database\Eloquent\Model
    {
        $query = $this->telemetryQuery()
            ->whereRaw('UPPER(protokol) = ?', [strtoupper($protocol)]);

        $query = $this->applyExcludedDeviceFilter($query, $excludedDeviceIds);

        return $query
            ->orderByRaw('COALESCE(timestamp_server, created_at) DESC')
            ->orderByDesc('id')
            ->first();
    }

    private function fetchLatestIncomingRow(array $excludedDeviceIds = []): ?\Illuminate\Database\Eloquent\Model
    {
        $query = $this->telemetryQuery();
        $query = $this->applyExcludedDeviceFilter($query, $excludedDeviceIds);

        return $query
            ->orderByRaw('COALESCE(timestamp_server, created_at) DESC')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveEsp32DebugHeartbeat(array $excludedDeviceIds, CarbonInterface $now): array
    {
        $heartbeatPath = storage_path('app/esp32_debug_heartbeat.json');
        if (!is_file($heartbeatPath)) {
            return [
                'available' => false,
                'timestamp' => null,
                'age_seconds' => null,
                'device_id' => null,
                'source_topic' => null,
                'last_message' => null,
            ];
        }

        $raw = @file_get_contents($heartbeatPath);
        if (!is_string($raw) || trim($raw) === '') {
            return [
                'available' => false,
                'timestamp' => null,
                'age_seconds' => null,
                'device_id' => null,
                'source_topic' => null,
                'last_message' => null,
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'available' => false,
                'timestamp' => null,
                'age_seconds' => null,
                'device_id' => null,
                'source_topic' => null,
                'last_message' => null,
            ];
        }

        $latestTimestamp = null;
        $latestDeviceId = null;
        $latestSourceTopic = null;
        $latestMessage = null;

        $devices = isset($decoded['devices']) && is_array($decoded['devices'])
            ? $decoded['devices']
            : [];

        foreach ($devices as $devicePayload) {
            if (!is_array($devicePayload)) {
                continue;
            }

            $deviceId = isset($devicePayload['device_id']) && is_numeric($devicePayload['device_id'])
                ? (int) $devicePayload['device_id']
                : null;

            if ($deviceId !== null && in_array($deviceId, $excludedDeviceIds, true)) {
                continue;
            }

            $timestampRaw = isset($devicePayload['last_seen_utc']) ? (string) $devicePayload['last_seen_utc'] : '';
            if ($timestampRaw === '') {
                continue;
            }

            try {
                $timestamp = Carbon::parse($timestampRaw, 'UTC');
            } catch (Throwable) {
                continue;
            }

            if ($latestTimestamp === null || $timestamp->greaterThan($latestTimestamp)) {
                $latestTimestamp = $timestamp;
                $latestDeviceId = $deviceId;
                $latestSourceTopic = isset($devicePayload['source_topic']) ? (string) $devicePayload['source_topic'] : null;
                $latestMessage = isset($devicePayload['last_message']) ? (string) $devicePayload['last_message'] : null;
            }
        }

        if ($latestTimestamp === null && empty($excludedDeviceIds)) {
            $globalLastSeen = isset($decoded['last_seen_utc']) ? (string) $decoded['last_seen_utc'] : '';
            if ($globalLastSeen !== '') {
                try {
                    $latestTimestamp = Carbon::parse($globalLastSeen, 'UTC');
                    $latestSourceTopic = isset($decoded['source_topic']) ? (string) $decoded['source_topic'] : null;
                    $latestMessage = null;
                } catch (Throwable) {
                    $latestTimestamp = null;
                }
            }
        }

        if ($latestTimestamp === null) {
            return [
                'available' => false,
                'timestamp' => null,
                'age_seconds' => null,
                'device_id' => null,
                'source_topic' => null,
                'last_message' => null,
            ];
        }

        return [
            'available' => true,
            'timestamp' => $latestTimestamp,
            'age_seconds' => $this->calculateAgeSeconds($latestTimestamp, $now),
            'device_id' => $latestDeviceId,
            'source_topic' => $latestSourceTopic,
            'last_message' => $latestMessage,
        ];
    }

    private function applyExcludedDeviceFilter(\Illuminate\Database\Eloquent\Builder $query, array $excludedDeviceIds): \Illuminate\Database\Eloquent\Builder
    {
        if (!empty($excludedDeviceIds)) {
            $query->whereNotIn('device_id', $excludedDeviceIds);
        }

        return $query;
    }

    private function resolveIncomingTimestamp(?\Illuminate\Database\Eloquent\Model $row): ?CarbonInterface
    {
        if ($row === null) {
            return null;
        }

        $timestamp = $row->timestamp_server ?? $row->created_at ?? $row->updated_at;
        if ($timestamp instanceof CarbonInterface) {
            return $timestamp;
        }

        if (is_string($timestamp) && trim($timestamp) !== '') {
            try {
                return Carbon::parse($timestamp, 'UTC');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function calculateAgeSeconds(?CarbonInterface $timestamp, CarbonInterface $now): ?int
    {
        if ($timestamp === null) {
            return null;
        }

        $ageSeconds = (int) $timestamp->diffInSeconds($now, false);
        return max(0, $ageSeconds);
    }

    private function formatTimestampToWib(?CarbonInterface $timestamp): string
    {
        if ($timestamp === null) {
            return '-';
        }

        try {
            return (clone $timestamp)->setTimezone('Asia/Jakarta')->format('d-m-Y H:i:s') . ' WIB';
        } catch (Throwable) {
            return '-';
        }
    }

    private function buildProtocolConnectionStatus(
        string $protocol,
        ?\Illuminate\Database\Eloquent\Model $latestRow,
        CarbonInterface $now,
        int $freshnessSeconds,
        array $sourceContext = []
    ): array {
        $freshnessSeconds = max(5, $freshnessSeconds);
        $timestamp = $this->resolveIncomingTimestamp($latestRow);
        $ageSeconds = $this->calculateAgeSeconds($timestamp, $now);
        $hasRow = $latestRow !== null;
        $timestampMissing = $hasRow && $timestamp === null;
        $isFilteredFallback = (bool) ($sourceContext['filtered_fallback'] ?? false);
        $filteredDeviceId = isset($sourceContext['filtered_device_id']) && is_numeric($sourceContext['filtered_device_id'])
            ? (int) $sourceContext['filtered_device_id']
            : null;
        $filteredFresh = $isFilteredFallback
            && !$timestampMissing
            && $ageSeconds !== null
            && $ageSeconds <= $freshnessSeconds;

        if ($filteredFresh) {
            $state = 'filtered';
            $label = 'Filtered';
            $connected = false;
            $deviceHint = $filteredDeviceId !== null && $filteredDeviceId > 0 ? " device #{$filteredDeviceId}" : '';
            $detail = "Data protocol terbaru berasal dari sumber simulator{$deviceHint} yang sedang dikecualikan.";
        } elseif (!$hasRow) {
            $state = 'not_found';
            $label = 'Not Found';
            $connected = false;
            $detail = 'Tidak ada data terkirim.';
        } else {
            $connected = !$timestampMissing && $ageSeconds !== null && $ageSeconds <= $freshnessSeconds;
            $state = $connected ? 'connected' : 'stale';
            $label = $connected ? 'Connected' : 'Disconnected';

            if ($connected) {
                $detail = 'Data baru masih dalam batas realtime.';
            } elseif ($timestampMissing) {
                $detail = 'Data ditemukan, tetapi timestamp telemetry tidak valid/kosong.';
            } elseif ($isFilteredFallback && $ageSeconds !== null) {
                $detail = "Data simulator terakhir {$ageSeconds} detik lalu. Menunggu telemetry perangkat fisik terbaru.";
            } else {
                $detail = "Tidak ada data baru lebih dari {$freshnessSeconds} detik.";
            }
        }

        return [
            'protocol' => strtoupper($protocol),
            'connected' => $connected,
            'state' => $state,
            'label' => $label,
            'detail' => $detail,
            'badge_class' => $connected ? 'is-online' : 'is-offline',
            'row_class' => $connected ? 'is-online' : 'is-offline',
            'freshness_seconds' => $freshnessSeconds,
            'age_seconds' => $ageSeconds,
            'last_seen_wib' => $this->formatTimestampToWib($timestamp),
            'filtered_fallback' => $isFilteredFallback,
            'filtered_device_id' => $filteredDeviceId,
            'latest_id' => $latestRow ? (int) $latestRow->id : null,
        ];
    }

    private function resolveFreshProtocolMetricValue(
        ?\Illuminate\Database\Eloquent\Model $latestRow,
        array $connectionStatus,
        string $field
    ): ?float {
        if ($latestRow === null || !($connectionStatus['connected'] ?? false)) {
            return null;
        }

        $value = $latestRow->{$field} ?? null;
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param array<int, float|null> $values
     */
    private function calculateFreshCombinedMetric(array $values): float
    {
        $validValues = array_values(array_filter($values, static fn ($value) => $value !== null));
        if ($validValues === []) {
            return 0.0;
        }

        return (float) (array_sum($validValues) / count($validValues));
    }

    private function buildEsp32ConnectionStatus(
        ?\Illuminate\Database\Eloquent\Model $latestIncomingRow,
        CarbonInterface $now,
        int $freshnessSeconds,
        array $debugHeartbeat = [],
        array $telemetryContext = []
    ): array {
        $freshnessSeconds = max(5, $freshnessSeconds);
        $debugFreshnessSeconds = max(
            $freshnessSeconds,
            (int) config('dashboard.connection.esp32_debug_freshness_seconds', 120)
        );
        $telemetryTimestamp = $this->resolveIncomingTimestamp($latestIncomingRow);
        $telemetryAgeSeconds = $this->calculateAgeSeconds($telemetryTimestamp, $now);
        $telemetryFilteredFallback = (bool) ($telemetryContext['filtered_fallback'] ?? false);
        $telemetryFilteredDeviceId = isset($telemetryContext['filtered_device_id']) && is_numeric($telemetryContext['filtered_device_id'])
            ? (int) $telemetryContext['filtered_device_id']
            : null;
        $telemetryFilteredFresh = $telemetryFilteredFallback
            && $telemetryTimestamp !== null
            && $telemetryAgeSeconds !== null
            && $telemetryAgeSeconds <= $freshnessSeconds;

        $heartbeatTimestamp = ($debugHeartbeat['timestamp'] ?? null) instanceof CarbonInterface
            ? $debugHeartbeat['timestamp']
            : null;
        $heartbeatAgeSeconds = $this->calculateAgeSeconds($heartbeatTimestamp, $now);
        $heartbeatDeviceId = isset($debugHeartbeat['device_id']) && is_numeric($debugHeartbeat['device_id'])
            ? (int) $debugHeartbeat['device_id']
            : null;

        $effectiveTimestamp = $telemetryTimestamp;
        $source = 'telemetry';
        if ($telemetryFilteredFallback && $heartbeatTimestamp !== null) {
            $effectiveTimestamp = $heartbeatTimestamp;
            $source = 'debug_heartbeat';
        } elseif ($heartbeatTimestamp !== null && ($effectiveTimestamp === null || $heartbeatTimestamp->greaterThan($effectiveTimestamp))) {
            $effectiveTimestamp = $heartbeatTimestamp;
            $source = 'debug_heartbeat';
        }

        $ageSeconds = $this->calculateAgeSeconds($effectiveTimestamp, $now);
        if ($source === 'debug_heartbeat') {
            $connected = $heartbeatTimestamp !== null && $heartbeatAgeSeconds !== null && $heartbeatAgeSeconds <= $debugFreshnessSeconds;
        } else {
            $connected = $telemetryTimestamp !== null
                && $telemetryAgeSeconds !== null
                && $telemetryAgeSeconds <= $freshnessSeconds
                && !$telemetryFilteredFresh;
        }
        $lastSeenWib = $this->formatTimestampToWib($effectiveTimestamp);

        if ($source === 'telemetry' && $telemetryFilteredFresh) {
            $deviceHint = $telemetryFilteredDeviceId !== null ? " device #{$telemetryFilteredDeviceId}" : '';
            if ($telemetryTimestamp === null) {
                $detail = "Belum ada telemetry perangkat fisik. Data terbaru berasal dari sumber simulator{$deviceHint} yang sedang dikecualikan.";
            } elseif ($telemetryAgeSeconds !== null) {
                $detail = "Telemetry terbaru {$telemetryAgeSeconds} detik lalu berasal dari sumber simulator{$deviceHint} yang sedang dikecualikan.";
            } else {
                $detail = "Telemetry terbaru berasal dari sumber simulator{$deviceHint} yang sedang dikecualikan.";
            }
        } elseif ($source === 'telemetry' && $telemetryFilteredFallback && $telemetryAgeSeconds !== null) {
            $detail = "Data simulator terakhir {$telemetryAgeSeconds} detik lalu. Menunggu telemetry perangkat fisik.";
        } elseif ($effectiveTimestamp === null) {
            $detail = 'Belum ada telemetry maupun heartbeat debug dari perangkat.';
        } elseif ($connected && $source === 'debug_heartbeat') {
            $deviceHint = $heartbeatDeviceId !== null ? " device #{$heartbeatDeviceId}" : '';
            if ($telemetryTimestamp === null) {
                $detail = "Perangkat aktif via MQTT debug heartbeat{$deviceHint}, tetapi telemetry sensor belum masuk.";
            } elseif ($telemetryAgeSeconds !== null && $telemetryAgeSeconds > $freshnessSeconds) {
                $detail = "Perangkat aktif via debug heartbeat{$deviceHint}, namun telemetry terakhir {$telemetryAgeSeconds} detik lalu (indikasi sensor/bacaan gagal).";
            } else {
                $detail = "Perangkat aktif via telemetry dan debug heartbeat{$deviceHint}.";
            }
        } elseif ($connected) {
            $detail = 'Perangkat terdeteksi aktif dari telemetry.';
        } elseif ($source === 'debug_heartbeat') {
            $detail = "Heartbeat debug terakhir {$ageSeconds} detik lalu. Perangkat tidak lagi realtime.";
        } else {
            $detail = "Perangkat tidak mengirim data baru lebih dari {$freshnessSeconds} detik.";
        }

        return [
            'connected' => $connected,
            'label' => $connected ? 'ON' : 'OFF',
            'detail' => $detail,
            'badge_class' => $connected ? 'is-online' : 'is-offline',
            'source' => $source,
            'freshness_seconds' => $freshnessSeconds,
            'debug_freshness_seconds' => $debugFreshnessSeconds,
            'age_seconds' => $ageSeconds,
            'last_seen_wib' => $lastSeenWib,
            'telemetry_last_seen_wib' => $this->formatTimestampToWib($telemetryTimestamp),
            'telemetry_age_seconds' => $telemetryAgeSeconds,
            'debug_last_seen_wib' => $this->formatTimestampToWib($heartbeatTimestamp),
            'debug_age_seconds' => $heartbeatAgeSeconds,
            'debug_device_id' => $heartbeatDeviceId,
            'filtered_fallback' => $telemetryFilteredFallback,
            'filtered_device_id' => $telemetryFilteredDeviceId,
            'latest_id' => $latestIncomingRow ? (int) $latestIncomingRow->id : null,
        ];
    }

    public function buildResetPagePayload(): array
    {
        try {
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
        } catch (Throwable $e) {
            Log::warning('Reset page fallback: database unavailable.', [
                'error' => $e->getMessage(),
            ]);

            return [
                'totalRows' => 0,
                'mqttRows' => 0,
                'httpRows' => 0,
                'latestWib' => '-',
                'statusType' => 'error',
                'statusMessage' => 'Database tidak terhubung. Jalankan MySQL/XAMPP agar fitur reset dapat digunakan.',
            ];
        }
    }

    public function renderResetPage(array $payload, int $statusCode = 200): Response
    {
        return response()
            ->view('reset-data', $payload, $statusCode)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderDashboardPage(array $payload, int $statusCode = 200): Response
    {
        return response()
            ->view('dashboard', $payload, $statusCode)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }

    private function resolveProtocolTotals(): array
    {
        return [
            'MQTT' => $this->countProtocolRows('MQTT'),
            'HTTP' => $this->countProtocolRows('HTTP'),
        ];
    }

    private function countProtocolRows(string $protocol): int
    {
        return (int) $this->telemetryQuery()
            ->whereRaw('UPPER(protokol) = ?', [strtoupper($protocol)])
            ->count();
    }

    private function buildFieldCompletenessForProtocol(string $protocol, array $requiredFields): array
    {
        $protocolUpper = strtoupper($protocol);
        $baseQuery = $this->telemetryQuery()->whereRaw('UPPER(protokol) = ?', [$protocolUpper]);

        $hasPacketSequence = (clone $baseQuery)->whereNotNull('packet_seq')->exists();
        $scopeQuery = $hasPacketSequence
            ? (clone $baseQuery)->whereNotNull('packet_seq')
            : (clone $baseQuery);

        $aggregateQuery = clone $scopeQuery;
        $grammar = $aggregateQuery->getQuery()->getGrammar();
        $selectParts = ['COUNT(*) as total_rows'];

        foreach (array_keys($requiredFields) as $fieldKey) {
            $wrappedField = $grammar->wrap($fieldKey);
            $selectParts[] = "SUM(CASE WHEN {$wrappedField} IS NULL THEN 1 ELSE 0 END) AS missing_{$fieldKey}";
        }

        $aggregate = $aggregateQuery
            ->selectRaw(implode(', ', $selectParts))
            ->first();

        $total = (int) ($aggregate?->total_rows ?? 0);
        $fields = [];

        foreach ($requiredFields as $fieldKey => $fieldLabel) {
            $missingKey = 'missing_' . $fieldKey;
            $missing = (int) ($aggregate?->{$missingKey} ?? 0);
            $valid = max(0, $total - $missing);
            $fields[$fieldKey] = [
                'label' => $fieldLabel,
                'valid' => $valid,
                'missing' => $missing,
                'total' => $total,
            ];
        }

        return [
            'total' => $total,
            'fields' => $fields,
        ];
    }

    private function resolveWarningConfig(): array
    {
        $mqttMinScore = (float) config('dashboard.warnings.mqtt_health_min_score', 70);
        $httpMinScore = (float) config('dashboard.warnings.http_health_min_score', 70);
        $balanceMinSamples = (int) config('dashboard.warnings.balance_min_samples', 20);
        $balanceAllowedDelta = (int) config('dashboard.warnings.balance_allowed_delta', 3);
        $balanceAllowedRatio = (float) config('dashboard.warnings.balance_allowed_ratio', 0.12);

        return [
            'mqtt_health_min_score' => max(0.0, min(100.0, $mqttMinScore)),
            'http_health_min_score' => max(0.0, min(100.0, $httpMinScore)),
            'balance_min_samples' => max(1, $balanceMinSamples),
            'balance_allowed_delta' => max(0, $balanceAllowedDelta),
            'balance_allowed_ratio' => max(0.0, $balanceAllowedRatio),
        ];
    }

    private function isDatabaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (Throwable $e) {
            Log::warning('Dashboard fallback: database unavailable.', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function buildDashboardFallbackPayload(string $warningMessage): array
    {
        $emptyTTest = [
            'valid' => false,
            'message' => 'Analisis statistik belum tersedia karena database belum terhubung.',
            'data1' => ['n' => 0, 'mean' => 0.0, 'variance' => 0.0, 'std_dev' => 0.0],
            'data2' => ['n' => 0, 'mean' => 0.0, 'variance' => 0.0, 'std_dev' => 0.0],
            't_value' => 0.0,
            'df' => 0,
            'critical_value' => 1.96,
            'is_significant' => false,
            'p_value' => 1.0,
            'interpretation' => 'Belum dapat dihitung.',
        ];

        return [
            'summary' => [
                'mqtt' => [
                    'total_data' => 0,
                    'avg_latency_ms' => 0.0,
                    'avg_daya_mw' => 0.0,
                    'avg_suhu' => 0.0,
                    'avg_kelembapan' => 0.0,
                    'std_latency' => 0.0,
                    'std_daya' => 0.0,
                    'std_kelembapan' => 0.0,
                ],
                'http' => [
                    'total_data' => 0,
                    'avg_latency_ms' => 0.0,
                    'avg_daya_mw' => 0.0,
                    'avg_suhu' => 0.0,
                    'avg_kelembapan' => 0.0,
                    'std_latency' => 0.0,
                    'std_daya' => 0.0,
                    'std_kelembapan' => 0.0,
                ],
                'ttest_latency' => $emptyTTest,
                'ttest_daya' => $emptyTTest,
            ],
            'reliability' => [
                'mqtt_reliability' => 0.0,
                'http_reliability' => 0.0,
                'mqtt_total_sent' => 0,
                'http_total_sent' => 0,
                'reliability_window_limit' => 0,
                'mqtt_window_size' => 0,
                'http_window_size' => 0,
                'mqtt_sequence_reliability' => 0.0,
                'http_sequence_reliability' => 0.0,
                'mqtt_data_completeness' => 0.0,
                'http_data_completeness' => 0.0,
                'mqtt_transmission_health' => 0.0,
                'http_transmission_health' => 0.0,
                'mqtt_expected_packets' => 0,
                'http_expected_packets' => 0,
                'mqtt_received_packets' => 0,
                'http_received_packets' => 0,
                'mqtt_missing_packets' => 0,
                'http_missing_packets' => 0,
            ],
            'latencyChartData' => [
                'labels' => [],
                'time_labels' => [],
                'full_time_labels' => [],
                'datasets' => [],
                'total_points' => 0,
                'total_records' => 0,
                'display_timezone' => 'Asia/Jakarta',
            ],
            'powerChartData' => [
                'labels' => [],
                'time_labels' => [],
                'full_time_labels' => [],
                'mqtt' => [],
                'http' => [],
                'total_points' => 0,
                'total_records' => 0,
                'display_timezone' => 'Asia/Jakarta',
            ],
            'mqttTotal' => 0,
            'httpTotal' => 0,
            'mqttConnected' => false,
            'httpConnected' => false,
            'esp32Connected' => false,
            'mqttConnectionStatus' => [
                'protocol' => 'MQTT',
                'connected' => false,
                'state' => 'not_found',
                'label' => 'Not Found',
                'detail' => 'Belum ada data telemetry.',
                'badge_class' => 'is-offline',
                'row_class' => 'is-offline',
                'freshness_seconds' => 30,
                'age_seconds' => null,
                'last_seen_wib' => '-',
                'latest_id' => null,
            ],
            'httpConnectionStatus' => [
                'protocol' => 'HTTP',
                'connected' => false,
                'state' => 'not_found',
                'label' => 'Not Found',
                'detail' => 'Belum ada data telemetry.',
                'badge_class' => 'is-offline',
                'row_class' => 'is-offline',
                'freshness_seconds' => 30,
                'age_seconds' => null,
                'last_seen_wib' => '-',
                'latest_id' => null,
            ],
            'esp32ConnectionStatus' => [
                'connected' => false,
                'label' => 'OFF',
                'detail' => 'Belum ada telemetry maupun heartbeat debug dari perangkat.',
                'badge_class' => 'is-offline',
                'source' => 'none',
                'freshness_seconds' => 30,
                'age_seconds' => null,
                'last_seen_wib' => '-',
                'telemetry_last_seen_wib' => '-',
                'telemetry_age_seconds' => null,
                'debug_last_seen_wib' => '-',
                'debug_age_seconds' => null,
                'debug_device_id' => null,
                'latest_id' => null,
            ],
            'connectionConfig' => [
                'protocol_freshness_seconds' => 30,
                'esp32_freshness_seconds' => 30,
                'ignore_simulator_when_stopped' => true,
            ],
            'simulationRunning' => false,
            'excludeSimulatorStatusSource' => true,
            'mqttAvgSuhu' => 0.0,
            'mqttAvgKelembapan' => 0.0,
            'httpAvgSuhu' => 0.0,
            'httpAvgKelembapan' => 0.0,
            'avgSuhu' => 0.0,
            'avgKelembapan' => 0.0,
            'headerSuhuDelta' => null,
            'headerKelembapanDelta' => null,
            'fieldCompleteness' => [
                'MQTT' => ['total' => 0, 'fields' => []],
                'HTTP' => ['total' => 0, 'fields' => []],
            ],
            'dataWarnings' => [$warningMessage],
            'protocolDiagnostics' => [
                'mqtt' => ['protocol' => 'MQTT', 'available' => false],
                'http' => ['protocol' => 'HTTP', 'available' => false],
                'delta' => null,
                'pair_available' => false,
                'sensor_sync_note' => 'Mode aman aktif: dashboard belum dapat mengambil data dari database.',
            ],
        ];
    }
}
