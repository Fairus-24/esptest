[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $projectRoot '.env'
$logDir = Join-Path $projectRoot 'storage\logs'
New-Item -ItemType Directory -Path $logDir -Force | Out-Null

function Read-DotEnv {
    param([string]$Path)

    $map = @{}
    if (-not (Test-Path $Path -PathType Leaf)) {
        return $map
    }

    Get-Content -Path $Path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) {
            return
        }

        $parts = $line.Split('=', 2)
        if ($parts.Count -ne 2) {
            return
        }

        $key = $parts[0].Trim()
        $value = $parts[1].Trim()

        if ($value.StartsWith('"') -and $value.EndsWith('"') -and $value.Length -ge 2) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        if ($value.StartsWith("'") -and $value.EndsWith("'") -and $value.Length -ge 2) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        if ($key -ne '') {
            $map[$key] = $value
        }
    }

    return $map
}

function Get-EnvValue {
    param(
        [hashtable]$Map,
        [string]$Key,
        [string]$DefaultValue
    )

    if ($Map.ContainsKey($Key) -and $Map[$Key] -ne '') {
        return [string]$Map[$Key]
    }

    return $DefaultValue
}

function To-Bool {
    param(
        [string]$Value,
        [bool]$DefaultValue = $true
    )

    if ($null -eq $Value -or $Value.Trim() -eq '') {
        return $DefaultValue
    }

    $normalized = $Value.Trim().ToLowerInvariant()
    return -not @('0', 'false', 'off', 'no').Contains($normalized)
}

function Test-TcpPort {
    param(
        [string]$TargetHost,
        [int]$Port,
        [int]$TimeoutMs = 1000
    )

    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $async = $client.BeginConnect($TargetHost, $Port, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne($TimeoutMs, $false)) {
            $client.Close()
            return $false
        }

        $client.EndConnect($async)
        $client.Close()
        return $true
    } catch {
        return $false
    }
}

function Test-HttpHealth {
    param(
        [string]$TargetHost,
        [int]$Port,
        [string]$Path
    )

    if (-not $Path.StartsWith('/')) {
        $Path = '/' + $Path
    }

    $uri = "http://$TargetHost`:$Port$Path"
    try {
        $response = Invoke-WebRequest -Uri $uri -UseBasicParsing -TimeoutSec 4
        return ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500)
    } catch {
        return $false
    }
}

function Start-DetachedProcess {
    param(
        [string]$FilePath,
        [string[]]$ArgumentList,
        [string]$WorkingDirectory,
        [string]$StdOutPath,
        [string]$StdErrPath
    )

    if (-not (Test-Path $FilePath -PathType Leaf) -and $FilePath -ne 'php' -and $FilePath -ne 'powershell.exe') {
        return $false
    }

    Start-Process `
        -FilePath $FilePath `
        -ArgumentList $ArgumentList `
        -WorkingDirectory $WorkingDirectory `
        -WindowStyle Hidden `
        -RedirectStandardOutput $StdOutPath `
        -RedirectStandardError $StdErrPath | Out-Null

    return $true
}

$envMap = Read-DotEnv -Path $envPath

$phpBinary = Get-EnvValue -Map $envMap -Key 'LARAVEL_HTTP_PHP_BINARY' -DefaultValue 'php'
$httpAutoStart = To-Bool -Value (Get-EnvValue -Map $envMap -Key 'LARAVEL_HTTP_AUTO_START' -DefaultValue 'true')
$httpHost = Get-EnvValue -Map $envMap -Key 'LARAVEL_HTTP_HOST' -DefaultValue '0.0.0.0'
$httpPort = [int](Get-EnvValue -Map $envMap -Key 'LARAVEL_HTTP_PORT' -DefaultValue '8000')
$httpHealthHost = Get-EnvValue -Map $envMap -Key 'LARAVEL_HTTP_HEALTH_HOST' -DefaultValue '127.0.0.1'
$httpHealthPath = Get-EnvValue -Map $envMap -Key 'LARAVEL_HTTP_HEALTH_PATH' -DefaultValue '/up'

