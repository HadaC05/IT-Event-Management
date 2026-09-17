$ErrorActionPreference = 'Stop'
$taskRoot = $PSScriptRoot
$taskRuntime = Join-Path $taskRoot '.test-tunnel'
$taskGateway = Join-Path $taskRoot 'js\shared\test-tunnel.cjs'
$taskNode = (Get-Command node -ErrorAction Stop).Source
if (Get-NetTCPConnection -State Listen -LocalPort 8787 -ErrorAction SilentlyContinue) {
    throw 'Port 8787 is already in use. Stop the existing test tunnel first.'
}
if (-not (Get-NetTCPConnection -State Listen -LocalPort 80 -ErrorAction SilentlyContinue)) {
    throw 'Start XAMPP Apache first.'
}
if (-not (Get-NetTCPConnection -State Listen -LocalPort 3306 -ErrorAction SilentlyContinue)) {
    throw 'Start XAMPP MySQL first.'
}
New-Item -ItemType Directory -Path $taskRuntime -Force | Out-Null
$taskCloudflared = Join-Path $taskRuntime 'cloudflared.exe'
$taskRelease = Invoke-RestMethod -Uri 'https://api.github.com/repos/cloudflare/cloudflared/releases/latest' -Headers @{ 'User-Agent' = 'ITEventManagement-Test' }
$taskAsset = $taskRelease.assets | Where-Object { $_.name -eq 'cloudflared-windows-amd64.exe' } | Select-Object -First 1
if (-not $taskAsset -or $taskAsset.digest -notmatch '^sha256:[a-f0-9]{64}$') {
    throw 'Cannot verify the official download checksum.'
}
$taskVerified = $false
if (Test-Path -LiteralPath $taskCloudflared) {
    $taskHash = (Get-FileHash -LiteralPath $taskCloudflared -Algorithm SHA256).Hash.ToLowerInvariant()
    $taskVerified = ('sha256:' + $taskHash) -eq $taskAsset.digest
}
if (-not $taskVerified) {
    Write-Host 'Downloading cloudflared from the official Cloudflare GitHub release...'
    Invoke-WebRequest -Uri $taskAsset.browser_download_url -OutFile $taskCloudflared -UseBasicParsing
    $taskHash = (Get-FileHash -LiteralPath $taskCloudflared -Algorithm SHA256).Hash.ToLowerInvariant()
    if ('sha256:' + $taskHash -ne $taskAsset.digest) {
        throw 'Downloaded executable failed checksum verification. Do not run it.'
    }
}
Write-Host ('Verified cloudflared ' + $taskRelease.tag_name)
$taskProcess = Start-Process -FilePath $taskNode -ArgumentList ('"' + $taskGateway + '"') -WorkingDirectory $taskRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $taskRuntime 'gateway.log') -RedirectStandardError (Join-Path $taskRuntime 'tunnel.log') -PassThru
Write-Host ('Gateway started, PID ' + $taskProcess.Id + '. Waiting for the temporary HTTPS address...')
$taskDeadline = (Get-Date).AddSeconds(45)
do {
    Start-Sleep -Milliseconds 500
    if ($taskProcess.HasExited) { throw ('Gateway exited. Check ' + (Join-Path $taskRuntime 'tunnel.log')) }
    $taskLog = Get-Content -LiteralPath (Join-Path $taskRuntime 'tunnel.log') -Raw -ErrorAction SilentlyContinue
    $taskMatch = [regex]::Match([string]$taskLog, 'https://[a-z0-9-]+\.trycloudflare\.com')
    if ($taskMatch.Success) {
        Write-Host ('TEST LINK: ' + $taskMatch.Value + '/ITEventManagement/')
        Write-Host 'Turn OFF phone Wi-Fi and use mobile data. Allow camera/location permissions.'
        Write-Host 'Keep XAMPP and this laptop running. The gateway stops automatically after 2 hours.'
        Write-Host 'Stop sooner with: powershell -ExecutionPolicy Bypass -File .\stop-test-tunnel.ps1'
        exit 0
    }
} while ((Get-Date) -lt $taskDeadline)
throw 'No tunnel address yet. Check .test-tunnel/tunnel.log or run stop-test-tunnel.ps1.'
