param(
    [string]$HostName = '127.0.0.1',
    [int]$Port = 3306,
    [string]$AdminUser = 'root',
    [switch]$BootstrapAccounts,
    [switch]$Authentication,
    [switch]$Topics,
    [switch]$Questions,
    [switch]$Import,
    [switch]$Generation,
    [switch]$Completion
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path Env:OPOMATIC_DB_ADMIN_PASSWORD)) {
    throw 'Define OPOMATIC_DB_ADMIN_PASSWORD en el proceso antes de ejecutar la prueba.'
}
if (@($BootstrapAccounts, $Authentication, $Topics, $Questions, $Import, $Generation, $Completion).Where({ $_ }).Count -gt 1) {
    throw 'Ejecuta cada modo de prueba en bases temporales distintas.'
}

$mysqlExe = (Get-Command mysql.exe -ErrorAction Stop).Source
$dumpExe = (Get-Command mysqldump.exe -ErrorAction Stop).Source
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$databaseName = 'opomatic_p03_' + [guid]::NewGuid().ToString('N').Substring(0, 12)
$restoreName = $databaseName + '_restore'
$backupPath = Join-Path ([IO.Path]::GetTempPath()) ($databaseName + '.sql')
$previousEnvironment = @{}
foreach ($key in @('MYSQL_PWD', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD')) {
    $previousEnvironment[$key] = [Environment]::GetEnvironmentVariable($key, 'Process')
}

$env:MYSQL_PWD = $env:OPOMATIC_DB_ADMIN_PASSWORD
$env:DB_HOST = $HostName
$env:DB_PORT = [string]$Port
$env:DB_NAME = $databaseName
$env:DB_USER = $AdminUser
$env:DB_PASSWORD = $env:OPOMATIC_DB_ADMIN_PASSWORD

try {
    & $mysqlExe --host=$HostName --port=$Port --user=$AdminUser --execute="CREATE DATABASE ``$databaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE ``$restoreName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron crear las bases temporales.' }

    $schemaPath = (Join-Path $repoRoot 'database\schema.sql').Replace('\', '/')
    & $mysqlExe --host=$HostName --port=$Port --user=$AdminUser --database=$databaseName --execute="source $schemaPath"
    if ($LASTEXITCODE -ne 0) { throw 'Falló la importación del esquema.' }

    Push-Location $repoRoot
    try {
        composer test:db
        if ($LASTEXITCODE -ne 0) { throw 'Falló la prueba de integración.' }
        if ($BootstrapAccounts) {
            composer test:bootstrap
            if ($LASTEXITCODE -ne 0) { throw 'Falló la prueba de alta inicial.' }
        }
        if ($Authentication -or $Topics -or $Questions -or $Import -or $Generation -or $Completion) {
            & (Join-Path $PSScriptRoot 'AuthHttpTest.ps1') -Topics:$Topics -Questions:$Questions -Import:$Import -Generation:$Generation -Completion:$Completion
            if ($LASTEXITCODE -ne 0) { throw 'Falló la prueba HTTP o de navegador.' }
        }
    } finally {
        Pop-Location
    }

    & $dumpExe --host=$HostName --port=$Port --user=$AdminUser $databaseName --result-file=$backupPath
    if ($LASTEXITCODE -ne 0) { throw 'Falló la copia de seguridad.' }

    $backupMysqlPath = $backupPath.Replace('\', '/')
    & $mysqlExe --host=$HostName --port=$Port --user=$AdminUser --database=$restoreName --execute="source $backupMysqlPath"
    if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración.' }

    $restoredTables = & $mysqlExe --host=$HostName --port=$Port --user=$AdminUser --database=$restoreName --batch --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    if ($LASTEXITCODE -ne 0 -or [int]$restoredTables -ne 9) {
        throw 'La copia restaurada no contiene nueve tablas.'
    }

    Write-Output 'Restauración de la copia: nueve tablas OK'
} finally {
    & $mysqlExe --host=$HostName --port=$Port --user=$AdminUser --execute="DROP DATABASE IF EXISTS ``$databaseName``; DROP DATABASE IF EXISTS ``$restoreName``"
    Remove-Item -LiteralPath $backupPath -ErrorAction SilentlyContinue
    foreach ($key in $previousEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($key, $previousEnvironment[$key], 'Process')
    }
}
