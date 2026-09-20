$ErrorActionPreference = 'Stop'

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$pythonRoot = Join-Path $repositoryRoot 'python_service'
$pythonExecutable = Join-Path $pythonRoot '.venv\Scripts\python.exe'
$laravelProcess = $null
$environmentPath = Join-Path $repositoryRoot '.env'
$environmentExamplePath = Join-Path $repositoryRoot '.env.example'
$viteHotPath = Join-Path $repositoryRoot 'public\hot'
$firstRun = $false

Set-Location -LiteralPath $repositoryRoot

# The Windows launcher serves the production Vite build. A stale hot file would
# incorrectly point Laravel at a no-longer-running `npm run dev` server.
if (Test-Path -LiteralPath $viteHotPath) {
    Remove-Item -LiteralPath $viteHotPath -Force
}

if (-not (Test-Path -LiteralPath $environmentPath)) {
    if (-not (Test-Path -LiteralPath $environmentExamplePath)) {
        throw 'Neither .env nor .env.example is available. Restore the repository environment template.'
    }

    Copy-Item -LiteralPath $environmentExamplePath -Destination $environmentPath
    $firstRun = $true
    Write-Host 'Created .env from .env.example. Review database settings in .env before the first launch if they differ from your local MySQL setup.'
}

if (-not (Test-Path -LiteralPath (Join-Path $repositoryRoot 'vendor\autoload.php'))) {
    $composerExecutable = (Get-Command composer -ErrorAction Stop).Source
    Write-Host 'Installing Laravel dependencies...'
    & $composerExecutable install --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) {
        throw 'Laravel dependency installation failed.'
    }
    $firstRun = $true
}

$phpExecutable = (Get-Command php -ErrorAction Stop).Source

if ($firstRun) {
    Write-Host 'Preparing AttendPro for its first local launch...'
    & $phpExecutable artisan key:generate --force --no-interaction
    if ($LASTEXITCODE -ne 0) {
        throw 'Laravel application key generation failed.'
    }

    & $phpExecutable artisan migrate --seed --force --no-interaction
    if ($LASTEXITCODE -ne 0) {
        throw 'Database migration or initial seed failed. Check the database settings in .env.'
    }

    if (-not (Test-Path -LiteralPath (Join-Path $repositoryRoot 'public\build\manifest.json'))) {
        $npmExecutable = (Get-Command npm -ErrorAction Stop).Source
        Write-Host 'Building browser assets...'
        & $npmExecutable ci --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) {
            throw 'Browser dependency installation failed.'
        }
        & $npmExecutable run build
        if ($LASTEXITCODE -ne 0) {
            throw 'Browser asset build failed.'
        }
    }
}

$pythonServiceReady = Test-Path -LiteralPath $pythonExecutable
if ($pythonServiceReady) {
    & $pythonExecutable -c 'import cv2, fastapi, uvicorn' *> $null
    $backendLine = Get-Content -LiteralPath (Join-Path $repositoryRoot '.env') -ErrorAction SilentlyContinue |
        Where-Object { $_ -match '^ATTENDPRO_FACE_BACKEND=' } |
        Select-Object -Last 1
    $faceBackend = if ($backendLine) { ($backendLine -split '=', 2)[1].Trim().ToLower() } else { 'insightface' }
    $modelsReady = $true
    if ($faceBackend -notin @('dlib', 'insightface')) {
        $modelsReady = (Test-Path -LiteralPath (Join-Path $pythonRoot 'models\face_detection_yunet_2023mar.onnx')) `
            -and (Test-Path -LiteralPath (Join-Path $pythonRoot 'models\face_recognition_sface_2021dec.onnx'))
    }
    $pythonServiceReady = ($LASTEXITCODE -eq 0) -and $modelsReady
}

if (-not $pythonServiceReady) {
    Write-Host 'Preparing the Python recognition service for the first time...'
    & (Join-Path $pythonRoot 'setup.bat')
    if ($LASTEXITCODE -ne 0) {
        throw 'Python recognition setup failed.'
    }
}

& $phpExecutable artisan attendpro:recognition-setup --no-interaction
if ($LASTEXITCODE -ne 0) {
    throw 'Local Laravel/Python recognition configuration failed.'
}

function Test-LaravelReady {
    try {
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/up' -UseBasicParsing -TimeoutSec 1
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

function Test-PythonReady {
    try {
        $serviceKeyLine = Get-Content -LiteralPath (Join-Path $repositoryRoot '.env') |
            Where-Object { $_ -match '^ATTENDPRO_PYTHON_SERVICE_KEY=' } |
            Select-Object -Last 1
        $serviceKey = ($serviceKeyLine -split '=', 2)[1].Trim()
        $response = Invoke-RestMethod -Uri 'http://127.0.0.1:5001/v1/health' `
            -Headers @{ 'X-AttendPro-Service-Key' = $serviceKey; Accept = 'application/json' } `
            -TimeoutSec 1

        return $response.data.status -eq 'ready'
    } catch {
        return $false
    }
}

function Get-DotEnvValue([string] $key) {
    $line = Get-Content -LiteralPath $environmentPath -ErrorAction SilentlyContinue |
        Where-Object { $_ -match ('^' + [regex]::Escape($key) + '=') } |
        Select-Object -First 1

    if ($null -eq $line) { return '' }

    return (($line -split '=', 2)[1]).Trim().Trim('"')
}

function Test-QueueWorkerReady {
    try {
        return @(
            Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction Stop |
                Where-Object { $_.CommandLine -match 'artisan\s+queue:work' }
        ).Count -gt 0
    } catch {
        return $false
    }
}

if (-not (Test-LaravelReady)) {
    $laravelProcess = Start-Process -FilePath $phpExecutable `
        -ArgumentList @('-d', 'post_max_size=64M', '-d', 'upload_max_filesize=8M', '-d', 'max_file_uploads=30', 'artisan', 'serve', '--host=127.0.0.1', '--port=8000') `
        -WorkingDirectory $repositoryRoot `
        -WindowStyle Hidden `
        -PassThru

    foreach ($attempt in 1..40) {
        if (Test-LaravelReady) { break }
        Start-Sleep -Milliseconds 250
    }

    if (-not (Test-LaravelReady)) {
        throw 'Laravel did not become ready at http://127.0.0.1:8000.'
    }
}

$queueConnection = (Get-DotEnvValue 'QUEUE_CONNECTION').ToLower()
if ($queueConnection -ne '' -and $queueConnection -ne 'sync' -and -not (Test-QueueWorkerReady)) {
    Start-Process -FilePath $phpExecutable `
        -ArgumentList @('artisan', 'queue:work', '--sleep=3', '--tries=3', '--timeout=60') `
        -WorkingDirectory $repositoryRoot `
        -WindowStyle Hidden | Out-Null
    Write-Host 'Started the background queue worker for attendance emails.'
}

Write-Host 'AttendPro is ready at http://127.0.0.1:8000/recognition'
Start-Process 'http://127.0.0.1:8000/recognition'

if (Test-PythonReady) {
    Write-Host 'The Python recognition service is already running at http://127.0.0.1:5001.'
    exit 0
}

try {
    Set-Location -LiteralPath $pythonRoot
    & $pythonExecutable -m attendpro_recognition
} finally {
    if ($null -ne $laravelProcess -and -not $laravelProcess.HasExited) {
        Stop-Process -Id $laravelProcess.Id
    }
}
