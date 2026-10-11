$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd([IO.Path]::DirectorySeparatorChar)
$authRoot = Join-Path $tempRoot ('opomatic-p05-' + [guid]::NewGuid().ToString('N'))
$stdoutPath = Join-Path $tempRoot ('opomatic-p05-' + [guid]::NewGuid().ToString('N') + '.out.log')
$stderrPath = Join-Path $tempRoot ('opomatic-p05-' + [guid]::NewGuid().ToString('N') + '.err.log')
$listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
$listener.Start()
$port = ($listener.LocalEndpoint).Port
$listener.Stop()
$viteListener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
$viteListener.Start()
$vitePort = ($viteListener.LocalEndpoint).Port
$viteListener.Stop()

$previousAppEnv = [Environment]::GetEnvironmentVariable('APP_ENV', 'Process')
$previousStorage = [Environment]::GetEnvironmentVariable('AUTH_STORAGE_DIR', 'Process')
$previousDevTarget = [Environment]::GetEnvironmentVariable('OPOMATIC_DEV_API_TARGET', 'Process')
$env:APP_ENV = 'test'
$env:AUTH_STORAGE_DIR = $authRoot
$env:OPOMATIC_DEV_API_TARGET = "http://127.0.0.1:$port"
$server = $null
$viteServer = $null

try {
    $server = Start-Process -FilePath (Get-Command php.exe).Source -ArgumentList @('-S', "127.0.0.1:$port", '-t', 'backend/public', 'backend/public/router.php') -WorkingDirectory $repoRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri "http://127.0.0.1:$port/health" -TimeoutSec 1
            if ($response.StatusCode -eq 200) { $ready = $true; break }
        } catch {
            Start-Sleep -Milliseconds 200
        }
    }
    if (-not $ready) { throw 'El servidor PHP de prueba no respondió.' }

    & php (Join-Path $PSScriptRoot 'AuthHttpTest.php') "http://127.0.0.1:$port"
    if ($LASTEXITCODE -ne 0) { throw 'Falló la prueba HTTP de autenticación.' }

    $viteServer = Start-Process -FilePath (Get-Command node.exe).Source -ArgumentList @('node_modules/vite/bin/vite.js', '--host', '127.0.0.1', '--port', [string]$vitePort) -WorkingDirectory (Join-Path $repoRoot 'frontend') -WindowStyle Hidden -PassThru
    $proxyReady = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri "http://127.0.0.1:$vitePort/health" -TimeoutSec 1 -SkipHttpErrorCheck
            if ($response.StatusCode -eq 200) { $proxyReady = $true; break }
        } catch {
            Start-Sleep -Milliseconds 200
        }
    }
    if (-not $proxyReady) { throw 'El proxy de Vite no respondió.' }
    $anonymousSession = Invoke-WebRequest -Uri "http://127.0.0.1:$vitePort/api/auth/session" -TimeoutSec 2 -SkipHttpErrorCheck
    if ($anonymousSession.StatusCode -ne 401) { throw 'Vite no envió /api al backend PHP.' }
    Write-Output 'Proxy Vite: /health 200 y /api/auth/session 401 OK'
} finally {
    if ($null -ne $viteServer -and -not $viteServer.HasExited) {
        Stop-Process -Id $viteServer.Id -Force
        $viteServer.WaitForExit()
    }
    if ($null -ne $server -and -not $server.HasExited) {
        Stop-Process -Id $server.Id -Force
        $server.WaitForExit()
    }
    [Environment]::SetEnvironmentVariable('APP_ENV', $previousAppEnv, 'Process')
    [Environment]::SetEnvironmentVariable('AUTH_STORAGE_DIR', $previousStorage, 'Process')
    [Environment]::SetEnvironmentVariable('OPOMATIC_DEV_API_TARGET', $previousDevTarget, 'Process')

    $resolvedAuthRoot = [IO.Path]::GetFullPath($authRoot)
    if ($resolvedAuthRoot.StartsWith($tempRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -and
        [IO.Path]::GetFileName($resolvedAuthRoot).StartsWith('opomatic-p05-', [StringComparison]::Ordinal)) {
        Remove-Item -LiteralPath $resolvedAuthRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
    Remove-Item -LiteralPath $stdoutPath, $stderrPath -ErrorAction SilentlyContinue
}
