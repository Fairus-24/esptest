[CmdletBinding()]
param(
    [double]$DurationHours = 24,
    [int]$IntervalSeconds = 60
)

$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
$logPath = Join-Path $projectRoot 'storage\logs\burnin_24h.log'
New-Item -ItemType Directory -Path (Split-Path -Parent $logPath) -Force | Out-Null

$startTime = Get-Date
$effectiveDurationHours = [math]::Max(0.05, $DurationHours)
$endTime = $startTime.AddHours($effectiveDurationHours)
$interval = [math]::Max(10, $IntervalSeconds)

$tinkerScript = @'
$r = app(App\Services\StatisticsService::class)->getReliability();
$httpRecent = App\Models\Eksperimen::query()
    ->where('created_at', '>=', now()->subMinutes(5))
    ->where('protokol', 'HTTP')
    ->count();
$mqttRecent = App\Models\Eksperimen::query()
    ->where('created_at', '>=', now()->subMinutes(5))
    ->where('protokol', 'MQTT')
    ->count();
$sharedSeq = App\Models\Eksperimen::query()
    ->where('created_at', '>=', now()->subMinutes(10))
    ->whereNotNull('sensor_read_seq')
    ->get(['sensor_read_seq', 'protokol'])
    ->groupBy('sensor_read_seq')
    ->filter(function ($rows) {
        $protocols = $rows->pluck('protokol')->unique();
        return $protocols->contains('HTTP') && $protocols->contains('MQTT');
    })
    ->count();
$latest = App\Models\Eksperimen::query()->latest('created_at')->first();
$secondsSinceLatest = null;
if ($latest) {
    $secondsSinceLatest = max(0, now()->timestamp - $latest->created_at->timestamp);
}
echo json_encode([
    'mqtt_total_sent' => $r['mqtt_total_sent'] ?? null,
    'http_total_sent' => $r['http_total_sent'] ?? null,
    'mqtt_missing_packets' => $r['mqtt_missing_packets'] ?? null,
    'http_missing_packets' => $r['http_missing_packets'] ?? null,
    'mqtt_transmission_health' => $r['mqtt_transmission_health'] ?? null,
    'http_transmission_health' => $r['http_transmission_health'] ?? null,
    'mqtt_reliability' => $r['mqtt_reliability'] ?? null,
    'http_reliability' => $r['http_reliability'] ?? null,
    'http_recent_5m' => $httpRecent,
    'mqtt_recent_5m' => $mqttRecent,
    'shared_sensor_seq_10m' => $sharedSeq,
    'max_sensor_age_ms_5m' => App\Models\Eksperimen::query()
        ->where('created_at', '>=', now()->subMinutes(5))
        ->max('sensor_age_ms'),
    'seconds_since_latest_row' => $secondsSinceLatest,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
'@

Push-Location $projectRoot
try {
    Add-Content -Path $logPath -Value ("[{0}] BURNIN_MONITOR_24H started (duration={1}h, interval={2}s)." -f $startTime.ToString('yyyy-MM-dd HH:mm:ss'), $effectiveDurationHours, $interval)

    while ((Get-Date) -lt $endTime) {
        $ts = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        $metrics = & php artisan tinker --execute $tinkerScript 2>$null
        $metrics = ($metrics | Out-String).Trim()
        if ([string]::IsNullOrWhiteSpace($metrics)) {
            $metrics = '{"error":"metrics_unavailable"}'
        }

        $mqttWorkerUp = $false
        try {
            $mqttWorkerUp = $null -ne (Get-CimInstance Win32_Process -ErrorAction Stop |
                Where-Object { $_.Name -match '^php(\.exe)?$' -and $_.CommandLine -like '*mqtt_worker.php*' } |
                Select-Object -First 1)
        } catch {
            $mqttWorkerUp = $false
        }

        $schedulerUp = $false
        try {
            $schedulerUp = $null -ne (Get-CimInstance Win32_Process -ErrorAction Stop |
                Where-Object { $_.Name -match '^php(\.exe)?$' -and $_.CommandLine -match 'artisan\s+schedule:work' } |
                Select-Object -First 1)
        } catch {
            $schedulerUp = $false
        }

        $port1883 = $false
        try {
            $port1883 = $null -ne (Get-NetTCPConnection -State Listen -LocalPort 1883 -ErrorAction Stop | Select-Object -First 1)
        } catch {
            $port1883 = $false
        }

        $port8010 = $false
        try {
            $port8010 = $null -ne (Get-NetTCPConnection -State Listen -LocalPort 8010 -ErrorAction Stop | Select-Object -First 1)
        } catch {
            $port8010 = $false
        }

        $port3306 = $false
        try {
            $port3306 = $null -ne (Get-NetTCPConnection -State Listen -LocalPort 3306 -ErrorAction Stop | Select-Object -First 1)
        } catch {
            $port3306 = $false
        }

        Add-Content -Path $logPath -Value ("[{0}] metrics={1} mqtt_worker={2} scheduler={3} port1883={4} port8010={5} port3306={6}" -f $ts, $metrics, $mqttWorkerUp, $schedulerUp, $port1883, $port8010, $port3306)
        Start-Sleep -Seconds $interval
    }

    Add-Content -Path $logPath -Value ("[{0}] BURNIN_MONITOR_24H finished." -f (Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))
} finally {
    Pop-Location
}
