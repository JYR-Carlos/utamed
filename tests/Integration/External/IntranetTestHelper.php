<?php

namespace Tests\Integration\External;

use App\Models\External\VwInscripcion;
use Illuminate\Support\Facades\DB;

class IntranetTestHelper
{
    private static ?bool $isConnected = null;
    private static ?string $connectionError = null;
    private static ?VwInscripcion $sampleData = null;
    private static ?array $parsedOracleEnv = null;

    /**
     * Carga las credenciales de Oracle desde el archivo .env en la configuración de la aplicación actual.
     * Se ejecuta en cada test para asegurar que cada nueva instancia de la aplicación Laravel tenga la config.
     */
    public static function loadOracleConfig(): void
    {
        if (self::$parsedOracleEnv === null) {
            self::$parsedOracleEnv = [];
            if (file_exists(base_path('.env'))) {
                $envContent = file_get_contents(base_path('.env'));
                $lines = explode("\n", $envContent);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'ORACLE_') && str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        self::$parsedOracleEnv[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                    }
                }
            }
        }

        if (!empty(self::$parsedOracleEnv['ORACLE_DB_USERNAME'])) {
            config([
                'database.connections.oracle.host' => self::$parsedOracleEnv['ORACLE_DB_HOST'] ?? config('database.connections.oracle.host'),
                'database.connections.oracle.port' => self::$parsedOracleEnv['ORACLE_DB_PORT'] ?? config('database.connections.oracle.port', '1521'),
                'database.connections.oracle.database' => self::$parsedOracleEnv['ORACLE_DB_DATABASE'] ?? config('database.connections.oracle.database'),
                'database.connections.oracle.service_name' => self::$parsedOracleEnv['ORACLE_DB_SERVICE_NAME'] ?? config('database.connections.oracle.service_name'),
                'database.connections.oracle.username' => self::$parsedOracleEnv['ORACLE_DB_USERNAME'] ?? config('database.connections.oracle.username'),
                'database.connections.oracle.password' => self::$parsedOracleEnv['ORACLE_DB_PASSWORD'] ?? config('database.connections.oracle.password'),
                'database.connections.oracle.charset' => self::$parsedOracleEnv['ORACLE_DB_CHARSET'] ?? config('database.connections.oracle.charset', 'AL32UTF8'),
            ]);

            DB::purge('oracle');
        }
    }

    /**
     * Comprueba si la conexión a Oracle está activa y disponible.
     */
    public static function isConnected(): bool
    {
        self::loadOracleConfig();

        if (self::$isConnected !== null) {
            return self::$isConnected;
        }

        if (empty(config('database.connections.oracle.username'))) {
            self::$isConnected = false;
            self::$connectionError = 'Credenciales de Oracle no configuradas en el archivo .env.';
            return false;
        }

        try {
            DB::connection('oracle')->select('SELECT 1 FROM DUAL');
            self::$isConnected = true;
            self::$connectionError = null;
        } catch (\Throwable $e) {
            self::$isConnected = false;
            self::$connectionError = $e->getMessage();
        }

        return self::$isConnected;
    }

    /**
     * Asegura que Oracle esté conectado. Si no lo está, salta el test actual.
     */
    public static function ensureConnected($test): void
    {
        self::loadOracleConfig();

        if (!self::isConnected()) {
            $msg = self::$connectionError ?? 'Conexión a Oracle no disponible (requiere VPN / Red interna).';
            $test->markTestSkipped("Saltando test de Intranet: {$msg}");
        }
    }

    /**
     * Obtiene un registro real de muestra desde Oracle (Inscripción + Curso + Alumno)
     * para alimentar los tests de servicios sin datos ficticios.
     */
    public static function getSampleRealData(): ?VwInscripcion
    {
        if (self::$sampleData !== null) {
            return self::$sampleData;
        }

        if (!self::isConnected()) {
            return null;
        }

        try {
            self::$sampleData = VwInscripcion::with(['alumno', 'carreraCurso'])->first();
            return self::$sampleData;
        } catch (\Throwable) {
            return null;
        }
    }
}
