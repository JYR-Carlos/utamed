#!/usr/bin/env pwsh
<#
.SYNOPSIS
Convierte el Compare/Diff de pgModeler en una migracion de Laravel.

.DESCRIPTION
Toma el DDL incremental generado por pgModeler, le quita el ruido que no puede
entrar en una migracion, bloquea las sentencias destructivas, ENSAYA el resultado
contra la base real y emite un archivo en database/migrations/.

El ensayo es la pieza central: cada bloque del diff se ejecuta contra la base
dentro de una transaccion que siempre termina en ROLLBACK. Lo que la base
rechaza por duplicado ya existe y se descarta; lo que acepta es el incremento
genuino; cualquier otro error aborta la generacion. La autoridad es la base, no
un archivo de referencia.

Ver docs/FLUJO_MIGRACIONES_ESQUEMA.md

.PARAMETER Nombre
Nombre descriptivo de la migracion. Ej: agregar_tabla_mensaje

.PARAMETER Diff
Ruta al diff de pgModeler. Por defecto database-model/docs/bd/diff.sql

.PARAMETER PermitirDrops
Genera la migracion aunque el diff contenga sentencias destructivas.
Requiere haber revisado cada una a mano.

.PARAMETER SoloSql
En lugar de generar una migracion, escribe el SQL ya filtrado en esta ruta.
Sirve para la puesta al dia del corte (aplicar con psql, sin versionar como
migracion) y para revisar el resultado del filtrado.
En este modo NO se descarta nada: la puesta al dia consiste justamente en
aplicar objetos que la base todavia no tiene. El ensayo sigue corriendo, pero
solo para reportar.

.PARAMETER SinEnsayo
Omite el ensayo contra la base. La migracion sale con TODO lo que traiga el
diff, incluidos los objetos que ya existan. Usar solo si no hay base a mano;
la migracion resultante hay que revisarla entera a mano.

.PARAMETER Php
Ruta al binario de PHP que se usa para el ensayo. Tiene que tener pdo_pgsql.
Si no se pasa, se busca uno automaticamente.

.EXAMPLE
pwsh -File scripts/migracion_desde_diff.ps1 -Nombre agregar_tabla_mensaje

.EXAMPLE
pwsh -File scripts/migracion_desde_diff.ps1 -Nombre puesta_al_dia -SoloSql tmp/al_dia.sql
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Nombre,

    [string]$Diff,

    [switch]$PermitirDrops,

    [string]$SoloSql,

    [switch]$SinEnsayo,

    [string]$Php
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lib/comun.ps1')

# $PSScriptRoot y no $MyInvocation: este ultimo queda vacio si el script se
# invoca dot-sourced o via pwsh -Command, y $raiz saldria vacio.
$raiz = Split-Path -Parent $PSScriptRoot

