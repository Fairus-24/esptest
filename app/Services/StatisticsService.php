<?php

namespace App\Services;

use App\Models\Eksperimen;
use App\Models\SimulatedEksperimen;
use Illuminate\Database\Eloquent\Collection;

class StatisticsService
{
    private const RELIABILITY_WINDOW_SIZE = 300;
    private string $telemetryModelClass = Eksperimen::class;

    public function setTelemetrySource(string $source): self
    {
        $normalized = strtolower(trim($source));
        $this->telemetryModelClass = in_array($normalized, ['simulation', 'sim'], true)
            ? SimulatedEksperimen::class
            : Eksperimen::class;

        return $this;
    }

    /**
     * Dapatkan data MQTT
     */
    public function getMqttData(): Collection
    {
        return $this->getProtocolData('MQTT', $this->analysisWindowSize());
    }

    /**
     * Dapatkan data HTTP
     */
    public function getHttpData(): Collection
    {
        return $this->getProtocolData('HTTP', $this->analysisWindowSize());
    }

    /**
     * Hitung mean (rata-rata)
     */
    public function calculateMean(Collection $data, string $column): float
    {
        if ($data->isEmpty()) {
            return 0.0;
        }
        return (float) ($data->avg($column) ?? 0.0);
    }

    /**
     * Hitung variance (varians)
     */
    public function calculateVariance(Collection $data, string $column): float
    {
        if ($data->count() < 2) {
            return 0;
        }

        $mean = $this->calculateMean($data, $column);
        $squaredDifferences = $data->map(function ($item) use ($mean, $column) {
            return pow($item->$column - $mean, 2);
        });

        $denominator = $data->count() - 1;
        if ($denominator == 0) {
            return 0;
        }
        return $squaredDifferences->sum() / $denominator; // Sample variance
    }

    /**
     * Hitung standard deviation (simpangan baku)
     */
    public function calculateStdDev(Collection $data, string $column): float
    {
        return sqrt($this->calculateVariance($data, $column));
    }

    /**
     * Independent Sample T-Test
     * 
     * Menguji apakah ada perbedaan signifikan antara dua sampel
     * H0: mean1 = mean2 (tidak ada perbedaan)
     * H1: mean1 ≠ mean2 (ada perbedaan)
     * 
     * Alpha (α) = 0.05 (two-tailed)
     * Critical value = ±1.96
     */
    public function tTest(Collection $data1, Collection $data2, string $column)
    {
        $n1 = $data1->count();
        $n2 = $data2->count();

        if ($n1 < 2 || $n2 < 2) {
            return [
                'valid' => false,
                'message' => 'Minimal 2 data point diperlukan untuk setiap grup',
            ];
        }

        // Hitung statistik dasar
        $mean1 = $this->calculateMean($data1, $column);
        $mean2 = $this->calculateMean($data2, $column);
        $var1 = $this->calculateVariance($data1, $column);
        $var2 = $this->calculateVariance($data2, $column);
        $stdDev1 = sqrt($var1);
        $stdDev2 = sqrt($var2);

        // Hitung pooled variance
        $df = $n1 + $n2 - 2;
        if ($df == 0) {
            return [
                'valid' => false,
                'message' => 'Degrees of freedom = 0, tidak bisa t-test',
            ];
        }
        $pooledVariance = (($n1 - 1) * $var1 + ($n2 - 1) * $var2) / $df;

        // Hitung standard error
        $standardErrorDenom = ($n1 > 0 ? 1 / $n1 : 0) + ($n2 > 0 ? 1 / $n2 : 0);
        if ($standardErrorDenom == 0) {
            return [
                'valid' => false,
                'message' => 'Standard error denominator = 0, tidak bisa t-test',
            ];
        }
        $standardError = sqrt(max(0, $pooledVariance) * $standardErrorDenom);

        $criticalValue = 1.96;
        $note = null;

        if ($standardError == 0.0) {
            // Edge case: variansi nol (nilai konstan).
            $meanDiff = $mean1 - $mean2;
            if (abs($meanDiff) < 0.000000001) {
                $tValue = 0.0;
                $pValue = 1.0;
                $isSignificant = false;
                $note = 'Variansi nol: nilai kedua grup konstan dan mean sama.';
            } else {
                $tValue = $meanDiff > 0 ? 999999.0 : -999999.0;
                $pValue = 0.0;
                $isSignificant = true;
                $note = 'Variansi nol: nilai kedua grup konstan tetapi mean berbeda.';
            }
        } else {
            // Hitung t-value normal
            $tValue = ($mean1 - $mean2) / $standardError;
            // Hitung p-value (approximate)
            $pValue = $this->calculatePValue($tValue, $df);
            // Tentukan signifikansi
            $isSignificant = abs($tValue) > $criticalValue;
        }

        $interpretation = $isSignificant
            ? 'Ada perbedaan signifikan (p < 0.05)'
            : 'Tidak ada perbedaan signifikan (p >= 0.05)';

        if ($note !== null) {
            $interpretation .= ' | ' . $note;
        }

        return [
            'valid' => true,
            'data1' => [
                'n' => $n1,
                'mean' => $mean1,
                'variance' => $var1,
                'std_dev' => $stdDev1,
            ],
            'data2' => [
                'n' => $n2,
                'mean' => $mean2,
                'variance' => $var2,
                'std_dev' => $stdDev2,
            ],
            't_value' => $tValue,
            'df' => $df,
            'critical_value' => $criticalValue,
            'is_significant' => $isSignificant,
            'p_value' => $pValue,
            'interpretation' => $interpretation,
            'note' => $note,
        ];
    }

