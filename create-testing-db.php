<?php

/**
 * Script para crear la base de datos de testing
 */

$host = '127.0.0.1';
$port = '15432';
$username = 'utamed';
$password = 'utamed';
$testDb = 'utamed_testing';

try {
    // Conectar a la base de datos postgres
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=postgres", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conectado a PostgreSQL\n";

    // Verificar si la base de datos existe
    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '$testDb'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "La base de datos '$testDb' ya existe.\n";
        echo "¿Desea recrearla? (s/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim(strtolower($line)) === 's') {
            // Terminar conexiones activas
            $pdo->exec("
                SELECT pg_terminate_backend(pg_stat_activity.pid)
                FROM pg_stat_activity
                WHERE pg_stat_activity.datname = '$testDb'
                AND pid <> pg_backend_pid()
            ");

            // Eliminar base de datos
            $pdo->exec("DROP DATABASE $testDb");
            echo "Base de datos eliminada.\n";
        } else {
            echo "Manteniendo base de datos existente.\n";
            exit(0);
        }
    }

    // Crear base de datos
    $pdo->exec("CREATE DATABASE $testDb OWNER $username");
    echo "✓ Base de datos '$testDb' creada exitosamente.\n\n";

    echo "Ahora ejecuta las migraciones:\n";
    echo "  php artisan migrate --env=testing\n\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