$mqttAutoStart = To-Bool -Value (Get-EnvValue -Map $envMap -Key 'MQTT_AUTO_START' -DefaultValue 'true')
$mqttPort = [int](Get-EnvValue -Map $envMap -Key 'MQTT_PORT' -DefaultValue '1883')
$mqttHost = Get-EnvValue -Map $envMap -Key 'MQTT_HOST' -DefaultValue '127.0.0.1'

$mosquittoAutoStart = To-Bool -Value (Get-EnvValue -Map $envMap -Key 'MOSQUITTO_AUTO_START' -DefaultValue 'true')
$mosquittoBinary = Get-EnvValue -Map $envMap -Key 'MOSQUITTO_BINARY' -DefaultValue 'C:/Program Files/mosquitto/mosquitto.exe'
$mosquittoConfig = Get-EnvValue -Map $envMap -Key 'MOSQUITTO_CONFIG' -DefaultValue 'C:/Program Files/mosquitto/mosquitto.conf'
$mosquittoVerbose = To-Bool -Value (Get-EnvValue -Map $envMap -Key 'MOSQUITTO_VERBOSE' -DefaultValue 'true')

$stackLog = Join-Path $logDir 'iot_stack_startup.log'
Add-Content -Path $stackLog -Value ("[{0}] Startup triggered." -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))

if ($mosquittoAutoStart -and -not (Test-TcpPort -TargetHost $mqttHost -Port $mqttPort)) {
    $mosqArgs = @()
    if ($mosquittoVerbose) {
        $mosqArgs += '-v'
    }

    if ($mosquittoConfig -ne '') {
        $mosqArgs += @('-c', $mosquittoConfig)
    }

    $mosqOut = Join-Path $logDir 'mosquitto.log'
    $mosqErr = Join-Path $logDir 'mosquitto_error.log'
    if (Start-DetachedProcess -FilePath $mosquittoBinary -ArgumentList $mosqArgs -WorkingDirectory $projectRoot -StdOutPath $mosqOut -StdErrPath $mosqErr) {
        Add-Content -Path $stackLog -Value ("[{0}] Mosquitto start requested." -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
        Start-Sleep -Seconds 1
    }
}

$httpPortOpen = Test-TcpPort -TargetHost $httpHealthHost -Port $httpPort
if ($httpAutoStart -and -not $httpPortOpen -and -not (Test-HttpHealth -TargetHost $httpHealthHost -Port $httpPort -Path $httpHealthPath)) {
    $httpOut = Join-Path $logDir 'laravel_http_server.log'
    $httpErr = Join-Path $logDir 'laravel_http_server_error.log'
    $httpArgs = @('artisan', 'serve', "--host=$httpHost", "--port=$httpPort")

    if (Start-DetachedProcess -FilePath $phpBinary -ArgumentList $httpArgs -WorkingDirectory $projectRoot -StdOutPath $httpOut -StdErrPath $httpErr) {
        Add-Content -Path $stackLog -Value ("[{0}] Laravel HTTP server start requested on port {1}." -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $httpPort)
        Start-Sleep -Seconds 2
    }
}

if ($mqttAutoStart) {
    $workerRunning = $false
    try {
        $workerRunning = $null -ne (Get-CimInstance Win32_Process -ErrorAction Stop |
            Where-Object { $_.Name -match '^php(\.exe)?$' -and $_.CommandLine -like '*mqtt_worker.php*' } |
            Select-Object -First 1)
    } catch {
        $workerRunning = $false
    }

    if (-not $workerRunning) {
        $workerOut = Join-Path $logDir 'mqtt_worker.log'
        $workerErr = Join-Path $logDir 'mqtt_worker_error.log'
        if (Start-DetachedProcess -FilePath $phpBinary -ArgumentList @('mqtt_worker.php') -WorkingDirectory $projectRoot -StdOutPath $workerOut -StdErrPath $workerErr) {
            Add-Content -Path $stackLog -Value ("[{0}] MQTT worker start requested." -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
        }
    }
}

Add-Content -Path $stackLog -Value ("[{0}] Startup completed." -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
