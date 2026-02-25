[CmdletBinding()]
param(
    [string]$TaskName = 'IoTStackAutoStart_esptest',
    [switch]$ForceStartupFolder
)

$ErrorActionPreference = 'Stop'

$scriptPath = Join-Path $PSScriptRoot 'start_iot_stack.ps1'
if (-not (Test-Path $scriptPath -PathType Leaf)) {
    throw "Startup script not found: $scriptPath"
}

function Invoke-QuietCmd {
    param([string]$Command)

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    cmd.exe /c $Command > $null 2> $null
    $exitCode = $LASTEXITCODE
    $ErrorActionPreference = $previous

    return $exitCode
}

$taskCommand = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$scriptPath`""
$registered = $false

if (-not $ForceStartupFolder) {
    $bootTaskName = "$TaskName`_boot"
    $bootCreateCommand = "schtasks /Create /TN `"$bootTaskName`" /SC ONSTART /RU SYSTEM /TR `"$taskCommand`" /F"
    $bootRunCommand = "schtasks /Run /TN `"$bootTaskName`""

    if ((Invoke-QuietCmd -Command $bootCreateCommand) -eq 0) {
        Invoke-QuietCmd -Command $bootRunCommand | Out-Null
        Write-Output "Task '$bootTaskName' (ONSTART/SYSTEM) berhasil dibuat."
        $registered = $true
    }
}

if (-not $registered -and -not $ForceStartupFolder) {
    $createCommand = "schtasks /Create /TN `"$TaskName`" /SC ONLOGON /TR `"$taskCommand`" /F"
    $runCommand = "schtasks /Run /TN `"$TaskName`""

    if ((Invoke-QuietCmd -Command $createCommand) -eq 0) {
        Invoke-QuietCmd -Command $runCommand | Out-Null
        Write-Output "Task '$TaskName' (ONLOGON) berhasil dibuat dan dijalankan."
        $registered = $true
    }
}

if (-not $registered) {
    $startupDir = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\Startup'
    if (-not (Test-Path $startupDir -PathType Container)) {
        throw "Startup folder tidak ditemukan: $startupDir"
    }

    $startupCmd = Join-Path $startupDir 'esptest_iot_stack_autostart.cmd'
    $cmdContent = "@echo off`r`npowershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$scriptPath`"`r`n"
    Set-Content -Path $startupCmd -Value $cmdContent -Encoding ASCII

    # Trigger once now so stack langsung aktif.
    cmd.exe /c $startupCmd | Out-Null

    Write-Output "Scheduled Task ditolak akses. Fallback Startup folder berhasil dibuat: $startupCmd"
}