    /**
     * Hitung p-value two-tailed dari t-value dan df.
     *
     * Implementasi memakai regularized incomplete beta function
     * untuk menghindari pembulatan kasar/bucketed approximation.
     */
    private function calculatePValue(float $tValue, int $df): float
    {
        $degreesOfFreedom = max(1, $df);
        $absT = abs($tValue);
        if ($absT == 0.0) {
            return 1.0;
        }

        $x = $degreesOfFreedom / ($degreesOfFreedom + ($absT * $absT));
        $pValue = $this->regularizedIncompleteBeta($x, $degreesOfFreedom / 2.0, 0.5);

        return max(0.0, min(1.0, $pValue));
    }

    /**
     * Regularized incomplete beta I_x(a, b).
     */
    private function regularizedIncompleteBeta(float $x, float $a, float $b): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }
        if ($x >= 1.0) {
            return 1.0;
        }

        $lnBeta = $this->logGamma($a + $b) - $this->logGamma($a) - $this->logGamma($b);
        $front = exp($lnBeta + ($a * log($x)) + ($b * log(1.0 - $x)));
        $switchThreshold = ($a + 1.0) / ($a + $b + 2.0);

        if ($x < $switchThreshold) {
            return $front * $this->betaContinuedFraction($a, $b, $x) / $a;
        }

        return 1.0 - ($front * $this->betaContinuedFraction($b, $a, 1.0 - $x) / $b);
    }

    /**
     * Continued fraction helper untuk incomplete beta.
     */
    private function betaContinuedFraction(float $a, float $b, float $x): float
    {
        $maxIterations = 200;
        $epsilon = 3.0e-14;
        $fpMin = 1.0e-300;

        $qab = $a + $b;
        $qap = $a + 1.0;
        $qam = $a - 1.0;

        $c = 1.0;
        $d = 1.0 - (($qab * $x) / $qap);
        if (abs($d) < $fpMin) {
            $d = $fpMin;
        }
        $d = 1.0 / $d;
        $h = $d;

        for ($m = 1; $m <= $maxIterations; $m++) {
            $m2 = 2 * $m;
            $aa = ($m * ($b - $m) * $x) / (($qam + $m2) * ($a + $m2));
            $d = 1.0 + ($aa * $d);
            if (abs($d) < $fpMin) {
                $d = $fpMin;
            }
            $c = 1.0 + ($aa / $c);
            if (abs($c) < $fpMin) {
                $c = $fpMin;
            }
            $d = 1.0 / $d;
            $h *= $d * $c;

            $aa = -(($a + $m) * ($qab + $m) * $x) / (($a + $m2) * ($qap + $m2));
            $d = 1.0 + ($aa * $d);
            if (abs($d) < $fpMin) {
                $d = $fpMin;
            }
            $c = 1.0 + ($aa / $c);
            if (abs($c) < $fpMin) {
                $c = $fpMin;
            }
            $d = 1.0 / $d;
            $delta = $d * $c;
            $h *= $delta;

            if (abs($delta - 1.0) < $epsilon) {
                break;
            }
        }

        return $h;
    }

    /**
     * Log-gamma via Lanczos approximation.
     * Menghindari ketergantungan ekstensi php tertentu (lgamma).
     */
    private function logGamma(float $value): float
    {
        $coefficients = [
            676.5203681218851,
            -1259.1392167224028,
            771.3234287776531,
            -176.6150291621406,
            12.507343278686905,
            -0.13857109526572012,
            0.000009984369578019572,
            0.00000015056327351493116,
        ];

        if ($value < 0.5) {
            return log(M_PI) - log(sin(M_PI * $value)) - $this->logGamma(1.0 - $value);
        }

        $adjusted = $value - 1.0;
        $series = 0.9999999999998099;

        foreach ($coefficients as $index => $coefficient) {
            $series += $coefficient / ($adjusted + $index + 1.0);
        }

        $t = $adjusted + count($coefficients) - 0.5;

        return (0.5 * log(2.0 * M_PI))
            + (($adjusted + 0.5) * log($t))
            - $t
            + log($series);
    }

    /**
     * Dapatkan summary statistik lengkap
     */
    public function getSummary(?Collection $mqttData = null, ?Collection $httpData = null)
    {
        $mqttData = $mqttData ?? $this->getMqttData();
        $httpData = $httpData ?? $this->getHttpData();

        // Statistik Latency
        $latencyTTest = $this->tTest($mqttData, $httpData, 'latency_ms');
        // Statistik Daya
        $dayaTTest = $this->tTest($mqttData, $httpData, 'daya_mw');
        // Statistik Kelembapan (optional t-test)
        // $kelembapanTTest = $this->tTest($mqttData, $httpData, 'kelembapan');

        return [
            'mqtt' => [
                'total_data' => $mqttData->count(),
                'avg_latency_ms' => round($this->calculateMean($mqttData, 'latency_ms'), 2),
                'avg_daya_mw' => round($this->calculateMean($mqttData, 'daya_mw'), 2),
                'avg_suhu' => round($this->calculateMean($mqttData, 'suhu'), 2),
                'avg_kelembapan' => round($this->calculateMean($mqttData, 'kelembapan'), 2),
                'std_latency' => round($this->calculateStdDev($mqttData, 'latency_ms'), 2),
                'std_daya' => round($this->calculateStdDev($mqttData, 'daya_mw'), 2),
                'std_kelembapan' => round($this->calculateStdDev($mqttData, 'kelembapan'), 2),
            ],
            'http' => [
                'total_data' => $httpData->count(),
                'avg_latency_ms' => round($this->calculateMean($httpData, 'latency_ms'), 2),
                'avg_daya_mw' => round($this->calculateMean($httpData, 'daya_mw'), 2),
                'avg_suhu' => round($this->calculateMean($httpData, 'suhu'), 2),
                'avg_kelembapan' => round($this->calculateMean($httpData, 'kelembapan'), 2),
                'std_latency' => round($this->calculateStdDev($httpData, 'latency_ms'), 2),
                'std_daya' => round($this->calculateStdDev($httpData, 'daya_mw'), 2),
                'std_kelembapan' => round($this->calculateStdDev($httpData, 'kelembapan'), 2),
            ],
            'ttest_latency' => $latencyTTest,
            'ttest_daya' => $dayaTTest,
            // 'ttest_kelembapan' => $kelembapanTTest,
        ];
    }

    /**
     * Hitung reliability (keandalan)
     * Dalam konteks penelitian ini, reliability = success rate pengiriman data
     */
    public function getReliability()
    {
        $mqttData = $this->getRecentProtocolData('MQTT');
        $httpData = $this->getRecentProtocolData('HTTP');

        $mqttSeqStats = $this->calculateSequenceReliability($mqttData);
        $httpSeqStats = $this->calculateSequenceReliability($httpData);
        $mqttQualityScope = $mqttSeqStats['has_sequence'] ? $mqttData->whereNotNull('packet_seq') : $mqttData;
        $httpQualityScope = $httpSeqStats['has_sequence'] ? $httpData->whereNotNull('packet_seq') : $httpData;
        $mqttCompleteness = $this->calculateRequiredFieldCompleteness($mqttQualityScope);
        $httpCompleteness = $this->calculateRequiredFieldCompleteness($httpQualityScope);
        $mqttTransmissionHealth = $this->calculateTransmissionHealth($mqttQualityScope, 'MQTT');
        $httpTransmissionHealth = $this->calculateTransmissionHealth($httpQualityScope, 'HTTP');

        $mqttReliability = $this->combineReliability(
            $mqttSeqStats['rate'],
            $mqttCompleteness,
            $mqttTransmissionHealth,
            $mqttSeqStats['has_sequence']
        );
        $httpReliability = $this->combineReliability(
            $httpSeqStats['rate'],
            $httpCompleteness,
            $httpTransmissionHealth,
            $httpSeqStats['has_sequence']
        );

        return [
            'mqtt_reliability' => round($mqttReliability, 2),
            'http_reliability' => round($httpReliability, 2),
            'mqtt_total_sent' => $mqttData->count(),
            'http_total_sent' => $httpData->count(),
            'reliability_window_limit' => self::RELIABILITY_WINDOW_SIZE,
            'mqtt_window_size' => $mqttData->count(),
            'http_window_size' => $httpData->count(),
            'mqtt_sequence_reliability' => round($mqttSeqStats['rate'], 2),
            'http_sequence_reliability' => round($httpSeqStats['rate'], 2),
            'mqtt_data_completeness' => round($mqttCompleteness, 2),
            'http_data_completeness' => round($httpCompleteness, 2),
            'mqtt_transmission_health' => round($mqttTransmissionHealth, 2),
            'http_transmission_health' => round($httpTransmissionHealth, 2),
            'mqtt_expected_packets' => $mqttSeqStats['expected'],
            'http_expected_packets' => $httpSeqStats['expected'],
            'mqtt_received_packets' => $mqttSeqStats['received'],
            'http_received_packets' => $httpSeqStats['received'],
            'mqtt_missing_packets' => $mqttSeqStats['missing'],
            'http_missing_packets' => $httpSeqStats['missing'],
        ];
    }

    private function combineReliability(
        float $sequenceRate,
        float $completenessRate,
        float $transmissionRate,
        bool $hasSequence
    ): float
    {
        if (!$hasSequence) {
            return ($completenessRate * 0.6) + ($transmissionRate * 0.4);
        }

        // Sequence continuity lebih merepresentasikan packet loss,
        // completeness menjaga kualitas payload,
        // transmission health memberi bobot kestabilan latency + tx.
        return ($sequenceRate * 0.55) + ($completenessRate * 0.25) + ($transmissionRate * 0.20);
    }

    private function getRecentProtocolData(string $protocol): Collection
    {
        return $this->telemetryQuery()
            ->where('protokol', strtoupper($protocol))
            ->orderByDesc('id')
            ->limit(self::RELIABILITY_WINDOW_SIZE)
            ->get()
            ->sortBy('id')
            ->values();
    }

    private function getProtocolData(string $protocol, int $limit): Collection
    {
        $query = $this->telemetryQuery()
            ->where('protokol', strtoupper($protocol));

        if ($limit > 0) {
            return $query
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->sortBy('id')
                ->values();
        }

        return $query
            ->orderBy('id')
            ->get()
            ->values();
    }

    private function telemetryQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $modelClass = $this->telemetryModelClass;

        return $modelClass::query();
    }

    private function analysisWindowSize(): int
    {
        return max(0, (int) config('dashboard.analysis_window', 0));
    }

    private function calculateRequiredFieldCompleteness(Collection $data): float
    {
        $total = $data->count();
        if ($total === 0) {
            return 0.0;
        }

        $requiredFields = [
            'suhu',
            'kelembapan',
            'timestamp_esp',
            'timestamp_server',
            'latency_ms',
            'daya_mw',
            'packet_seq',
            'rssi_dbm',
            'tx_duration_ms',
            'payload_bytes',
            'uptime_s',
            'free_heap_bytes',
            'sensor_age_ms',
            'sensor_read_seq',
            'send_tick_ms',
        ];

        $valid = $data->filter(function ($row) use ($requiredFields) {
            foreach ($requiredFields as $field) {
                if (!isset($row->{$field})) {
                    return false;
                }
            }

            return true;
        })->count();

        return ($valid / $total) * 100;
    }

    private function calculateSequenceReliability(Collection $data): array
    {
        $withSequence = $data->filter(function ($row) {
            return isset($row->packet_seq) && is_numeric($row->packet_seq);
        });

        if ($withSequence->isEmpty()) {
            return [
                'has_sequence' => false,
                'rate' => 0.0,
                'expected' => 0,
                'received' => 0,
                'missing' => 0,
            ];
        }

        $expected = 0;
        $received = 0;
        $maxGapForLoss = max(1, (int) config('dashboard.sequence.max_gap_for_loss', 120));
        $rebootUptimeDropSeconds = max(1, (int) config('dashboard.sequence.reboot_uptime_drop_seconds', 30));

        foreach ($withSequence->groupBy('device_id') as $deviceRows) {
            $deviceStats = $this->calculateDeviceSequenceStats(
                $deviceRows->sortBy('id')->values(),
                $maxGapForLoss,
                $rebootUptimeDropSeconds
            );

            $expected += $deviceStats['expected'];
            $received += $deviceStats['received'];
        }

        if ($expected === 0) {
            return [
                'has_sequence' => false,
                'rate' => 0.0,
                'expected' => 0,
                'received' => 0,
                'missing' => 0,
            ];
        }

        $missing = max(0, $expected - $received);
        $rate = ($received / $expected) * 100;

        return [
            'has_sequence' => true,
            'rate' => $rate,
            'expected' => $expected,
            'received' => $received,
            'missing' => $missing,
        ];
    }

    private function calculateDeviceSequenceStats(Collection $rows, int $maxGapForLoss, int $rebootUptimeDropSeconds): array
    {
        $expected = 0;
        $received = 0;
        $previousSeq = null;
        $previousUptime = null;

        foreach ($rows as $row) {
            if (!isset($row->packet_seq) || !is_numeric($row->packet_seq)) {
                continue;
            }

            $currentSeq = (int) $row->packet_seq;
            $currentUptime = isset($row->uptime_s) && is_numeric($row->uptime_s)
                ? (int) $row->uptime_s
                : null;

            if ($previousSeq === null) {
                $expected += 1;
                $received += 1;
                $previousSeq = $currentSeq;
                $previousUptime = $currentUptime;
                continue;
            }

            $seqDiff = $currentSeq - $previousSeq;
            if ($seqDiff === 0) {
                $previousUptime = $currentUptime;
                continue;
            }

            $uptimeResetDetected = $currentUptime !== null
                && $previousUptime !== null
                && ($currentUptime + $rebootUptimeDropSeconds) < $previousUptime;

            $startsNewSegment = $uptimeResetDetected
                || $seqDiff < 0
                || $seqDiff > $maxGapForLoss;

            if ($startsNewSegment) {
                $expected += 1;
                $received += 1;
            } else {
                $expected += $seqDiff;
                $received += 1;
            }

            $previousSeq = $currentSeq;
            $previousUptime = $currentUptime;
        }

        return [
            'expected' => $expected,
            'received' => $received,
        ];
    }

    private function calculateTransmissionHealth(Collection $data, string $protocol): float
    {
        if ($data->isEmpty()) {
            return 0.0;
        }

        $thresholds = $this->resolveTransmissionHealthThresholds($protocol);
        $weights = $this->resolveTransmissionHealthWeights();
        $latencyTargetMs = $thresholds['latency_target_ms'];
        $txTargetMs = $thresholds['tx_target_ms'];

        $scores = $data->filter(function ($row) {
            return isset($row->latency_ms, $row->tx_duration_ms)
                && is_numeric($row->latency_ms)
                && is_numeric($row->tx_duration_ms);
        })->map(function ($row) use ($latencyTargetMs, $txTargetMs, $weights) {
            $latency = max(0.0, (float) $row->latency_ms);
            $txDuration = max(0.0, (float) $row->tx_duration_ms);
            $payloadBytes = isset($row->payload_bytes) && is_numeric($row->payload_bytes) ? (int) $row->payload_bytes : 0;

            $latencyScore = 100.0 - min(100.0, ($latency / max(1.0, $latencyTargetMs)) * 100.0);
            $txScore = 100.0 - min(100.0, ($txDuration / max(1.0, $txTargetMs)) * 100.0);
            $payloadScore = $payloadBytes > 0 ? 100.0 : 0.0;

            return ($latencyScore * $weights['latency'])
                + ($txScore * $weights['tx_duration'])
                + ($payloadScore * $weights['payload']);
        });

        if ($scores->isEmpty()) {
            return 0.0;
        }

        return (float) $scores->avg();
    }

    private function resolveTransmissionHealthThresholds(string $protocol): array
    {
        $isMqtt = strtoupper($protocol) === 'MQTT';
        $protocolKey = $isMqtt ? 'mqtt' : 'http';

        $config = config("dashboard.transmission_health.{$protocolKey}", []);
        if (!is_array($config)) {
            $config = [];
        }

        $defaultLatencyTarget = $isMqtt ? 1500.0 : 3000.0;
        $defaultTxTarget = $isMqtt ? 120.0 : 4500.0;

        $latencyTarget = isset($config['latency_target_ms']) ? (float) $config['latency_target_ms'] : $defaultLatencyTarget;
        $txTarget = isset($config['tx_target_ms']) ? (float) $config['tx_target_ms'] : $defaultTxTarget;

        return [
            'latency_target_ms' => max(1.0, $latencyTarget),
            'tx_target_ms' => max(1.0, $txTarget),
        ];
    }

    private function resolveTransmissionHealthWeights(): array
    {
        $weights = config('dashboard.transmission_health.weights', []);
        if (!is_array($weights)) {
            $weights = [];
        }

        $latencyWeight = max(0.0, isset($weights['latency']) ? (float) $weights['latency'] : 0.50);
        $txWeight = max(0.0, isset($weights['tx_duration']) ? (float) $weights['tx_duration'] : 0.35);
        $payloadWeight = max(0.0, isset($weights['payload']) ? (float) $weights['payload'] : 0.15);
        $totalWeight = $latencyWeight + $txWeight + $payloadWeight;

        if ($totalWeight <= 0.0) {
            return [
                'latency' => 0.50,
                'tx_duration' => 0.35,
                'payload' => 0.15,
            ];
        }

        return [
            'latency' => $latencyWeight / $totalWeight,
            'tx_duration' => $txWeight / $totalWeight,
            'payload' => $payloadWeight / $totalWeight,
        ];
    }
}
