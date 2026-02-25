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
            --bg-light: #f4f7fb;
            --text-dark: #111827;
            --text-light: #667085;
            --shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            --shadow-hover: 0 22px 48px rgba(15, 23, 42, 0.14);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            background:
                radial-gradient(1100px 560px at 4% -8%, rgba(21, 94, 239, 0.22), transparent 60%),
                radial-gradient(900px 500px at 96% 0%, rgba(16, 185, 129, 0.16), transparent 55%),
                linear-gradient(165deg, #f2f6ff 0%, #f3fff9 45%, #f6f7fb 100%);
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
            color: #0f172a;
            text-transform: none;
            letter-spacing: 0;
            text-align: left;
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

        .reset-form {
            margin: 0;
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
        }

        .quality-card {
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.72);
            padding: 10px 12px;
        }

        .quality-card h4 {
            font-size: 0.88rem;
            color: #0f172a;
            margin-bottom: 8px;
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

        .footer {
            color: #334155;
            border-top-color: rgba(148, 163, 184, 0.34);
        }

        .footer-meta {
            color: #64748b;
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
            .reset-status {
                width: 100%;
                justify-content: center;
                text-align: center;
            }

            .chart-wrapper {
                min-height: 220px;
                height: 250px;
            }
        }
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
                    <div class="metric-value" id="avgSuhuValue">{{ number_format((float) ($avgSuhu ?? 0), 2) }}<span class="metric-unit">C</span></div>
                    <div class="metric-detail" id="avgSuhuDetail">
                        MQTT: {{ number_format((float) ($mqttAvgSuhu ?? 0), 2) }} C<br>
                        HTTP: {{ number_format((float) ($httpAvgSuhu ?? 0), 2) }} C
                    </div>
                </div>
                <div class="header-center-content">
                    <h1><i class="fas fa-chart-line"></i> IoT Research System</h1>
                    <p>Analisis Komparatif Protokol MQTT vs HTTP</p>
                    <div class="header-subtitle">
                        <span id="mqttStatusBadge" class="header-badge status-badge {{ $mqttConnected ? 'is-online' : 'is-offline' }}">
                            <i class="fas fa-wifi"></i> MQTT {{ $mqttConnected ? 'Connected' : 'Disconnected' }}
                        </span>
                        <span id="httpStatusBadge" class="header-badge status-badge {{ $httpConnected ? 'is-online' : 'is-offline' }}">
                            <i class="fas fa-globe"></i> HTTP {{ $httpConnected ? 'Connected' : 'Disconnected' }}
                        </span>
                        <span class="header-badge"><i class="fas fa-microscope"></i> T-Test Active</span>
                    </div>
                </div>
                <div class="header-metric-card kelembapan-card">
                    <div class="metric-icon"><i class="fas fa-tint"></i></div>
                    <div class="metric-label">Rata-rata Kelembapan</div>
                    <div class="metric-value" id="avgKelembapanValue">{{ number_format((float) ($avgKelembapan ?? 0), 2) }}<span class="metric-unit">%</span></div>
                    <div class="metric-detail" id="avgKelembapanDetail">
                        MQTT: {{ number_format((float) ($mqttAvgKelembapan ?? 0), 2) }}%<br>
                        HTTP: {{ number_format((float) ($httpAvgKelembapan ?? 0), 2) }}%
                    </div>
                </div>
            </div>
        </div>
        <!-- Statistics Cards -->
        <h2 class="section-title"><i class="fas fa-tachometer-alt"></i> Real-Time Metrics</h2>
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card mqtt-color">
                <div class="stat-icon mqtt"><i class="fas fa-broadcast-tower"></i></div>
                <span class="stat-label">MQTT - Total Data</span>
                <span class="stat-value">{{ $summary['mqtt']['total_data'] }}</span>
                <span class="stat-unit">data points</span>
            </div>
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
                <span class="stat-unit">seq {{ $reliability['mqtt_expected_packets'] > 0 ? ($reliability['mqtt_received_packets'] . '/' . $reliability['mqtt_expected_packets']) : '-' }} | complete {{ $reliability['mqtt_data_completeness'] }}% | tx {{ $reliability['mqtt_transmission_health'] ?? 0 }}%</span>
            </div>
            <div class="stat-card http-color">
                <div class="stat-icon http"><i class="fas fa-server"></i></div>
                <span class="stat-label">HTTP - Total Data</span>
                <span class="stat-value">{{ $summary['http']['total_data'] }}</span>
                <span class="stat-unit">data points</span>
            </div>
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
                <span class="stat-unit">seq {{ $reliability['http_expected_packets'] > 0 ? ($reliability['http_received_packets'] . '/' . $reliability['http_expected_packets']) : '-' }} | complete {{ $reliability['http_data_completeness'] }}% | tx {{ $reliability['http_transmission_health'] ?? 0 }}%</span>
            </div>
        </div>
        <div class="action-row">
            <form id="resetEksperimenForm" class="reset-form" method="POST" action="{{ route('reset.data') }}">
                @csrf
                <button type="submit" class="reset-btn"><i class="fas fa-trash-alt"></i> Reset Data Eksperimen</button>
            </form>
            @if(session('status'))
                <span id="resetStatusMessage" class="reset-status">{{ session('status') }}</span>
            @endif
        </div>
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
        <div id="protocolQualityPanel" class="quality-grid">
            @foreach($fieldCompleteness as $protocol => $protocolMeta)
                <div class="quality-card">
                    <h4>{{ $protocol }} Field Completeness ({{ $protocolMeta['total'] }} data)</h4>
                    @if($protocolMeta['total'] === 0)
                        <div class="quality-row">
                            <span>Belum ada data untuk validasi.</span>
                        </div>
                    @else
                        @foreach($protocolMeta['fields'] as $fieldMeta)
                            <div class="quality-row">
                                <span>{{ $fieldMeta['label'] }}</span>
                                <span class="quality-badge {{ $fieldMeta['missing'] > 0 ? 'quality-bad' : 'quality-good' }}">
                                    {{ $fieldMeta['valid'] }}/{{ $fieldMeta['total'] }}
                                </span>
                            </div>
                        @endforeach
                    @endif
                </div>
            @endforeach
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
                    <div class="chart-hint">Menampilkan urutan data terbaru per titik data agar variasi daya real-time terlihat jelas.</div>
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
            <div class="chart-container"><div class="no-data"><i class="fas fa-database"></i><p>Belum ada data</p></div></div>
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
                            <div class="ttest-row"><span class="ttest-label">Mean (mu)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['mean'] }} ms</span></div>
                            <div class="ttest-row"><span class="ttest-label">Std Deviation (sigma)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['std_dev'] }} ms</span></div>
                            <div class="ttest-row"><span class="ttest-label">Variance (sigma^2)</span><span class="ttest-value">{{ $summary['ttest_latency']['data1']['variance'] }}</span></div>
                        </div>
                        <div class="ttest-card http-card">
                            <div class="ttest-card-header"><i class="fas fa-server"></i> HTTP Protocol</div>
                            <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['n'] }}</span></div>
                            <div class="ttest-row"><span class="ttest-label">Mean (mu)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['mean'] }} ms</span></div>
                            <div class="ttest-row"><span class="ttest-label">Std Deviation (sigma)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['std_dev'] }} ms</span></div>
                            <div class="ttest-row"><span class="ttest-label">Variance (sigma^2)</span><span class="ttest-value">{{ $summary['ttest_latency']['data2']['variance'] }}</span></div>
                        </div>
                        <div class="ttest-card result-card">
                            <div class="ttest-card-header"><i class="fas fa-flask-vial"></i> T-Test Results</div>
                            <div class="ttest-row"><span class="ttest-label">t-value</span><span class="ttest-value">{{ $summary['ttest_latency']['t_value'] }}</span></div>
                            <div class="ttest-row"><span class="ttest-label">Degrees of Freedom</span><span class="ttest-value">{{ $summary['ttest_latency']['df'] }}</span></div>
                            <div class="ttest-row"><span class="ttest-label">Critical Value</span><span class="ttest-value">+/-{{ $summary['ttest_latency']['critical_value'] }}</span></div>
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
                                <div class="ttest-row"><span class="ttest-label">Mean (mu)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['mean'] }} mW</span></div>
                                <div class="ttest-row"><span class="ttest-label">Std Deviation (sigma)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['std_dev'] }} mW</span></div>
                                <div class="ttest-row"><span class="ttest-label">Variance (sigma^2)</span><span class="ttest-value">{{ $summary['ttest_daya']['data1']['variance'] }}</span></div>
                            </div>
                            <div class="ttest-card http-card">
                                <div class="ttest-card-header"><i class="fas fa-server"></i> HTTP Protocol</div>
                                <div class="ttest-row"><span class="ttest-label">Sample Size (N)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['n'] }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Mean (mu)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['mean'] }} mW</span></div>
                                <div class="ttest-row"><span class="ttest-label">Std Deviation (sigma)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['std_dev'] }} mW</span></div>
                                <div class="ttest-row"><span class="ttest-label">Variance (sigma^2)</span><span class="ttest-value">{{ $summary['ttest_daya']['data2']['variance'] }}</span></div>
                            </div>
                            <div class="ttest-card result-card">
                                <div class="ttest-card-header"><i class="fas fa-flask-vial"></i> T-Test Results</div>
                                <div class="ttest-row"><span class="ttest-label">t-value</span><span class="ttest-value">{{ $summary['ttest_daya']['t_value'] }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Degrees of Freedom</span><span class="ttest-value">{{ $summary['ttest_daya']['df'] }}</span></div>
                                <div class="ttest-row"><span class="ttest-label">Critical Value</span><span class="ttest-value">+/-{{ $summary['ttest_daya']['critical_value'] }}</span></div>
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
        @endif

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text"><i class="fas fa-graduation-cap"></i> Sistem Penelitian - Analisis Komparatif MQTT vs HTTP</p>
            <p class="footer-meta">MySQL | Laravel | Chart.js | T-Test Analysis</p>
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
                    time_labels: Array.isArray(powerData.time_labels) ? powerData.time_labels : [],
                    full_time_labels: Array.isArray(powerData.full_time_labels) ? powerData.full_time_labels : [],
                    mqtt: Array.isArray(powerData.mqtt) ? powerData.mqtt : [],
                    http: Array.isArray(powerData.http) ? powerData.http : [],
                    total_points: Number(powerData.total_points || (Array.isArray(powerData.labels) ? powerData.labels.length : 0)),
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

        function syncHeaderRealtimeData(newDoc) {
            syncElementById(newDoc, 'avgSuhuValue', true);
            syncElementById(newDoc, 'avgKelembapanValue', true);
            syncElementById(newDoc, 'avgSuhuDetail', true);
            syncElementById(newDoc, 'avgKelembapanDetail', true);

            ['mqttStatusBadge', 'httpStatusBadge'].forEach((badgeId) => {
                const currentBadge = document.getElementById(badgeId);
                const nextBadge = newDoc.getElementById(badgeId);
                if (!currentBadge || !nextBadge) return;

                currentBadge.className = nextBadge.className;
                currentBadge.innerHTML = nextBadge.innerHTML;
            });

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

            const currentQualityPanel = document.getElementById('protocolQualityPanel');
            const nextQualityPanel = newDoc.getElementById('protocolQualityPanel');
            if (currentQualityPanel && nextQualityPanel) {
                if (currentQualityPanel.innerHTML !== nextQualityPanel.innerHTML) {
                    currentQualityPanel.innerHTML = nextQualityPanel.innerHTML;
                }
            }
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

            const totalPoints = Math.max(0, Number(powerData.total_points || (Array.isArray(powerData.labels) ? powerData.labels.length : 0)));
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

            const defaultWindow = Math.min(30, totalPoints);
            const windowStart = Math.max(1, totalPoints - defaultWindow + 1);
            const windowEnd = totalPoints;

            if (!powerChartInstance) {
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
                                min: windowStart,
                                max: windowEnd,
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
                                    threshold: 6
                                },
                                zoom: {
                                    mode: 'x',
                                    wheel: { enabled: false },
                                    pinch: { enabled: false },
                                    drag: { enabled: false }
                                },
                                limits: {
                                    x: { min: 1, max: totalPoints, minRange: Math.min(5, totalPoints) }
                                }
                            }
                        },
                        animation: false
                    }
                });
                return;
            }

            powerChartInstance.data.datasets[0].data = mqttPoints;
            powerChartInstance.data.datasets[1].data = httpPoints;
            powerChartInstance.options.scales.x.max = totalPoints;
            powerChartInstance.options.scales.x.min = Math.max(1, totalPoints - Math.min(30, totalPoints) + 1);
            powerChartInstance.options.plugins.zoom.limits.x.max = totalPoints;
            powerChartInstance.options.plugins.zoom.limits.x.minRange = Math.min(5, totalPoints);
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
