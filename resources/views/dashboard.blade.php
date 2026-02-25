<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Analisis Komparatif MQTT vs HTTP</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8/hammer.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --mqtt-blue: #0066ff;
            --http-green: #00cc88;
            --accent: #ff6b6b;
            --bg-light: #f8f9fa;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --shadow-hover: 0 15px 40px rgba(0,0,0,0.15);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            background-attachment: fixed;
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

        .container {
            max-width: 1500px;
            margin: 0 auto;
        }

        /* Header Section */
        .header {
            color: white;
            margin-bottom: 50px;
            text-align: center;
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
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
            gap: 8px;
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
            border-left-color: var(--primary);
            background: linear-gradient(135deg, rgba(102,126,234,0.05), rgba(118,75,162,0.05));
        }

        .ttest-card.result-card .ttest-card-header {
            color: var(--primary);
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
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content header-metric-row">
                <div class="header-metric-card suhu-card">
                    <div class="metric-icon"><i class="fas fa-thermometer-half"></i></div>
                    <div class="metric-label">Rata-rata Suhu</div>
                    <div class="metric-value" id="avgSuhuValue">{{ number_format(($mqttAvgSuhu + $httpAvgSuhu)/2, 2) }}<span class="metric-unit">°C</span></div>
                    <div class="metric-detail">MQTT: {{ number_format($mqttAvgSuhu, 2) }}°C<br>HTTP: {{ number_format($httpAvgSuhu, 2) }}°C</div>
                </div>
                <div class="header-center-content">
                    <h1><i class="fas fa-chart-line"></i> IoT Research System</h1>
                    <p>Analisis Komparatif Protokol MQTT vs HTTP</p>
                    <div class="header-subtitle">
                        <span class="header-badge" style="background:{{ $mqttConnected ? 'rgba(0,255,0,0.2)' : 'rgba(255,0,0,0.2)' }}">
                            <i class="fas fa-wifi"></i> MQTT {{ $mqttConnected ? 'Connected' : 'Disconnected' }}
                        </span>
                        <span class="header-badge" style="background:{{ $httpConnected ? 'rgba(0,255,0,0.2)' : 'rgba(255,0,0,0.2)' }}">
                            <i class="fas fa-globe"></i> HTTP {{ $httpConnected ? 'Connected' : 'Disconnected' }}
                        </span>
                        <span class="header-badge"><i class="fas fa-microscope"></i> T-Test Active</span>
                    </div>
                </div>
                <div class="header-metric-card kelembapan-card">
                    <div class="metric-icon"><i class="fas fa-tint"></i></div>
                    <div class="metric-label">Rata-rata Kelembapan</div>
                    <div class="metric-value" id="avgKelembapanValue">{{ number_format(($mqttAvgKelembapan + $httpAvgKelembapan)/2, 2) }}<span class="metric-unit">%</span></div>
                    <div class="metric-detail">MQTT: {{ number_format($mqttAvgKelembapan, 2) }}%<br>HTTP: {{ number_format($httpAvgKelembapan, 2) }}%</div>
                </div>
            </div>
        </div>
        <style>
        /* HEADER FLEX ROW, RESPONSIVE, MOBILE STACK */
        .header-content.header-metric-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 18px;
            flex-wrap: nowrap;
            overflow-x: auto;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 25px;
            padding: 18px 6px;
        }
        .header-metric-card {
            flex: 1 1 0;
            min-width: 60px;
            max-width: 120px;
            background: rgba(255,255,255,0.18);
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 10px 4px 8px 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1.5px solid rgba(255,255,255,0.22);
            transition: box-shadow 0.3s;
            height: 100%;
        }
        .header-center-content {
            flex: 2 1 0;
            min-width: 0;
            margin: 0 8px;
            text-align: center;
            width: 100%;
        }
        .header-metric-card:hover {
            box-shadow: 0 8px 32px rgba(0,0,0,0.13);
        }
        .metric-icon {
            font-size: 1.2em;
            margin-bottom: 4px;
            color: var(--primary);
            filter: drop-shadow(0 2px 8px rgba(102,126,234,0.12));
        }
        .kelembapan-card .metric-icon { color: #00cc88; }
        .suhu-card .metric-icon { color: #ff6b6b; }
        .metric-label {
            font-size: 0.8em;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }
        .metric-value {
            font-size: 0.9em;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 2px;
            letter-spacing: -1px;
            display: flex;
            align-items: flex-end;
        }
        .kelembapan-card .metric-value { color: #00cc88; }
        .suhu-card .metric-value { color: #ff6b6b; }
        .metric-unit {
            font-size: 0.7em;
            margin-left: 4px;
            font-weight: bold;
            text-shadow: 0 1px 4px rgba(0,0,0,0.12);
        }
        .suhu-card .metric-unit {
            color: #ff6b6b !important;
        }
        .kelembapan-card .metric-unit {
            color: #00cc88 !important;
        }
        }
        .metric-detail {
            font-size: 0.7em;
            color: #fff !important;
            margin-top: 1px;
            text-align: center;
            font-weight: 600;
            text-shadow: 0 1px 6px rgba(0,0,0,0.18);
        }
        .header-center-content {
            margin: 0 8px;
            text-align: center;
            width: 100%;
        }
        @media (max-width: 900px) {
            .header-content.header-metric-row {
                gap: 10px;
                padding: 8px 2px 10px 2px;
            }
            .header-metric-card {
                max-width: 90px;
                min-width: 48px;
                padding: 5px 2px 5px 2px;
                font-size: 0.95em;
                border-radius: 10px;
            }
            .header-center-content {
                margin: 0 4px;
            }
        }
        @media (max-width: 600px) {
            .header-content.header-metric-row {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding: 6px 2px 10px 2px;
            }
            .header-metric-card {
                flex: none;
                max-width: 320px;
                min-width: 80px;
                width: 90vw;
                margin: 0 auto;
                padding: 4px 2px 4px 2px;
                font-size: 0.90em;
                border-radius: 10px;
            }
            .header-metric-card.suhu-card {
                margin-bottom: 6px;
            }
            .header-metric-card.kelembapan-card {
                margin-top: 6px;
            }
            .header-center-content {
                flex: none;
                margin: 0;
                padding: 8px 2px 8px 2px;
            }
            .header-center-content h1 {
                font-size: 1.1em;
                margin-bottom: 4px;
            }
            .header-center-content p {
                font-size: 0.92em;
                margin-bottom: 6px;
            }
            .header-subtitle {
                margin-bottom: 2px;
            }
        }
        </style>

        <!-- Statistics Cards -->
        <h2 class="section-title"><i class="fas fa-tachometer-alt"></i> Real-Time Metrics</h2>
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card mqtt-color">
                <div class="stat-icon mqtt"><i class="fas fa-broadcast-tower"></i></div>
                <span class="stat-label">MQTT - Total Data</span>
                <span class="stat-value">{{ $summary['mqtt']['total_data'] }}</span>
                <span class="stat-unit">data points</span>
            </div>
            <!-- Card Suhu & Kelembapan MQTT dihapus, sudah di header -->
            <div class="stat-card mqtt-color">
                <div class="stat-icon mqtt"><i class="fas fa-clock"></i></div>
                <span class="stat-label">MQTT - Avg Latency</span>
                <span class="stat-value">{{ $summary['mqtt']['avg_latency_ms'] }}</span>
                <span class="stat-unit">milliseconds</span>
            </div>
            <div class="stat-card mqtt-color">
                <div class="stat-icon mqtt"><i class="fas fa-bolt"></i></div>
                <span class="stat-label">MQTT - Avg Power</span>
                <span class="stat-value">{{ $summary['mqtt']['avg_daya_mw'] }}</span>
                <span class="stat-unit">milliwatts</span>
            </div>
            <div class="stat-card mqtt-color">
                <div class="stat-icon mqtt"><i class="fas fa-shield-alt"></i></div>
                <span class="stat-label">MQTT - Reliability</span>
                <span class="stat-value">{{ $reliability['mqtt_reliability'] }}%</span>
                <span class="stat-unit">success rate</span>
            </div>
            <div class="stat-card http-color">
                <div class="stat-icon http"><i class="fas fa-server"></i></div>
                <span class="stat-label">HTTP - Total Data</span>
                <span class="stat-value">{{ $summary['http']['total_data'] }}</span>
                <span class="stat-unit">data points</span>
            </div>
            <!-- Card Suhu & Kelembapan HTTP dihapus, sudah di header -->
                    <style>
                    .header-content { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
                    .header-metric-card {
                        background: rgba(255,255,255,0.18);
                        box-shadow: 0 4px 24px rgba(0,0,0,0.08);
                        border-radius: 18px;
                        padding: 32px 28px 24px 28px;
                        min-width: 220px;
                        max-width: 260px;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        border: 1.5px solid rgba(255,255,255,0.22);
                        transition: box-shadow 0.3s;
                    }
                    .header-metric-card:hover {
                        box-shadow: 0 8px 32px rgba(0,0,0,0.13);
                    }
                    .metric-icon {
                        font-size: 2.8em;
                        margin-bottom: 10px;
                        color: var(--primary);
                        filter: drop-shadow(0 2px 8px rgba(102,126,234,0.12));
                    }
                    .kelembapan-card .metric-icon { color: #00cc88; }
                    .suhu-card .metric-icon { color: #ff6b6b; }
                    .metric-label {
                        font-size: 1.1em;
                        font-weight: 600;
                        color: var(--text-dark);
                        margin-bottom: 6px;
                    }
                    .metric-value {
                        font-size: 2.2em;
                        font-weight: bold;
                        color: var(--primary);
                        margin-bottom: 6px;
                        letter-spacing: -1px;
                        display: flex;
                        align-items: flex-end;
                    }
                    .kelembapan-card .metric-value { color: #00cc88; }
                    .suhu-card .metric-value { color: #ff6b6b; }
                    .metric-unit {
                        font-size: 0.6em;
                        margin-left: 4px;
                        font-weight: bold;
                        text-shadow: 0 1px 4px rgba(0,0,0,0.12);
                    }
                    .suhu-card .metric-unit {
                        color: #ff6b6b !important;
                    }
                    .kelembapan-card .metric-unit {
                        color: #00cc88 !important;
                    }
                    .metric-detail {
                        font-size: 0.95em;
                        color: #fff !important;
                        margin-top: 2px;
                        text-align: center;
                        font-weight: 600;
                        text-shadow: 0 1px 6px rgba(0,0,0,0.18);
                    }
                    @media (max-width: 900px) {
                        .header-content { flex-direction: column; gap: 18px; }
                        .header-metric-card { min-width: 180px; max-width: 100%; padding: 22px 12px 16px 12px; }
                    }
                    </style>
                <script>
                // Animasi angka suhu & kelembapan di header
                function animateHeaderMetric(id, target, unit) {
                    const el = document.getElementById(id);
                    if (!el) return;
                    const start = 0;
                    const end = parseFloat(target);
                    const duration = 1200;
                    const startTime = performance.now();
                    function animate(now) {
                        const elapsed = now - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        const value = start + (end - start) * (1 - Math.cos(progress * Math.PI)) / 2;
                        el.innerHTML = value.toFixed(2) + '<span class="metric-unit">' + unit + '</span>';
                        if (progress < 1) requestAnimationFrame(animate);
                    }
                    requestAnimationFrame(animate);
                }
                document.addEventListener('DOMContentLoaded', function() {
                    animateHeaderMetric('avgSuhuValue', '{{ number_format(($mqttAvgSuhu + $httpAvgSuhu)/2, 2) }}', '°C');
                    animateHeaderMetric('avgKelembapanValue', '{{ number_format(($mqttAvgKelembapan + $httpAvgKelembapan)/2, 2) }}', '%');
                });
                </script>
            <div class="stat-card http-color">
                <div class="stat-icon http"><i class="fas fa-hourglass-end"></i></div>
                <span class="stat-label">HTTP - Avg Latency</span>
                <span class="stat-value">{{ $summary['http']['avg_latency_ms'] }}</span>
                <span class="stat-unit">milliseconds</span>
            </div>
            <div class="stat-card http-color">
                <div class="stat-icon http"><i class="fas fa-plug"></i></div>
                <span class="stat-label">HTTP - Avg Power</span>
                <span class="stat-value">{{ $summary['http']['avg_daya_mw'] }}</span>
                <span class="stat-unit">milliwatts</span>
            </div>
            <div class="stat-card http-color">
                <div class="stat-icon http"><i class="fas fa-check-circle"></i></div>
                <span class="stat-label">HTTP - Reliability</span>
                <span class="stat-value">{{ $reliability['http_reliability'] }}%</span>
                <span class="stat-unit">success rate</span>
            </div>
            <form id="resetEksperimenForm" method="POST" action="{{ route('reset.data') }}" style="margin-bottom: 20px;">
                @csrf
                <button type="submit" style="background:#ff6b6b;color:white;padding:10px 24px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;">Reset Data Eksperimen</button>
                @if(session('status'))<span style="margin-left:20px;color:green;font-weight:bold;">{{ session('status') }}</span>@endif
            </form>
        </div>

        <!-- Charts Section -->
        @if($mqttTotal > 0 || $httpTotal > 0)
            <h2 class="section-title"><i class="fas fa-chart-bar"></i> Comparative Analysis</h2>
            <div class="chart-container">
                <h3 class="chart-title"><i class="fas fa-stopwatch"></i> Latency Comparison</h3>
                @if(count($latencyChartData['labels']) > 0)
                    <div class="chart-toolbar">
                        <div class="chart-toolbar-info" id="latencyToolbarInfo">
                            Total data point: {{ $latencyChartData['total_points'] }} | View: {{ min(10, $latencyChartData['total_points']) }}-{{ min(30, $latencyChartData['total_points']) }} data
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
                <h3 class="chart-title"><i class="fas fa-battery-half"></i> Power Consumption Comparison</h3>
                @if(count($powerChartData['labels']) > 0)
                    <div class="chart-wrapper">
                        <canvas id="powerChart"></canvas>
                    </div>
                    <div class="legend">
                        <div class="legend-item"><div class="legend-color mqtt-legend"></div><span>MQTT Protocol</span></div>
                        <div class="legend-item"><div class="legend-color http-legend"></div><span>HTTP Protocol</span></div>
                    </div>
                @else
                    <div class="no-data"><i class="fas fa-battery-full"></i><p>Belum ada data</p></div>
                @endif
            </div>
        @else
            <div class="chart-container"><div class="no-data"><i class="fas fa-database"></i><p>📊 Belum ada data</p></div></div>
        @endif

        <!-- T-Test Results -->
        @if($summary['ttest_latency']['valid'])
            <h2 class="section-title"><i class="fas fa-flask"></i> Statistical Analysis</h2>
            <div class="ttest-section">
                <h2 class="ttest-title"><i class="fas fa-calculator"></i> Independent Sample T-Test Results</h2>
                <div class="ttest-subsection">
                    <h3>Latency Analysis</h3>
                    <div class="ttest-grid">
                        <div class="ttest-card mqtt-card">
                            <div class="ttest-card-header"><i class="fas fa-broadcast-tower"></i> MQTT Protocol</div>
                            <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['n'] }}</span></div>
                            <div class="ttest-row"><span class="ttest-label">Mean (μ)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['mean'] }} ms</span></div>
                            <div class="ttest-row"><span class="ttest-label">Std Deviation (σ)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['std_dev'] }} ms</span></div>
                            <div class="ttest-row"><span class="ttest-label">Variance (σ²)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['variance'] }}</span></div>
                        </div>
                        <div class="ttest-card http-card">
                            <div class="ttest-card-header"><i class="fas fa-server"></i> HTTP Protocol</div>
                            <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['n'] }}</span></div>
                            <div class="ttest-row"><span class="ttest-label">Mean (μ)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['mean'] }} ms</span></div>
                            <div class="ttest-row"><span class="ttest-label">Std Deviation (σ)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['std_dev'] }} ms</span></div>
                            <div class="ttest-row"><span class="ttest-label">Variance (σ²)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['variance'] }}</span></div>
                        </div>
                        <div class="ttest-card result-card">
                            <div class="ttest-card-header"><i class="fas fa-flask-vial"></i> T-Test Results</div>
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
                    <h3>Power Consumption Analysis</h3>
                    @if($summary['ttest_daya']['valid'])
                        <div class="ttest-grid">
                            <div class="ttest-card mqtt-card">
                                <div class="ttest-card-header"><i class="fas fa-broadcast-tower"></i> MQTT Protocol</div>
                                <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['n'] }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Mean (μ)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['mean'] }} mW</span></div>
                                <div class="ttest-row"><span class="ttest-label">Std Deviation (σ)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['std_dev'] }} mW</span></div>
                                <div class="ttest-row"><span class="ttest-label">Variance (σ²)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['variance'] }}</span></div>
                            </div>
                            <div class="ttest-card http-card">
                                <div class="ttest-card-header"><i class="fas fa-server"></i> HTTP Protocol</div>
                                <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['n'] }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Mean (μ)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['mean'] }} mW</span></div>
                                <div class="ttest-row"><span class="ttest-label">Std Deviation (σ)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['std_dev'] }} mW</span></div>
                                <div class="ttest-row"><span class="ttest-label">Variance (σ²)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['variance'] }}</span></div>
                            </div>
                            <div class="ttest-card result-card">
                                <div class="ttest-card-header"><i class="fas fa-flask-vial"></i> T-Test Results</div>
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
                    @endif
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text"><i class="fas fa-graduation-cap"></i> Sistem Penelitian - Analisis Komparatif MQTT vs HTTP</p>
            <p class="footer-meta">🗄️ MySQL | 🚀 Laravel | 📊 Chart.js | 📐 T-Test Analysis</p>
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
            minWindowPoints: 1,
            maxWindowPoints: 1,
            currentWindowSpan: 10,
            lastUserActionAt: Date.now(),
            idleAutoFollowMs: 5000,
            controlsBound: false,
            autoFollowFrameId: null,
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
                },
                powerData: {
                    labels: Array.isArray(powerData.labels) ? powerData.labels : [],
                    mqtt: Array.isArray(powerData.mqtt) ? powerData.mqtt : [],
                    http: Array.isArray(powerData.http) ? powerData.http : [],
                }
            };
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

        function updateLatencyToolbarInfo() {
            const toolbarInfo = document.getElementById('latencyToolbarInfo');
            if (!toolbarInfo) return;

            const total = latencyRuntimeState.totalPoints;
            const minView = Math.min(10, total);
            const maxView = Math.min(30, total);
            toolbarInfo.textContent = `Total data point: ${total} | View: ${minView}-${maxView} data`;
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
                const span = clampValue(
                    latencyRuntimeState.currentWindowSpan,
                    latencyRuntimeState.minWindowPoints,
                    latencyRuntimeState.maxWindowPoints
                );
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
            latencyRuntimeState.totalPoints = totalPoints;

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

        function refreshPowerChart(powerData) {
            latestPowerData = powerData;
            const powerCtx = document.getElementById('powerChart');
            if (!powerCtx) return;

            const hasData = powerData.labels.length > 0;
            if (!hasData) {
                if (powerChartInstance) {
                    powerChartInstance.destroy();
                    powerChartInstance = null;
                }
                return;
            }

            if (!powerChartInstance) {
                powerChartInstance = new Chart(powerCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: powerData.labels,
                        datasets: [
                            { label: 'MQTT', data: powerData.mqtt, backgroundColor: 'rgba(0, 102, 255, 0.8)', borderColor: '#0066ff', borderWidth: 2, borderRadius: 6, hoverBackgroundColor: 'rgba(0, 102, 255, 1)' },
                            { label: 'HTTP', data: powerData.http, backgroundColor: 'rgba(0, 204, 136, 0.8)', borderColor: '#00cc88', borderWidth: 2, borderRadius: 6, hoverBackgroundColor: 'rgba(0, 204, 136, 1)' }
                        ]
                    },
                    options: { ...chartOptions, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
                });
                return;
            }

            powerChartInstance.data.labels = powerData.labels;
            powerChartInstance.data.datasets[0].data = powerData.mqtt;
            powerChartInstance.data.datasets[1].data = powerData.http;
            powerChartInstance.update('none');
        }

        function initCharts(doc, options) {
            const payload = getChartPayload(doc || document);
            refreshLatencyChart(payload.latencyData, options || { initialLoad: true });
            refreshPowerChart(payload.powerData);
        }

        function autoRefreshData() {
            fetch(window.location.href, { cache: 'no-store' })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');

                    // Keep CSRF token on reset form in sync with latest session token.
                    const currentResetForm = document.getElementById('resetEksperimenForm');
                    const freshResetForm = newDoc.getElementById('resetEksperimenForm');
                    if (currentResetForm && freshResetForm) {
                        const currentTokenInput = currentResetForm.querySelector('input[name="_token"]');
                        const freshTokenInput = freshResetForm.querySelector('input[name="_token"]');
                        if (currentTokenInput && freshTokenInput && freshTokenInput.value) {
                            currentTokenInput.value = freshTokenInput.value;
                        }
                    }

                    // Update and animate stat values
                    document.querySelectorAll('.stat-value').forEach((el, index) => {
                        const newValue = newDoc.querySelectorAll('.stat-value')[index];
                        if (newValue && el.textContent !== newValue.textContent) {
                            animateValue(el, newValue.textContent.trim(), 800);
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

                    // Jika struktur berubah (misal habis reset jadi no-data), reload sekali agar DOM sinkron.
                    if (currentHasLatencyChart !== nextHasLatencyChart || currentHasPowerChart !== nextHasPowerChart) {
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
        });
    </script>
</body>
</html>