function Get-RutaRelativa {
    # Relativa a la raiz del repo, no al directorio actual: el docblock de la
    # migracion tiene que decir lo mismo se corra el script desde donde se corra.
    param([string]$Ruta)
    $completa = (Resolve-Path -LiteralPath $Ruta).Path
    $base = (Resolve-Path -LiteralPath $script:raiz).Path.TrimEnd('\', '/')
    if ($completa.StartsWith($base, [StringComparison]::OrdinalIgnoreCase)) {
        return $completa.Substring($base.Length).TrimStart('\', '/').Replace('\', '/')
    }
    return $completa
}

if (-not $Diff) {
    $Diff = Join-Path $raiz 'database-model/docs/bd/diff.sql'
}

if (-not (Test-Path $Diff)) {
    Write-Host "ERROR: no se encontro el diff en $Diff" -ForegroundColor Red
    Write-Host "Generalo en pgModeler: Database -> Compare (Diff), origen = modelo, destino = base." -ForegroundColor Yellow
    exit 1
}

$sql = Get-Content $Diff -Raw

# ---------------------------------------------------------------------------
# Metadatos del encabezado de pgModeler
# ---------------------------------------------------------------------------
$fechaDiff = if ($sql -match '(?m)^--\s*\*\*\s*Diff date:\s*(.+?)\s*$') { $Matches[1] } else { 'desconocida' }
$versionPg = if ($sql -match '(?m)^--\s*\*\*\s*pgModeler version:\s*(.+?)\s*$') { $Matches[1] } else { 'desconocida' }

$resumen = @()
foreach ($clave in 'Dropped objects', 'Created objects', 'Changed objects') {
    if ($sql -match "(?m)^--\s*\*\*\s*$clave\:\s*(\d+)") {
        $resumen += "$clave`: $($Matches[1])"
    }
}
$resumenTexto = if ($resumen.Count -gt 0) { $resumen -join ' / ' } else { 'no declarado por pgModeler' }

# ---------------------------------------------------------------------------
# Tokenizado en sentencias
#
# Hace falta un parser de verdad y no un barrido por lineas: el diff trae
# cuerpos de funcion en $$...$$, cadenas E'...' con saltos de linea y comentarios
# /* */. Clasificar por linea confunde un ON DELETE CASCADE de una FK con un
# DELETE, y se pierde un DROP partido en dos lineas.
# ---------------------------------------------------------------------------
function Split-SentenciasSql {
    param([string]$Texto)

    $sentencias = New-Object System.Collections.Generic.List[object]
    $buffer = New-Object System.Text.StringBuilder
    $linea = 1
    $lineaInicio = 1
    $i = 0
    $n = $Texto.Length

    while ($i -lt $n) {
        $c = $Texto[$i]
        $siguiente = if ($i + 1 -lt $n) { $Texto[$i + 1] } else { [char]0 }

        # Comentario de linea: se descarta del texto efectivo.
        if ($c -eq '-' -and $siguiente -eq '-') {
            while ($i -lt $n -and $Texto[$i] -ne "`n") { $i++ }
            continue
        }

        # Comentario de bloque (anidable en PostgreSQL).
        if ($c -eq '/' -and $siguiente -eq '*') {
            $profundidad = 1
            $i += 2
            while ($i -lt $n -and $profundidad -gt 0) {
                if ($Texto[$i] -eq "`n") { $linea++ }
                if ($i + 1 -lt $n -and $Texto[$i] -eq '/' -and $Texto[$i + 1] -eq '*') { $profundidad++; $i += 2; continue }
                if ($i + 1 -lt $n -and $Texto[$i] -eq '*' -and $Texto[$i + 1] -eq '/') { $profundidad--; $i += 2; continue }
                $i++
            }
            continue
        }

        # Cadena entre comillas simples (incluye E'...' con \' y '' doblada).
        if ($c -eq "'") {
            [void]$buffer.Append($c); $i++
            while ($i -lt $n) {
                if ($Texto[$i] -eq "`n") { $linea++ }
                if ($Texto[$i] -eq '\' -and $i + 1 -lt $n) {
                    [void]$buffer.Append($Texto[$i]); [void]$buffer.Append($Texto[$i + 1]); $i += 2; continue
                }
                if ($Texto[$i] -eq "'") {
                    if ($i + 1 -lt $n -and $Texto[$i + 1] -eq "'") {
                        [void]$buffer.Append("''"); $i += 2; continue
                    }
                    [void]$buffer.Append("'"); $i++; break
                }
                [void]$buffer.Append($Texto[$i]); $i++
            }
            continue
        }

        # Identificador entre comillas dobles.
        if ($c -eq '"') {
            [void]$buffer.Append($c); $i++
            while ($i -lt $n) {
                if ($Texto[$i] -eq "`n") { $linea++ }
                [void]$buffer.Append($Texto[$i])
                if ($Texto[$i] -eq '"') { $i++; break }
                $i++
            }
            continue
        }

        # Cadena con delimitador de dolar: $$ ... $$ o $tag$ ... $tag$
        if ($c -eq '$') {
            $m = [regex]::Match($Texto.Substring($i), '^\$[A-Za-z_][A-Za-z0-9_]*\$|^\$\$')
            if ($m.Success) {
                $etiqueta = $m.Value
                [void]$buffer.Append($etiqueta)
                $i += $etiqueta.Length
                $cierre = $Texto.IndexOf($etiqueta, $i)
                if ($cierre -lt 0) { $cierre = $n }
                $cuerpo = $Texto.Substring($i, [Math]::Min($cierre - $i, $n - $i))
                $linea += ([regex]::Matches($cuerpo, "`n")).Count
                [void]$buffer.Append($cuerpo)
                if ($cierre -lt $n) { [void]$buffer.Append($etiqueta); $i = $cierre + $etiqueta.Length }
                else { $i = $n }
                continue
            }
        }

        if ($c -eq ';') {
            # OJO: no llamar a esta variable $texto. PowerShell no distingue
            # mayusculas en los nombres de variable, asi que pisaria el parametro
            # $Texto y el recorrido se quedaria sin entrada a mitad de camino.
            $completa = $buffer.ToString().Trim()
            if ($completa -ne '') {
                $sentencias.Add([pscustomobject]@{ Texto = $completa; Linea = $lineaInicio; Cerrada = $true })
            }
            [void]$buffer.Clear()
            $i++
            # La siguiente sentencia empieza en la proxima linea con contenido.
            $lineaInicio = $linea
            continue
        }

        if ($c -eq "`n") { $linea++ }
        if ($buffer.Length -eq 0 -and [char]::IsWhiteSpace($c)) { $lineaInicio = $linea; $i++; continue }

        [void]$buffer.Append($c)
        $i++
    }

    # Resto sin punto y coma: la sentencia quedo abierta. Se marca para que la
    # segmentacion en unidades sepa que tiene que seguir acumulando lineas.
    $resto = $buffer.ToString().Trim()
    if ($resto -ne '') {
        $sentencias.Add([pscustomobject]@{ Texto = $resto; Linea = $lineaInicio; Cerrada = $false })
    }

    return $sentencias
}

# ---------------------------------------------------------------------------
# Clasificacion de sentencias: lista de PERMITIDOS
#
# Antes esto era una lista de prohibidos con seis entradas, y dejaba pasar
# DROP CONSTRAINT, DROP OWNED BY, DROP FUNCTION ... CASCADE y todo lo que no
# estuviera literalmente enumerado. Se invirtio: lo que no esta reconocido como
# seguro, bloquea.
# ---------------------------------------------------------------------------
$verbosSeguros = @(
    'CREATE', 'COMMENT', 'SET', 'GRANT', 'REVOKE', 'INSERT', 'UPDATE',
    'DO', 'SELECT', 'WITH', 'ANALYZE', 'REFRESH', 'CALL', 'BEGIN', 'COMMIT', 'SAVEPOINT'
)

function Get-RiesgoSentencia {
    param([string]$Texto)

    $t = ($Texto -replace '\s+', ' ').Trim()

    if ($t -match '(?i)^DROP\s+OWNED\b')  { return 'DROP OWNED BY: elimina todos los objetos del rol' }
    if ($t -match '(?i)^DROP\s+DATABASE\b') { return 'DROP DATABASE: elimina la base entera' }
    if ($t -match '(?i)^DROP\s+SCHEMA\b') { return 'DROP SCHEMA: elimina un esquema completo' }
    if ($t -match '(?i)^DROP\s+TABLE\b')  { return 'DROP TABLE: elimina una tabla y todas sus filas' }
    if ($t -match '(?i)^DROP\s+(\w+)\b')  { return "DROP $($Matches[1].ToUpperInvariant()): elimina un objeto existente" }
    if ($t -match '(?i)^TRUNCATE\b')      { return 'TRUNCATE: vacia una tabla' }
    if ($t -match '(?i)^DELETE\s+FROM\b') { return 'DELETE FROM: borra filas' }

    if ($t -match '(?i)^ALTER\b') {
        # DROP DEFAULT / DROP NOT NULL / DROP IDENTITY relajan una restriccion:
        # no pierden datos. DROP COLUMN y DROP CONSTRAINT si.
        if ($t -match '(?i)\bDROP\s+COLUMN\b')     { return 'ALTER ... DROP COLUMN: elimina una columna y sus datos' }
        if ($t -match '(?i)\bDROP\s+CONSTRAINT\b') { return 'ALTER ... DROP CONSTRAINT: elimina una garantia de integridad' }
        if ($t -match '(?i)\bDROP\s+(?!DEFAULT\b|NOT\s+NULL\b|IDENTITY\b|EXPRESSION\b)(\w+)') {
            return "ALTER ... DROP $($Matches[1].ToUpperInvariant())"
        }
        return $null
    }

    $verbo = ($t -split '\s+', 2)[0].ToUpperInvariant()
    if ($script:verbosSeguros -contains $verbo) { return $null }

    return "verbo no reconocido ($verbo): el filtro no sabe si es seguro"
}

$sentencias = Split-SentenciasSql -Texto $sql

$hallazgos = @()
foreach ($s in $sentencias) {
    $riesgo = Get-RiesgoSentencia -Texto $s.Texto
    if ($riesgo) {
        $recorte = ($s.Texto -replace '\s+', ' ').Trim()
        if ($recorte.Length -gt 120) { $recorte = $recorte.Substring(0, 117) + '...' }
        $hallazgos += [pscustomobject]@{
            Linea     = $s.Linea
            Riesgo    = $riesgo
            Sentencia = $recorte
            Cascade   = $s.Texto -match '(?i)\bCASCADE\b'
        }
    }
}

if ($hallazgos.Count -gt 0) {
    Write-Host ''
    Write-Host "El diff contiene $($hallazgos.Count) sentencia(s) destructiva(s) o no reconocida(s):" -ForegroundColor Red
    foreach ($h in $hallazgos) {
        Write-Host ("  linea {0,-5} {1}" -f $h.Linea, $h.Riesgo) -ForegroundColor Red
        Write-Host ("        {0}" -f $h.Sentencia) -ForegroundColor DarkGray
        if ($h.Cascade) {
            Write-Host '        -> lleva CASCADE: arrastra todo lo que dependa del objeto' -ForegroundColor Yellow
        }
    }

    if (-not $PermitirDrops) {
        Write-Host ''
        Write-Host 'Migracion NO generada.' -ForegroundColor Red
        Write-Host 'pgModeler representa el rename de una columna como drop + add. Si eso es lo que' -ForegroundColor Yellow
        Write-Host 'pasa aca, corregilo a mano en el diff con ALTER TABLE ... RENAME COLUMN ...' -ForegroundColor Yellow
        Write-Host 'Si los drops son intencionales y ya respaldaste, repeti con -PermitirDrops.' -ForegroundColor Yellow
        exit 1
    }

    Write-Host ''
    Write-Host '-PermitirDrops activo: se genera igual. Revisa cada sentencia antes de migrar.' -ForegroundColor Yellow
}

# ---------------------------------------------------------------------------
# Filtrado del ruido
# ---------------------------------------------------------------------------
$removidos = @()

# ALTER ROLE ... PASSWORD ...  (pgModeler lo parte en dos lineas)
$reRolePwd = '(?ms)^ALTER\s+ROLE\s+\S+\s*\r?\n?\s*PASSWORD\s+[^;]*;\s*\r?\n?'
if ($sql -match $reRolePwd) {
    $removidos += 'ALTER ROLE ... PASSWORD (resetearia la credencial del entorno destino)'
    $sql = [regex]::Replace($sql, $reRolePwd, '')
}

# ALTER DATABASE ... OWNER TO ...
$reDbOwner = '(?m)^ALTER\s+DATABASE\s+\S+\s+OWNER\s+TO\s+[^;]*;\s*\r?\n?'
if ($sql -match $reDbOwner) {
    $removidos += 'ALTER DATABASE ... OWNER TO (asume el nombre de base y el rol locales)'
    $sql = [regex]::Replace($sql, $reDbOwner, '')
}

# ALTER <objeto> ... OWNER TO ...
# pgModeler emite el owner que tenga el modelo (a veces postgres, a veces utamed).
# En una migracion eso no sirve: el dueño correcto es el usuario con el que Laravel
# conecta en cada entorno, que es justamente el que queda si no se dice nada.
$reObjOwner = '(?m)^ALTER\s+(TABLE|TYPE|FUNCTION|PROCEDURE|VIEW|MATERIALIZED\s+VIEW|SEQUENCE|SCHEMA|DOMAIN|AGGREGATE)\s+[^;]*?\s+OWNER\s+TO\s+[^;]*;\s*\r?\n?'
$cuentaObjOwner = ([regex]::Matches($sql, $reObjOwner)).Count
if ($cuentaObjOwner -gt 0) {
    $removidos += "$cuentaObjOwner x ALTER ... OWNER TO (el dueño lo define el usuario de conexion de cada entorno)"
    $sql = [regex]::Replace($sql, $reObjOwner, '')
}

# ALTER TABLE ... ALTER COLUMN ... SET DEFAULT NULL
# Postgres descarta un DEFAULT NULL explicito: no llega a pg_attrdef. El modelo lo
# declara, la base nunca lo guarda, y una base construida desde cero tampoco. Es un
# no-op eterno que reaparece en todos los diffs.
$reDefaultNull = '(?im)^\s*ALTER\s+TABLE\s+[^;]*?\s+ALTER\s+COLUMN\s+\S+\s+SET\s+DEFAULT\s+NULL\s*;\s*\r?\n?'
$cuentaDefaultNull = ([regex]::Matches($sql, $reDefaultNull)).Count
if ($cuentaDefaultNull -gt 0) {
    $removidos += "$cuentaDefaultNull x ALTER COLUMN ... SET DEFAULT NULL (Postgres lo descarta; no-op)"
    $sql = [regex]::Replace($sql, $reDefaultNull, '')
}

# SET check_function_bodies
$reCheckBodies = '(?m)^SET\s+check_function_bodies\s*=[^;]*;\s*\r?\n?'
if ($sql -match $reCheckBodies) {
    $removidos += 'SET check_function_bodies (ajuste de sesion de pgModeler)'
    $sql = [regex]::Replace($sql, $reCheckBodies, '')
}

# ---------------------------------------------------------------------------
# search_path
#
# El diff trae el search_path del entorno de modelado, que empieza por `public`.
# El de la aplicacion (config/database.php) empieza por `usuario`. Inyectar el de
# pgModeler en la migracion hacia que cualquier DDL sin calificar aterrizara en un
# esquema distinto del que la aplicacion espera.
#
#  - En una migracion se ELIMINA: Laravel ya abre la conexion con el search_path
#    configurado, que es el correcto por definicion.
#  - En -SoloSql se deja como SET normal (no LOCAL): psql aplica el archivo fuera
#    de un bloque de transaccion, y ahi SET LOCAL no hace nada mas que avisar.
# ---------------------------------------------------------------------------
# El default replica el de config/database.php.
$searchPathApp = Get-ValorEnv -Archivo (Join-Path $raiz '.env') -Clave 'DB_SEARCH_PATH'
if (-not $searchPathApp) {
    $searchPathApp = 'usuario, agenda, administrativo, curso, public, auditoria, operaciones'
}

$reSearchPath = '(?m)^SET\s+search_path\s*=[^;]*;\s*\r?\n?'
if ($sql -match $reSearchPath) {
    if ($SoloSql) {
        $removidos += 'SET search_path del diff -> se conserva (psql lo necesita)'
    } else {
        $removidos += "SET search_path del diff (la migracion usa el de la conexion de Laravel: $searchPathApp)"
        $sql = [regex]::Replace($sql, $reSearchPath, '')
    }
}

$sql = $sql.Trim()

# ---------------------------------------------------------------------------
# Segmentacion en unidades
#
# Una unidad es o bien un bloque con marcador de pgModeler
# (-- object: X | type: Y --  ...  -- ddl-end --) o bien una sentencia suelta.
# Las sueltas importan: la seccion [ Changed objects ] del diff se emite sin
# marcador, y el reporte anterior no las veia.
# ---------------------------------------------------------------------------
$reMarcador = '^\s*--\s*object:\s*(.+?)\s*\|\s*type:\s*(.+?)\s*--\s*$'
$reFinDdl   = '^\s*--\s*ddl-end\s*--'

$lineas = $sql -split "`r?`n"
$unidades = New-Object System.Collections.Generic.List[object]

$i = 0
while ($i -lt $lineas.Count) {
    $m = [regex]::Match($lineas[$i], $reMarcador)

    if ($m.Success) {
        $fin = $i
        while ($fin -lt $lineas.Count -and $lineas[$fin] -notmatch $reFinDdl) { $fin++ }
        if ($fin -ge $lineas.Count) { $fin = $lineas.Count - 1 }

        $cuerpo = $lineas[$i..$fin]
        $unidades.Add([pscustomobject]@{
            Nombre  = $m.Groups[1].Value.Trim()
            Tipo    = $m.Groups[2].Value.Trim().ToUpperInvariant()
            Lineas  = $cuerpo
            Linea   = $i + 1
        })
        $i = $fin + 1
        continue
    }

    $t = $lineas[$i].Trim()
    if ($t -eq '' -or $t.StartsWith('--')) { $i++; continue }

    # Sentencia suelta: se acumula hasta que el parser diga que no queda nada
    # abierto. Mirar si la linea termina en ';' no alcanza — puede venir seguida
    # de un comentario, o el ';' puede estar dentro de un cuerpo $$...$$.
    $inicio = $i
    $acum = @()
    while ($i -lt $lineas.Count) {
        $acum += $lineas[$i]
        $i++
        $parseadas = @(Split-SentenciasSql -Texto ($acum -join "`n"))
        if ($parseadas.Count -ge 1 -and -not ($parseadas | Where-Object { -not $_.Cerrada })) { break }
    }

    $unidades.Add([pscustomobject]@{
        Nombre  = $null
        Tipo    = 'SENTENCIA'
        Lineas  = $acum
        Linea   = $inicio + 1
    })
}

foreach ($u in $unidades) {
    $efectivo = (Split-SentenciasSql -Texto (($u.Lineas) -join "`n") | ForEach-Object { $_.Texto + ';' }) -join "`n"
    $u | Add-Member -NotePropertyName SqlEfectivo -NotePropertyValue $efectivo
    if (-not $u.Nombre) {
        $etiqueta = ($efectivo -replace '\s+', ' ').Trim()
        if ($etiqueta.Length -gt 60) { $etiqueta = $etiqueta.Substring(0, 57) + '...' }
        $u.Nombre = $etiqueta
    }
}

$conDdl = @($unidades | Where-Object { $_.SqlEfectivo.Trim() -ne '' })

if ($conDdl.Count -eq 0) {
    Write-Host ''
    Write-Host 'ERROR: el diff quedo vacio despues del filtrado. No hay nada que migrar.' -ForegroundColor Red
    Write-Host ''
    exit 1
}

# ---------------------------------------------------------------------------
# Ensayo contra la base real
# ---------------------------------------------------------------------------
$ensayoOk        = $false
$baseEnsayada    = $null
$motivoSinEnsayo = $null
$estadoPorUnidad = @{}

if ($SinEnsayo) {
    $motivoSinEnsayo = '-SinEnsayo activo'
} elseif ($hallazgos.Count -gt 0) {
    # El ensayo ejecuta el DDL de verdad antes de revertirlo. Con un DROP de por
    # medio eso significa tomar un lock exclusivo (y con DROP OWNED BY, sobre la
    # base entera) hasta el ROLLBACK. No vale el riesgo en una base compartida.
    $motivoSinEnsayo = 'el diff tiene sentencias destructivas y -PermitirDrops esta activo'
} else {
    $php = Get-PhpConPgsql -Preferido $Php
    if (-not $php) {
        $motivoSinEnsayo = 'no se encontro un PHP con pdo_pgsql (usa -Php <ruta> o define PHP_BINARIO)'
    } else {
        $tmp = [System.IO.Path]::GetTempPath()
        $entradaJson = Join-Path $tmp ("ensayo_in_{0}.json"  -f [guid]::NewGuid())
        $salidaJson  = Join-Path $tmp ("ensayo_out_{0}.json" -f [guid]::NewGuid())

        # CREATE INDEX CONCURRENTLY no puede correr dentro de una transaccion, y
        # el ensayo es una transaccion. Se salta del ensayo, no de la migracion.
        $paraEnsayo = @()
        for ($k = 0; $k -lt $conDdl.Count; $k++) {
            if ($conDdl[$k].SqlEfectivo -match '(?i)\bCONCURRENTLY\b') {
                $estadoPorUnidad[$k] = 'sin-ensayar'
                continue
            }
            $paraEnsayo += [pscustomobject]@{ id = $k; sql = $conDdl[$k].SqlEfectivo }
        }

        $payload = [pscustomobject]@{
            preludio = "SET LOCAL search_path=$searchPathApp"
            bloques  = $paraEnsayo
        }
        $payload | ConvertTo-Json -Depth 6 | Set-Content -Path $entradaJson -Encoding utf8NoBOM

        $script = Join-Path $PSScriptRoot 'lib/ensayo_ddl.php'
        & $php $script $entradaJson $raiz $salidaJson 2>&1 | Out-Null

        if (Test-Path $salidaJson) {
            $respuesta = Get-Content $salidaJson -Raw -Encoding utf8 | ConvertFrom-Json
            if ($respuesta.ok) {
                $ensayoOk = $true
                $baseEnsayada = $respuesta.base
                foreach ($r in $respuesta.resultados) { $estadoPorUnidad[[int]$r.id] = $r }
            } else {
                $motivoSinEnsayo = $respuesta.error
            }
        } else {
            $motivoSinEnsayo = 'el ensayo no produjo salida'
        }

        Remove-Item $entradaJson, $salidaJson -ErrorAction SilentlyContinue
    }
}

$existentes = @()
$aplicables = @()
$errores    = @()

for ($k = 0; $k -lt $conDdl.Count; $k++) {
    $u = $conDdl[$k]
    $r = $estadoPorUnidad[$k]

    $estado = if (-not $ensayoOk) { 'nuevo' }
              elseif ($null -eq $r) { 'nuevo' }
              elseif ($r -is [string]) { 'nuevo' }
              else { $r.estado }

    switch ($estado) {
        'existe' {
            $existentes += [pscustomobject]@{ Tipo = $u.Tipo; Nombre = $u.Nombre; Motivo = $r.mensaje }
            $u | Add-Member -NotePropertyName Incluir -NotePropertyValue $false -Force
        }
        'error' {
            $errores += [pscustomobject]@{ Tipo = $u.Tipo; Nombre = $u.Nombre; Sqlstate = $r.sqlstate; Mensaje = $r.mensaje }
            $u | Add-Member -NotePropertyName Incluir -NotePropertyValue $true -Force
        }
        default {
            $aplicables += [pscustomobject]@{ Tipo = $u.Tipo; Nombre = $u.Nombre }
            $u | Add-Member -NotePropertyName Incluir -NotePropertyValue $true -Force
        }
    }
}

if (-not $ensayoOk) {
    Write-Host ''
    Write-Host "ADVERTENCIA: no se ensayo contra la base ($motivoSinEnsayo)." -ForegroundColor Yellow
    Write-Host 'La migracion sale con TODO lo que trae el diff. Si algun objeto ya existe en la' -ForegroundColor Yellow
    Write-Host 'base, artisan migrate va a abortar con "already exists". Revisala entera a mano.' -ForegroundColor Yellow
}

# En -SoloSql no se descarta nada: la puesta al dia consiste justamente en
# aplicar objetos que la base todavia no tiene, y el reporte de "ya existe" es
# informativo.
if ($SoloSql) {
    foreach ($u in $conDdl) { $u.Incluir = $true }
}

# Un error que no sea "ya existe" significa que la migracion no funcionaria.
# Mejor enterarse ahora que en artisan migrate.
if ($errores.Count -gt 0 -and -not $SoloSql) {
    Write-Host ''
    Write-Host "El ensayo contra $baseEnsayada fallo en $($errores.Count) bloque(s):" -ForegroundColor Red
    foreach ($e in $errores) {
        Write-Host ("  {0,-10} {1}" -f $e.Tipo, $e.Nombre) -ForegroundColor Red
        Write-Host ("             [{0}] {1}" -f $e.Sqlstate, $e.Mensaje) -ForegroundColor DarkGray
    }
    Write-Host ''
    Write-Host 'Migracion NO generada: tal como esta, artisan migrate fallaria.' -ForegroundColor Red
    Write-Host 'Corregi el modelo o el diff y volve a intentar.' -ForegroundColor Yellow
    Write-Host ''
    exit 1
}

$incluidas = @($conDdl | Where-Object { $_.Incluir })

if ($incluidas.Count -eq 0) {
    Write-Host ''
    Write-Host "Todos los objetos del diff ($($existentes.Count)) ya existen en $baseEnsayada." -ForegroundColor Yellow
    foreach ($o in $existentes) {
        Write-Host ("  {0,-10} {1}" -f $o.Tipo, $o.Nombre) -ForegroundColor DarkGray
    }
    Write-Host ''
    Write-Host 'Esto NO es un incremento: no hay cambio de modelo que migrar.' -ForegroundColor Yellow
    Write-Host 'Si el diff no sale vacio contra la base, es que pgModeler no logra importar esos' -ForegroundColor Yellow
    Write-Host 'objetos (indices GIN, constraints gist) y los reporta como faltantes. Ver' -ForegroundColor Yellow
    Write-Host 'docs/FLUJO_MIGRACIONES_ESQUEMA.md seccion 7.' -ForegroundColor Yellow
    Write-Host ''
    exit 1
}

# ---------------------------------------------------------------------------
# Armado del SQL final
#
# Los comentarios pasan de -- a bloque. Motivo: artisan migrate --pretend y el
# log de errores de Laravel colapsan la consulta en una sola linea, y ahi un
# comentario -- se traga todo lo que venga detras. Es el paso de revision del
# flujo; tiene que poder leerse.
# ---------------------------------------------------------------------------
function ConvertTo-ComentarioDeBloque {
    param([string[]]$Lineas)
    $salida = @()
    foreach ($l in $Lineas) {
        $t = $l.Trim()
        if ($t -match '^--\s*ddl-end\s*--') { continue }   # delimitador de pgModeler, sin valor aca
        if ($t.StartsWith('--')) {
            # El marcador de pgModeler abre y cierra con --; el de cierre sobra
            # una vez convertido a comentario de bloque.
            $contenido = ($t.Substring(2).Trim() -replace '\s*--\s*$', '') -replace '\*/', '* /'
            if ($contenido -ne '') { $salida += "/* $contenido */" }
            continue
        }
        $salida += $l
    }
    return $salida
}

$bloquesSql = @()
foreach ($u in $incluidas) {
    $bloquesSql += (ConvertTo-ComentarioDeBloque -Lineas $u.Lineas)
    $bloquesSql += ''
}

$sqlFinal = (($bloquesSql -join "`n") -replace '(?m)^\s*$\r?\n(\s*$\r?\n)+', "`n").Trim()

if ($SoloSql) {
    # psql aplica el archivo fuera de un bloque de transaccion; SET LOCAL no
    # serviria. Se envuelve en BEGIN/COMMIT para que un error a mitad de camino
    # no deje la base aplicada por la mitad.
    $sqlFinal = "BEGIN;`n`n$sqlFinal`n`nCOMMIT;"
}

# ---------------------------------------------------------------------------
# Advertencias que no bloquean
# ---------------------------------------------------------------------------
$sqlEfectivo = ($incluidas | ForEach-Object { $_.SqlEfectivo }) -join "`n"

$advertencias = @()
if ($sqlEfectivo -match '(?im)ALTER\s+COLUMN\s+\S+\s+(SET\s+DATA\s+)?TYPE\s') {
    $advertencias += 'Hay un cambio de tipo de columna: puede truncar o fallar segun los datos existentes.'
}
if ($sqlEfectivo -match '(?im)SET\s+NOT\s+NULL') {
    $advertencias += 'Hay un SET NOT NULL: falla si existen filas con NULL. Puede necesitar un UPDATE previo en la misma migracion.'
}
if ($sqlEfectivo -match '(?im)ADD\s+CONSTRAINT') {
    $advertencias += 'Hay un ADD CONSTRAINT: falla si los datos existentes lo violan.'
}
if ($sqlEfectivo -match '(?im)\bCREATE\s+INDEX\b') {
    $advertencias += 'Hay un CREATE INDEX: bloquea escrituras en la tabla. En produccion evalua CREATE INDEX CONCURRENTLY (requiere migracion sin transaccion).'
}
if ($sqlEfectivo -match '(?im)\bCREATE\s+TABLE\b') {
    $advertencias += 'Hay tablas nuevas: 04-grants.sql usa GRANT ... ON ALL TABLES, que sólo alcanza a las tablas que existian al ejecutarlo. Verifica los privilegios del usuario de la aplicacion sobre las tablas nuevas.'
}
if ($sqlEfectivo -match '(?im)ADD\s+COLUMN\s+\S+[^;]*?\bNOT\s+NULL\b(?![^;]*\bDEFAULT\b)') {
    $advertencias += 'Hay un ADD COLUMN ... NOT NULL sin DEFAULT: falla si la tabla ya tiene filas. Agrega un DEFAULT o un UPDATE previo en la misma migracion.'
}

# ---------------------------------------------------------------------------
# Modo -SoloSql
# ---------------------------------------------------------------------------
if ($SoloSql) {
    $rutaSalida = if ([System.IO.Path]::IsPathRooted($SoloSql)) { $SoloSql } else { Join-Path $raiz $SoloSql }
    $dirSalida = Split-Path -Parent $rutaSalida
    if ($dirSalida -and -not (Test-Path $dirSalida)) {
        New-Item -ItemType Directory -Force -Path $dirSalida | Out-Null
    }

    $encabezado = @(
        "-- SQL filtrado desde $(Get-RutaRelativa $Diff)"
        "-- Generado por scripts/migracion_desde_diff.ps1 el $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        "-- Resumen pgModeler: $resumenTexto"
        '--'
        '-- Aplicar con:'
        "--   psql -U postgres -d <base> -1 -v ON_ERROR_STOP=1 -f $(Split-Path -Leaf $rutaSalida)"
        '--'
        '-- El -1 y el ON_ERROR_STOP no son opcionales: sin ellos psql sigue adelante'
        '-- despues de un error y deja la base aplicada por la mitad.'
        '--'
        '-- Ruido eliminado:'
    )
    if ($removidos.Count -gt 0) {
        $encabezado += ($removidos | ForEach-Object { "--   - $_" })
    } else {
        $encabezado += '--   (ninguno)'
    }
    if ($existentes.Count -gt 0) {
        $encabezado += "-- Objetos que la base $baseEnsayada YA tiene (se conservan a proposito: en"
        $encabezado += '-- una puesta al dia el objetivo es aplicarlos donde falten):'
        $encabezado += ($existentes | ForEach-Object { "--   - $($_.Tipo) $($_.Nombre)" })
    }
    $encabezado += '-- NO versionar esto como migracion si son objetos ya presentes en el baseline.'
    $encabezado += ''

    Set-Content -Path $rutaSalida -Value (($encabezado -join "`n") + "`n" + $sqlFinal) -Encoding utf8NoBOM

    Write-Host ''
    Write-Host "SQL filtrado escrito en: $(Get-RutaRelativa $rutaSalida)" -ForegroundColor Green
    Write-Host "  Resumen pgModeler: $resumenTexto" -ForegroundColor Gray
    if ($ensayoOk) { Write-Host "  Ensayado contra: $baseEnsayada" -ForegroundColor Gray }
    if ($removidos.Count -gt 0) {
        Write-Host '  Ruido filtrado:' -ForegroundColor Gray
        foreach ($r in $removidos) { Write-Host "    - $r" -ForegroundColor DarkGray }
    }
    if ($existentes.Count -gt 0) {
        Write-Host "  Ya presentes en la base ($($existentes.Count), conservados en modo -SoloSql):" -ForegroundColor Gray
        foreach ($o in $existentes) { Write-Host ("    {0,-10} {1}" -f $o.Tipo, $o.Nombre) -ForegroundColor DarkGray }
    }
    if ($errores.Count -gt 0) {
        Write-Host "  Bloques que fallaron en el ensayo ($($errores.Count)):" -ForegroundColor Yellow
        foreach ($e in $errores) { Write-Host ("    {0,-10} {1}  [{2}] {3}" -f $e.Tipo, $e.Nombre, $e.Sqlstate, $e.Mensaje) -ForegroundColor Yellow }
    }
    if ($advertencias.Count -gt 0) {
        Write-Host ''
        Write-Host '  Advertencias:' -ForegroundColor Yellow
        foreach ($a in $advertencias) { Write-Host "    - $a" -ForegroundColor Yellow }
    }
    Write-Host ''
    exit 0
}

# ---------------------------------------------------------------------------
# Generacion del archivo
# ---------------------------------------------------------------------------

# Salvaguarda para el heredoc nowdoc del PHP generado.
# El ancla va con \s*: desde PHP 7.3 el delimitador de cierre puede ir indentado,
# asi que anclar en la columna 0 dejaba pasar "  SQL_PGMODELER" y el archivo
# generado no compilaba.
if ($sqlFinal -match '(?m)^\s*SQL_PGMODELER') {
    Write-Host 'ERROR: el diff contiene una linea que colisiona con el delimitador heredoc.' -ForegroundColor Red
    exit 1
}

$slug = ($Nombre.ToLowerInvariant() -replace '[^a-z0-9]+', '_').Trim('_')
if (-not $slug) {
    Write-Host 'ERROR: -Nombre no produjo un slug valido.' -ForegroundColor Red
    exit 1
}

$dirMigraciones = Join-Path $raiz 'database/migrations'
if (-not (Test-Path $dirMigraciones)) { New-Item -ItemType Directory -Force -Path $dirMigraciones | Out-Null }

$yaExiste = Get-ChildItem -Path $dirMigraciones -Filter "*_${slug}.php" -File -ErrorAction SilentlyContinue
if ($yaExiste) {
    Write-Host ''
    Write-Host "ERROR: ya hay una migracion con el mismo nombre: $($yaExiste[0].Name)" -ForegroundColor Red
    Write-Host 'Si todavia no la aplicaste, corre artisan migrate en vez de generar otra.' -ForegroundColor Yellow
    Write-Host 'Si es un cambio distinto, usa un -Nombre distinto.' -ForegroundColor Yellow
    Write-Host ''
    exit 1
}

$marca = (Get-Date).ToString('yyyy_MM_dd_HHmmss')
$destino = Join-Path $dirMigraciones "${marca}_${slug}.php"
$sufijo = 0
while (Test-Path $destino) {
    # Dos corridas en el mismo segundo pisaban la migracion anterior sin avisar.
    $sufijo++
    $destino = Join-Path $dirMigraciones "${marca}${sufijo}_${slug}.php"
}

$plantilla = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DDL incremental generado con el Compare/Diff de pgModeler.
 *
 * Origen del diff : {{ORIGEN}}
 * Fecha del diff  : {{FECHA}}
 * pgModeler       : {{VERSION}}
 * Resumen         : {{RESUMEN}}
 * Ensayo          : {{ENSAYO}}
 *
 * Generado por scripts/migracion_desde_diff.ps1
 * Flujo: docs/FLUJO_MIGRACIONES_ESQUEMA.md
 *
 * Ruido filtrado del diff original:
{{REMOVIDOS}}
 *
 * Objetos que la base ya tenia y quedaron fuera de esta migracion:
{{OMITIDOS}}
 *
 * Objetos que SI aplica esta migracion:
{{APLICADOS}}
 */
return new class extends Migration
{
    public function up(): void
    {
        // getConnection() y no el default: con artisan migrate --database=otra,
        // DB::unprepared() iria a la conexion equivocada y fuera de la
        // transaccion que Laravel abrio para esta migracion.
        DB::connection($this->getConnection())->unprepared(<<<'SQL_PGMODELER'
{{SQL}}
SQL_PGMODELER);
    }

    public function down(): void
    {
        // TODO: completar antes de mergear. El test MigracionesTest lo exige.
        //
        // Para obtener el DDL inverso, invertir el Compare en pgModeler:
        // origen = la base ya migrada, destino = el .dbm anterior.
        //
        // Si el cambio es irreversible (se elimino una columna con datos),
        // restaurar la estructura y dejar constancia de que los datos no vuelven.
        throw new RuntimeException(
            'down() sin implementar en '.basename(__FILE__).': el rollback no esta disponible.'
        );
    }
};
'@

function Format-ListaPhpdoc {
    param([string[]]$Items)
    if ($Items.Count -gt 0) { return (($Items | ForEach-Object { " *   - $_" }) -join "`n") }
    return ' *   (ninguno)'
}

$textoEnsayo = if ($ensayoOk) { "OK contra $baseEnsayada" } else { "NO EJECUTADO ($motivoSinEnsayo)" }

$listaRemovidos = Format-ListaPhpdoc -Items $removidos
$listaOmitidos  = Format-ListaPhpdoc -Items ($existentes | ForEach-Object { "$($_.Tipo) $($_.Nombre)" })
$listaAplicados = Format-ListaPhpdoc -Items ($aplicables | ForEach-Object { "$($_.Tipo) $($_.Nombre)" })

$contenido = $plantilla.
    Replace('{{ORIGEN}}', (Get-RutaRelativa $Diff)).
    Replace('{{FECHA}}', $fechaDiff).
    Replace('{{VERSION}}', $versionPg).
    Replace('{{RESUMEN}}', $resumenTexto).
    Replace('{{ENSAYO}}', $textoEnsayo).
    Replace('{{REMOVIDOS}}', $listaRemovidos).
    Replace('{{OMITIDOS}}', $listaOmitidos).
    Replace('{{APLICADOS}}', $listaAplicados).
    Replace('{{SQL}}', $sqlFinal)

Set-Content -Path $destino -Value $contenido -Encoding utf8NoBOM

# El archivo generado tiene que compilar. Un heredoc mal cerrado producia PHP
# invalido y el script igual reportaba exito.
$phpLint = Get-PhpConPgsql -Preferido $Php
if (-not $phpLint) { $phpLint = 'php' }
$lint = & $phpLint -l $destino 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host ''
    Write-Host 'ERROR: la migracion generada no es PHP valido. Se elimina.' -ForegroundColor Red
    Write-Host ($lint -join "`n") -ForegroundColor DarkGray
    Remove-Item $destino -Force
    exit 1
}

# ---------------------------------------------------------------------------
# Reporte
# ---------------------------------------------------------------------------
$relDestino = Get-RutaRelativa $destino

Write-Host ''
Write-Host "Migracion generada: $relDestino" -ForegroundColor Green
Write-Host "  Resumen pgModeler: $resumenTexto" -ForegroundColor Gray
Write-Host "  Ensayo: $textoEnsayo" -ForegroundColor Gray

if ($removidos.Count -gt 0) {
    Write-Host '  Ruido filtrado:' -ForegroundColor Gray
    foreach ($r in $removidos) { Write-Host "    - $r" -ForegroundColor DarkGray }
}

if ($existentes.Count -gt 0) {
    Write-Host "  La base ya los tenia, quedan fuera ($($existentes.Count)):" -ForegroundColor Gray
    foreach ($o in $existentes) { Write-Host ("    {0,-10} {1}" -f $o.Tipo, $o.Nombre) -ForegroundColor DarkGray }
}

Write-Host "  En la migracion ($($aplicables.Count)):" -ForegroundColor Green
foreach ($a in $aplicables) { Write-Host ("    {0,-10} {1}" -f $a.Tipo, $a.Nombre) -ForegroundColor Green }

if ($advertencias.Count -gt 0) {
    Write-Host ''
    Write-Host '  Advertencias:' -ForegroundColor Yellow
    foreach ($a in $advertencias) { Write-Host "    - $a" -ForegroundColor Yellow }
}

Write-Host ''
Write-Host 'Siguientes pasos:' -ForegroundColor Cyan
Write-Host "  1. Completar down() en $relDestino" -ForegroundColor Cyan
Write-Host '  2. Revisar el SQL en el propio archivo (no en migrate --pretend: colapsa todo a una linea).' -ForegroundColor Cyan
Write-Host '  3. php artisan migrate' -ForegroundColor Cyan
Write-Host '  4. Compare/Diff del .dbm contra la base: debe salir 0/0/0.' -ForegroundColor Cyan
Write-Host '  5. Regenerar foto, doc y dump.sql en el submodulo. NO tocar 01-sql_def.sql.' -ForegroundColor Cyan
Write-Host ''
