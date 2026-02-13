@echo off
echo Configurando base de datos de testing para UTAMED...
echo.

REM Verificar si la base de datos existe
php -r "try { $pdo = new PDO('pgsql:host=127.0.0.1;port=15432;dbname=utamed_testing', 'utamed', 'utamed'); echo 'Base de datos utamed_testing ya existe.'; exit(0); } catch (Exception $e) { echo 'Base de datos no existe, creando...'; exit(1); }"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ¿Desea recrear la base de datos? ^(S/N^)
    set /p RECREATE=
    if /i "%RECREATE%"=="S" (
        echo Eliminando base de datos existente...
        php -r "$pdo = new PDO('pgsql:host=127.0.0.1;port=15432;dbname=postgres', 'utamed', 'utamed'); $pdo->exec('DROP DATABASE IF EXISTS utamed_testing;'); echo 'Base de datos eliminada.';"
        goto CREATE_DB
    ) else (
        goto MIGRATE
    )
)

:CREATE_DB
echo Creando base de datos utamed_testing...
php -r "$pdo = new PDO('pgsql:host=127.0.0.1;port=15432;dbname=postgres', 'utamed', 'utamed'); $pdo->exec('CREATE DATABASE utamed_testing OWNER utamed;'); echo 'Base de datos creada exitosamente.';"

if %ERRORLEVEL% NEQ 0 (
    echo Error al crear la base de datos.
    exit /b 1
)

:MIGRATE
echo.
echo Ejecutando migraciones...
php artisan migrate --env=testing --force

if %ERRORLEVEL% NEQ 0 (
    echo Error al ejecutar migraciones.
    exit /b 1
)

echo.
echo ========================================
echo Base de datos de testing configurada!
echo ========================================
echo.
echo Ahora puedes ejecutar:
echo   php artisan test
echo.
