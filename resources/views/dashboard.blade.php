<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Analisis Komparatif MQTT vs HTTP</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            color: white;
            margin-bottom: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-card span {
            display: block;
        }

        .stat-label {
            font-size: 0.95em;
            color: #666;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #333;
        }

        .stat-unit {
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }

        .mqtt-color {
            border-left: 5px solid #007bff;
        }

        .http-color {
            border-left: 5px solid #28a745;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .chart-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .ttest-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .ttest-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .ttest-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .ttest-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .ttest-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.95em;
        }

        .ttest-row:last-child {
            border-bottom: none;
        }

        .ttest-label {
            font-weight: 500;
            color: #555;
        }

        .ttest-value {
            font-weight: 600;
            color: #333;
        }

        .significance-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            margin-top: 10px;
        }

        .significance-yes {
            background-color: #d4edda;
            color: #155724;
        }

        .significance-no {
            background-color: #f8d7da;
            color: #721c24;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-style: italic;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95em;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 3px;
        }

        .mqtt-legend {
            background-color: #007bff;
        }

        .http-legend {
            background-color: #28a745;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 1.8em;
            }
        }

        .footer {
            text-align: center;
            color: rgba(255,255,255,0.8);
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Analisis Komparatif MQTT vs HTTP</h1>
            <p>Monitoring Suhu & Analisis Latensi dengan ESP32</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <!-- MQTT Stats -->
            <div class="stat-card mqtt-color">
                <span class="stat-label">MQTT - Total Data</span>
                <span class="stat-value">{{ $summary['mqtt']['total_data'] }}</span>
                <span class="stat-unit">data point</span>
            </div>

            <div class="stat-card mqtt-color">
                <span class="stat-label">MQTT - Avg Latency</span>
                <span class="stat-value">{{ $summary['mqtt']['avg_latency_ms'] }}</span>
                <span class="stat-unit">ms</span>
            </div>

            <div class="stat-card mqtt-color">
                <span class="stat-label">MQTT - Avg Power</span>
                <span class="stat-value">{{ $summary['mqtt']['avg_daya_mw'] }}</span>
                <span class="stat-unit">mW</span>
            </div>

            <div class="stat-card mqtt-color">
                <span class="stat-label">MQTT - Reliability</span>
                <span class="stat-value">{{ $reliability['mqtt_reliability'] }}</span>
                <span class="stat-unit">%</span>
            </div>

            <!-- HTTP Stats -->
            <div class="stat-card http-color">
                <span class="stat-label">HTTP - Total Data</span>
                <span class="stat-value">{{ $summary['http']['total_data'] }}</span>
                <span class="stat-unit">data point</span>
            </div>

            <div class="stat-card http-color">
                <span class="stat-label">HTTP - Avg Latency</span>
                <span class="stat-value">{{ $summary['http']['avg_latency_ms'] }}</span>
                <span class="stat-unit">ms</span>
            </div>

            <div class="stat-card http-color">
                <span class="stat-label">HTTP - Avg Power</span>
                <span class="stat-value">{{ $summary['http']['avg_daya_mw'] }}</span>
                <span class="stat-unit">mW</span>
            </div>

            <div class="stat-card http-color">
                <span class="stat-label">HTTP - Reliability</span>
                <span class="stat-value">{{ $reliability['http_reliability'] }}</span>
                <span class="stat-unit">%</span>
            </div>
        </div>

        <!-- Charts Section -->
        @if($mqttTotal > 0 || $httpTotal > 0)
            <!-- Latency Comparison Chart -->
            <div class="chart-container">
                <h2 class="chart-title">📈 Perbandingan Latensi (MQTT vs HTTP)</h2>
                @if(count($latencyChartData['labels']) > 0)
                    <div class="chart-wrapper">
                        <canvas id="latencyChart"></canvas>
                    </div>
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color mqtt-legend"></div>
                            <span>MQTT</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color http-legend"></div>
                            <span>HTTP</span>
                        </div>
                    </div>
                @else
                    <div class="no-data">Belum ada data untuk ditampilkan</div>
                @endif
            </div>

            <!-- Power Consumption Chart -->
            <div class="chart-container">
                <h2 class="chart-title">⚡ Perbandingan Konsumsi Daya (MQTT vs HTTP)</h2>
                @if(count($powerChartData['labels']) > 0)
                    <div class="chart-wrapper">
                        <canvas id="powerChart"></canvas>
                    </div>
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color mqtt-legend"></div>
                            <span>MQTT</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color http-legend"></div>
                            <span>HTTP</span>
                        </div>
                    </div>
                @else
                    <div class="no-data">Belum ada data untuk ditampilkan</div>
                @endif
            </div>
        @else
            <div class="chart-container">
                <div class="no-data">
                    📊 Belum ada data. Jalankan MQTT Listener atau kirim data HTTP untuk memulai analisis.
                </div>
            </div>
        @endif

        <!-- T-Test Results -->
        @if($summary['ttest_latency']['valid'])
            <div class="ttest-section">
                <h2 class="ttest-title">📐 Independent Sample T-Test Results</h2>

                <div style="margin-bottom: 30px;">
                    <h3 style="font-size: 1.2em; color: #333; margin-bottom: 15px;">Latensi (Latency)</h3>
                    <div class="ttest-grid">
                        <!-- MQTT Stats -->
                        <div class="ttest-card">
                            <div style="font-weight: 600; color: #007bff; margin-bottom: 10px;">MQTT</div>
                            <div class="ttest-row">
                                <span class="ttest-label">N (sample size)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['data1']['n'] }}</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">Mean (μ)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['data1']['mean'] }} ms</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">Std. Dev (σ)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['data1']['std_dev'] }} ms</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">Variance (σ²)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['data1']['variance'] }}</span>
                            </div>
                        </div>

                        <!-- HTTP Stats -->
                        <div class="ttest-card">
                            <div style="font-weight: 600; color: #28a745; margin-bottom: 10px;">HTTP</div>
                            <div class="ttest-row">
                                <span class="ttest-label">N (sample size)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['data2']['n'] }}</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">Mean (μ)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['data2']['mean'] }} ms</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">Std. Dev (σ)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['data2']['std_dev'] }} ms</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">Variance (σ²)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['data2']['variance'] }}</span>
                            </div>
                        </div>

                        <!-- T-Test Results -->
                        <div class="ttest-card" style="border-left-color: #667eea;">
                            <div style="font-weight: 600; color: #667eea; margin-bottom: 10px;">T-Test Results</div>
                            <div class="ttest-row">
                                <span class="ttest-label">t-value</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['t_value'] }}</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">df (degrees of freedom)</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['df'] }}</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">Critical Value (α=0.05)</span>
                                <span class="ttest-value">±{{ $summary['ttest_latency']['critical_value'] }}</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">p-value</span>
                                <span class="ttest-value">{{ $summary['ttest_latency']['p_value'] }}</span>
                            </div>
                            <div class="ttest-row">
                                <span class="ttest-label">Hypothesis</span>
                                <span class="ttest-value">H0: μ₁ = μ₂</span>
                            </div>
                            <span class="significance-badge @if($summary['ttest_latency']['is_significant']) significance-yes @else significance-no @endif">
                                @if($summary['ttest_latency']['is_significant'])
                                    ✓ Perbedaan Signifikan
                                @else
                                    ✗ Tidak Signifikan
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 style="font-size: 1.2em; color: #333; margin-bottom: 15px;">Konsumsi Daya (Power)</h3>
                    @if($summary['ttest_daya']['valid'])
                        <div class="ttest-grid">
                            <!-- MQTT Stats -->
                            <div class="ttest-card">
                                <div style="font-weight: 600; color: #007bff; margin-bottom: 10px;">MQTT</div>
                                <div class="ttest-row">
                                    <span class="ttest-label">N (sample size)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['data1']['n'] }}</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">Mean (μ)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['data1']['mean'] }} mW</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">Std. Dev (σ)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['data1']['std_dev'] }} mW</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">Variance (σ²)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['data1']['variance'] }}</span>
                                </div>
                            </div>

                            <!-- HTTP Stats -->
                            <div class="ttest-card">
                                <div style="font-weight: 600; color: #28a745; margin-bottom: 10px;">HTTP</div>
                                <div class="ttest-row">
                                    <span class="ttest-label">N (sample size)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['data2']['n'] }}</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">Mean (μ)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['data2']['mean'] }} mW</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">Std. Dev (σ)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['data2']['std_dev'] }} mW</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">Variance (σ²)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['data2']['variance'] }}</span>
                                </div>
                            </div>

                            <!-- T-Test Results -->
                            <div class="ttest-card" style="border-left-color: #667eea;">
                                <div style="font-weight: 600; color: #667eea; margin-bottom: 10px;">T-Test Results</div>
                                <div class="ttest-row">
                                    <span class="ttest-label">t-value</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['t_value'] }}</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">df (degrees of freedom)</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['df'] }}</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">Critical Value (α=0.05)</span>
                                    <span class="ttest-value">±{{ $summary['ttest_daya']['critical_value'] }}</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">p-value</span>
                                    <span class="ttest-value">{{ $summary['ttest_daya']['p_value'] }}</span>
                                </div>
                                <div class="ttest-row">
                                    <span class="ttest-label">Hypothesis</span>
                                    <span class="ttest-value">H0: μ₁ = μ₂</span>
                                </div>
                                <span class="significance-badge @if($summary['ttest_daya']['is_significant']) significance-yes @else significance-no @endif">
                                    @if($summary['ttest_daya']['is_significant'])
                                        ✓ Perbedaan Signifikan
                                    @else
                                        ✗ Tidak Signifikan
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Sistem Penelitian Skripsi - Analisis Komparatif MQTT vs HTTP</p>
            <p style="margin-top: 10px; font-size: 0.85em;">Database: MySQL | Backend: Laravel | Frontend: Chart.js</p>
        </div>
    </div>

    <script>
        // Latency Chart
        const latencyData = @json($latencyChartData);
        if (latencyData.labels.length > 0) {
            const latencyCtx = document.getElementById('latencyChart').getContext('2d');
            new Chart(latencyCtx, {
                type: 'bar',
                data: {
                    labels: latencyData.labels,
                    datasets: [
                        {
                            label: 'MQTT',
                            data: latencyData.mqtt,
                            backgroundColor: '#007bff',
                            borderColor: '#0056b3',
                            borderWidth: 1,
                        },
                        {
                            label: 'HTTP',
                            data: latencyData.http,
                            backgroundColor: '#28a745',
                            borderColor: '#1e7e34',
                            borderWidth: 1,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false,
                        },
                        legend: {
                            display: false,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Latency (ms)'
                            }
                        }
                    }
                }
            });
        }

        // Power Chart
        const powerData = @json($powerChartData);
        if (powerData.labels.length > 0) {
            const powerCtx = document.getElementById('powerChart').getContext('2d');
            new Chart(powerCtx, {
                type: 'bar',
                data: {
                    labels: powerData.labels,
                    datasets: [
                        {
                            label: 'MQTT',
                            data: powerData.mqtt,
                            backgroundColor: '#007bff',
                            borderColor: '#0056b3',
                            borderWidth: 1,
                        },
                        {
                            label: 'HTTP',
                            data: powerData.http,
                            backgroundColor: '#28a745',
                            borderColor: '#1e7e34',
                            borderWidth: 1,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false,
                        },
                        legend: {
                            display: false,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Power (mW)'
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
