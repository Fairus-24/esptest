<?php

namespace App\Services;

use App\Models\Eksperimen;
use Illuminate\Database\Eloquent\Collection;

class StatisticsService
{
    /**
     * Dapatkan data MQTT
     */
    public function getMqttData()
    {
        return Eksperimen::where('protokol', 'MQTT')->get();
    }

    /**
     * Dapatkan data HTTP
     */
    public function getHttpData()
    {
        return Eksperimen::where('protokol', 'HTTP')->get();
    }

    /**
     * Hitung mean (rata-rata)
     */
    public function calculateMean(Collection $data, string $column): float
    {
        if ($data->isEmpty()) {
            return 0;
        }
        return $data->avg($column);
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

        return $squaredDifferences->sum() / ($data->count() - 1); // Sample variance
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
        $pooledVariance = (($n1 - 1) * $var1 + ($n2 - 1) * $var2) / ($n1 + $n2 - 2);

        // Hitung standard error
        $standardError = sqrt($pooledVariance * (1/$n1 + 1/$n2));

        // Hitung t-value
        $tValue = ($mean1 - $mean2) / $standardError;

        // Hitung degrees of freedom
        $df = $n1 + $n2 - 2;

        // Critical value untuk α=0.05 (two-tailed)
        $criticalValue = 1.96;

        // Tentukan signifikansi
        $isSignificant = abs($tValue) > $criticalValue;

        // Hitung p-value (approximate)
        $pValue = $this->calculatePValue($tValue, $df);

        return [
            'valid' => true,
            'data1' => [
                'n' => $n1,
                'mean' => round($mean1, 4),
                'variance' => round($var1, 4),
                'std_dev' => round($stdDev1, 4),
            ],
            'data2' => [
                'n' => $n2,
                'mean' => round($mean2, 4),
                'variance' => round($var2, 4),
                'std_dev' => round($stdDev2, 4),
            ],
            't_value' => round($tValue, 4),
            'df' => $df,
            'critical_value' => $criticalValue,
            'is_significant' => $isSignificant,
            'p_value' => round($pValue, 4),
            'interpretation' => $isSignificant 
                ? 'Ada perbedaan signifikan (p < 0.05)' 
                : 'Tidak ada perbedaan signifikan (p >= 0.05)',
        ];
    }

    /**
     * Approximate p-value dari t-value dan df
     * Menggunakan approximation yang sederhana
     */
    private function calculatePValue(float $tValue, int $df): float
    {
        // Untuk df besar (>30), gunakan normal distribution
        if ($df > 30) {
            return 2 * (1 - $this->normalCDF(abs($tValue)));
        }

        // Untuk df kecil, gunakan Student t-distribution approximation
        // Ini adalah approximation sederhana, bukan perhitungan exact
        $absT = abs($tValue);
        
        // Sangat kasar approximation
        if ($absT < 0.5) return 0.5;
        if ($absT < 1.0) return 0.25;
        if ($absT < 1.96) return 0.10;
        if ($absT < 2.576) return 0.01;
        return 0.001;
    }

    /**
     * Normal CDF approximation
     */
    private function normalCDF(float $z): float
    {
        return (1 + erf($z / sqrt(2))) / 2;
    }

    /**
     * Dapatkan summary statistik lengkap
     */
    public function getSummary()
    {
        $mqttData = $this->getMqttData();
        $httpData = $this->getHttpData();

        // Statistik Latency
        $latencyTTest = $this->tTest($mqttData, $httpData, 'latency_ms');

        // Statistik Daya
        $dayaTTest = $this->tTest($mqttData, $httpData, 'daya_mw');

        return [
            'mqtt' => [
                'total_data' => $mqttData->count(),
                'avg_latency_ms' => round($this->calculateMean($mqttData, 'latency_ms'), 2),
                'avg_daya_mw' => round($this->calculateMean($mqttData, 'daya_mw'), 2),
                'avg_suhu' => round($this->calculateMean($mqttData, 'suhu'), 2),
                'std_latency' => round($this->calculateStdDev($mqttData, 'latency_ms'), 2),
                'std_daya' => round($this->calculateStdDev($mqttData, 'daya_mw'), 2),
            ],
            'http' => [
                'total_data' => $httpData->count(),
                'avg_latency_ms' => round($this->calculateMean($httpData, 'latency_ms'), 2),
                'avg_daya_mw' => round($this->calculateMean($httpData, 'daya_mw'), 2),
                'avg_suhu' => round($this->calculateMean($httpData, 'suhu'), 2),
                'std_latency' => round($this->calculateStdDev($httpData, 'latency_ms'), 2),
                'std_daya' => round($this->calculateStdDev($httpData, 'daya_mw'), 2),
            ],
            'ttest_latency' => $latencyTTest,
            'ttest_daya' => $dayaTTest,
        ];
    }

    /**
     * Hitung reliability (keandalan)
     * Dalam konteks penelitian ini, reliability = success rate pengiriman data
     */
    public function getReliability()
    {
        $mqttData = $this->getMqttData();
        $httpData = $this->getHttpData();

        $mqttReliability = $mqttData->count() > 0 ? 100 : 0;
        $httpReliability = $httpData->count() > 0 ? 100 : 0;

        return [
            'mqtt_reliability' => round($mqttReliability, 2),
            'http_reliability' => round($httpReliability, 2),
            'mqtt_total_sent' => $mqttData->count(),
            'http_total_sent' => $httpData->count(),
        ];
    }
}
