$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpBinary = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $phpBinary)) { $phpBinary = (Get-Command php).Source }
Set-Location -LiteralPath $projectRoot
Write-Host 'Abre http://127.0.0.1:8080 (Ctrl+C para detener).'
& $phpBinary -S 127.0.0.1:8080 -t public
