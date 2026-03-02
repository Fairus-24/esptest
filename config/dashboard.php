<?php

return [
    // Jumlah maksimum sampel terbaru per protokol untuk statistik dashboard + t-test.
    'analysis_window' => (int) env('DASHBOARD_ANALYSIS_WINDOW', 1200),
    // Batas data chart per protokol. 0 = tanpa batas (pakai seluruh data real/simulation).
    'chart_window' => (int) env('DASHBOARD_CHART_WINDOW', 0),

    // Freshness status koneksi realtime untuk badge header + realtime link monitor.
    'connection' => [
        'protocol_freshness_seconds' => (int) env('DASHBOARD_PROTOCOL_FRESHNESS_SECONDS', 30),
        'esp32_freshness_seconds' => (int) env('DASHBOARD_ESP32_FRESHNESS_SECONDS', 30),
        // Heartbeat debug diberi jendela lebih longgar agar ESP32 tetap terdeteksi ON saat telemetry sensor sementara gagal kirim.
        'esp32_debug_freshness_seconds' => (int) env('DASHBOARD_ESP32_DEBUG_FRESHNESS_SECONDS', 120),
        // Saat simulasi tidak berjalan, abaikan data device SIMULATOR-APP agar status merefleksikan perangkat fisik.
        'ignore_simulator_when_stopped' => filter_var(env('DASHBOARD_IGNORE_SIMULATOR_WHEN_STOPPED', true), FILTER_VALIDATE_BOOL),
    ],

    // Retensi data historis (hari). Set 0 atau nilai negatif untuk menonaktifkan auto-prune.
    'retention_days' => (int) env('DATA_RETENTION_DAYS', 30),

    // Target dan bobot untuk skor transmission health.
    'transmission_health' => [
        'mqtt' => [
            'latency_target_ms' => (float) env('DASHBOARD_MQTT_HEALTH_LATENCY_TARGET_MS', 1500),
            'tx_target_ms' => (float) env('DASHBOARD_MQTT_HEALTH_TX_TARGET_MS', 120),
        ],
        'http' => [
            'latency_target_ms' => (float) env('DASHBOARD_HTTP_HEALTH_LATENCY_TARGET_MS', 3000),
            'tx_target_ms' => (float) env('DASHBOARD_HTTP_HEALTH_TX_TARGET_MS', 4500),
        ],
        'weights' => [
            'latency' => (float) env('DASHBOARD_HEALTH_WEIGHT_LATENCY', 0.50),
            'tx_duration' => (float) env('DASHBOARD_HEALTH_WEIGHT_TX_DURATION', 0.35),
            'payload' => (float) env('DASHBOARD_HEALTH_WEIGHT_PAYLOAD', 0.15),
        ],
    ],

    // Parameter continuity packet_seq agar robust terhadap reboot/seed jump.
    'sequence' => [
        'max_gap_for_loss' => (int) env('DASHBOARD_SEQUENCE_MAX_GAP_FOR_LOSS', 120),
        'reboot_uptime_drop_seconds' => (int) env('DASHBOARD_SEQUENCE_REBOOT_UPTIME_DROP_SECONDS', 30),
    ],

    // Ambang warning dashboard agar lebih adaptif terhadap jitter dan ukuran sampel kecil.
    'warnings' => [
        'mqtt_health_min_score' => (float) env('DASHBOARD_MQTT_HEALTH_MIN_SCORE', 70),
        'http_health_min_score' => (float) env('DASHBOARD_HTTP_HEALTH_MIN_SCORE', 70),
        'balance_min_samples' => (int) env('DASHBOARD_BALANCE_MIN_SAMPLES', 20),
        'balance_allowed_delta' => (int) env('DASHBOARD_BALANCE_ALLOWED_DELTA', 3),
        'balance_allowed_ratio' => (float) env('DASHBOARD_BALANCE_ALLOWED_RATIO', 0.12),
    ],

    // Guard tambahan untuk aksi reset data (disarankan aktif di production).
    'reset' => [
        'token' => env('RESET_DATA_TOKEN', ''),
        'allow_without_token' => filter_var(env('RESET_ALLOW_WITHOUT_TOKEN', true), FILTER_VALIDATE_BOOL),
    ],
];
