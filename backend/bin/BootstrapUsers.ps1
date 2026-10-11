$ErrorActionPreference = 'Stop'

$firstSecure = Read-Host 'Contraseña para Gogloglo' -AsSecureString
$secondSecure = Read-Host 'Contraseña para Virginia' -AsSecureString
$firstPlain = [System.Net.NetworkCredential]::new('', $firstSecure).Password
$secondPlain = [System.Net.NetworkCredential]::new('', $secondSecure).Password
$previousOutputEncoding = $OutputEncoding

try {
    $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
    @($firstPlain, $secondPlain) | & php (Join-Path $PSScriptRoot 'bootstrap-users.php')
    if ($LASTEXITCODE -ne 0) {
        throw "El alta inicial terminó con el código $LASTEXITCODE."
    }
} finally {
    $OutputEncoding = $previousOutputEncoding
    $firstPlain = $null
    $secondPlain = $null
    $firstSecure.Dispose()
    $secondSecure.Dispose()
}
