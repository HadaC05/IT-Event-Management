$ErrorActionPreference = 'Stop'
$taskStateFile = Join-Path $PSScriptRoot '.test-tunnel\state.json'
if (-not (Test-Path -LiteralPath $taskStateFile)) { Write-Host 'No test tunnel state found.'; exit 0 }
$taskState = Get-Content -LiteralPath $taskStateFile -Raw | ConvertFrom-Json
$taskCloudflared = Join-Path $PSScriptRoot '.test-tunnel\cloudflared.exe'
$taskGateway = Join-Path $PSScriptRoot 'js\shared\test-tunnel.cjs'
foreach ($taskTarget in @(@{ Id = $taskState.tunnelPid; Type = 'tunnel' }, @{ Id = $taskState.gatewayPid; Type = 'gateway' })) {
    $taskProcess = Get-CimInstance Win32_Process -Filter ('ProcessId=' + [int]$taskTarget.Id) -ErrorAction SilentlyContinue
    if (-not $taskProcess) { continue }
    $taskMatches = if ($taskTarget.Type -eq 'tunnel') {
        $taskProcess.ExecutablePath -eq $taskCloudflared
    } else {
        $taskProcess.Name -eq 'node.exe' -and $taskProcess.CommandLine.Contains($taskGateway)
    }
    if (-not $taskMatches) { throw 'Process identity mismatch; refusing to stop an unrelated process.' }
    Stop-Process -Id $taskProcess.ProcessId
}
Write-Host 'Temporary test tunnel stopped. XAMPP and your database were not stopped.'
