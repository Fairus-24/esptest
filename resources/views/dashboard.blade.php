<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Analisis Komparatif MQTT vs HTTP</title>
    <link rel="icon" type="image/svg+xml" href="project-favicon.svg?v=20260226">
    <link rel="icon" type="image/png" sizes="32x32" href="project-favicon.png?v=20260226">
    <link rel="icon" type="image/x-icon" href="favicon.ico?v=20260226">
    <link rel="shortcut icon" type="image/png" href="project-favicon.png?v=20260226">
    <link rel="apple-touch-icon" sizes="180x180" href="project-favicon.png?v=20260226">
    <meta name="theme-color" content="#1f4fd7">
    <meta name="application-name" content="IoT Research Dashboard">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8/hammer.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1f4fd7;
            --secondary: #0e8c63;
            --mqtt-blue: #1654e6;
            --http-green: #09a26b;
            --accent: #db3f57;
            --ttest-accent: #b45309;
            --bg-light: #f4f7fb;
            --text-dark: #111827;
            --text-light: #667085;
            --dashboard-bg-main: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            --dashboard-bg-desktop: var(--dashboard-bg-main);
            --dashboard-bg-mobile: var(--dashboard-bg-main);
            --shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            --shadow-hover: 0 22px 48px rgba(15, 23, 42, 0.14);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            background: var(--dashboard-bg-desktop);
            background-attachment: fixed;
            background-size: cover;
            background-repeat: no-repeat;
            min-height: 100vh;
            padding: 20px;
            color: var(--text-dark);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        html {
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
        }

        input, button, textarea, select {
            -webkit-appearance: none;
            border-radius: 8px;
        }

        /* Touch optimizations */
        @media (hover: none) and (pointer: coarse) {
            .stat-card:hover::before {
                transform: scaleX(0);
            }

            .stat-card:active {
                transform: translateY(-4px);
                box-shadow: 0 8px 16px rgba(0,0,0,0.12);
            }

            .ttest-card:hover {
                transform: none;
            }

            .ttest-card:active {
                background-color: rgba(248, 249, 250, 0.8);
            }
        }

        .footer {
            margin-top: 60px;
            text-align: center;
            opacity: 0.98;
        }

        .footer-text {
            font-size: 1.1em;
            font-weight: 500;
            margin-bottom: 8px;
            color: #fff;
            text-shadow: 1px 2px 8px rgba(44,62,80,0.25);
        }

        .footer-meta {
            font-size: 0.95em;
            color: #fff;
            font-weight: 400;
            text-shadow: 1px 2px 8px rgba(44,62,80,0.18);
        }

        .header-content {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 3em;
            margin-bottom: 15px;
            text-shadow: 2px 4px 8px rgba(0,0,0,0.3);
            font-weight: 700;
            letter-spacing: -1px;
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.95;
            font-weight: 300;
            margin-bottom: 20px;
        }

        .header-subtitle {
            font-size: 0.95em;
            opacity: 0.8;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .header-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .network-float {
            position: fixed;
            top: 14px;
            right: 14px;
            width: min(332px, calc(100vw - 28px));
            border-radius: 14px;
            padding: 10px 12px;
            background: linear-gradient(142deg, rgba(2, 6, 23, 0.92), rgba(18, 71, 121, 0.86));
            border: 1px solid rgba(148, 163, 184, 0.36);
            box-shadow: 0 20px 36px rgba(2, 6, 23, 0.28);
            backdrop-filter: blur(8px);
            color: #e2e8f0;
            z-index: 1200;
        }

        .network-float.is-collapsed {
            width: auto;
            max-width: calc(100vw - 28px);
            padding: 8px 10px;
            border-radius: 999px;
        }

        .network-float-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 7px;
        }

        .network-float.is-collapsed .network-float-head {
            margin-bottom: 0;
            justify-content: flex-end;
        }

        .network-title {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.79rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: #f8fafc;
        }

        .network-title i {
            color: #60a5fa;
        }

        .network-float.is-collapsed .network-title {
            display: none;
        }

        .network-float-body {
            display: block;
        }

        .network-float.is-collapsed .network-float-body {
            display: none;
        }

        .network-widget-status {
            appearance: none;
            -webkit-appearance: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.66rem;
            font-weight: 800;
            border-radius: 999px;
            padding: 3px 8px;
            border: 1px solid transparent;
            line-height: 1;
            cursor: pointer;
            user-select: none;
            transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
        }

        .network-widget-status:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.28);
        }

        .network-widget-status:hover {
            transform: translateY(-1px);
        }

        .network-widget-status .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            display: inline-block;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.24);
        }

        .network-widget-status.is-online {
            color: #bbf7d0;
            background: rgba(16, 185, 129, 0.16);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .network-widget-status.is-online .status-dot {
            background: #22c55e;
        }

        .network-widget-status.is-offline {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.16);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .network-widget-status.is-offline .status-dot {
            background: #ef4444;
        }

        .network-protocol-row {
            display: grid;
            grid-template-columns: 76px minmax(0, 1fr) minmax(0, 1fr);
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.28);
        }

        .network-protocol-row:last-of-type {
            border-bottom: none;
        }

        .network-protocol-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.72rem;
            font-weight: 800;
            color: #cbd5e1;
        }

        .network-protocol-row.is-online .network-protocol-label {
            color: #f8fafc;
        }

        .network-protocol-row.is-offline .network-protocol-label {
            color: #fecaca;
        }

        .network-protocol-label i {
            font-size: 0.7rem;
        }

        .network-protocol-state {
            display: inline-block;
            margin-left: 4px;
            font-size: 0.58rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .network-metric {
            display: flex;
            flex-direction: column;
            min-width: 0;
            text-align: right;
        }

        .network-metric small {
            color: rgba(203, 213, 225, 0.82);
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .network-metric strong {
            color: #f8fafc;
            font-size: 0.8rem;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .network-protocol-row.is-offline .network-metric strong {
            color: #fecaca;
        }

        .network-stamp {
            margin-top: 6px;
            color: rgba(203, 213, 225, 0.84);
            font-size: 0.62rem;
            line-height: 1.35;
        }

        .network-note {
            margin-top: 4px;
            color: rgba(148, 163, 184, 0.9);
            font-size: 0.6rem;
            line-height: 1.35;
        }

        .network-external-note {
            margin-top: 6px;
            color: rgba(147, 197, 253, 0.9);
            font-size: 0.62rem;
            line-height: 1.35;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .network-float {
                top: 10px;
                right: 10px;
                width: min(304px, calc(100vw - 20px));
                padding: 9px 10px;
            }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .stat-card-help {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 3;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--mqtt-blue), var(--http-green));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(0,0,0,0.1);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card.mqtt-color::before {
            background: linear-gradient(90deg, var(--mqtt-blue), #0052cc);
        }

        .stat-card.http-color::before {
            background: linear-gradient(90deg, var(--http-green), #00b373);
        }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .stat-icon.mqtt { color: var(--mqtt-blue); }
        .stat-icon.http { color: var(--http-green); }

        .stat-label {
            font-size: 0.95em;
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
            transition: background-color 0.6s ease;
        }

        .stat-value.updated {
            /* Animation trigger only, no visual highlight */
        }

        .stat-unit {
            font-size: 0.85em;
            color: var(--text-light);
            font-weight: 500;
        }

        /* Chart Containers */
        .section-title {
            font-size: 1.6em;
            color: white;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .chart-container {
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            border: 1px solid rgba(0,0,0,0.05);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .chart-title {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chart-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .chart-title-row .chart-title {
            margin-bottom: 0;
        }

        .chart-title i {
            font-size: 1.3em;
        }

        .chart-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .chart-toolbar-info {
            font-size: 0.9em;
            color: var(--text-light);
            font-weight: 500;
        }

        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .zoom-btn {
            border: 1px solid #d9e1ef;
            background: #f5f8ff;
            color: #24334f;
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 38px;
        }

        .zoom-btn:hover {
            background: #e9f0ff;
            border-color: #b8c8ea;
        }

        .zoom-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background: #f4f4f4;
            border-color: #e0e0e0;
        }

        .zoom-btn.zoom-reset {
            min-width: 70px;
            font-weight: 600;
        }

        .chart-hint {
            font-size: 0.85em;
            color: #6f7d95;
            margin-bottom: 12px;
        }

        .chart-wrapper {
            position: relative;
            height: 350px;
            margin-bottom: 25px;
            background: linear-gradient(135deg, rgba(102,126,234,0.05), rgba(118,75,162,0.05));
            padding: 20px;
            border-radius: 12px;
            overflow: hidden;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1em;
            font-weight: 500;
            color: var(--text-dark);
        }

        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .mqtt-legend { background: var(--mqtt-blue); }
        .http-legend { background: var(--http-green); }

        /* T-Test Section */
        .ttest-section {
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .ttest-title {
            font-size: 1.6em;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 35px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .ttest-title i {
            color: var(--primary);
            font-size: 1.4em;
        }

        .ttest-subsection {
            margin-bottom: 40px;
        }

        .ttest-subsection:last-child {
            margin-bottom: 0;
        }

        .ttest-subsection h3 {
            font-size: 1.3em;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--bg-light);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ttest-subsection h3 i {
            color: var(--ttest-accent);
            font-size: 0.95em;
        }

        .ttest-subsection h3 .latency-icon {
            color: #d97706;
        }

        .ttest-subsection h3 .power-icon {
            color: #ea580c;
        }

        .ttest-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .ttest-card {
            background: var(--bg-light);
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid var(--primary);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            border-left: 5px solid transparent;
        }

        .ttest-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .ttest-card-header {
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .ttest-header-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .ttest-help-btn,
        .card-help-btn {
            width: 26px;
            height: 26px;
            border: 1px solid rgba(100, 116, 139, 0.35);
            border-radius: 999px;
            background: #ffffff;
            color: #334155;
            font-weight: 800;
            font-size: 0.85rem;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .ttest-help-btn:hover,
        .card-help-btn:hover {
            background: #eef2ff;
            border-color: rgba(79, 70, 229, 0.35);
            color: #3730a3;
        }

        .ttest-help-btn[aria-expanded="true"],
        .card-help-btn[aria-expanded="true"] {
            background: #dbeafe;
            border-color: rgba(37, 99, 235, 0.4);
            color: #1d4ed8;
        }

        .ttest-help-panel,
        .card-help-panel {
            margin: 0 0 12px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.34);
            background: rgba(248, 250, 252, 0.82);
            color: #334155;
            font-size: 0.79rem;
            line-height: 1.5;
        }

        .ttest-help-item,
        .card-help-item {
            margin: 0 0 6px;
        }

        .ttest-help-item:last-child,
        .card-help-item:last-child {
            margin-bottom: 0;
        }

        .ttest-help-label,
        .card-help-label {
            font-weight: 700;
            color: #0f172a;
        }

        .ttest-card.mqtt-card {
            border-left-color: var(--mqtt-blue);
        }

        .ttest-card.mqtt-card .ttest-card-header {
            color: var(--mqtt-blue);
        }

        .ttest-card.http-card {
            border-left-color: var(--http-green);
        }

        .ttest-card.http-card .ttest-card-header {
            color: var(--http-green);
        }

        .ttest-card.result-card {
            border-left-color: var(--ttest-accent);
            border-color: rgba(180, 83, 9, 0.28);
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.18), rgba(249, 115, 22, 0.12));
        }

        .ttest-card.result-card .ttest-card-header {
            color: var(--ttest-accent);
        }

        .ttest-card.result-card .ttest-header-title i {
            color: #c2410c;
        }

        .ttest-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-size: 0.95em;
            align-items: center;
        }

        .ttest-row:last-child {
            border-bottom: none;
        }

        .ttest-label {
            font-weight: 500;
            color: var(--text-light);
            flex: 1;
        }

        .ttest-value {
            font-weight: 700;
            color: var(--text-dark);
            text-align: right;
            min-width: 100px;
            transition: background-color 0.6s ease;
        }

        .ttest-value.updated {
            /* Animation trigger only, no visual highlight */
        }

        .significance-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.95em;
            margin-top: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .significance-yes {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .significance-no {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
            font-size: 1.1em;
        }

        .no-data i {
            font-size: 4em;
            opacity: 0.3;
            margin-bottom: 20px;
            display: block;
        }

        /* Footer */
        .footer {
            text-align: center;
            color: rgba(255,255,255,0.8);
            margin-top: 60px;
            padding-top: 30px;
            padding-bottom: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            font-size: 0.95em;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInChart {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out both;
        }

        .fade-in-chart {
            animation: fadeInChart 0.8s ease-out both;
        }

        .footer-text {
            margin-bottom: 10px;
            font-weight: 500;
        }

        .footer-meta {
            font-size: 0.85em;
            opacity: 0.75;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .header h1 { font-size: 2.2em; }
            .chart-wrapper { height: 300px; }
            .ttest-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        }

        /* TABLET (768px - 1024px) */
        @media (max-width: 768px) {
            body {
                padding: 12px;
                background: var(--dashboard-bg-mobile);
                background-attachment: fixed;
            }

            .container { max-width: 100%; }

            .header {
                margin-bottom: 40px;
            }

            .header-content {
                padding: 30px 20px;
                border-radius: 16px;
                margin-bottom: 25px;
            }

            .header h1 {
                font-size: 2em;
                margin-bottom: 12px;
                font-weight: 700;
            }

            .header p {
                font-size: 1em;
                margin-bottom: 15px;
            }

            .header-subtitle {
                gap: 8px;
                font-size: 0.85em;
            }

            .header-badge {
                padding: 6px 12px;
                border-radius: 16px;
                font-size: 0.8em;
            }

            .section-title {
                font-size: 1.4em;
                margin-bottom: 25px;
                letter-spacing: 0px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-bottom: 35px;
            }

            .stat-card {
                padding: 20px 18px;
                border-radius: 14px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            }

            .stat-icon { font-size: 2.2em; }
            .stat-label { font-size: 0.85em; }
            .stat-value { font-size: 2em; }
            .stat-unit { font-size: 0.8em; }

            .chart-container {
                padding: 25px 20px;
                margin-bottom: 30px;
                border-radius: 14px;
            }

            .chart-title {
                font-size: 1.2em;
                margin-bottom: 20px;
                gap: 10px;
            }

            .chart-wrapper {
                height: 280px;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 20px;
            }

            .legend {
                gap: 30px;
                margin-top: 20px;
            }

            .legend-item { font-size: 0.9em; }

            .ttest-section {
                padding: 25px 20px;
                border-radius: 14px;
            }

            .ttest-title {
                font-size: 1.3em;
                margin-bottom: 30px;
                gap: 10px;
            }

            .ttest-subsection h3 {
                font-size: 1.1em;
                margin-bottom: 18px;
                padding-bottom: 10px;
            }

            .ttest-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-bottom: 25px;
            }

            .ttest-card {
                padding: 18px;
                border-radius: 10px;
            }

            .ttest-card-header {
                font-size: 1em;
                margin-bottom: 12px;
                gap: 8px;
            }

            .ttest-help-btn {
                width: 24px;
                height: 24px;
                font-size: 0.8rem;
            }

            .ttest-help-panel {
                font-size: 0.76rem;
                padding: 9px 10px;
            }

            .ttest-row {
                padding: 10px 0;
                font-size: 0.9em;
            }

            .ttest-label { font-size: 0.9em; }
            .ttest-value { font-size: 0.9em; min-width: 70px; }

            .significance-badge {
                padding: 8px 14px;
                font-size: 0.85em;
                margin-top: 12px;
            }

            .footer {
                margin-top: 40px;
                padding-top: 25px;
                padding-bottom: 15px;
                font-size: 0.9em;
            }

            .footer-text { margin-bottom: 8px; }
            .footer-meta { font-size: 0.8em; }
        }

        /* MOBILE (481px - 768px) */
        @media (max-width: 640px) {
            body {
                padding: 10px;
            }

            .header {
                margin-bottom: 35px;
            }

            .header-content {
                padding: 25px 15px;
                border-radius: 14px;
                margin-bottom: 20px;
            }

            .header h1 {
                font-size: 1.7em;
                margin-bottom: 10px;
                letter-spacing: -0.5px;
            }

            .header p {
                font-size: 0.95em;
                margin-bottom: 12px;
                font-weight: 400;
            }

            .header-subtitle {
                flex-direction: row;
                gap: 6px;
                font-size: 0.8em;
                justify-content: center;
                flex-wrap: wrap;
            }

            .header-badge {
                padding: 5px 10px;
                border-radius: 12px;
                font-size: 0.75em;
                background: rgba(255,255,255,0.15);
            }

            .section-title {
                font-size: 1.2em;
                margin-bottom: 20px;
                letter-spacing: 0px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 30px;
            }

            .stat-card {
                padding: 18px 16px;
                border-radius: 12px;
                box-shadow: 0 6px 16px rgba(0,0,0,0.1);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            .stat-card:active {
                transform: scale(0.98);
                box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            }

            .stat-icon {
                font-size: 2em;
                margin-bottom: 12px;
            }

            .stat-label {
                font-size: 0.8em;
                margin-bottom: 8px;
                letter-spacing: 0.3px;
            }

            .stat-value {
                font-size: 1.8em;
                margin-bottom: 4px;
            }

            .stat-unit {
                font-size: 0.75em;
            }

            .chart-container {
                padding: 20px 16px;
                margin-bottom: 25px;
                border-radius: 12px;
                box-shadow: 0 6px 16px rgba(0,0,0,0.1);
            }

            .chart-title {
                font-size: 1.1em;
                margin-bottom: 16px;
                gap: 8px;
            }

            .chart-title i {
                font-size: 1.1em;
            }

            .chart-wrapper {
                height: 240px;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 16px;
            }

            .legend {
                gap: 20px;
                margin-top: 16px;
                flex-wrap: wrap;
            }

            .legend-item {
                font-size: 0.8em;
            }

            .legend-color {
                width: 20px;
                height: 20px;
                border-radius: 4px;
            }

            .ttest-section {
                padding: 20px 16px;
                border-radius: 12px;
                box-shadow: 0 6px 16px rgba(0,0,0,0.1);
            }

            .ttest-title {
                font-size: 1.2em;
                margin-bottom: 25px;
                gap: 8px;
            }

            .ttest-title i {
                font-size: 1.2em;
            }

            .ttest-subsection {
                margin-bottom: 30px;
            }

            .ttest-subsection h3 {
                font-size: 1em;
                margin-bottom: 16px;
                padding-bottom: 8px;
                border-bottom: 2px solid rgba(0,0,0,0.08);
            }

            .ttest-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-bottom: 20px;
            }

            .ttest-card {
                padding: 16px;
                border-radius: 10px;
                background: white;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            }

            .ttest-card-header {
                font-size: 0.95em;
                margin-bottom: 12px;
                gap: 6px;
            }

            .ttest-help-btn {
                width: 22px;
                height: 22px;
                font-size: 0.78rem;
            }

            .ttest-help-panel {
                font-size: 0.74rem;
                padding: 8px 9px;
            }

            .ttest-row {
                padding: 10px 0;
                font-size: 0.85em;
                gap: 8px;
            }

            .ttest-label {
                font-size: 0.85em;
                flex: 1;
            }

            .ttest-value {
                font-size: 0.85em;
                min-width: 60px;
                text-align: right;
                font-weight: 700;
                word-break: break-word;
            }

            .significance-badge {
                padding: 7px 12px;
                font-size: 0.8em;
                margin-top: 10px;
                gap: 6px;
            }

            .significance-badge i {
                font-size: 0.9em;
            }

            .no-data {
                padding: 40px 15px;
                font-size: 1em;
            }

            .no-data i {
                font-size: 3em;
                margin-bottom: 15px;
            }

            .footer {
                margin-top: 30px;
                padding-top: 20px;
                padding-bottom: 15px;
                font-size: 0.85em;
            }

            .footer-text {
                margin-bottom: 6px;
                font-size: 0.9em;
            }

            .footer-meta {
                font-size: 0.75em;
            }
        }

        /* SMALL MOBILE (max 480px) */
        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .header {
                margin-bottom: 30px;
            }

            .header-content {
                padding: 20px 12px;
                border-radius: 12px;
                margin-bottom: 18px;
            }

            .header h1 {
                font-size: 1.5em;
                margin-bottom: 8px;
            }

            .header p {
                font-size: 0.9em;
                margin-bottom: 10px;
            }

            .header-subtitle {
                flex-direction: column;
                gap: 4px;
                font-size: 0.75em;
            }

            .header-badge {
                padding: 4px 8px;
                font-size: 0.7em;
                border-radius: 10px;
            }

            .section-title {
                font-size: 1.1em;
                margin-bottom: 18px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 25px;
            }

            .stat-card {
                padding: 16px 14px;
                border-radius: 10px;
            }

            .stat-icon {
                font-size: 1.8em;
                margin-bottom: 10px;
            }

            .stat-label {
                font-size: 0.75em;
                margin-bottom: 6px;
            }

            .stat-value {
                font-size: 1.6em;
                margin-bottom: 3px;
            }

            .stat-unit {
                font-size: 0.7em;
            }

            .chart-container {
                padding: 16px 14px;
                margin-bottom: 20px;
                border-radius: 10px;
            }

            .chart-title {
                font-size: 1em;
                margin-bottom: 14px;
                gap: 6px;
            }

            .chart-title i {
                font-size: 1em;
            }

            .chart-wrapper {
                height: 220px;
                padding: 10px;
                border-radius: 8px;
                margin-bottom: 14px;
            }

            .legend {
                gap: 15px;
                margin-top: 12px;
            }

            .legend-item {
                font-size: 0.75em;
            }

            .legend-color {
                width: 18px;
                height: 18px;
            }

            .ttest-section {
                padding: 16px 14px;
                border-radius: 10px;
            }

            .ttest-title {
                font-size: 1.1em;
                margin-bottom: 20px;
                gap: 6px;
            }

            .ttest-subsection h3 {
                font-size: 0.95em;
                margin-bottom: 14px;
                padding-bottom: 8px;
            }

            .ttest-grid {
                gap: 14px;
                margin-bottom: 18px;
            }

            .ttest-card {
                padding: 14px;
                border-radius: 8px;
            }

            .ttest-card-header {
                font-size: 0.9em;
                margin-bottom: 10px;
            }

            .ttest-help-btn {
                width: 21px;
                height: 21px;
                font-size: 0.76rem;
            }

            .ttest-help-panel {
                font-size: 0.72rem;
                padding: 7px 8px;
            }

            .ttest-row {
                padding: 8px 0;
                font-size: 0.8em;
                flex-direction: column;
            }

            .ttest-label {
                font-size: 0.8em;
                margin-bottom: 2px;
            }

            .ttest-value {
                font-size: 0.8em;
                text-align: right;
            }

            .significance-badge {
                padding: 6px 10px;
                font-size: 0.75em;
                margin-top: 8px;
            }

            .footer {
                margin-top: 25px;
                padding-top: 15px;
                font-size: 0.8em;
            }

            .footer-text {
                margin-bottom: 4px;
                font-size: 0.85em;
            }

            .footer-meta {
                font-size: 0.7em;
            }
        }

        /* EXTRA SMALL (max 360px) */
        @media (max-width: 360px) {
            body { padding: 6px; }

            .header-content { padding: 18px 10px; }
            .header h1 { font-size: 1.3em; }
            .header p { font-size: 0.85em; }
            .section-title { font-size: 1em; }
            .stat-card { padding: 14px 12px; }
            .stat-icon { font-size: 1.6em; }
            .stat-value { font-size: 1.4em; }
            .stat-label { font-size: 0.7em; }

            .chart-container { padding: 14px 12px; }
            .chart-wrapper { height: 200px; }
            .chart-title { font-size: 0.9em; }

            .ttest-section { padding: 14px 12px; }
            .ttest-card { padding: 12px; }
            .ttest-value { word-break: break-all; }
        }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 5px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.5); }

        /* Consolidated UI overrides for cleaner mobile/tablet/desktop behavior */
        .header-content.header-metric-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
            background: linear-gradient(132deg, rgba(8, 27, 74, 0.78), rgba(17, 67, 112, 0.72));
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 20px;
        }

        .header-center-content {
            grid-column: 1 / -1;
            order: -1;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 18px 14px;
            margin: 0;
            text-align: center;
            width: 100%;
        }

        .header-metric-card {
            min-width: 0;
            max-width: none;
            border-radius: 14px;
            padding: 14px 10px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.22);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .metric-value {
            font-size: clamp(1.25rem, 2.4vw, 1.9rem);
            letter-spacing: -0.02em;
            line-height: 1.1;
            justify-content: center;
            color: #fff;
        }

        .metric-detail {
            line-height: 1.4;
            color: #e2e8f0;
            text-align: center;
            font-weight: 600;
        }

        .suhu-card .metric-icon,
        .suhu-card .metric-value,
        .suhu-card .metric-unit {
            color: #ff8a8a;
        }

        .kelembapan-card .metric-icon,
        .kelembapan-card .metric-value,
        .kelembapan-card .metric-unit {
            color: #66e6b6;
        }

        .status-badge {
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
        }

        .status-badge.is-online {
            background: rgba(34, 197, 94, 0.22);
            border-color: rgba(34, 197, 94, 0.45);
        }

        .status-badge.is-offline {
            background: rgba(239, 68, 68, 0.22);
            border-color: rgba(239, 68, 68, 0.45);
        }

        .section-title {
            color: #ffffff;
            text-transform: none;
            letter-spacing: 0;
            text-align: left;
            text-shadow: 0 2px 10px rgba(15, 23, 42, 0.22);
        }

        .section-title i {
            color: #ffffff;
        }

        .chart-title {
            color: #0f172a;
        }

        .chart-title i {
            color: var(--mqtt-blue);
        }

        .stats-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .stat-card {
            border-radius: 14px;
            padding: 20px 16px;
            min-height: 140px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.09);
        }

        .stat-label {
            font-size: 0.78rem;
            line-height: 1.2;
        }

        .stat-value {
            font-size: clamp(1.15rem, 2vw, 1.8rem);
            line-height: 1.15;
        }

        .stat-unit {
            font-size: 0.74rem;
        }

        .action-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 34px;
        }

        .reset-btn {
            border: none;
            border-radius: 12px;
            padding: 10px 16px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #dc2626, #e11d48);
            box-shadow: 0 12px 24px rgba(220, 38, 38, 0.28);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .admin-config-btn {
            border: 1px solid rgba(6, 182, 212, 0.35);
            border-radius: 12px;
            padding: 10px 16px;
            font-weight: 700;
            color: #ecfeff;
            cursor: pointer;
            background: linear-gradient(135deg, #0891b2, #0e7490);
            box-shadow: 0 12px 24px rgba(14, 116, 144, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .reset-status {
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(16, 185, 129, 0.35);
            background: rgba(16, 185, 129, 0.12);
            color: #047857;
            font-size: 0.82rem;
            font-weight: 700;
            border-radius: 10px;
            padding: 8px 10px;
        }

        .inline-warning {
            margin-bottom: 18px;
            text-align: left;
            border: 1px solid rgba(245, 158, 11, 0.32);
            background: rgba(254, 243, 199, 0.62);
            color: #92400e;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .inline-warning ul {
            margin: 8px 0 0 20px;
            padding: 0;
            font-weight: 600;
        }

        .inline-warning li {
            margin: 2px 0;
            line-height: 1.4;
        }

        .quality-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
            align-items: start;
        }

        .quality-card {
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.72);
            overflow: hidden;
            padding: 0;
            align-self: start;
        }

        .quality-details {
            width: 100%;
        }

        .quality-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            cursor: pointer;
            list-style: none;
            user-select: none;
        }

        .quality-summary::-webkit-details-marker {
            display: none;
        }

        .quality-summary:hover {
            background: rgba(148, 163, 184, 0.08);
        }

        .quality-summary h4 {
            font-size: 0.88rem;
            color: #0f172a;
            margin: 0;
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-width: 0;
        }

        .quality-title-main {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .quality-title-count {
            color: #475569;
            font-weight: 700;
            font-size: 0.78rem;
            flex-shrink: 0;
        }

        .quality-main-icon {
            color: #7c3aed;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .quality-protocol-icon {
            font-size: 0.82rem;
            flex-shrink: 0;
        }

        .quality-protocol-mqtt {
            color: var(--mqtt-blue);
        }

        .quality-protocol-http {
            color: var(--http-green);
        }

        @media (max-width: 480px) {
            .quality-summary h4 {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .quality-title-main {
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
            }
        }

        .quality-summary-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .quality-help-btn {
            width: 22px;
            height: 22px;
            font-size: 0.72rem;
        }

        .quality-help-panel {
            margin: 0 12px 10px;
        }

        .quality-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            display: inline-block;
            flex-shrink: 0;
        }

        .quality-dot-good {
            background: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.18);
        }

        .quality-dot-bad {
            background: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18);
        }

        .quality-toggle {
            color: #64748b;
            font-size: 0.72rem;
            transition: transform 0.2s ease;
        }

        .quality-details[open] .quality-toggle {
            transform: rotate(180deg);
        }

        .quality-content {
            padding: 0 12px 10px;
        }

        .quality-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 4px 0;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.32);
            font-size: 0.8rem;
        }

        .quality-row:last-child {
            border-bottom: none;
        }

        .quality-badge {
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 0.74rem;
            font-weight: 800;
        }

        .quality-good {
            color: #065f46;
            background: rgba(16, 185, 129, 0.18);
            border: 1px solid rgba(16, 185, 129, 0.35);
        }

        .quality-bad {
            color: #991b1b;
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.35);
        }

        .protocol-diagnostics {
            margin: 18px 0 24px;
            padding: 16px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.24);
        }

        .protocol-diagnostics h3 {
            margin: 0 0 12px;
            font-size: 1.02rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .protocol-sync-note {
            margin: 0 0 12px;
            padding: 9px 11px;
            border-radius: 10px;
            background: rgba(219, 234, 254, 0.65);
            border: 1px solid rgba(59, 130, 246, 0.28);
            color: #1e3a8a;
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .protocol-diagnostics-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .protocol-diagnostic-card {
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 12px;
        }

        .protocol-card-header {
            margin: 0 0 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .protocol-diagnostic-card.mqtt {
            border-left: 4px solid var(--mqtt-blue);
        }

        .protocol-diagnostic-card.http {
            border-left: 4px solid var(--http-green);
        }

        .protocol-diagnostic-card.delta {
            grid-column: 1 / -1;
            border-left: 4px solid #7c3aed;
        }

        .protocol-diagnostic-card h4 {
            margin: 0;
            font-size: 0.9rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .header-metric-card {
            position: relative;
        }

        .header-metric-help {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 2;
            width: 22px;
            height: 22px;
            font-size: 0.72rem;
            background: rgba(255, 255, 255, 0.24);
            color: #f8fafc;
            border-color: rgba(255, 255, 255, 0.48);
        }

        .header-metric-help:hover {
            background: rgba(255, 255, 255, 0.36);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.7);
        }

        .header-metric-help[aria-expanded="true"] {
            background: rgba(30, 64, 175, 0.45);
            color: #ffffff;
            border-color: rgba(191, 219, 254, 0.7);
        }

        .header-metric-help-panel {
            margin-top: 8px;
            background: rgba(2, 6, 23, 0.56);
            border-color: rgba(148, 163, 184, 0.5);
            color: #e2e8f0;
            font-size: 0.75rem;
        }

        .header-metric-help-panel .card-help-label {
            color: #f8fafc;
        }

        .protocol-diagnostic-list {
            margin: 0;
            display: grid;
            gap: 5px;
        }

        .protocol-diagnostic-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 0.78rem;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.28);
            padding-bottom: 4px;
        }

        .protocol-diagnostic-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .protocol-diagnostic-row .label {
            color: #475569;
            font-weight: 600;
        }

        .protocol-diagnostic-row .value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        @media (max-width: 900px) {
            .protocol-diagnostics-grid {
                grid-template-columns: 1fr;
            }

            .protocol-diagnostic-card.delta {
                grid-column: auto;
            }
        }

        .chart-container,
        .ttest-section {
            background: rgba(255, 255, 255, 0.94);
            border-radius: 16px;
        }

        .chart-wrapper {
            min-height: 240px;
            height: clamp(240px, 38vw, 360px);
        }

        .chart-wrapper canvas {
            width: 100% !important;
            height: 100% !important;
            display: block !important;
        }

        .chart-toolbar {
            row-gap: 8px;
        }

        .chart-toolbar-info {
            font-size: 0.83rem;
        }

        .chart-hint {
            font-size: 0.78rem;
        }

        .zoom-btn {
            border-radius: 9px;
            font-size: 0.83rem;
        }

        /* High-contrast palette repair for header/footer readability */
        .header-content.header-metric-row {
            background: linear-gradient(136deg, rgba(13, 37, 84, 0.94), rgba(13, 86, 116, 0.92));
            border-color: rgba(255, 255, 255, 0.34);
        }

        .header-center-content {
            background: rgba(2, 6, 23, 0.28);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .header-center-content h1,
        .header-center-content p {
            color: #f8fafc;
            text-shadow: none;
        }

        .header-subtitle {
            opacity: 1;
        }

        .header-badge {
            color: #f8fafc;
            background: rgba(15, 23, 42, 0.38);
            border: 1px solid rgba(255, 255, 255, 0.28);
        }

        .header-badge i {
            color: inherit;
        }

        .metric-label {
            color: #e2e8f0;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .metric-detail {
            color: #e5edf7;
        }

        .suhu-card .metric-icon,
        .suhu-card .metric-value,
        .suhu-card .metric-unit {
            color: #fca5a5;
        }

        .kelembapan-card .metric-icon,
        .kelembapan-card .metric-value,
        .kelembapan-card .metric-unit {
            color: #86efac;
        }

        .status-badge.is-online {
            background: rgba(22, 163, 74, 0.3);
            border-color: rgba(134, 239, 172, 0.66);
        }

        .status-badge.is-offline {
            background: rgba(220, 38, 38, 0.3);
            border-color: rgba(252, 165, 165, 0.64);
        }

        .footer {
            margin-top: 48px;
            padding-top: 28px;
            padding-bottom: 18px;
            text-align: center;
            color: var(--text-dark);
            background: transparent;
            font-size: 1.05em;
            font-weight: 500;
            letter-spacing: 0.01em;
            box-shadow: none;
            border-radius: 0;
            transition: background 0.3s;
            position: relative;
        }

        .footer::before {
            content: "";
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: #fff;
            opacity: 0.85;
            border-radius: 1px;
        }

        .footer-text {
            font-size: 1.13em;
            font-weight: 600;
            margin-bottom: 8px;
            color: #fff !important;
            letter-spacing: 0.01em;
            position: relative;
        }

        .footer-text i {
            color: #fff !important;
        }

        .footer-text::after {
            content: "";
            display: block;
            margin: 10px auto 0 auto;
            width: 90%;
            max-width: 340px;
            border-bottom: 1.5px solid rgba(255,255,255,0.22);
            border-radius: 1px;
        }
        }

        .footer-meta {
            margin-top: 2px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.98em;
            opacity: 0.85;
        }

        .footer-pill {
            display: inline-flex;
            align-items: center;
            font-size: 0.98em;
            font-weight: 500;
            border-radius: 20px;
            padding: 4px 16px 4px 12px;
            background: rgba(241,245,249,0.85);
            color: #334155;
            border: 1px solid rgba(148,163,184,0.18);
            margin: 0 2px 6px 2px;
            box-shadow: 0 1px 4px rgba(15,23,42,0.04);
            transition: background 0.2s, color 0.2s;
        }

        .footer-pill-db {
            background: #e6f9f0;
            color: #0e8c63;
            border-color: #b6e7d2;
        }
        .footer-pill-laravel {
            background: #eaf0fb;
            color: #1f4fd7;
            border-color: #b6c7e7;
        }
        .footer-pill-chart {
            background: #eaf6fb;
            color: #1654e6;
            border-color: #b6d3e7;
        }
        .footer-pill-ttest {
            background: #fbeaea;
            color: #db3f57;
            border-color: #e7b6b6;
        }
        .footer-pill i {
            margin-right: 7px;
            font-size: 1.08em;
        }

        .simulation-nav-wrap {
            margin-top: 26px;
        }

        .simulation-nav-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.38);
            background: linear-gradient(132deg, #0f172a 0%, #1e3a8a 56%, #0f766e 100%);
            color: #e2e8f0;
            text-decoration: none;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            box-shadow: 0 16px 30px rgba(2, 6, 23, 0.26);
        }

        .simulation-nav-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 34px rgba(2, 6, 23, 0.34);
        }

        .docs-nav-card {
            margin-top: 12px;
            background: linear-gradient(132deg, #0f172a 0%, #1e293b 50%, #334155 100%);
        }

        .docs-nav-card .simulation-nav-arrow {
            color: #cbd5e1;
        }

        .simulation-nav-title {
            margin: 0 0 4px;
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #f8fafc;
        }

        .simulation-nav-desc {
            margin: 0;
            font-size: 0.86rem;
            color: rgba(226, 232, 240, 0.92);
            font-weight: 500;
            line-height: 1.55;
        }

        .simulation-nav-arrow {
            flex: 0 0 auto;
            font-size: 1.2rem;
            color: #93c5fd;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 920px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .section-title {
                text-align: center;
            }
        }

        @media (max-width: 640px) {
            .header-content.header-metric-row {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                padding: 10px 6px 12px 6px;
            }

            .header-center-content {
                grid-column: 1 / -1;
                order: -1;
                margin: 0;
                padding: 10px 8px;
            }

            .header-metric-card {
                width: 100%;
                margin: 0;
                padding: 10px 8px;
                font-size: 0.9em;
                border-radius: 12px;
            }

            .header-center-content h1 {
                font-size: 1.1em;
                margin-bottom: 4px;
            }

            .header-center-content p {
                font-size: 0.92em;
                margin-bottom: 6px;
            }

            .action-row {
                flex-direction: column;
                align-items: stretch;
            }

            .quality-grid {
                grid-template-columns: 1fr;
            }

            .reset-btn,
            .admin-config-btn,
            .reset-status {
                width: 100%;
                justify-content: center;
                text-align: center;
            }

            .chart-wrapper {
                min-height: 220px;
                height: 250px;
            }

            /* Keep Statistical Analysis layout centered and consistent with tablet */
            .ttest-section {
                margin-left: auto;
                margin-right: auto;
            }

            .ttest-title,
            .ttest-subsection h3 {
                text-align: center;
                justify-content: center;
            }

            .ttest-grid {
                grid-template-columns: 1fr;
                justify-items: center;
                gap: 16px;
            }

            .ttest-card {
                width: 100%;
                max-width: 560px;
                margin-left: auto;
                margin-right: auto;
            }

            .ttest-row {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }

            .ttest-label {
                margin-bottom: 0;
                text-align: left;
            }

            .ttest-value {
                text-align: right;
            }
        }
    </style>
