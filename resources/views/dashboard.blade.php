<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Analisis Komparatif MQTT vs HTTP</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
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
            <div class="header-content">
                <h1><i class="fas fa-chart-line"></i> IoT Research System</h1>
                <p>Analisis Komparatif Protokol MQTT vs HTTP</p>
                <div class="header-subtitle">
                    <span class="header-badge"><i class="fas fa-wifi"></i> MQTT Ready</span>
                    <span class="header-badge"><i class="fas fa-globe"></i> HTTP Ready</span>
                    <span class="header-badge"><i class="fas fa-microscope"></i> T-Test Active</span>
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
                <span class="stat-unit">success rate</span>
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
                <span class="stat-unit">success rate</span>
            </div>
        </div>

        <!-- Charts Section -->
        @if($mqttTotal > 0 || $httpTotal > 0)
            <h2 class="section-title"><i class="fas fa-chart-bar"></i> Comparative Analysis</h2>
            <div class="chart-container">
                <h3 class="chart-title"><i class="fas fa-stopwatch"></i> Latency Comparison</h3>
                @if(count($latencyChartData['labels']) > 0)
                    <div class="chart-wrapper">
                        <canvas id="latencyChart"></canvas>
                    </div>
                    <div class="legend">
                        <div class="legend-item"><div class="legend-color mqtt-legend"></div><span>MQTT Protocol</span></div>
                        <div class="legend-item"><div class="legend-color http-legend"></div><span>HTTP Protocol</span></div>
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

    <script>
        let latencyChartInstance = null;
        let powerChartInstance = null;

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

        function initCharts() {
            const latencyData = @json($latencyChartData);
            if (latencyData.labels.length > 0) {
                const latencyCtx = document.getElementById('latencyChart');
                if (latencyCtx) {
                    if (latencyChartInstance) latencyChartInstance.destroy();
                    latencyChartInstance = new Chart(latencyCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: latencyData.labels,
                            datasets: [
                                { label: 'MQTT', data: latencyData.mqtt, backgroundColor: 'rgba(0, 102, 255, 0.8)', borderColor: '#0066ff', borderWidth: 2, borderRadius: 6, hoverBackgroundColor: 'rgba(0, 102, 255, 1)' },
                                { label: 'HTTP', data: latencyData.http, backgroundColor: 'rgba(0, 204, 136, 0.8)', borderColor: '#00cc88', borderWidth: 2, borderRadius: 6, hoverBackgroundColor: 'rgba(0, 204, 136, 1)' }
                            ]
                        },
                        options: { ...chartOptions, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
                    });
                }
            }

            const powerData = @json($powerChartData);
            if (powerData.labels.length > 0) {
                const powerCtx = document.getElementById('powerChart');
                if (powerCtx) {
                    if (powerChartInstance) powerChartInstance.destroy();
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
                }
            }
        }

        function autoRefreshData() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');

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

                    // Charts only animate on initial load, not on refresh
                })
                .catch(err => console.log('Auto-refresh failed:', err));
        }

        document.addEventListener('DOMContentLoaded', () => {
            initCharts();
            
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
