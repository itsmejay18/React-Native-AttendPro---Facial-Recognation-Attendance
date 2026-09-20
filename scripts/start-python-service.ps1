$ErrorActionPreference = 'Stop'

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$pythonRoot = Join-Path $repositoryRoot 'python_service'
$pythonExecutable = Join-Path $pythonRoot '.venv\Scripts\python.exe'
$logRoot = Join-Path $repositoryRoot 'storage\logs'

$process = Start-Process -FilePath $pythonExecutable `
    -ArgumentList @('-m', 'attendpro_recognition') `
    -WorkingDirectory $pythonRoot `
    -WindowStyle Hidden `
    -RedirectStandardOutput (Join-Path $logRoot 'python-service.log') `
    -RedirectStandardError (Join-Path $logRoot 'python-service-error.log') `
    -PassThru

Write-Output $process.Id