</head>
    <body>
    @php
        $isEmbeddedDashboard = request()->boolean('embedded');
        $mqttConnectionStatus = is_array($mqttConnectionStatus ?? null)
            ? $mqttConnectionStatus
            : [
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
            ];
        $httpConnectionStatus = is_array($httpConnectionStatus ?? null)
            ? $httpConnectionStatus
            : [
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
            ];
        $esp32ConnectionStatus = is_array($esp32ConnectionStatus ?? null)
            ? $esp32ConnectionStatus
            : [
                'connected' => false,
                'label' => 'OFF',
                'detail' => 'Belum ada data telemetry dari perangkat.',
                'badge_class' => 'is-offline',
                'freshness_seconds' => 30,
                'age_seconds' => null,
                'last_seen_wib' => '-',
            ];
        $telemetrySource = strtolower(trim((string) ($telemetrySource ?? 'real')));
        $isSimulationTelemetrySource = $telemetrySource === 'simulation';
        $telemetrySourceLabel = $isSimulationTelemetrySource ? 'SOURCE SIMULATION' : 'SOURCE REAL';
        $telemetrySourceTitle = $isSimulationTelemetrySource
            ? 'Dashboard membaca tabel telemetry simulasi (terpisah dari data real).'
            : 'Dashboard membaca tabel telemetry real produksi.';

        $computeRealtimeMbitPerSecond = static function ($payloadBytes, $txDurationMs): ?float {
            if ($payloadBytes === null || $txDurationMs === null) {
                return null;
            }

            $bytes = (float) $payloadBytes;
            $durationMs = (float) $txDurationMs;
            if ($bytes <= 0 || $durationMs <= 0) {
                return null;
            }

            return (($bytes * 8) / 1000000) / ($durationMs / 1000);
        };

        $formatRealtimeLatency = static function ($latency): string {
            if ($latency === null || $latency === '') {
                return '-';
            }

            return number_format((float) $latency, 2) . ' ms';
        };

        $formatRealtimeSpeed = static function ($speed): string {
            if ($speed === null) {
                return '-';
            }

            return number_format((float) $speed, 3) . ' Mb/s';
        };

        $latestMqttRealtime = $protocolDiagnostics['mqtt'] ?? ['available' => false];
        $latestHttpRealtime = $protocolDiagnostics['http'] ?? ['available' => false];

        $mqttRealtimeLatency = ($mqttConnectionStatus['connected'] ?? false)
            ? $formatRealtimeLatency($latestMqttRealtime['latency_ms'] ?? null)
            : '-';
        $httpRealtimeLatency = ($httpConnectionStatus['connected'] ?? false)
            ? $formatRealtimeLatency($latestHttpRealtime['latency_ms'] ?? null)
            : '-';
        $mqttRealtimeSpeed = ($mqttConnectionStatus['connected'] ?? false)
            ? $formatRealtimeSpeed($computeRealtimeMbitPerSecond($latestMqttRealtime['payload_bytes'] ?? null, $latestMqttRealtime['tx_duration_ms'] ?? null))
            : '-';
        $httpRealtimeSpeed = ($httpConnectionStatus['connected'] ?? false)
            ? $formatRealtimeSpeed($computeRealtimeMbitPerSecond($latestHttpRealtime['payload_bytes'] ?? null, $latestHttpRealtime['tx_duration_ms'] ?? null))
            : '-';

        $formatProtocolStamp = static function (array $status): string {
            $label = strtoupper((string) ($status['protocol'] ?? 'PROTO'));
            $stateLabel = (string) ($status['label'] ?? 'Unknown');
            $lastSeen = (string) ($status['last_seen_wib'] ?? '-');
            $age = $status['age_seconds'] ?? null;
            $freshness = (int) ($status['freshness_seconds'] ?? 30);

            if (($status['state'] ?? '') === 'not_found') {
                return "{$label}: {$stateLabel} (belum ada data)";
            }
            if (($status['connected'] ?? false) !== true) {
                $ageText = is_numeric($age) ? "{$age} detik" : ">{$freshness} detik";
                return "{$label}: {$stateLabel} (last {$lastSeen}, age {$ageText})";
            }

            return "{$label}: {$stateLabel} (last {$lastSeen})";
        };

        $mqttRealtimeStamp = $formatProtocolStamp($mqttConnectionStatus);
        $httpRealtimeStamp = $formatProtocolStamp($httpConnectionStatus);
        $isRealtimeWidgetLive = $mqttConnected || $httpConnected;
    @endphp

    <aside id="realtimeNetworkWidget" class="network-float is-collapsed" aria-live="polite">
        <div class="network-float-head">
            <span class="network-title"><i class="fas fa-gauge-high"></i> Realtime Link Monitor</span>
            <button id="networkWidgetStatus" type="button" class="network-widget-status {{ $isRealtimeWidgetLive ? 'is-online' : 'is-offline' }}" aria-expanded="false" aria-controls="networkWidgetBody">
                <span class="status-dot"></span>
                {{ $isRealtimeWidgetLive ? 'LIVE' : 'IDLE' }}
            </button>
        </div>
        <div id="networkWidgetBody" class="network-float-body">
            <div id="mqttNetworkRow" class="network-protocol-row {{ $mqttConnectionStatus['row_class'] ?? ($mqttConnected ? 'is-online' : 'is-offline') }}">
                <span class="network-protocol-label"><i class="fas fa-broadcast-tower"></i> MQTT <small class="network-protocol-state">{{ strtoupper((string) ($mqttConnectionStatus['label'] ?? 'UNKNOWN')) }}</small></span>
                <span class="network-metric"><small>Ping</small><strong>{{ $mqttRealtimeLatency }}</strong></span>
                <span class="network-metric"><small>Speed</small><strong>{{ $mqttRealtimeSpeed }}</strong></span>
            </div>
            <div id="httpNetworkRow" class="network-protocol-row {{ $httpConnectionStatus['row_class'] ?? ($httpConnected ? 'is-online' : 'is-offline') }}">
                <span class="network-protocol-label"><i class="fas fa-server"></i> HTTP <small class="network-protocol-state">{{ strtoupper((string) ($httpConnectionStatus['label'] ?? 'UNKNOWN')) }}</small></span>
                <span class="network-metric"><small>Ping</small><strong>{{ $httpRealtimeLatency }}</strong></span>
                <span class="network-metric"><small>Speed</small><strong>{{ $httpRealtimeSpeed }}</strong></span>
            </div>
            <div id="externalNetworkRow" class="network-protocol-row is-online">
                <span class="network-protocol-label"><i class="fas fa-globe"></i> External</span>
                <span class="network-metric"><small>Ping</small><strong id="externalPingValue">Mengukur...</strong></span>
                <span class="network-metric"><small>Speed</small><strong id="externalSpeedValue">Mengukur...</strong></span>
            </div>
            <div id="networkWidgetStamp" class="network-stamp">
                MQTT: {{ $mqttRealtimeStamp }}<br>
                HTTP: {{ $httpRealtimeStamp }}
            </div>
            <div id="externalNetworkStamp" class="network-external-note">External: menunggu pengukuran browser...</div>
            <p class="network-note">MQTT/HTTP dari telemetry device. External diukur langsung dari browser perangkat ini.</p>
        </div>
    </aside>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content header-metric-row">
                <div class="header-metric-card suhu-card">
                    <button type="button" class="card-help-btn header-metric-help" data-help-target="helpHeaderSuhu" aria-controls="helpHeaderSuhu" aria-expanded="false" title="Lihat penjelasan card suhu">?</button>
                    <div class="metric-icon"><i class="fas fa-thermometer-half"></i></div>
                    <div class="metric-label">Rata-rata Suhu</div>
                    <div id="helpHeaderSuhu" class="card-help-panel header-metric-help-panel" hidden>
                        <p class="card-help-item"><span class="card-help-label">Rata-rata Suhu:</span> Nilai rata-rata suhu gabungan dari data MQTT dan HTTP terbaru.</p>
                        <p class="card-help-item"><span class="card-help-label">MQTT / HTTP:</span> Menampilkan rata-rata suhu masing-masing protokol untuk perbandingan langsung.</p>
                        <p class="card-help-item"><span class="card-help-label">Delta (MQTT-HTTP):</span> Selisih rata-rata suhu antara dua protokol pada sampel terbaru.</p>
                    </div>
                    <div class="metric-value" id="avgSuhuValue">{{ number_format((float) ($avgSuhu ?? 0), 2) }}<span class="metric-unit">C</span></div>
                    <div class="metric-detail" id="avgSuhuDetail">
                        MQTT: {{ number_format((float) ($mqttAvgSuhu ?? 0), 2) }} C<br>
                        HTTP: {{ number_format((float) ($httpAvgSuhu ?? 0), 2) }} C
                        @if(($protocolDiagnostics['pair_available'] ?? false) && isset($protocolDiagnostics['delta']))
                            <br>Delta (MQTT-HTTP): {{ sprintf('%+.2f', (float) ($protocolDiagnostics['delta']['suhu'] ?? 0)) }} C
                        @endif
                    </div>
                </div>
                <div class="header-center-content">
                    <h1><i class="fas fa-chart-line"></i> IoT Research System</h1>
                    <p>Analisis Komparatif Protokol MQTT vs HTTP</p>
                    <div class="header-subtitle">
                        <span id="telemetrySourceBadge" class="header-badge" title="{{ $telemetrySourceTitle }}">
                            <i class="fas fa-database"></i> {{ $telemetrySourceLabel }}
                        </span>
                        <span id="esp32StatusBadge" class="header-badge status-badge {{ $esp32ConnectionStatus['badge_class'] ?? ($esp32Connected ? 'is-online' : 'is-offline') }}" title="{{ $esp32ConnectionStatus['detail'] ?? '' }}">
                            <i class="fas fa-microchip"></i> ESP32 {{ $esp32ConnectionStatus['label'] ?? ($esp32Connected ? 'ON' : 'OFF') }}
                        </span>
                        <span id="mqttStatusBadge" class="header-badge status-badge {{ $mqttConnectionStatus['badge_class'] ?? ($mqttConnected ? 'is-online' : 'is-offline') }}" title="{{ $mqttConnectionStatus['detail'] ?? '' }}">
                            <i class="fas fa-wifi"></i> MQTT {{ $mqttConnectionStatus['label'] ?? ($mqttConnected ? 'Connected' : 'Disconnected') }}
                        </span>
                        <span id="httpStatusBadge" class="header-badge status-badge {{ $httpConnectionStatus['badge_class'] ?? ($httpConnected ? 'is-online' : 'is-offline') }}" title="{{ $httpConnectionStatus['detail'] ?? '' }}">
                            <i class="fas fa-globe"></i> HTTP {{ $httpConnectionStatus['label'] ?? ($httpConnected ? 'Connected' : 'Disconnected') }}
                        </span>
                        <span class="header-badge"><i class="fas fa-microscope"></i> T-Test Active</span>
                    </div>
                </div>
                <div class="header-metric-card kelembapan-card">
                    <button type="button" class="card-help-btn header-metric-help" data-help-target="helpHeaderHumidity" aria-controls="helpHeaderHumidity" aria-expanded="false" title="Lihat penjelasan card kelembapan">?</button>
                    <div class="metric-icon"><i class="fas fa-tint"></i></div>
                    <div class="metric-label">Rata-rata Kelembapan</div>
                    <div id="helpHeaderHumidity" class="card-help-panel header-metric-help-panel" hidden>
                        <p class="card-help-item"><span class="card-help-label">Rata-rata Kelembapan:</span> Nilai rata-rata kelembapan gabungan dari data MQTT dan HTTP terbaru.</p>
                        <p class="card-help-item"><span class="card-help-label">MQTT / HTTP:</span> Menampilkan rata-rata kelembapan per protokol untuk melihat perbedaan pembacaan.</p>
                        <p class="card-help-item"><span class="card-help-label">Delta (MQTT-HTTP):</span> Selisih kelembapan terbaru antar protokol.</p>
                    </div>
                    <div class="metric-value" id="avgKelembapanValue">{{ number_format((float) ($avgKelembapan ?? 0), 2) }}<span class="metric-unit">%</span></div>
                    <div class="metric-detail" id="avgKelembapanDetail">
                        MQTT: {{ number_format((float) ($mqttAvgKelembapan ?? 0), 2) }}%<br>
                        HTTP: {{ number_format((float) ($httpAvgKelembapan ?? 0), 2) }}%
                        @if(($protocolDiagnostics['pair_available'] ?? false) && isset($protocolDiagnostics['delta']))
                            <br>Delta (MQTT-HTTP): {{ sprintf('%+.2f', (float) ($protocolDiagnostics['delta']['kelembapan'] ?? 0)) }}%
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @php
            $formatCompactCount = static function ($value, int $threshold): string {
                $numeric = max(0, (int) $value);
                if ($numeric > $threshold) {
                    return (string) floor($numeric / 1000) . 'K';
                }

                return (string) $numeric;
            };
            $formatStatCount = static fn ($value): string => $formatCompactCount($value, 99999);
            $formatQualityCount = static fn ($value): string => $formatCompactCount($value, 999);
            $formatChartTotalCount = static fn ($value): string => $formatCompactCount($value, 9999);
        @endphp
        <!-- Statistics Cards -->
        <h2 class="section-title"><i class="fas fa-tachometer-alt"></i> Real-Time Metrics</h2>
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card mqtt-color">
                <button type="button" class="card-help-btn stat-card-help" data-help-target="helpStatMqttTotal" aria-controls="helpStatMqttTotal" aria-expanded="false" title="Lihat penjelasan card">?</button>
                <div class="stat-icon mqtt"><i class="fas fa-broadcast-tower"></i></div>
                <div id="helpStatMqttTotal" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">MQTT - Total Data:</span> Jumlah seluruh baris data protokol MQTT yang sudah tersimpan.</p>
                    <p class="card-help-item"><span class="card-help-label">Nilai utama:</span> Counter realtime total data MQTT.</p>
                    <p class="card-help-item"><span class="card-help-label">data points:</span> Satuan jumlah record, bukan satuan fisik sensor.</p>
                </div>
                <span class="stat-label">MQTT - Total Data</span>
                <span class="stat-value" title="{{ (int) ($summary['mqtt']['total_data'] ?? 0) }}">{{ $formatStatCount($summary['mqtt']['total_data'] ?? 0) }}</span>
                <span class="stat-unit">data points</span>
            </div>
            <div class="stat-card mqtt-color">
                <button type="button" class="card-help-btn stat-card-help" data-help-target="helpStatMqttLatency" aria-controls="helpStatMqttLatency" aria-expanded="false" title="Lihat penjelasan card">?</button>
                <div class="stat-icon mqtt"><i class="fas fa-clock"></i></div>
                <div id="helpStatMqttLatency" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">MQTT - Avg Latency:</span> Rata-rata waktu tempuh pengiriman data MQTT hingga diterima server.</p>
                    <p class="card-help-item"><span class="card-help-label">Nilai utama:</span> Mean latency dari sampel data MQTT yang dipakai analisis.</p>
                    <p class="card-help-item"><span class="card-help-label">milliseconds:</span> Satuan milidetik; makin kecil biasanya makin baik.</p>
                </div>
                <span class="stat-label">MQTT - Avg Latency</span>
                <span class="stat-value">{{ $summary['mqtt']['avg_latency_ms'] }}</span>
                <span class="stat-unit">milliseconds</span>
            </div>
            <div class="stat-card mqtt-color">
                <button type="button" class="card-help-btn stat-card-help" data-help-target="helpStatMqttPower" aria-controls="helpStatMqttPower" aria-expanded="false" title="Lihat penjelasan card">?</button>
                <div class="stat-icon mqtt"><i class="fas fa-bolt"></i></div>
                <div id="helpStatMqttPower" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">MQTT - Avg Power:</span> Rata-rata konsumsi daya pada siklus kirim MQTT.</p>
                    <p class="card-help-item"><span class="card-help-label">Nilai utama:</span> Mean `daya_mw` data MQTT yang sudah tervalidasi.</p>
                    <p class="card-help-item"><span class="card-help-label">milliwatts:</span> Satuan daya listrik dalam mW.</p>
                </div>
                <span class="stat-label">MQTT - Avg Power</span>
                <span class="stat-value">{{ $summary['mqtt']['avg_daya_mw'] }}</span>
                <span class="stat-unit">milliwatts</span>
            </div>
            <div class="stat-card mqtt-color">
                <button type="button" class="card-help-btn stat-card-help" data-help-target="helpStatMqttReliability" aria-controls="helpStatMqttReliability" aria-expanded="false" title="Lihat penjelasan card">?</button>
                <div class="stat-icon mqtt"><i class="fas fa-shield-alt"></i></div>
                <div id="helpStatMqttReliability" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">MQTT - Reliability:</span> Skor kesehatan pengiriman MQTT berdasarkan sequence, kelengkapan field, dan transmission health.</p>
                    <p class="card-help-item"><span class="card-help-label">Nilai utama (%):</span> Skor gabungan reliabilitas protokol MQTT.</p>
                    <p class="card-help-item"><span class="card-help-label">seq | complete | tx:</span> Ringkasan continuity packet, completeness payload, dan kesehatan transmisi.</p>
                </div>
                <span class="stat-label">MQTT - Reliability</span>
                <span class="stat-value">{{ $reliability['mqtt_reliability'] }}%</span>
                <span class="stat-unit">seq {{ $reliability['mqtt_expected_packets'] > 0 ? ($reliability['mqtt_received_packets'] . '/' . $reliability['mqtt_expected_packets']) : '-' }} | complete {{ $reliability['mqtt_data_completeness'] }}% | tx {{ $reliability['mqtt_transmission_health'] ?? 0 }}%</span>
            </div>
            <div class="stat-card http-color">
                <button type="button" class="card-help-btn stat-card-help" data-help-target="helpStatHttpTotal" aria-controls="helpStatHttpTotal" aria-expanded="false" title="Lihat penjelasan card">?</button>
                <div class="stat-icon http"><i class="fas fa-server"></i></div>
                <div id="helpStatHttpTotal" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">HTTP - Total Data:</span> Jumlah seluruh baris data protokol HTTP yang sudah tersimpan.</p>
                    <p class="card-help-item"><span class="card-help-label">Nilai utama:</span> Counter realtime total data HTTP.</p>
                    <p class="card-help-item"><span class="card-help-label">data points:</span> Satuan jumlah record, bukan satuan fisik sensor.</p>
                </div>
                <span class="stat-label">HTTP - Total Data</span>
                <span class="stat-value" title="{{ (int) ($summary['http']['total_data'] ?? 0) }}">{{ $formatStatCount($summary['http']['total_data'] ?? 0) }}</span>
                <span class="stat-unit">data points</span>
            </div>
            <div class="stat-card http-color">
                <button type="button" class="card-help-btn stat-card-help" data-help-target="helpStatHttpLatency" aria-controls="helpStatHttpLatency" aria-expanded="false" title="Lihat penjelasan card">?</button>
                <div class="stat-icon http"><i class="fas fa-hourglass-end"></i></div>
                <div id="helpStatHttpLatency" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">HTTP - Avg Latency:</span> Rata-rata waktu tempuh request HTTP hingga diproses server.</p>
                    <p class="card-help-item"><span class="card-help-label">Nilai utama:</span> Mean latency dari sampel data HTTP.</p>
                    <p class="card-help-item"><span class="card-help-label">milliseconds:</span> Satuan milidetik; makin kecil biasanya makin baik.</p>
                </div>
                <span class="stat-label">HTTP - Avg Latency</span>
                <span class="stat-value">{{ $summary['http']['avg_latency_ms'] }}</span>
                <span class="stat-unit">milliseconds</span>
            </div>
            <div class="stat-card http-color">
                <button type="button" class="card-help-btn stat-card-help" data-help-target="helpStatHttpPower" aria-controls="helpStatHttpPower" aria-expanded="false" title="Lihat penjelasan card">?</button>
                <div class="stat-icon http"><i class="fas fa-plug"></i></div>
                <div id="helpStatHttpPower" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">HTTP - Avg Power:</span> Rata-rata konsumsi daya pada siklus kirim HTTP.</p>
                    <p class="card-help-item"><span class="card-help-label">Nilai utama:</span> Mean `daya_mw` data HTTP yang sudah tervalidasi.</p>
                    <p class="card-help-item"><span class="card-help-label">milliwatts:</span> Satuan daya listrik dalam mW.</p>
                </div>
                <span class="stat-label">HTTP - Avg Power</span>
                <span class="stat-value">{{ $summary['http']['avg_daya_mw'] }}</span>
                <span class="stat-unit">milliwatts</span>
            </div>
            <div class="stat-card http-color">
                <button type="button" class="card-help-btn stat-card-help" data-help-target="helpStatHttpReliability" aria-controls="helpStatHttpReliability" aria-expanded="false" title="Lihat penjelasan card">?</button>
                <div class="stat-icon http"><i class="fas fa-check-circle"></i></div>
                <div id="helpStatHttpReliability" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">HTTP - Reliability:</span> Skor kesehatan pengiriman HTTP berdasarkan sequence, kelengkapan field, dan transmission health.</p>
                    <p class="card-help-item"><span class="card-help-label">Nilai utama (%):</span> Skor gabungan reliabilitas protokol HTTP.</p>
                    <p class="card-help-item"><span class="card-help-label">seq | complete | tx:</span> Ringkasan continuity packet, completeness payload, dan kesehatan transmisi.</p>
                </div>
                <span class="stat-label">HTTP - Reliability</span>
                <span class="stat-value">{{ $reliability['http_reliability'] }}%</span>
                <span class="stat-unit">seq {{ $reliability['http_expected_packets'] > 0 ? ($reliability['http_received_packets'] . '/' . $reliability['http_expected_packets']) : '-' }} | complete {{ $reliability['http_data_completeness'] }}% | tx {{ $reliability['http_transmission_health'] ?? 0 }}%</span>
            </div>
        </div>
        @if(!$isEmbeddedDashboard)
            <div class="action-row">
                <a href="{{ (rtrim(request()->getBaseUrl(), '/') !== '' ? rtrim(request()->getBaseUrl(), '/') : '') . '/reset-data' }}" class="reset-btn"><i class="fas fa-trash-alt"></i> Reset Data Eksperimen</a>
                <a href="{{ (rtrim(request()->getBaseUrl(), '/') !== '' ? rtrim(request()->getBaseUrl(), '/') : '') . '/admin/config' }}" class="admin-config-btn"><i class="fas fa-sliders"></i> Admin Config & Firmware</a>
                @if(session('status'))
                    <span id="resetStatusMessage" class="reset-status">{{ session('status') }}</span>
                @endif
            </div>
        @endif
        @if(!empty($dataWarnings))
            <div id="dataQualityWarnings" class="inline-warning">
                <i class="fas fa-triangle-exclamation"></i>
                Warning kualitas data terdeteksi:
                <ul>
                    @foreach($dataWarnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div id="protocolDiagnosticsPanel" class="protocol-diagnostics">
            <h3><i class="fas fa-wave-square"></i> Protocol Payload Diagnostics (Latest Data)</h3>
            @if(!empty($protocolDiagnostics['sensor_sync_note']))
                <p class="protocol-sync-note">{{ $protocolDiagnostics['sensor_sync_note'] }}</p>
            @endif
            @php
                $diagnosticCards = [
                    'mqtt' => $protocolDiagnostics['mqtt'] ?? ['protocol' => 'MQTT', 'available' => false],
                    'http' => $protocolDiagnostics['http'] ?? ['protocol' => 'HTTP', 'available' => false],
                ];
                $protocolDelta = $protocolDiagnostics['delta'] ?? null;
                $formatPreciseFloat = static function ($value, int $decimals = 8): string {
                    if ($value === null || $value === '') {
                        return '-';
                    }
                    return number_format((float) $value, $decimals, '.', '');
                };
                $formatSignedPreciseFloat = static function ($value, int $decimals = 8): string {
                    if ($value === null || $value === '') {
                        return '-';
                    }
                    $numeric = (float) $value;
                    $prefix = $numeric >= 0 ? '+' : '';
                    return $prefix . number_format($numeric, $decimals, '.', '');
                };
                $protocolRowHelp = [
                    'Data ID' => 'ID unik row pada database untuk payload terbaru protokol ini.',
                    'Packet Seq' => 'Nomor urut paket dari ESP32 untuk mendeteksi gap/loss.',
                    'Timestamp ESP' => 'Waktu saat data dicatat di ESP32 (ditampilkan dalam WIB).',
                    'Timestamp Server' => 'Waktu saat payload diterima server Laravel (WIB).',
                    'Suhu (raw)' => 'Nilai suhu mentah presisi tinggi yang dikirim payload protokol ini.',
                    'Kelembapan (raw)' => 'Nilai kelembapan mentah presisi tinggi dari payload protokol ini.',
                    'Latency' => 'Estimasi waktu kirim data protokol dari sumber ke server.',
                    'Daya' => 'Estimasi konsumsi daya saat proses kirim protokol.',
                    'RSSI' => 'Kekuatan sinyal WiFi saat payload dikirim (semakin mendekati 0 biasanya lebih kuat).',
                    'TX Duration' => 'Durasi transmisi payload untuk protokol terkait.',
                    'Payload' => 'Ukuran payload yang dikirim dalam byte.',
                    'Sensor Age' => 'Usia data sensor saat payload dikirim.',
                    'Sensor Read Seq' => 'Urutan pembacaan sensor untuk menelusuri snapshot data.',
                    'Send Tick' => 'Tick monotonic ESP32 saat payload dipublish/dikirim.',
                    'Uptime' => 'Lamanya ESP32 menyala saat payload dikirim.',
                    'Free Heap' => 'Memori bebas ESP32 saat payload dikirim.',
                ];
                $deltaRowHelp = [
                    'Suhu' => 'Selisih suhu MQTT terhadap HTTP pada sampel terbaru.',
                    'Kelembapan' => 'Selisih kelembapan MQTT terhadap HTTP pada sampel terbaru.',
                    'Latency' => 'Selisih latency antara MQTT dan HTTP.',
                    'Daya' => 'Selisih konsumsi daya antara MQTT dan HTTP.',
                    'TX Duration' => 'Selisih waktu transmisi payload antara dua protokol.',
                    'Payload' => 'Perbedaan ukuran payload MQTT dan HTTP.',
                    'RSSI' => 'Perbedaan kekuatan sinyal saat kedua protokol mengirim.',
                    'Sensor Read Seq' => 'Gap urutan pembacaan sensor antara MQTT dan HTTP.',
                    'Send Tick' => 'Gap tick kirim monotonic antar protokol.',
                    'Sensor Age' => 'Gap usia snapshot sensor antar protokol.',
                    'Server Timestamp Gap' => 'Jarak waktu penerimaan server antara payload MQTT dan HTTP.',
                ];
            @endphp
            <div class="protocol-diagnostics-grid">
                @foreach($diagnosticCards as $diagnosticCard)
                    @php
                        $cardProtocol = strtoupper((string) ($diagnosticCard['protocol'] ?? 'UNKNOWN'));
                        $cardClass = strtolower($cardProtocol);
                        $cardIcon = $cardProtocol === 'MQTT' ? 'fa-broadcast-tower' : 'fa-server';
                        $helpPanelId = 'protocolHelp' . ucfirst(strtolower($cardProtocol));
                        $rows = [
                            ['label' => 'Data ID', 'value' => $diagnosticCard['id'] ?? '-'],
                            ['label' => 'Packet Seq', 'value' => $diagnosticCard['packet_seq'] ?? '-'],
                            ['label' => 'Timestamp ESP', 'value' => $diagnosticCard['timestamp_esp'] ?? '-'],
                            ['label' => 'Timestamp Server', 'value' => $diagnosticCard['timestamp_server'] ?? '-'],
                            ['label' => 'Suhu (raw)', 'value' => isset($diagnosticCard['suhu']) ? $formatPreciseFloat($diagnosticCard['suhu']) . ' C' : '-'],
                            ['label' => 'Kelembapan (raw)', 'value' => isset($diagnosticCard['kelembapan']) ? $formatPreciseFloat($diagnosticCard['kelembapan']) . ' %' : '-'],
                            ['label' => 'Latency', 'value' => isset($diagnosticCard['latency_ms']) ? number_format((float) $diagnosticCard['latency_ms'], 2) . ' ms' : '-'],
                            ['label' => 'Daya', 'value' => isset($diagnosticCard['daya_mw']) ? number_format((float) $diagnosticCard['daya_mw'], 2) . ' mW' : '-'],
                            ['label' => 'RSSI', 'value' => isset($diagnosticCard['rssi_dbm']) ? $diagnosticCard['rssi_dbm'] . ' dBm' : '-'],
                            ['label' => 'TX Duration', 'value' => isset($diagnosticCard['tx_duration_ms']) ? number_format((float) $diagnosticCard['tx_duration_ms'], 2) . ' ms' : '-'],
                            ['label' => 'Payload', 'value' => isset($diagnosticCard['payload_bytes']) ? number_format((int) $diagnosticCard['payload_bytes']) . ' bytes' : '-'],
                            ['label' => 'Sensor Age', 'value' => isset($diagnosticCard['sensor_age_ms']) ? number_format((int) $diagnosticCard['sensor_age_ms']) . ' ms' : '-'],
                            ['label' => 'Sensor Read Seq', 'value' => isset($diagnosticCard['sensor_read_seq']) ? number_format((int) $diagnosticCard['sensor_read_seq']) : '-'],
                            ['label' => 'Send Tick', 'value' => isset($diagnosticCard['send_tick_ms']) ? number_format((int) $diagnosticCard['send_tick_ms']) . ' ms' : '-'],
                            ['label' => 'Uptime', 'value' => isset($diagnosticCard['uptime_s']) ? number_format((int) $diagnosticCard['uptime_s']) . ' s' : '-'],
                            ['label' => 'Free Heap', 'value' => isset($diagnosticCard['free_heap_bytes']) ? number_format((int) $diagnosticCard['free_heap_bytes']) . ' bytes' : '-'],
                        ];
                    @endphp
                    <article class="protocol-diagnostic-card {{ $cardClass }}">
                        <div class="protocol-card-header">
                            <h4><i class="fas {{ $cardIcon }}"></i> {{ $cardProtocol }} Latest Payload</h4>
                            <button type="button" class="card-help-btn" data-help-target="{{ $helpPanelId }}" aria-controls="{{ $helpPanelId }}" aria-expanded="false" title="Lihat penjelasan row card ini">?</button>
                        </div>
                        <div id="{{ $helpPanelId }}" class="card-help-panel" hidden>
                            @foreach($rows as $row)
                                <p class="card-help-item"><span class="card-help-label">{{ $row['label'] }}:</span> {{ $protocolRowHelp[$row['label']] ?? 'Keterangan row belum tersedia.' }}</p>
                            @endforeach
                        </div>
                        <div class="protocol-diagnostic-list">
                            @if(!($diagnosticCard['available'] ?? false))
                                <div class="protocol-diagnostic-row">
                                    <span class="label">Status</span>
                                    <span class="value">Belum ada data</span>
                                </div>
                            @else
                                @foreach($rows as $row)
                                    <div class="protocol-diagnostic-row">
                                        <span class="label">{{ $row['label'] }}</span>
                                        <span class="value">{{ $row['value'] }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </article>
                @endforeach
                <article class="protocol-diagnostic-card delta">
                    <div class="protocol-card-header">
                        <h4><i class="fas fa-code-compare"></i> Delta MQTT - HTTP</h4>
                        <button type="button" class="card-help-btn" data-help-target="protocolHelpDelta" aria-controls="protocolHelpDelta" aria-expanded="false" title="Lihat penjelasan row card ini">?</button>
                    </div>
                    <div id="protocolHelpDelta" class="card-help-panel" hidden>
                        @foreach($deltaRowHelp as $deltaLabel => $deltaDesc)
                            <p class="card-help-item"><span class="card-help-label">{{ $deltaLabel }}:</span> {{ $deltaDesc }}</p>
                        @endforeach
                    </div>
                    <div class="protocol-diagnostic-list">
                        @if($protocolDiagnostics['pair_available'] ?? false)
                            <div class="protocol-diagnostic-row"><span class="label">Suhu</span><span class="value">{{ $formatSignedPreciseFloat($protocolDelta['suhu'] ?? null) }} C</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">Kelembapan</span><span class="value">{{ $formatSignedPreciseFloat($protocolDelta['kelembapan'] ?? null) }} %</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">Latency</span><span class="value">{{ sprintf('%+.2f', (float) ($protocolDelta['latency_ms'] ?? 0)) }} ms</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">Daya</span><span class="value">{{ sprintf('%+.2f', (float) ($protocolDelta['daya_mw'] ?? 0)) }} mW</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">TX Duration</span><span class="value">{{ sprintf('%+.2f', (float) ($protocolDelta['tx_duration_ms'] ?? 0)) }} ms</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">Payload</span><span class="value">{{ (($protocolDelta['payload_bytes'] ?? 0) >= 0 ? '+' : '') . ($protocolDelta['payload_bytes'] ?? 0) }} bytes</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">RSSI</span><span class="value">{{ (($protocolDelta['rssi_dbm'] ?? 0) >= 0 ? '+' : '') . ($protocolDelta['rssi_dbm'] ?? 0) }} dBm</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">Sensor Read Seq</span><span class="value">{{ (($protocolDelta['sensor_read_seq'] ?? 0) >= 0 ? '+' : '') . ($protocolDelta['sensor_read_seq'] ?? 0) }}</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">Send Tick</span><span class="value">{{ (($protocolDelta['send_tick_ms'] ?? 0) >= 0 ? '+' : '') . ($protocolDelta['send_tick_ms'] ?? 0) }} ms</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">Sensor Age</span><span class="value">{{ (($protocolDelta['sensor_age_ms'] ?? 0) >= 0 ? '+' : '') . ($protocolDelta['sensor_age_ms'] ?? 0) }} ms</span></div>
                            <div class="protocol-diagnostic-row"><span class="label">Server Timestamp Gap</span><span class="value">{{ isset($protocolDelta['server_gap_ms']) ? number_format((float) $protocolDelta['server_gap_ms'], 2) . ' ms' : '-' }}</span></div>
                        @else
                            <div class="protocol-diagnostic-row"><span class="label">Status</span><span class="value">Belum cukup data MQTT + HTTP untuk dihitung.</span></div>
                        @endif
                    </div>
                </article>
            </div>
        </div>
        <div id="protocolQualityPanel" class="quality-grid">
            @foreach($fieldCompleteness as $protocol => $protocolMeta)
                @php
                    $hasQualityIssue = false;
                    $isMqttProtocol = strtoupper((string) $protocol) === 'MQTT';
                    $protocolIconClass = $isMqttProtocol ? 'fa-broadcast-tower quality-protocol-mqtt' : 'fa-server quality-protocol-http';
                    $qualityHelpPanelId = 'qualityHelp' . preg_replace('/[^a-z0-9]/i', '', ucfirst(strtolower((string) $protocol)));
                    if (($protocolMeta['total'] ?? 0) > 0 && isset($protocolMeta['fields']) && is_array($protocolMeta['fields'])) {
                        foreach ($protocolMeta['fields'] as $fieldMeta) {
                            if (($fieldMeta['missing'] ?? 0) > 0) {
                                $hasQualityIssue = true;
                                break;
                            }
                        }
                    }
                @endphp
                <div class="quality-card">
                    <details class="quality-details" data-protocol="{{ $protocol }}" open>
                        <summary class="quality-summary">
                            <h4>
                                <span class="quality-title-main">
                                    <i class="fas fa-shield-halved quality-main-icon" aria-hidden="true"></i>
                                    <i class="fas {{ $protocolIconClass }} quality-protocol-icon" aria-hidden="true"></i>
                                    {{ $protocol }} Field Completeness
                                </span>
                                <span class="quality-title-count" title="{{ (int) ($protocolMeta['total'] ?? 0) }} data">({{ $formatQualityCount($protocolMeta['total'] ?? 0) }} data)</span>
                            </h4>
                            <span class="quality-summary-meta">
                                <button type="button" class="card-help-btn quality-help-btn" data-help-target="{{ $qualityHelpPanelId }}" aria-controls="{{ $qualityHelpPanelId }}" aria-expanded="false" title="Lihat penjelasan row card ini">?</button>
                                <span class="quality-dot {{ $hasQualityIssue ? 'quality-dot-bad' : 'quality-dot-good' }}" aria-label="{{ $hasQualityIssue ? 'Status warning' : 'Status aman' }}"></span>
                                <span class="quality-toggle"><i class="fas fa-chevron-down"></i></span>
                            </span>
                        </summary>
                        <div id="{{ $qualityHelpPanelId }}" class="card-help-panel quality-help-panel" hidden>
                            @if($protocolMeta['total'] === 0)
                                <p class="card-help-item"><span class="card-help-label">Status:</span> Belum ada data untuk validasi kelengkapan field protokol ini.</p>
                            @else
                                @foreach($protocolMeta['fields'] as $fieldMeta)
                                    <p class="card-help-item"><span class="card-help-label">{{ $fieldMeta['label'] }}:</span> Menampilkan jumlah data valid dibanding total data untuk field ini.</p>
                                @endforeach
                            @endif
                        </div>
                        <div class="quality-content">
                        @if($protocolMeta['total'] === 0)
                            <div class="quality-row">
                                <span>Belum ada data untuk validasi.</span>
                            </div>
                        @else
                            @foreach($protocolMeta['fields'] as $fieldMeta)
                                <div class="quality-row">
                                    <span>{{ $fieldMeta['label'] }}</span>
                                    <span class="quality-badge {{ $fieldMeta['missing'] > 0 ? 'quality-bad' : 'quality-good' }}" title="{{ (int) ($fieldMeta['valid'] ?? 0) }}/{{ (int) ($fieldMeta['total'] ?? 0) }}">
                                        {{ $formatQualityCount($fieldMeta['valid'] ?? 0) }}/{{ $formatQualityCount($fieldMeta['total'] ?? 0) }}
                                    </span>
                                </div>
                            @endforeach
                        @endif
                        </div>
                    </details>
                </div>
            @endforeach
        </div>

        <!-- Charts Section -->
        @if($mqttTotal > 0 || $httpTotal > 0)
            <h2 class="section-title"><i class="fas fa-chart-bar"></i> Comparative Analysis</h2>
            <div class="chart-container">
                <div class="chart-title-row">
                    <h3 class="chart-title"><i class="fas fa-stopwatch"></i> Latency Comparison</h3>
                    <button type="button" class="card-help-btn" data-help-target="chartHelpLatency" aria-controls="chartHelpLatency" aria-expanded="false" title="Lihat penjelasan row card ini">?</button>
                </div>
                <div id="chartHelpLatency" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">Total data point:</span> Jumlah titik data latency gabungan MQTT + HTTP yang tersedia di chart.</p>
                    <p class="card-help-item"><span class="card-help-label">Default(min):</span> Jumlah titik minimum yang ditampilkan ketika zoom di-reset.</p>
                    <p class="card-help-item"><span class="card-help-label">View saat ini:</span> Jumlah titik yang sedang terlihat pada viewport chart sekarang.</p>
                    <p class="card-help-item"><span class="card-help-label">Zoom/Pan:</span> Gunakan tombol `-`, `+`, `Reset` dan geser horizontal untuk melihat data lama/baru.</p>
                </div>
                @if(count($latencyChartData['labels']) > 0)
                    <div class="chart-toolbar">
                        <div class="chart-toolbar-info" id="latencyToolbarInfo">
                            Total data point: {{ $formatChartTotalCount($latencyChartData['total_records'] ?? $latencyChartData['total_points']) }} | Default(min): {{ min(10, $latencyChartData['total_points']) }} data | View saat ini: {{ min(10, $latencyChartData['total_points']) }} data
                        </div>
                        <div class="zoom-controls">
                            <button type="button" id="latencyZoomOut" class="zoom-btn" aria-label="Zoom Out">-</button>
                            <button type="button" id="latencyZoomIn" class="zoom-btn" aria-label="Zoom In">+</button>
                            <button type="button" id="latencyZoomReset" class="zoom-btn zoom-reset" aria-label="Reset Zoom">Reset</button>
                        </div>
                    </div>
                    <div class="chart-hint">Geser kiri/kanan untuk melihat data lain. Zoom hanya lewat tombol supaya tetap rapi.</div>
                    <div class="chart-wrapper">
                        <canvas id="latencyChart"></canvas>
                    </div>
                @else
                    <div class="no-data"><i class="fas fa-chart-line"></i><p>Belum ada data</p></div>
                @endif
            </div>
            <div class="chart-container">
                <div class="chart-title-row">
                    <h3 class="chart-title"><i class="fas fa-battery-half"></i> Power Consumption Comparison</h3>
                    <button type="button" class="card-help-btn" data-help-target="chartHelpPower" aria-controls="chartHelpPower" aria-expanded="false" title="Lihat penjelasan row card ini">?</button>
                </div>
                <div id="chartHelpPower" class="card-help-panel" hidden>
                    <p class="card-help-item"><span class="card-help-label">Total data point:</span> Jumlah titik data daya gabungan MQTT + HTTP yang tersedia di chart.</p>
                    <p class="card-help-item"><span class="card-help-label">Default(min):</span> Jumlah titik minimum saat reset tampilan power chart.</p>
                    <p class="card-help-item"><span class="card-help-label">View saat ini:</span> Jumlah titik yang sedang tampil saat ini pada chart power.</p>
                    <p class="card-help-item"><span class="card-help-label">Zoom/Pan:</span> Gunakan tombol `-`, `+`, `Reset` dan geser horizontal untuk eksplorasi data.</p>
                </div>
                @if(count($powerChartData['labels']) > 0)
                    <div class="chart-toolbar">
                        <div class="chart-toolbar-info" id="powerToolbarInfo">
                            Total data point: {{ $formatChartTotalCount($powerChartData['total_records'] ?? $powerChartData['total_points']) }} | Default(min): {{ min(15, $powerChartData['total_points']) }} data | View saat ini: {{ min(15, $powerChartData['total_points']) }} data
                        </div>
                        <div class="zoom-controls">
                            <button type="button" id="powerZoomOut" class="zoom-btn" aria-label="Zoom Out">-</button>
                            <button type="button" id="powerZoomIn" class="zoom-btn" aria-label="Zoom In">+</button>
                            <button type="button" id="powerZoomReset" class="zoom-btn zoom-reset" aria-label="Reset Zoom">Reset</button>
                        </div>
                    </div>
                    <div class="chart-hint">Geser kiri/kanan untuk melihat data lain. Zoom hanya lewat tombol supaya tetap rapi.</div>
                    <div class="chart-wrapper">
                        <canvas id="powerChart"></canvas>
                    </div>
                @else
                    <div class="no-data"><i class="fas fa-battery-full"></i><p>Belum ada data</p></div>
                @endif
            </div>
        @else
            <div class="chart-container"><div class="no-data"><i class="fas fa-database"></i><p>Belum ada data</p></div></div>
        @endif

        <!-- T-Test Results -->
        @if($summary['ttest_latency']['valid'])
            <section id="statisticalAnalysisSection">
                <h2 class="section-title"><i class="fas fa-flask"></i> Statistical Analysis</h2>
                <div class="ttest-section">
                    <h2 class="ttest-title"><i class="fas fa-calculator"></i> Independent Sample T-Test Results</h2>
                    <div class="ttest-subsection">
                        <h3><i class="fas fa-stopwatch latency-icon"></i> Latency Analysis</h3>
                        <div class="ttest-grid">
                            <div class="ttest-card mqtt-card">
                                <div class="ttest-card-header">
                                    <span class="ttest-header-title"><i class="fas fa-broadcast-tower"></i> MQTT Protocol</span>
                                    <button type="button" class="ttest-help-btn" data-help-target="ttestHelpLatencyMqtt" aria-controls="ttestHelpLatencyMqtt" aria-expanded="false" title="Lihat penjelasan label">?</button>
                                </div>
                                <div id="ttestHelpLatencyMqtt" class="ttest-help-panel" hidden>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Sample Size (N):</span> Jumlah data latency MQTT yang dianalisis.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Mean (μ):</span> Nilai rata-rata latency (ms); makin kecil biasanya makin baik.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Std Deviation (σ):</span> Besar sebaran data latency terhadap rata-rata.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Variance (σ²):</span> Kuadrat dari simpangan baku; menunjukkan tingkat variasi data.</p>
                                </div>
                                <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ (int) ($summary['mqtt']['total_data'] ?? 0) }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Mean (μ)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['mean'] }} ms</span></div>
                                <div class="ttest-row"><span class="ttest-label">Std Deviation (σ)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['std_dev'] }} ms</span></div>
                                <div class="ttest-row"><span class="ttest-label">Variance (σ²)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['variance'] }}</span></div>
                            </div>
                            <div class="ttest-card http-card">
                                <div class="ttest-card-header">
                                    <span class="ttest-header-title"><i class="fas fa-server"></i> HTTP Protocol</span>
                                    <button type="button" class="ttest-help-btn" data-help-target="ttestHelpLatencyHttp" aria-controls="ttestHelpLatencyHttp" aria-expanded="false" title="Lihat penjelasan label">?</button>
                                </div>
                                <div id="ttestHelpLatencyHttp" class="ttest-help-panel" hidden>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Sample Size (N):</span> Jumlah data latency HTTP yang dianalisis.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Mean (μ):</span> Nilai rata-rata latency (ms); makin kecil biasanya makin baik.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Std Deviation (σ):</span> Besar sebaran data latency terhadap rata-rata.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Variance (σ²):</span> Kuadrat dari simpangan baku; menunjukkan tingkat variasi data.</p>
                                </div>
                                <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ (int) ($summary['http']['total_data'] ?? 0) }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Mean (μ)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['mean'] }} ms</span></div>
                                <div class="ttest-row"><span class="ttest-label">Std Deviation (σ)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['std_dev'] }} ms</span></div>
                                <div class="ttest-row"><span class="ttest-label">Variance (σ²)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['variance'] }}</span></div>
                            </div>
                            <div class="ttest-card result-card">
                                <div class="ttest-card-header">
                                    <span class="ttest-header-title"><i class="fas fa-flask-vial"></i> T-Test Results</span>
                                    <button type="button" class="ttest-help-btn" data-help-target="ttestHelpLatencyResult" aria-controls="ttestHelpLatencyResult" aria-expanded="false" title="Lihat penjelasan label">?</button>
                                </div>
                                <div id="ttestHelpLatencyResult" class="ttest-help-panel" hidden>
                                    <p class="ttest-help-item"><span class="ttest-help-label">t-value:</span> Ukuran jarak perbedaan dua rata-rata terhadap variasi data.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Degrees of Freedom:</span> Parameter statistik yang dipakai untuk interpretasi uji t.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">Critical Value:</span> Nilai batas keputusan uji t pada tingkat signifikansi tertentu.</p>
                                    <p class="ttest-help-item"><span class="ttest-help-label">p-value:</span> Probabilitas hasil terjadi jika H0 benar; makin kecil makin signifikan.</p>
                                </div>
                                <div class="ttest-row"><span class="ttest-label">t-value</span><span class="ttest-value">{{ $summary['ttest_latency']['t_value'] }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Degrees of Freedom</span><span class="ttest-value">{{ $summary['ttest_latency']['df'] }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Critical Value</span><span class="ttest-value">±{{ $summary['ttest_latency']['critical_value'] }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">p-value</span><span class="ttest-value">{{ $summary['ttest_latency']['p_value'] }}</span></div>
                                <span class="significance-badge @if($summary['ttest_latency']['is_significant']) significance-yes @else significance-no @endif">
                                    @if($summary['ttest_latency']['is_significant'])
                                        <i class="fas fa-check-circle"></i> Signifikan
                                    @else
                                        <i class="fas fa-times-circle"></i> Tidak Signifikan
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="ttest-subsection">
                        <h3><i class="fas fa-bolt power-icon"></i> Power Consumption Analysis</h3>
                        @if($summary['ttest_daya']['valid'])
                            <div class="ttest-grid">
                                <div class="ttest-card mqtt-card">
                                    <div class="ttest-card-header">
                                        <span class="ttest-header-title"><i class="fas fa-broadcast-tower"></i> MQTT Protocol</span>
                                        <button type="button" class="ttest-help-btn" data-help-target="ttestHelpPowerMqtt" aria-controls="ttestHelpPowerMqtt" aria-expanded="false" title="Lihat penjelasan label">?</button>
                                    </div>
                                    <div id="ttestHelpPowerMqtt" class="ttest-help-panel" hidden>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Sample Size (N):</span> Jumlah data daya MQTT yang dianalisis.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Mean (μ):</span> Nilai rata-rata daya (mW) selama pengukuran.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Std Deviation (σ):</span> Besar sebaran konsumsi daya terhadap rata-rata.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Variance (σ²):</span> Kuadrat dari simpangan baku untuk melihat variasi daya.</p>
                                    </div>
                                    <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ (int) ($summary['mqtt']['total_data'] ?? 0) }}</span></div>
                                    <div class="ttest-row"><span class="ttest-label">Mean (μ)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['mean'] }} mW</span></div>
                                    <div class="ttest-row"><span class="ttest-label">Std Deviation (σ)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['std_dev'] }} mW</span></div>
                                    <div class="ttest-row"><span class="ttest-label">Variance (σ²)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['variance'] }}</span></div>
                                </div>
                                <div class="ttest-card http-card">
                                    <div class="ttest-card-header">
                                        <span class="ttest-header-title"><i class="fas fa-server"></i> HTTP Protocol</span>
                                        <button type="button" class="ttest-help-btn" data-help-target="ttestHelpPowerHttp" aria-controls="ttestHelpPowerHttp" aria-expanded="false" title="Lihat penjelasan label">?</button>
                                    </div>
                                    <div id="ttestHelpPowerHttp" class="ttest-help-panel" hidden>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Sample Size (N):</span> Jumlah data daya HTTP yang dianalisis.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Mean (μ):</span> Nilai rata-rata daya (mW) selama pengukuran.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Std Deviation (σ):</span> Besar sebaran konsumsi daya terhadap rata-rata.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Variance (σ²):</span> Kuadrat dari simpangan baku untuk melihat variasi daya.</p>
                                    </div>
                                    <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ (int) ($summary['http']['total_data'] ?? 0) }}</span></div>
                                    <div class="ttest-row"><span class="ttest-label">Mean (μ)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['mean'] }} mW</span></div>
                                    <div class="ttest-row"><span class="ttest-label">Std Deviation (σ)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['std_dev'] }} mW</span></div>
                                    <div class="ttest-row"><span class="ttest-label">Variance (σ²)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['variance'] }}</span></div>
                                </div>
                                <div class="ttest-card result-card">
                                    <div class="ttest-card-header">
                                        <span class="ttest-header-title"><i class="fas fa-flask-vial"></i> T-Test Results</span>
                                        <button type="button" class="ttest-help-btn" data-help-target="ttestHelpPowerResult" aria-controls="ttestHelpPowerResult" aria-expanded="false" title="Lihat penjelasan label">?</button>
                                    </div>
                                    <div id="ttestHelpPowerResult" class="ttest-help-panel" hidden>
                                        <p class="ttest-help-item"><span class="ttest-help-label">t-value:</span> Ukuran jarak perbedaan dua rata-rata terhadap variasi data.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Degrees of Freedom:</span> Parameter statistik yang dipakai untuk interpretasi uji t.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">Critical Value:</span> Nilai batas keputusan uji t pada tingkat signifikansi tertentu.</p>
                                        <p class="ttest-help-item"><span class="ttest-help-label">p-value:</span> Probabilitas hasil terjadi jika H0 benar; makin kecil makin signifikan.</p>
                                    </div>
                                    <div class="ttest-row"><span class="ttest-label">t-value</span><span class="ttest-value">{{ $summary['ttest_daya']['t_value'] }}</span></div>
                                    <div class="ttest-row"><span class="ttest-label">Degrees of Freedom</span><span class="ttest-value">{{ $summary['ttest_daya']['df'] }}</span></div>
                                    <div class="ttest-row"><span class="ttest-label">Critical Value</span><span class="ttest-value">±{{ $summary['ttest_daya']['critical_value'] }}</span></div>
                                    <div class="ttest-row"><span class="ttest-label">p-value</span><span class="ttest-value">{{ $summary['ttest_daya']['p_value'] }}</span></div>
                                    <span class="significance-badge @if($summary['ttest_daya']['is_significant']) significance-yes @else significance-no @endif">
                                        @if($summary['ttest_daya']['is_significant'])
                                            <i class="fas fa-check-circle"></i> Signifikan
                                        @else
                                            <i class="fas fa-times-circle"></i> Tidak Signifikan
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="no-data">
                                <i class="fas fa-circle-info"></i>
                                <p>{{ $summary['ttest_daya']['message'] ?? 'Data daya belum cukup untuk analisis statistik.' }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if(!$isEmbeddedDashboard)
            @php
                $basePath = rtrim(request()->getBaseUrl(), '/');
                $simulationPath = ($basePath !== '' ? $basePath : '') . '/simulation';
                $docPath = ($basePath !== '' ? $basePath : '') . '/doc';
            @endphp
            <div class="simulation-nav-wrap">
                <a href="{{ $simulationPath }}" class="simulation-nav-card">
                    <div>
                        <h3 class="simulation-nav-title"><i class="fas fa-vial-circle-check"></i> Mode Simulasi Keseluruhan Aplikasi</h3>
                        <p class="simulation-nav-desc">
                            Buka halaman simulasi untuk meniru alur end-to-end MQTT vs HTTP secara realtime
                            (generator data, packet sequence, reliability, diagnostics, chart, dan auto-refresh dashboard).
                        </p>
                    </div>
                    <i class="fas fa-arrow-right simulation-nav-arrow" aria-hidden="true"></i>
                </a>
                <a href="{{ $docPath }}" class="simulation-nav-card docs-nav-card">
                    <div>
                        <h3 class="simulation-nav-title"><i class="fas fa-book-open"></i> Technical Docs</h3>
                        <p class="simulation-nav-desc">
                            Buka dokumentasi teknis implementasi aktual sistem (arsitektur, payload, perhitungan latency/daya/reliability,
                            t-test, struktur database, dan alur dashboard).
                        </p>
                    </div>
                    <i class="fas fa-arrow-right simulation-nav-arrow" aria-hidden="true"></i>
                </a>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text"><i class="fas fa-graduation-cap"></i> Sistem Penelitian - Analisis Komparatif MQTT vs HTTP</p>
            <p class="footer-meta">
                <span class="footer-pill footer-pill-db"><i class="fas fa-database"></i> MySQL</span>
                <span class="footer-pill footer-pill-laravel"><i class="fas fa-rocket"></i> Laravel</span>
                <span class="footer-pill footer-pill-chart"><i class="fas fa-chart-line"></i> Chart.js</span>
                <span class="footer-pill footer-pill-ttest"><i class="fas fa-ruler-combined"></i> T-Test Analysis</span>
            </p>
        </div>
    </div>

    <script id="latency-chart-data-json" type="application/json">@json($latencyChartData)</script>
    <script id="power-chart-data-json" type="application/json">@json($powerChartData)</script>

    <script>
        let latencyChartInstance = null;
        let powerChartInstance = null;
        let latestLatencyData = null;
        let latestPowerData = null;

        const latencyRuntimeState = {
            totalPoints: 0,
            totalRecords: 0,
            minWindowPoints: 1,
            maxWindowPoints: 1,
            currentWindowSpan: 10,
            lastUserActionAt: Date.now(),
            idleAutoFollowMs: 5000,
            controlsBound: false,
            autoFollowFrameId: null,
        };

        const powerRuntimeState = {
            totalPoints: 0,
            totalRecords: 0,
            minWindowPoints: 1,
            maxWindowPoints: 1,
            currentWindowSpan: 10,
            lastUserActionAt: Date.now(),
            idleAutoFollowMs: 5000,
            controlsBound: false,
            autoFollowFrameId: null,
        };

        const networkWidgetState = {
            collapsed: true,
            interactionsBound: false,
            externalProbeInFlight: false,
            externalProbeIntervalMs: 15000,
            externalProbeTimeoutMs: 7000,
            lastExternalProbeAt: 0,
        };

        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => {
                    let delay = 0;
                    if (context.type === 'data' && context.mode === 'default' && !context.dropped) {
                        delay = context.dataIndex * 100;
                    }
                    return delay;
                }
            },
            plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, cornerRadius: 8, titleFont: { size: 12, weight: 'bold' }, bodyFont: { size: 11 } } }
        };

        // Function to animate numbers from 0 to target
        function animateValue(element, targetText, duration = 800) {
            // Extract numeric value from text
            const numMatch = targetText.match(/[\d.]+/);
            if (!numMatch) return;

            const target = parseFloat(numMatch[0]);
            const suffix = targetText.replace(numMatch[0], '').trim();
            const startTime = Date.now();
            const startValue = 0;
            const hasDecimals = target % 1 !== 0;

            function update() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function for smooth animation
                const easeOutQuad = 1 - (1 - progress) * (1 - progress);
                const current = startValue + (target - startValue) * easeOutQuad;
                
                // Format number with decimals if needed
                const displayValue = hasDecimals ? current.toFixed(2) : Math.round(current);
                element.textContent = displayValue + ' ' + suffix;
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    element.textContent = targetText;
                }
            }

            update();
        }

        // Setup Intersection Observer for scroll-based animations
        function setupIntersectionObserver() {
            const observerOptions = {
                threshold: 0.05,
                rootMargin: '50px'
            };

            const valuesToAnimate = new Set();

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        if (!valuesToAnimate.has(el)) {
                            valuesToAnimate.add(el);
                            const originalText = el.textContent.trim();
                            const numMatch = originalText.match(/[\d.]+/);
                            if (numMatch) {
                                const isStat = el.classList.contains('stat-value');
                                const duration = isStat ? 1000 : 800;
                                animateValue(el, originalText, duration);
                            }
                        }
                    }
                });
            }, observerOptions);

            // Observe stat values only (not the card itself)
            document.querySelectorAll('.stat-value').forEach(el => {
                observer.observe(el);
            });

            // Observe ttest values
            document.querySelectorAll('.ttest-value').forEach(el => {
                observer.observe(el);
            });

            // Observe charts with fade-in
            document.querySelectorAll('.chart-wrapper').forEach(chart => {
                chart.classList.add('fade-in-chart');
            });
        }

        function parseChartDataFromDocument(doc, elementId) {
            const sourceDoc = doc || document;
            const sourceElement = sourceDoc.getElementById(elementId);
            if (!sourceElement) return null;

            try {
                return JSON.parse(sourceElement.textContent || '{}');
            } catch (error) {
                console.log('Failed to parse chart JSON:', error);
                return null;
            }
        }

        function getChartPayload(doc) {
            const payloadDoc = doc || document;
            const latencyData = parseChartDataFromDocument(payloadDoc, 'latency-chart-data-json') || {};
            const powerData = parseChartDataFromDocument(payloadDoc, 'power-chart-data-json') || {};

            return {
                latencyData: {
                    labels: Array.isArray(latencyData.labels) ? latencyData.labels : [],
                    time_labels: Array.isArray(latencyData.time_labels) ? latencyData.time_labels : [],
                    full_time_labels: Array.isArray(latencyData.full_time_labels) ? latencyData.full_time_labels : [],
                    datasets: Array.isArray(latencyData.datasets) ? latencyData.datasets : [],
                    total_points: Number(latencyData.total_points || (Array.isArray(latencyData.labels) ? latencyData.labels.length : 0)),
                    total_records: Number(latencyData.total_records || latencyData.total_points || (Array.isArray(latencyData.labels) ? latencyData.labels.length : 0)),
                },
                powerData: {
                    labels: Array.isArray(powerData.labels) ? powerData.labels : [],
                    time_labels: Array.isArray(powerData.time_labels) ? powerData.time_labels : [],
                    full_time_labels: Array.isArray(powerData.full_time_labels) ? powerData.full_time_labels : [],
                    mqtt: Array.isArray(powerData.mqtt) ? powerData.mqtt : [],
                    http: Array.isArray(powerData.http) ? powerData.http : [],
                    total_points: Number(powerData.total_points || (Array.isArray(powerData.labels) ? powerData.labels.length : 0)),
                    total_records: Number(powerData.total_records || powerData.total_points || (Array.isArray(powerData.labels) ? powerData.labels.length : 0)),
                }
            };
        }

        function syncElementById(newDoc, elementId, useHtml = false) {
            const currentElement = document.getElementById(elementId);
            const nextElement = newDoc.getElementById(elementId);
            if (!currentElement || !nextElement) return;

            if (useHtml) {
                if (currentElement.innerHTML !== nextElement.innerHTML) {
                    currentElement.innerHTML = nextElement.innerHTML;
                }
                return;
            }

            if (currentElement.textContent !== nextElement.textContent) {
                currentElement.textContent = nextElement.textContent;
            }
        }

        function syncElementClassAndHtml(newDoc, elementId) {
            const currentElement = document.getElementById(elementId);
            const nextElement = newDoc.getElementById(elementId);
            if (!currentElement || !nextElement) return;

            currentElement.className = nextElement.className;
            const nextTitle = nextElement.getAttribute('title');
            if (nextTitle !== null) {
                currentElement.setAttribute('title', nextTitle);
            } else {
                currentElement.removeAttribute('title');
            }
            if (currentElement.innerHTML !== nextElement.innerHTML) {
                currentElement.innerHTML = nextElement.innerHTML;
            }
        }

        function getNetworkWidgetElements() {
            return {
                widget: document.getElementById('realtimeNetworkWidget'),
                toggle: document.getElementById('networkWidgetStatus'),
                body: document.getElementById('networkWidgetBody'),
                externalRow: document.getElementById('externalNetworkRow'),
                externalPing: document.getElementById('externalPingValue'),
                externalSpeed: document.getElementById('externalSpeedValue'),
                externalStamp: document.getElementById('externalNetworkStamp'),
            };
        }

        function applyNetworkWidgetCollapsedState(collapsed) {
            const { widget, toggle } = getNetworkWidgetElements();
            if (!widget || !toggle) return;

            const shouldCollapse = collapsed === true;
            networkWidgetState.collapsed = shouldCollapse;
            widget.classList.toggle('is-collapsed', shouldCollapse);
            toggle.setAttribute('aria-expanded', shouldCollapse ? 'false' : 'true');
        }

        function bindNetworkWidgetInteractions() {
            if (networkWidgetState.interactionsBound) return;

            const { widget, toggle } = getNetworkWidgetElements();
            if (!widget || !toggle) return;

            toggle.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                applyNetworkWidgetCollapsedState(!networkWidgetState.collapsed);
            });

            document.addEventListener('pointerdown', function(event) {
                const { widget: currentWidget } = getNetworkWidgetElements();
                if (!currentWidget || networkWidgetState.collapsed) return;
                if (currentWidget.contains(event.target)) return;
                applyNetworkWidgetCollapsedState(true);
            });

            networkWidgetState.interactionsBound = true;
            applyNetworkWidgetCollapsedState(true);
        }

        function formatExternalPingLabel(pingMs) {
            if (!Number.isFinite(pingMs) || pingMs <= 0) {
                return '-';
            }

            return `${pingMs.toFixed(1)} ms`;
        }

        function formatExternalSpeedLabel(speedMbit) {
            if (!Number.isFinite(speedMbit) || speedMbit <= 0) {
                return '-';
            }

            return `${speedMbit.toFixed(3)} Mb/s`;
        }

        function getNetworkConnectionHints() {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (!connection) {
                return { rtt: null, downlink: null, effectiveType: null };
            }

            const rtt = Number.isFinite(Number(connection.rtt)) ? Number(connection.rtt) : null;
            const downlink = Number.isFinite(Number(connection.downlink)) ? Number(connection.downlink) : null;
            const effectiveType = typeof connection.effectiveType === 'string' ? connection.effectiveType : null;

            return { rtt, downlink, effectiveType };
        }

        function updateExternalNetworkUi(payload) {
            const { externalRow, externalPing, externalSpeed, externalStamp } = getNetworkWidgetElements();
            if (!externalRow || !externalPing || !externalSpeed || !externalStamp) return;

            externalPing.textContent = formatExternalPingLabel(payload.pingMs);
            externalSpeed.textContent = formatExternalSpeedLabel(payload.speedMbit);

            const hasValidData = Number.isFinite(payload.pingMs) || Number.isFinite(payload.speedMbit);
            externalRow.classList.toggle('is-online', hasValidData);
            externalRow.classList.toggle('is-offline', !hasValidData);

            externalStamp.textContent = payload.stampText || 'External: data tidak tersedia.';
        }

        async function fetchWithTimeout(url, options = {}, timeoutMs = 6000) {
            const safeTimeoutMs = Number.isFinite(timeoutMs) && timeoutMs > 0 ? timeoutMs : 6000;
            if (typeof AbortController === 'undefined') {
                return fetch(url, options);
            }

            const controller = new AbortController();
            const timeoutId = window.setTimeout(() => controller.abort(), safeTimeoutMs);

            try {
                return await fetch(url, {
                    ...options,
                    signal: controller.signal,
                });
            } finally {
                window.clearTimeout(timeoutId);
            }
        }

        async function probeExternalPingMs() {
            const targets = [
                `https://www.gstatic.com/generate_204?ts=${Date.now()}`,
                `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js?ping=${Date.now()}`,
            ];

            for (const target of targets) {
                try {
                    const startedAt = performance.now();
                    await fetchWithTimeout(target, {
                        method: 'GET',
                        mode: 'no-cors',
                        cache: 'no-store',
                    }, networkWidgetState.externalProbeTimeoutMs);
                    const elapsedMs = performance.now() - startedAt;
                    if (Number.isFinite(elapsedMs) && elapsedMs > 0) {
                        return elapsedMs;
                    }
                } catch (error) {
                    // ignore target failure and try next.
                }
            }

            return null;
        }

        async function probeExternalSpeedMbit() {
            const targets = [
                `https://speed.cloudflare.com/__down?bytes=700000&ts=${Date.now()}`,
                `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js?speed=${Date.now()}`,
            ];

            for (const target of targets) {
                try {
                    const startedAt = performance.now();
                    const response = await fetchWithTimeout(target, {
                        method: 'GET',
                        mode: 'cors',
                        cache: 'no-store',
                    }, networkWidgetState.externalProbeTimeoutMs);

                    if (!response.ok) {
                        continue;
                    }

                    const blob = await response.blob();
                    const elapsedMs = performance.now() - startedAt;
                    if (!Number.isFinite(elapsedMs) || elapsedMs <= 0 || blob.size <= 0) {
                        continue;
                    }

                    return ((blob.size * 8) / 1000000) / (elapsedMs / 1000);
                } catch (error) {
                    // ignore target failure and try next.
                }
            }

            return null;
        }

        async function updateExternalNetworkMetrics(force = false) {
            if (networkWidgetState.externalProbeInFlight) return;

            const now = Date.now();
            if (!force && document.visibilityState === 'hidden') {
                return;
            }

            if (!force && networkWidgetState.lastExternalProbeAt > 0) {
                const elapsed = now - networkWidgetState.lastExternalProbeAt;
                if (elapsed < networkWidgetState.externalProbeIntervalMs - 400) {
                    return;
                }
            }

            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                updateExternalNetworkUi({
                    pingMs: null,
                    speedMbit: null,
                    stampText: 'External: browser terdeteksi offline.',
                });
                networkWidgetState.lastExternalProbeAt = now;
                return;
            }

            networkWidgetState.externalProbeInFlight = true;
            updateExternalNetworkUi({
                pingMs: null,
                speedMbit: null,
                stampText: 'External: mengukur koneksi internet perangkat...',
            });

            try {
                const [rawPingMs, rawSpeedMbit] = await Promise.all([
                    probeExternalPingMs(),
                    probeExternalSpeedMbit(),
                ]);

                const hints = getNetworkConnectionHints();
                const pingMs = Number.isFinite(rawPingMs) ? rawPingMs : hints.rtt;
                const speedMbit = Number.isFinite(rawSpeedMbit) ? rawSpeedMbit : hints.downlink;
                const checkedAtWib = new Date().toLocaleTimeString('id-ID', {
                    hour12: false,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    timeZone: 'Asia/Jakarta',
                });
                const hintLabel = hints.effectiveType ? ` | mode ${String(hints.effectiveType).toUpperCase()}` : '';

                updateExternalNetworkUi({
                    pingMs: Number.isFinite(pingMs) ? pingMs : null,
                    speedMbit: Number.isFinite(speedMbit) ? speedMbit : null,
                    stampText: `External check WIB ${checkedAtWib}${hintLabel}`,
                });
            } catch (error) {
                updateExternalNetworkUi({
                    pingMs: null,
                    speedMbit: null,
                    stampText: 'External: pengukuran gagal (cek akses internet/browser).',
                });
            } finally {
                networkWidgetState.lastExternalProbeAt = Date.now();
                networkWidgetState.externalProbeInFlight = false;
            }
        }

        function captureOpenHelpTargets() {
            const targets = [];

            document.querySelectorAll('.ttest-help-btn[aria-expanded="true"], .card-help-btn[aria-expanded="true"]').forEach((btn) => {
                const targetId = btn.getAttribute('data-help-target');
                if (targetId) {
                    targets.push(targetId);
                }
            });

            document.querySelectorAll('.ttest-help-panel:not([hidden]), .card-help-panel:not([hidden])').forEach((panel) => {
                if (panel.id) {
                    targets.push(panel.id);
                }
            });

            return Array.from(new Set(targets));
        }

        function restoreOpenHelpTargets(targetIds) {
            if (!Array.isArray(targetIds) || targetIds.length === 0) return;

            const buttons = document.querySelectorAll('.ttest-help-btn, .card-help-btn');
            const panels = document.querySelectorAll('.ttest-help-panel, .card-help-panel');
            buttons.forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
            panels.forEach((panel) => panel.setAttribute('hidden', 'hidden'));

            targetIds.forEach((targetId) => {
                if (!targetId) return;
                const panel = document.getElementById(targetId);
                if (!panel) return;

                panel.removeAttribute('hidden');
                buttons.forEach((btn) => {
                    if (btn.getAttribute('data-help-target') === targetId) {
                        btn.setAttribute('aria-expanded', 'true');
                    }
                });
            });
        }

        function syncHeaderRealtimeData(newDoc) {
            const openHelpTargets = captureOpenHelpTargets();

            syncElementById(newDoc, 'avgSuhuValue', true);
            syncElementById(newDoc, 'avgKelembapanValue', true);
            syncElementById(newDoc, 'avgSuhuDetail', true);
            syncElementById(newDoc, 'avgKelembapanDetail', true);

            ['telemetrySourceBadge', 'esp32StatusBadge', 'mqttStatusBadge', 'httpStatusBadge'].forEach((badgeId) => {
                const currentBadge = document.getElementById(badgeId);
                const nextBadge = newDoc.getElementById(badgeId);
                if (!currentBadge || !nextBadge) return;

                currentBadge.className = nextBadge.className;
                currentBadge.innerHTML = nextBadge.innerHTML;
                const nextTitle = nextBadge.getAttribute('title');
                if (nextTitle !== null) {
                    currentBadge.setAttribute('title', nextTitle);
                } else {
                    currentBadge.removeAttribute('title');
                }
            });

            syncElementClassAndHtml(newDoc, 'networkWidgetStatus');
            syncElementClassAndHtml(newDoc, 'mqttNetworkRow');
            syncElementClassAndHtml(newDoc, 'httpNetworkRow');
            syncElementById(newDoc, 'networkWidgetStamp', true);
            applyNetworkWidgetCollapsedState(networkWidgetState.collapsed);

            const currentStatus = document.getElementById('resetStatusMessage');
            const nextStatus = newDoc.getElementById('resetStatusMessage');
            if (currentStatus && nextStatus) {
                currentStatus.textContent = nextStatus.textContent;
            } else if (!currentStatus && nextStatus) {
                const actionRow = document.querySelector('.action-row');
                if (actionRow) {
                    actionRow.insertAdjacentHTML('beforeend', nextStatus.outerHTML);
                }
            } else if (currentStatus && !nextStatus) {
                currentStatus.remove();
            }

            const currentDataWarnings = document.getElementById('dataQualityWarnings');
            const nextDataWarnings = newDoc.getElementById('dataQualityWarnings');
            if (currentDataWarnings && nextDataWarnings) {
                if (currentDataWarnings.innerHTML !== nextDataWarnings.innerHTML) {
                    currentDataWarnings.innerHTML = nextDataWarnings.innerHTML;
                }
            } else if (!currentDataWarnings && nextDataWarnings) {
                const actionRow = document.querySelector('.action-row');
                if (actionRow) {
                    actionRow.insertAdjacentHTML('afterend', nextDataWarnings.outerHTML);
                }
            } else if (currentDataWarnings && !nextDataWarnings) {
                currentDataWarnings.remove();
            }

            const currentDiagnosticsPanel = document.getElementById('protocolDiagnosticsPanel');
            const nextDiagnosticsPanel = newDoc.getElementById('protocolDiagnosticsPanel');
            if (currentDiagnosticsPanel && nextDiagnosticsPanel) {
                if (currentDiagnosticsPanel.innerHTML !== nextDiagnosticsPanel.innerHTML) {
                    currentDiagnosticsPanel.innerHTML = nextDiagnosticsPanel.innerHTML;
                }
            } else if (!currentDiagnosticsPanel && nextDiagnosticsPanel) {
                const qualityPanel = document.getElementById('protocolQualityPanel');
                if (qualityPanel) {
                    qualityPanel.insertAdjacentHTML('beforebegin', nextDiagnosticsPanel.outerHTML);
                }
            } else if (currentDiagnosticsPanel && !nextDiagnosticsPanel) {
                currentDiagnosticsPanel.remove();
            }

            const currentQualityPanel = document.getElementById('protocolQualityPanel');
            const nextQualityPanel = newDoc.getElementById('protocolQualityPanel');
            if (currentQualityPanel && nextQualityPanel) {
                const qualityOpenState = {};
                currentQualityPanel.querySelectorAll('.quality-details[data-protocol]').forEach((item) => {
                    qualityOpenState[item.dataset.protocol] = item.open;
                });

                if (currentQualityPanel.innerHTML !== nextQualityPanel.innerHTML) {
                    currentQualityPanel.innerHTML = nextQualityPanel.innerHTML;
                }

                currentQualityPanel.querySelectorAll('.quality-details[data-protocol]').forEach((item) => {
                    const protocolKey = item.dataset.protocol;
                    if (Object.prototype.hasOwnProperty.call(qualityOpenState, protocolKey)) {
                        item.open = qualityOpenState[protocolKey];
                    }
                });
            }

            restoreOpenHelpTargets(openHelpTargets);
        }

        function decorateLatencyDataset(dataset) {
            return {
                ...dataset,
                data: Array.isArray(dataset.data) ? dataset.data : [],
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: false,
            };
        }

        function getLatencyTimeLabel(pointIndex, useFull) {
            if (!latestLatencyData) return '-';
            const source = useFull ? latestLatencyData.full_time_labels : latestLatencyData.time_labels;
            if (!Array.isArray(source)) return '-';
            return source[pointIndex - 1] || '-';
        }

        function getPowerTimeLabel(pointIndex, useFull) {
            if (!latestPowerData) return '-';
            const source = useFull ? latestPowerData.full_time_labels : latestPowerData.time_labels;
            if (!Array.isArray(source)) return '-';
            return source[pointIndex - 1] || '-';
        }

        function bindHelpButtons() {
            if (document.body && document.body.dataset.helpButtonsBound === '1') return;

            document.addEventListener('click', function(event) {
                const helpBtn = event.target.closest('.ttest-help-btn, .card-help-btn');
                if (!helpBtn) return;

                if (helpBtn.closest('.quality-summary')) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                const targetId = helpBtn.getAttribute('data-help-target');
                if (!targetId) return;

                const panel = document.getElementById(targetId);
                if (!panel) return;

                const shouldOpen = panel.hasAttribute('hidden');

                document.querySelectorAll('.ttest-help-btn[aria-expanded="true"], .card-help-btn[aria-expanded="true"]').forEach((openBtn) => {
                    const openTarget = openBtn.getAttribute('data-help-target');
                    const openPanel = openTarget ? document.getElementById(openTarget) : null;
                    if (!openPanel || openBtn === helpBtn) return;
                    openPanel.setAttribute('hidden', 'hidden');
                    openBtn.setAttribute('aria-expanded', 'false');
                });

                if (shouldOpen) {
                    panel.removeAttribute('hidden');
                    helpBtn.setAttribute('aria-expanded', 'true');
                } else {
                    panel.setAttribute('hidden', 'hidden');
                    helpBtn.setAttribute('aria-expanded', 'false');
                }
            });

            if (document.body) {
                document.body.dataset.helpButtonsBound = '1';
            }
        }

        function formatChartTotalCount(value) {
            const numeric = Math.max(0, Math.round(Number(value || 0)));
            if (numeric > 9999) {
                return `${Math.floor(numeric / 1000)}K`;
            }

            return String(numeric);
        }

        function updateLatencyToolbarInfo() {
            const toolbarInfo = document.getElementById('latencyToolbarInfo');
            if (!toolbarInfo) return;

            const total = latencyRuntimeState.totalPoints;
            if (total <= 0) {
                toolbarInfo.textContent = 'Total data point: 0 | Default(min): 0 data | View saat ini: 0 data';
                return;
            }

            const minView = Math.min(latencyRuntimeState.minWindowPoints, total);
            const maxView = Math.min(latencyRuntimeState.maxWindowPoints, total);
            const currentSpan = clampValue(
                Math.round(Number(latencyRuntimeState.currentWindowSpan || minView)),
                minView,
                maxView
            );
            const totalDisplay = formatChartTotalCount(latencyRuntimeState.totalRecords || total);

            toolbarInfo.textContent = `Total data point: ${totalDisplay} | Default(min): ${minView} data | View saat ini: ${currentSpan} data`;
        }

        function markLatencyUserAction() {
            latencyRuntimeState.lastUserActionAt = Date.now();
            if (latencyRuntimeState.autoFollowFrameId) {
                cancelAnimationFrame(latencyRuntimeState.autoFollowFrameId);
                latencyRuntimeState.autoFollowFrameId = null;
            }
        }

        function isLatencyIdle() {
            return (Date.now() - latencyRuntimeState.lastUserActionAt) >= latencyRuntimeState.idleAutoFollowMs;
        }

        function clampValue(value, min, max) {
            return Math.min(max, Math.max(min, value));
        }

        function buildLatencyWindow(start, end) {
            const totalPoints = latencyRuntimeState.totalPoints;
            if (totalPoints <= 0) {
                return { start: 0, end: 0, span: 0 };
            }

            const minWindowPoints = Math.min(latencyRuntimeState.minWindowPoints, totalPoints);
            const maxWindowPoints = Math.min(latencyRuntimeState.maxWindowPoints, totalPoints);

            let normalizedStart = Math.round(Number(start));
            let normalizedEnd = Math.round(Number(end));

            if (!Number.isFinite(normalizedStart) || !Number.isFinite(normalizedEnd)) {
                normalizedEnd = totalPoints;
                normalizedStart = Math.max(1, normalizedEnd - minWindowPoints + 1);
            }

            if (normalizedStart > normalizedEnd) {
                const temp = normalizedStart;
                normalizedStart = normalizedEnd;
                normalizedEnd = temp;
            }

            const currentSpan = Math.max(1, normalizedEnd - normalizedStart + 1);
            const targetSpan = clampValue(currentSpan, minWindowPoints, maxWindowPoints);
            const center = normalizedStart + (currentSpan - 1) / 2;

            normalizedStart = Math.round(center - (targetSpan - 1) / 2);
            normalizedEnd = normalizedStart + targetSpan - 1;

            if (normalizedStart < 1) {
                normalizedEnd += 1 - normalizedStart;
                normalizedStart = 1;
            }

            if (normalizedEnd > totalPoints) {
                normalizedStart -= normalizedEnd - totalPoints;
                normalizedEnd = totalPoints;
            }

            normalizedStart = clampValue(normalizedStart, 1, totalPoints);
            normalizedEnd = clampValue(normalizedEnd, normalizedStart, totalPoints);

            return {
                start: normalizedStart,
                end: normalizedEnd,
                span: normalizedEnd - normalizedStart + 1,
            };
        }

        function updateLatencyZoomButtons(span) {
            const zoomInBtn = document.getElementById('latencyZoomIn');
            const zoomOutBtn = document.getElementById('latencyZoomOut');

            if (zoomInBtn) zoomInBtn.disabled = span <= latencyRuntimeState.minWindowPoints;
            if (zoomOutBtn) zoomOutBtn.disabled = span >= latencyRuntimeState.maxWindowPoints;
        }

        function animateLatencyWindow(currentWindow, targetWindow, durationMs) {
            if (!latencyChartInstance || !latencyChartInstance.scales || !latencyChartInstance.scales.x) return;

            if (latencyRuntimeState.autoFollowFrameId) {
                cancelAnimationFrame(latencyRuntimeState.autoFollowFrameId);
                latencyRuntimeState.autoFollowFrameId = null;
            }

            const xScale = latencyChartInstance.scales.x;
            const animationDuration = durationMs || 700;
            const startTime = performance.now();
            const easeOutCubic = (progress) => 1 - Math.pow(1 - progress, 3);

            const step = (now) => {
                if (!latencyChartInstance || !latencyChartInstance.scales || !latencyChartInstance.scales.x) {
                    latencyRuntimeState.autoFollowFrameId = null;
                    return;
                }

                const progress = Math.min((now - startTime) / animationDuration, 1);
                const eased = easeOutCubic(progress);

                const currentMin = currentWindow.start + (targetWindow.start - currentWindow.start) * eased;
                const currentMax = currentWindow.end + (targetWindow.end - currentWindow.end) * eased;

                xScale.options.min = currentMin;
                xScale.options.max = currentMax;
                latencyChartInstance.update('none');

                if (progress < 1) {
                    latencyRuntimeState.autoFollowFrameId = requestAnimationFrame(step);
                    return;
                }

                xScale.options.min = targetWindow.start;
                xScale.options.max = targetWindow.end;
                latencyChartInstance.update('none');
                latencyRuntimeState.currentWindowSpan = targetWindow.span;
                updateLatencyZoomButtons(targetWindow.span);
                updateLatencyToolbarInfo();
                latencyRuntimeState.autoFollowFrameId = null;
            };

            latencyRuntimeState.autoFollowFrameId = requestAnimationFrame(step);
        }

        function applyLatencyWindow(start, end, options) {
            if (!latencyChartInstance || !latencyChartInstance.scales || !latencyChartInstance.scales.x) return null;

            const applyOptions = options || {};
            const animate = applyOptions.animate === true;
            const targetWindow = buildLatencyWindow(start, end);
            const xScale = latencyChartInstance.scales.x;
            const currentWindow = buildLatencyWindow(xScale.min, xScale.max);

            if (currentWindow.start === targetWindow.start && currentWindow.end === targetWindow.end) {
                latencyRuntimeState.currentWindowSpan = targetWindow.span;
                updateLatencyZoomButtons(targetWindow.span);
                updateLatencyToolbarInfo();
                return targetWindow;
            }

            if (animate) {
                animateLatencyWindow(currentWindow, targetWindow, applyOptions.durationMs || 700);
                return targetWindow;
            }

            if (latencyRuntimeState.autoFollowFrameId) {
                cancelAnimationFrame(latencyRuntimeState.autoFollowFrameId);
                latencyRuntimeState.autoFollowFrameId = null;
            }

            xScale.options.min = targetWindow.start;
            xScale.options.max = targetWindow.end;
            latencyChartInstance.update('none');

            latencyRuntimeState.currentWindowSpan = targetWindow.span;
            updateLatencyZoomButtons(targetWindow.span);
            updateLatencyToolbarInfo();
            return targetWindow;
        }

        function zoomLatencyByStep(direction) {
            if (!latencyChartInstance || !latencyChartInstance.scales || !latencyChartInstance.scales.x) return;

            const current = buildLatencyWindow(latencyChartInstance.scales.x.min, latencyChartInstance.scales.x.max);
            const step = 2;
            const targetSpan = direction === 'in'
                ? Math.max(latencyRuntimeState.minWindowPoints, current.span - step)
                : Math.min(latencyRuntimeState.maxWindowPoints, current.span + step);

            const center = current.start + (current.span - 1) / 2;
            const start = Math.round(center - (targetSpan - 1) / 2);
            const end = start + targetSpan - 1;

            applyLatencyWindow(start, end, { animate: false });
        }

        function bindLatencyControls() {
            if (latencyRuntimeState.controlsBound) return;

            const zoomInBtn = document.getElementById('latencyZoomIn');
            const zoomOutBtn = document.getElementById('latencyZoomOut');
            const zoomResetBtn = document.getElementById('latencyZoomReset');
            if (!zoomInBtn || !zoomOutBtn || !zoomResetBtn) return;

            zoomInBtn.addEventListener('click', function() {
                markLatencyUserAction();
                zoomLatencyByStep('in');
            });

            zoomOutBtn.addEventListener('click', function() {
                markLatencyUserAction();
                zoomLatencyByStep('out');
            });

            zoomResetBtn.addEventListener('click', function() {
                markLatencyUserAction();
                const end = latencyRuntimeState.totalPoints;
                const span = latencyRuntimeState.minWindowPoints;
                latencyRuntimeState.currentWindowSpan = span;
                const start = Math.max(1, end - span + 1);
                applyLatencyWindow(start, end, { animate: false });
            });

            latencyRuntimeState.controlsBound = true;
        }

        function bindLatencyCanvasInteractions(canvas) {
            if (!canvas || canvas.dataset.latencyInteractionsBound === '1') return;

            ['mousedown', 'touchstart', 'pointerdown'].forEach(function(eventName) {
                canvas.addEventListener(eventName, markLatencyUserAction, { passive: true });
            });

            canvas.dataset.latencyInteractionsBound = '1';
        }

        function refreshLatencyChart(latencyData, options) {
            const refreshOptions = options || {};
            const initialLoad = refreshOptions.initialLoad === true;
            const latencyCtx = document.getElementById('latencyChart');
            if (!latencyCtx) return;

            latestLatencyData = latencyData;
            const totalPoints = Math.max(0, Number(latencyData.total_points || (latencyData.labels ? latencyData.labels.length : 0)));
            const totalRecords = Math.max(0, Number(latencyData.total_records || totalPoints));
            latencyRuntimeState.totalPoints = totalPoints;
            latencyRuntimeState.totalRecords = totalRecords;

            const defaultWindowPoints = Math.max(1, Math.min(10, Math.max(totalPoints, 1)));
            latencyRuntimeState.minWindowPoints = totalPoints > 0 ? Math.min(defaultWindowPoints, totalPoints) : 1;
            latencyRuntimeState.maxWindowPoints = totalPoints > 0
                ? Math.min(totalPoints, Math.max(latencyRuntimeState.minWindowPoints, 30))
                : 1;

            if (!Number.isFinite(latencyRuntimeState.currentWindowSpan) || latencyRuntimeState.currentWindowSpan <= 0 || initialLoad) {
                latencyRuntimeState.currentWindowSpan = latencyRuntimeState.minWindowPoints;
            }

            latencyRuntimeState.currentWindowSpan = clampValue(
                latencyRuntimeState.currentWindowSpan,
                latencyRuntimeState.minWindowPoints,
                latencyRuntimeState.maxWindowPoints
            );
            updateLatencyToolbarInfo();

            if (totalPoints === 0 || !Array.isArray(latencyData.datasets) || latencyData.datasets.length === 0) {
                if (latencyChartInstance) {
                    latencyChartInstance.destroy();
                    latencyChartInstance = null;
                }
                return;
            }

            const previousWindow = latencyChartInstance && latencyChartInstance.scales && latencyChartInstance.scales.x
                ? buildLatencyWindow(latencyChartInstance.scales.x.min, latencyChartInstance.scales.x.max)
                : null;

            const decoratedDatasets = latencyData.datasets.map(decorateLatencyDataset);

            if (!latencyChartInstance) {
                const initialEnd = totalPoints;
                const initialStart = Math.max(1, initialEnd - latencyRuntimeState.currentWindowSpan + 1);

                latencyChartInstance = new Chart(latencyCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        datasets: decoratedDatasets
                    },
                    options: {
                        ...chartOptions,
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, title: { display: true, text: 'Latency (ms)' } },
                            x: {
                                type: 'linear',
                                grid: { display: false },
                                title: { display: true, text: 'Urutan Data (WIB - Surabaya)' },
                                min: initialStart,
                                max: initialEnd,
                                ticks: {
                                    autoSkip: true,
                                    maxTicksLimit: 6,
                                    callback: function(value) {
                                        const pointIndex = Math.round(value);
                                        if (pointIndex < 1 || pointIndex > latencyRuntimeState.totalPoints) return '';
                                        return getLatencyTimeLabel(pointIndex, false);
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                padding: 12,
                                cornerRadius: 8,
                                titleFont: { size: 12, weight: 'bold' },
                                bodyFont: { size: 11 },
                                callbacks: {
                                    title: function(items) {
                                        const firstItem = items && items.length ? items[0] : null;
                                        const parsedX = firstItem && firstItem.parsed ? firstItem.parsed.x : 0;
                                        const pointIndex = Math.round(parsedX || 0);
                                        return `Data #${pointIndex} | ${getLatencyTimeLabel(pointIndex, true)}`;
                                    },
                                    label: function(context) {
                                        const device = context.raw && context.raw.device ? context.raw.device : '';
                                        return `${context.dataset.label}: ${context.parsed.y} ms (${device})`;
                                    }
                                }
                            },
                            zoom: {
                                pan: {
                                    enabled: true,
                                    mode: 'x',
                                    threshold: 6,
                                    onPanStart: function() {
                                        markLatencyUserAction();
                                    },
                                    onPanComplete: function({chart}) {
                                        markLatencyUserAction();
                                        applyLatencyWindow(chart.scales.x.min, chart.scales.x.max, { animate: false });
                                    }
                                },
                                zoom: {
                                    mode: 'x',
                                    wheel: { enabled: false },
                                    pinch: { enabled: false },
                                    drag: { enabled: false }
                                },
                                limits: {
                                    x: { min: 1, max: totalPoints, minRange: latencyRuntimeState.minWindowPoints }
                                }
                            }
                        },
                        animation: false
                    }
                });

                bindLatencyControls();
                bindLatencyCanvasInteractions(latencyCtx);
                applyLatencyWindow(initialStart, initialEnd, { animate: false });
                return;
            }

            latencyChartInstance.data.datasets = decoratedDatasets;
            latencyChartInstance.options.scales.x.max = totalPoints;
            latencyChartInstance.options.plugins.zoom.limits.x.max = totalPoints;
            latencyChartInstance.options.plugins.zoom.limits.x.minRange = latencyRuntimeState.minWindowPoints;
            latencyChartInstance.update('none');

            const preservedSpan = previousWindow ? previousWindow.span : latencyRuntimeState.currentWindowSpan;
            latencyRuntimeState.currentWindowSpan = clampValue(
                preservedSpan,
                latencyRuntimeState.minWindowPoints,
                latencyRuntimeState.maxWindowPoints
            );

            const shouldFollowLatest = initialLoad || isLatencyIdle();
            if (shouldFollowLatest) {
                const followEnd = totalPoints;
                const followStart = Math.max(1, followEnd - latencyRuntimeState.currentWindowSpan + 1);
                applyLatencyWindow(followStart, followEnd, { animate: !initialLoad, durationMs: 650 });
            } else if (previousWindow) {
                const preservedStart = previousWindow.start;
                const preservedEnd = preservedStart + latencyRuntimeState.currentWindowSpan - 1;
                applyLatencyWindow(preservedStart, preservedEnd, { animate: false });
            } else {
                const fallbackEnd = totalPoints;
                const fallbackStart = Math.max(1, fallbackEnd - latencyRuntimeState.currentWindowSpan + 1);
                applyLatencyWindow(fallbackStart, fallbackEnd, { animate: false });
            }

            bindLatencyControls();
            bindLatencyCanvasInteractions(latencyCtx);
        }

        function updatePowerToolbarInfo() {
            const toolbarInfo = document.getElementById('powerToolbarInfo');
            if (!toolbarInfo) return;

            const total = powerRuntimeState.totalPoints;
            if (total <= 0) {
                toolbarInfo.textContent = 'Total data point: 0 | Default(min): 0 data | View saat ini: 0 data';
                return;
            }

            const minView = Math.min(powerRuntimeState.minWindowPoints, total);
            const maxView = Math.min(powerRuntimeState.maxWindowPoints, total);
            const currentSpan = clampValue(
                Math.round(Number(powerRuntimeState.currentWindowSpan || minView)),
                minView,
                maxView
            );
            const totalDisplay = formatChartTotalCount(powerRuntimeState.totalRecords || total);

            toolbarInfo.textContent = `Total data point: ${totalDisplay} | Default(min): ${minView} data | View saat ini: ${currentSpan} data`;
        }

        function markPowerUserAction() {
            powerRuntimeState.lastUserActionAt = Date.now();
            if (powerRuntimeState.autoFollowFrameId) {
                cancelAnimationFrame(powerRuntimeState.autoFollowFrameId);
                powerRuntimeState.autoFollowFrameId = null;
            }
        }

        function isPowerIdle() {
            return (Date.now() - powerRuntimeState.lastUserActionAt) >= powerRuntimeState.idleAutoFollowMs;
        }

        function buildPowerWindow(start, end) {
            const totalPoints = powerRuntimeState.totalPoints;
            if (totalPoints <= 0) {
                return { start: 0, end: 0, span: 0 };
            }

            const minWindowPoints = Math.min(powerRuntimeState.minWindowPoints, totalPoints);
            const maxWindowPoints = Math.min(powerRuntimeState.maxWindowPoints, totalPoints);

            let normalizedStart = Math.round(Number(start));
            let normalizedEnd = Math.round(Number(end));

            if (!Number.isFinite(normalizedStart) || !Number.isFinite(normalizedEnd)) {
                normalizedEnd = totalPoints;
                normalizedStart = Math.max(1, normalizedEnd - minWindowPoints + 1);
            }

            if (normalizedStart > normalizedEnd) {
                const temp = normalizedStart;
                normalizedStart = normalizedEnd;
                normalizedEnd = temp;
            }

            const currentSpan = Math.max(1, normalizedEnd - normalizedStart + 1);
            const targetSpan = clampValue(currentSpan, minWindowPoints, maxWindowPoints);
            const center = normalizedStart + (currentSpan - 1) / 2;

            normalizedStart = Math.round(center - (targetSpan - 1) / 2);
            normalizedEnd = normalizedStart + targetSpan - 1;

            if (normalizedStart < 1) {
                normalizedEnd += 1 - normalizedStart;
                normalizedStart = 1;
            }

            if (normalizedEnd > totalPoints) {
                normalizedStart -= normalizedEnd - totalPoints;
                normalizedEnd = totalPoints;
            }

            normalizedStart = clampValue(normalizedStart, 1, totalPoints);
            normalizedEnd = clampValue(normalizedEnd, normalizedStart, totalPoints);

            return {
                start: normalizedStart,
                end: normalizedEnd,
                span: normalizedEnd - normalizedStart + 1,
            };
        }

        function updatePowerZoomButtons(span) {
            const zoomInBtn = document.getElementById('powerZoomIn');
            const zoomOutBtn = document.getElementById('powerZoomOut');

            if (zoomInBtn) zoomInBtn.disabled = span <= powerRuntimeState.minWindowPoints;
            if (zoomOutBtn) zoomOutBtn.disabled = span >= powerRuntimeState.maxWindowPoints;
        }

        function animatePowerWindow(currentWindow, targetWindow, durationMs) {
            if (!powerChartInstance || !powerChartInstance.scales || !powerChartInstance.scales.x) return;

            if (powerRuntimeState.autoFollowFrameId) {
                cancelAnimationFrame(powerRuntimeState.autoFollowFrameId);
                powerRuntimeState.autoFollowFrameId = null;
            }

            const xScale = powerChartInstance.scales.x;
            const animationDuration = durationMs || 700;
            const startTime = performance.now();
            const easeOutCubic = (progress) => 1 - Math.pow(1 - progress, 3);

            const step = (now) => {
                if (!powerChartInstance || !powerChartInstance.scales || !powerChartInstance.scales.x) {
                    powerRuntimeState.autoFollowFrameId = null;
                    return;
                }

                const progress = Math.min((now - startTime) / animationDuration, 1);
                const eased = easeOutCubic(progress);

                const currentMin = currentWindow.start + (targetWindow.start - currentWindow.start) * eased;
                const currentMax = currentWindow.end + (targetWindow.end - currentWindow.end) * eased;

                xScale.options.min = currentMin;
                xScale.options.max = currentMax;
                powerChartInstance.update('none');

                if (progress < 1) {
                    powerRuntimeState.autoFollowFrameId = requestAnimationFrame(step);
                    return;
                }

                xScale.options.min = targetWindow.start;
                xScale.options.max = targetWindow.end;
                powerChartInstance.update('none');
                powerRuntimeState.currentWindowSpan = targetWindow.span;
                updatePowerZoomButtons(targetWindow.span);
                updatePowerToolbarInfo();
                powerRuntimeState.autoFollowFrameId = null;
            };

            powerRuntimeState.autoFollowFrameId = requestAnimationFrame(step);
        }

        function applyPowerWindow(start, end, options) {
            if (!powerChartInstance || !powerChartInstance.scales || !powerChartInstance.scales.x) return null;

            const applyOptions = options || {};
            const animate = applyOptions.animate === true;
            const targetWindow = buildPowerWindow(start, end);
            const xScale = powerChartInstance.scales.x;
            const currentWindow = buildPowerWindow(xScale.min, xScale.max);

            if (currentWindow.start === targetWindow.start && currentWindow.end === targetWindow.end) {
                powerRuntimeState.currentWindowSpan = targetWindow.span;
                updatePowerZoomButtons(targetWindow.span);
                updatePowerToolbarInfo();
                return targetWindow;
            }

            if (animate) {
                animatePowerWindow(currentWindow, targetWindow, applyOptions.durationMs || 700);
                return targetWindow;
            }

            if (powerRuntimeState.autoFollowFrameId) {
                cancelAnimationFrame(powerRuntimeState.autoFollowFrameId);
                powerRuntimeState.autoFollowFrameId = null;
            }

            xScale.options.min = targetWindow.start;
            xScale.options.max = targetWindow.end;
            powerChartInstance.update('none');

            powerRuntimeState.currentWindowSpan = targetWindow.span;
            updatePowerZoomButtons(targetWindow.span);
            updatePowerToolbarInfo();
            return targetWindow;
        }

        function zoomPowerByStep(direction) {
            if (!powerChartInstance || !powerChartInstance.scales || !powerChartInstance.scales.x) return;

            const current = buildPowerWindow(powerChartInstance.scales.x.min, powerChartInstance.scales.x.max);
            const step = 2;
            const targetSpan = direction === 'in'
                ? Math.max(powerRuntimeState.minWindowPoints, current.span - step)
                : Math.min(powerRuntimeState.maxWindowPoints, current.span + step);

            const center = current.start + (current.span - 1) / 2;
            const start = Math.round(center - (targetSpan - 1) / 2);
            const end = start + targetSpan - 1;

            applyPowerWindow(start, end, { animate: false });
        }

        function bindPowerControls() {
            if (powerRuntimeState.controlsBound) return;

            const zoomInBtn = document.getElementById('powerZoomIn');
            const zoomOutBtn = document.getElementById('powerZoomOut');
            const zoomResetBtn = document.getElementById('powerZoomReset');
            if (!zoomInBtn || !zoomOutBtn || !zoomResetBtn) return;

            zoomInBtn.addEventListener('click', function() {
                markPowerUserAction();
                zoomPowerByStep('in');
            });

            zoomOutBtn.addEventListener('click', function() {
                markPowerUserAction();
                zoomPowerByStep('out');
            });

            zoomResetBtn.addEventListener('click', function() {
                markPowerUserAction();
                const end = powerRuntimeState.totalPoints;
                const span = powerRuntimeState.minWindowPoints;
                powerRuntimeState.currentWindowSpan = span;
                const start = Math.max(1, end - span + 1);
                applyPowerWindow(start, end, { animate: false });
            });

            powerRuntimeState.controlsBound = true;
        }

        function bindPowerCanvasInteractions(canvas) {
            if (!canvas || canvas.dataset.powerInteractionsBound === '1') return;

            ['mousedown', 'touchstart', 'pointerdown'].forEach(function(eventName) {
                canvas.addEventListener(eventName, markPowerUserAction, { passive: true });
            });

            canvas.dataset.powerInteractionsBound = '1';
        }

        function refreshPowerChart(powerData, options) {
            const refreshOptions = options || {};
            const initialLoad = refreshOptions.initialLoad === true;
            latestPowerData = powerData;
            const powerCtx = document.getElementById('powerChart');
            if (!powerCtx) return;

            const totalPoints = Math.max(0, Number(powerData.total_points || (Array.isArray(powerData.labels) ? powerData.labels.length : 0)));
            const totalRecords = Math.max(0, Number(powerData.total_records || totalPoints));
            powerRuntimeState.totalPoints = totalPoints;
            powerRuntimeState.totalRecords = totalRecords;

            const defaultWindowPoints = Math.max(1, Math.min(15, Math.max(totalPoints, 1)));
            powerRuntimeState.minWindowPoints = totalPoints > 0 ? Math.min(defaultWindowPoints, totalPoints) : 1;
            powerRuntimeState.maxWindowPoints = totalPoints > 0
                ? Math.min(120, totalPoints)
                : 1;

            if (!Number.isFinite(powerRuntimeState.currentWindowSpan) || powerRuntimeState.currentWindowSpan <= 0 || initialLoad) {
                powerRuntimeState.currentWindowSpan = powerRuntimeState.minWindowPoints;
            }

            powerRuntimeState.currentWindowSpan = clampValue(
                powerRuntimeState.currentWindowSpan,
                powerRuntimeState.minWindowPoints,
                powerRuntimeState.maxWindowPoints
            );
            updatePowerToolbarInfo();

            const toPowerPoints = function(series) {
                if (!Array.isArray(series)) return [];
                return series
                    .map(function(value, index) {
                        if (value === null || value === undefined || value === '') return null;
                        const numericValue = Number(value);
                        if (!Number.isFinite(numericValue)) return null;
                        return {
                            x: index + 1,
                            y: numericValue,
                        };
                    })
                    .filter(function(point) {
                        return point !== null;
                    });
            };

            const mqttPoints = toPowerPoints(powerData.mqtt);
            const httpPoints = toPowerPoints(powerData.http);
            const hasData = totalPoints > 0 && (mqttPoints.length > 0 || httpPoints.length > 0);
            if (!hasData) {
                if (powerChartInstance) {
                    powerChartInstance.destroy();
                    powerChartInstance = null;
                }
                return;
            }

            const previousWindow = powerChartInstance && powerChartInstance.scales && powerChartInstance.scales.x
                ? buildPowerWindow(powerChartInstance.scales.x.min, powerChartInstance.scales.x.max)
                : null;

            if (!powerChartInstance) {
                const initialEnd = totalPoints;
                const initialStart = Math.max(1, initialEnd - powerRuntimeState.currentWindowSpan + 1);

                powerChartInstance = new Chart(powerCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        datasets: [
                            {
                                label: 'MQTT',
                                data: mqttPoints,
                                borderColor: '#0066ff',
                                backgroundColor: 'rgba(0, 102, 255, 0.18)',
                                pointBackgroundColor: '#0066ff',
                                pointBorderColor: '#0066ff',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                tension: 0.25,
                                fill: false
                            },
                            {
                                label: 'HTTP',
                                data: httpPoints,
                                borderColor: '#00cc88',
                                backgroundColor: 'rgba(0, 204, 136, 0.18)',
                                pointBackgroundColor: '#00cc88',
                                pointBorderColor: '#00cc88',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                tension: 0.25,
                                fill: false
                            }
                        ]
                    },
                    options: {
                        ...chartOptions,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                title: { display: true, text: 'Power (mW)' }
                            },
                            x: {
                                type: 'linear',
                                min: initialStart,
                                max: initialEnd,
                                grid: { display: false },
                                title: { display: true, text: 'Urutan Data (WIB - Surabaya)' },
                                ticks: {
                                    autoSkip: true,
                                    maxTicksLimit: 6,
                                    callback: function(value) {
                                        const pointIndex = Math.round(value);
                                        const latestTotal = latestPowerData
                                            ? Number(latestPowerData.total_points || 0)
                                            : totalPoints;
                                        if (pointIndex < 1 || pointIndex > latestTotal) return '';
                                        return getPowerTimeLabel(pointIndex, false);
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                padding: 12,
                                cornerRadius: 8,
                                titleFont: { size: 12, weight: 'bold' },
                                bodyFont: { size: 11 },
                                callbacks: {
                                    title: function(items) {
                                        const firstItem = items && items.length ? items[0] : null;
                                        const parsedX = firstItem && firstItem.parsed ? firstItem.parsed.x : 0;
                                        const pointIndex = Math.round(parsedX || 0);
                                        return `Data #${pointIndex} | ${getPowerTimeLabel(pointIndex, true)}`;
                                    },
                                    label: function(context) {
                                        return `${context.dataset.label}: ${context.parsed.y} mW`;
                                    }
                                }
                            },
                            zoom: {
                                pan: {
                                    enabled: true,
                                    mode: 'x',
                                    threshold: 6,
                                    onPanStart: function() {
                                        markPowerUserAction();
                                    },
                                    onPanComplete: function({chart}) {
                                        markPowerUserAction();
                                        applyPowerWindow(chart.scales.x.min, chart.scales.x.max, { animate: false });
                                    }
                                },
                                zoom: {
                                    mode: 'x',
                                    wheel: { enabled: false },
                                    pinch: { enabled: false },
                                    drag: { enabled: false }
                                },
                                limits: {
                                    x: { min: 1, max: totalPoints, minRange: powerRuntimeState.minWindowPoints }
                                }
                            }
                        },
                        animation: false
                    }
                });
                bindPowerControls();
                bindPowerCanvasInteractions(powerCtx);
                applyPowerWindow(initialStart, initialEnd, { animate: false });
                return;
            }

            powerChartInstance.data.datasets[0].data = mqttPoints;
            powerChartInstance.data.datasets[1].data = httpPoints;
            powerChartInstance.options.scales.x.max = totalPoints;
            powerChartInstance.options.plugins.zoom.limits.x.max = totalPoints;
            powerChartInstance.options.plugins.zoom.limits.x.minRange = powerRuntimeState.minWindowPoints;
            powerChartInstance.update('none');

            const preservedSpan = previousWindow ? previousWindow.span : powerRuntimeState.currentWindowSpan;
            powerRuntimeState.currentWindowSpan = clampValue(
                preservedSpan,
                powerRuntimeState.minWindowPoints,
                powerRuntimeState.maxWindowPoints
            );

            const shouldFollowLatest = initialLoad || isPowerIdle();
            if (shouldFollowLatest) {
                const followEnd = totalPoints;
                const followStart = Math.max(1, followEnd - powerRuntimeState.currentWindowSpan + 1);
                applyPowerWindow(followStart, followEnd, { animate: !initialLoad, durationMs: 650 });
            } else if (previousWindow) {
                const preservedStart = previousWindow.start;
                const preservedEnd = preservedStart + powerRuntimeState.currentWindowSpan - 1;
                applyPowerWindow(preservedStart, preservedEnd, { animate: false });
            } else {
                const fallbackEnd = totalPoints;
                const fallbackStart = Math.max(1, fallbackEnd - powerRuntimeState.currentWindowSpan + 1);
                applyPowerWindow(fallbackStart, fallbackEnd, { animate: false });
            }

            bindPowerControls();
            bindPowerCanvasInteractions(powerCtx);
        }

        function initCharts(doc, options) {
            const payload = getChartPayload(doc || document);
            refreshLatencyChart(payload.latencyData, options || { initialLoad: true });
            refreshPowerChart(payload.powerData, options || { initialLoad: true });
        }

        function autoRefreshData() {
            const refreshUrl = new URL(window.location.href);
            refreshUrl.searchParams.set('__ts', Date.now().toString());

            fetch(refreshUrl.toString(), { cache: 'no-store' })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');

                    syncHeaderRealtimeData(newDoc);

                    // Update and animate stat values
                    document.querySelectorAll('.stat-value').forEach((el, index) => {
                        const newValue = newDoc.querySelectorAll('.stat-value')[index];
                        if (newValue && el.textContent !== newValue.textContent) {
                            animateValue(el, newValue.textContent.trim(), 800);
                        }
                    });
                    document.querySelectorAll('.stat-unit').forEach((el, index) => {
                        const newValue = newDoc.querySelectorAll('.stat-unit')[index];
                        if (newValue && el.textContent !== newValue.textContent) {
                            el.textContent = newValue.textContent;
                        }
                    });

                    // Update and animate ttest values
                    document.querySelectorAll('.ttest-value').forEach((el, index) => {
                        const newValue = newDoc.querySelectorAll('.ttest-value')[index];
                        if (newValue && el.textContent !== newValue.textContent) {
                            animateValue(el, newValue.textContent.trim(), 600);
                        }
                    });

                    // Update significance badges
                    document.querySelectorAll('.significance-badge').forEach((el, index) => {
                        const newValue = newDoc.querySelectorAll('.significance-badge')[index];
                        if (newValue && el.innerHTML !== newValue.innerHTML) {
                            el.innerHTML = newValue.innerHTML;
                            el.className = newValue.className;
                        }
                    });

                    const currentHasLatencyChart = !!document.getElementById('latencyChart');
                    const nextHasLatencyChart = !!newDoc.getElementById('latencyChart');
                    const currentHasPowerChart = !!document.getElementById('powerChart');
                    const nextHasPowerChart = !!newDoc.getElementById('powerChart');
                    const currentHasStatisticalSection = !!document.getElementById('statisticalAnalysisSection');
                    const nextHasStatisticalSection = !!newDoc.getElementById('statisticalAnalysisSection');
                    const currentTtestValueCount = document.querySelectorAll('.ttest-value').length;
                    const nextTtestValueCount = newDoc.querySelectorAll('.ttest-value').length;
                    const currentSignificanceCount = document.querySelectorAll('.significance-badge').length;
                    const nextSignificanceCount = newDoc.querySelectorAll('.significance-badge').length;

                    // Jika struktur berubah (misal habis reset / Statistical Analysis baru muncul), reload sekali agar DOM sinkron.
                    if (
                        currentHasLatencyChart !== nextHasLatencyChart ||
                        currentHasPowerChart !== nextHasPowerChart ||
                        currentHasStatisticalSection !== nextHasStatisticalSection ||
                        currentTtestValueCount !== nextTtestValueCount ||
                        currentSignificanceCount !== nextSignificanceCount
                    ) {
                        window.location.reload();
                        return;
                    }

                    // Refresh chart data terbaru; jika user idle akan auto-follow halus ke paling kanan.
                    initCharts(newDoc, { initialLoad: false });
                })
                .catch(err => console.log('Auto-refresh failed:', err));
        }

        document.addEventListener('DOMContentLoaded', () => {
            initCharts(document, { initialLoad: true });
            bindHelpButtons();
            bindNetworkWidgetInteractions();
            updateExternalNetworkMetrics(true);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    updateExternalNetworkMetrics(false);
                }
            });
              
            // Animate all stat values immediately on load
            document.querySelectorAll('.stat-value').forEach(el => {
                const originalText = el.textContent.trim();
                animateValue(el, originalText, 1000);
            });

            // Animate all ttest values immediately on load
            document.querySelectorAll('.ttest-value').forEach(el => {
                const originalText = el.textContent.trim();
                const numMatch = originalText.match(/[\d.]+/);
                if (numMatch) {
                    animateValue(el, originalText, 800);
                }
            });

            setupIntersectionObserver();
            // Auto-refresh every 5 seconds
            setInterval(autoRefreshData, 5000);
            setInterval(() => updateExternalNetworkMetrics(false), networkWidgetState.externalProbeIntervalMs);
        });
    </script>
</body>
</html>
