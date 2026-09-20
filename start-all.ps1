$ErrorActionPreference = 'Stop'

# AttendPro - run Laravel + Python + Expo at the same time.
# NOTE: port 8000 is unusable on this PC (a dead process left a phantom
# listener on 127.0.0.1:8000 that swallows connections), so Laravel runs on 8002.
# Phone API base URL: http://<LAN-IP>:8002/api/v1
# - Python service  : http://127.0.0.1:5001 (called by Laravel, PC only)
# - Expo dev server : scan QR with Expo Go (phone must be on the same Wi-Fi)

$rnRoot = $PSScriptRoot
$expoRoot = Join-Path $rnRoot 'attendpro'
$laravelRoot = $rnRoot
$pythonServiceRoot = Join-Path $laravelRoot 'python_service'
$pythonExe = Join-Path $pythonServiceRoot '.venv\Scripts\python.exe'

if (-not (Test-Path -LiteralPath $expoRoot)) {
    throw "Expo app not found at: $expoRoot"
}
if (-not (Test-Path -LiteralPath (Join-Path $laravelRoot 'artisan'))) {
    throw "Laravel backend not found at: $laravelRoot"
}

$phpExe = (Get-Command php -ErrorAction Stop).Source

$lanIp = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -like '192.168.*' -or $_.IPAddress -like '10.*' } |
    Select-Object -First 1 -ExpandProperty IPAddress)
if (-not $lanIp) { $lanIp = '<YOUR-PC-LAN-IP>' }

# 1. Laravel backend (0.0.0.0 so a real phone on Wi-Fi can reach it).
Start-Process -FilePath $phpExe `
    -ArgumentList @('artisan', 'serve', '--host=0.0.0.0', '--port=8002') `
    -WorkingDirectory $laravelRoot

# 2. Python face-recognition service (localhost only; Laravel calls it).
if (Test-Path -LiteralPath $pythonExe) {
    Start-Process -FilePath $pythonExe `
        -ArgumentList @('-m', 'attendpro_recognition') `
        -WorkingDirectory $pythonServiceRoot
} else {
    Write-Warning "Python venv not found at: $pythonExe"
    Write-Warning 'Start it manually: cd python_service; call setup.bat; call start.bat'
}

# 3. Expo dev server for the React Native app.
Start-Process -FilePath 'powershell.exe' `
    -ArgumentList @('-NoExit', '-Command', 'npx expo start') `
    -WorkingDirectory $expoRoot

Write-Host ''
Write-Host 'All three starting in separate windows:'
Write-Host "  Laravel (PC)    : http://127.0.0.1:8002"
Write-Host "  Laravel (phone) : http://${lanIp}:8002/api/v1/health"
Write-Host '  Python (PC)     : http://127.0.0.1:5001'
Write-Host '  Expo            : scan the QR in Expo Go (same Wi-Fi as this PC)'
Write-Host ''
Write-Host 'Phone API base URL for the Expo app:'
Write-Host "  http://${lanIp}:8002/api/v1"
