param(
    [switch] $NonInteractive
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$environmentPath = Join-Path $repositoryRoot '.env'
$environmentExamplePath = Join-Path $repositoryRoot '.env.example'

Set-Location -LiteralPath $repositoryRoot

if (-not (Test-Path -LiteralPath $environmentPath)) {
    Copy-Item -LiteralPath $environmentExamplePath -Destination $environmentPath
    Write-Host 'Created local .env from .env.example.'
} else {
    Write-Host 'Keeping the existing local .env file unchanged except for missing values you provide.'
}

function Get-DotEnvValue([string] $Key) {
    $line = Get-Content -LiteralPath $environmentPath -ErrorAction SilentlyContinue |
        Where-Object { $_ -match ('^' + [regex]::Escape($Key) + '=') } |
        Select-Object -First 1

    if ($null -eq $line) { return '' }

    $value = ($line -split '=', 2)[1].Trim()
    if ($value.Length -ge 2 -and $value.StartsWith('"') -and $value.EndsWith('"')) {
        return $value.Substring(1, $value.Length - 2)
    }

    return $value
}

function ConvertTo-DotEnvValue([string] $Value) {
    if ($Value -match '[\s#"\\]') {
        return '"' + $Value.Replace('\', '\\').Replace('"', '\"') + '"'
    }

    return $Value
}

function Set-DotEnvValue([string] $Key, [string] $Value) {
    $replacement = $Key + '=' + (ConvertTo-DotEnvValue $Value)
    $contents = Get-Content -LiteralPath $environmentPath -Raw
    $pattern = '(?m)^' + [regex]::Escape($Key) + '=.*$'

    if ($contents -match $pattern) {
        $contents = [regex]::Replace($contents, $pattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $replacement })
    } else {
        $contents = $contents.TrimEnd() + [Environment]::NewLine + $replacement + [Environment]::NewLine
    }

    [System.IO.File]::WriteAllText($environmentPath, $contents)
}

function Get-SetupValue([string] $Key, [string] $EnvironmentKey, [string] $Prompt, [bool] $Secret = $false) {
    $existing = Get-DotEnvValue $Key
    if (-not [string]::IsNullOrWhiteSpace($existing)) { return $existing }

    $provided = [Environment]::GetEnvironmentVariable($EnvironmentKey, 'Process')
    if ([string]::IsNullOrWhiteSpace($provided)) { $provided = [Environment]::GetEnvironmentVariable($EnvironmentKey, 'User') }
    if ([string]::IsNullOrWhiteSpace($provided)) { $provided = [Environment]::GetEnvironmentVariable($EnvironmentKey, 'Machine') }
    if (-not [string]::IsNullOrWhiteSpace($provided)) {
        Set-DotEnvValue $Key $provided
        return $provided
    }

    if ($NonInteractive -or -not [Environment]::UserInteractive) { return '' }

    if ($Secret) {
        $secureValue = Read-Host -Prompt $Prompt -AsSecureString
        $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureValue)
        try { $provided = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
        finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    } else {
        $provided = Read-Host -Prompt $Prompt
    }

    if (-not [string]::IsNullOrWhiteSpace($provided)) { Set-DotEnvValue $Key $provided }
    return $provided
}

& composer install --no-interaction

if ([string]::IsNullOrWhiteSpace((Get-DotEnvValue 'APP_KEY'))) {
    & php artisan key:generate --force --no-interaction
}

$gmailAddress = Get-SetupValue 'MAIL_USERNAME' 'ATTENDPRO_MAIL_USERNAME' 'Gmail address'
[void](Get-SetupValue 'MAIL_PASSWORD' 'ATTENDPRO_MAIL_PASSWORD' 'Gmail App Password' $true)
[void](Get-SetupValue 'GOOGLE_CLIENT_ID' 'ATTENDPRO_GOOGLE_CLIENT_ID' 'Google OAuth Client ID')
[void](Get-SetupValue 'GOOGLE_CLIENT_SECRET' 'ATTENDPRO_GOOGLE_CLIENT_SECRET' 'Google OAuth Client Secret' $true)
[void](Get-SetupValue 'GOOGLE_REDIRECT_URI' 'ATTENDPRO_GOOGLE_REDIRECT_URI' 'Google OAuth redirect URI')

if ([string]::IsNullOrWhiteSpace((Get-DotEnvValue 'MAIL_FROM_ADDRESS')) -and -not [string]::IsNullOrWhiteSpace($gmailAddress)) {
    Set-DotEnvValue 'MAIL_FROM_ADDRESS' $gmailAddress
}

& php artisan config:clear
& php artisan cache:clear

Write-Host 'Environment configuration complete.'
