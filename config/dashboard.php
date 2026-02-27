<?php

return [
    // Jumlah maksimum sampel terbaru per protokol yang dipakai untuk dashboard/chart/t-test.
    'analysis_window' => (int) env('DASHBOARD_ANALYSIS_WINDOW', 1200),

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
