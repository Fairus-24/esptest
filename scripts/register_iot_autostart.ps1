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

$taskCommand = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$scriptPath`""
$registered = $false

if (-not $ForceStartupFolder) {
    $createCommand = "schtasks /Create /TN `"$TaskName`" /SC ONLOGON /TR `"$taskCommand`" /F"
    $runCommand = "schtasks /Run /TN `"$TaskName`""

    cmd.exe /c $createCommand | Out-Null
    if ($LASTEXITCODE -eq 0) {
        cmd.exe /c $runCommand | Out-Null
        Write-Output "Task '$TaskName' berhasil dibuat dan dijalankan."
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
