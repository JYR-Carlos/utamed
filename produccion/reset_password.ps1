<#
.SYNOPSIS
    Conecta por SSH a producción y ejecuta el reseteo forzado de contraseña interactivo.

.DESCRIPTION
    Script compatible con Windows (PowerShell 5.1+, PowerShell 7+) y Linux/macOS (pwsh).
    Lee la IP y el usuario del servidor desde .env o parámetros de entrada, y abre una
    sesión SSH interactiva con TTY (-t) para ejecutar el reseteo forzado de contraseñas
    con búsqueda de usuario, selección ante colisiones y disparo de cambio obligatorio.

.PARAMETER ServerIp
    IP o hostname del servidor de producción. Por defecto se lee de SERVER_IP en .env.

.PARAMETER ServerUser
    Usuario SSH del servidor de producción. Por defecto se lee de SERVER_USER en .env.

.PARAMETER RemoteDir
    Ruta a la raíz de la aplicación Laravel en producción. Por defecto: /var/www/prod_utamed.

.PARAMETER Port
    Puerto SSH del servidor. Por defecto: 22.

.PARAMETER IdentityFile
    Ruta al archivo de clave privada SSH (opcional).

.EXAMPLE
    .\produccion\reset_password.ps1
    pwsh -File .\produccion\reset_password.ps1 -ServerIp 146.83.111.155 -ServerUser utamed
#>

[CmdletBinding()]
param(
    [string]$ServerIp,
    [string]$ServerUser,
    [string]$RemoteDir = "/var/www/prod_utamed",
    [int]$Port = 22,
    [string]$IdentityFile,
    [string]$EnvFile = ".env"
)

$ErrorActionPreference = 'Stop'

Write-Host ""
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "  UTAMED - Reseteo Forzado de Contraseña en Producción (SSH)" -ForegroundColor White -NoNewline
Write-Host " [Windows/Linux]" -ForegroundColor DarkGray
Write-Host "======================================================================" -ForegroundColor Cyan

# 1. Resolver ruta del repositorio y archivo de entorno (.env.prod o .env)
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoDir = Split-Path -Parent $scriptDir

$resolvedEnv = $null
if ($EnvFile -and (Test-Path $EnvFile)) {
    $resolvedEnv = $EnvFile
} else {
    $envProd = Join-Path $repoDir ".env.prod"
    $envNormal = Join-Path $repoDir ".env"
    if (Test-Path $envProd) {
        $resolvedEnv = $envProd
    } elseif (Test-Path $envNormal) {
        $resolvedEnv = $envNormal
    }
}

# 2. Cargar variables desde .env si existen y no se pasaron como parámetro
if (Test-Path $resolvedEnv) {
    Get-Content $resolvedEnv | ForEach-Object {
        $line = $_.Trim()
        if ($line -and -not $line.StartsWith("#") -and $line.Contains("=")) {
            $parts = $line.Split("=", 2)
            $key = $parts[0].Trim()
            $val = $parts[1].Trim().Trim('"').Trim("'")
            if ($key -eq "SERVER_IP" -and [string]::IsNullOrWhiteSpace($ServerIp)) {
                $ServerIp = $val
            }
            if ($key -eq "SERVER_USER" -and [string]::IsNullOrWhiteSpace($ServerUser)) {
                $ServerUser = $val
            }
        }
    }
}

# 3. Solicitar interactivamente si aún faltan
if ([string]::IsNullOrWhiteSpace($ServerIp)) {
    $ServerIp = Read-Host "Ingrese la IP del servidor de producción (SERVER_IP)"
}

if ([string]::IsNullOrWhiteSpace($ServerUser)) {
    $ServerUser = Read-Host "Ingrese el usuario SSH del servidor (SERVER_USER)"
}

if ([string]::IsNullOrWhiteSpace($ServerIp) -or [string]::IsNullOrWhiteSpace($ServerUser)) {
    Write-Error "Error: Se requiere SERVER_IP y SERVER_USER para conectarse por SSH."
    exit 1
}

# 4. Verificar existencia de binario SSH
$sshCmd = Get-Command "ssh" -ErrorAction SilentlyContinue
if (-not $sshCmd) {
    Write-Error "Error: El cliente OpenSSH ('ssh') no está instalado o no se encuentra en el PATH."
    exit 1
}

# 5. Localizar el worker script
$workerFile = Join-Path $scriptDir "reset_password_worker.php"
if (-not (Test-Path $workerFile)) {
    Write-Error "Error: No se encontró el script worker en: $workerFile"
    exit 1
}

Write-Host "• Servidor destino : $ServerUser@$ServerIp (Puerto: $Port)" -ForegroundColor Yellow
Write-Host "• Directorio Laravel: $RemoteDir" -ForegroundColor Yellow
Write-Host "• Preparando sesión interactiva SSH..." -ForegroundColor Green
Write-Host ""

# 6. Codificar worker en Base64 para inyección directa y autónoma sin transferencias previas
$workerBytes = [System.IO.File]::ReadAllBytes($workerFile)
$workerB64 = [Convert]::ToBase64String($workerBytes)

# 7. Construir argumentos SSH
$sshArgs = @(
    "-t",
    "-p", $Port,
    "-o", "StrictHostKeyChecking=accept-new"
)

if ($IdentityFile -and (Test-Path $IdentityFile)) {
    $sshArgs += @("-i", $IdentityFile)
}

$remoteCommand = "echo '$workerB64' | base64 -d > /tmp/utamed_reset_pwd_worker.php && chmod 644 /tmp/utamed_reset_pwd_worker.php && sudo php /tmp/utamed_reset_pwd_worker.php --app-dir='$RemoteDir'; rm -f /tmp/utamed_reset_pwd_worker.php"

$sshArgs += @(
    "$ServerUser@$ServerIp",
    $remoteCommand
)

# 8. Ejecutar SSH con TTY
try {
    & ssh $sshArgs
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) {
        Write-Host "`nLa conexión o el script finalizaron con código: $exitCode" -ForegroundColor Yellow
    }
} catch {
    Write-Error "Ocurrió un error al ejecutar la conexión SSH: $_"
    exit 1
}
