<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use PDO;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Get database credentials from config
        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');
        $dbname = config('database.connections.pgsql.database');
        $user = config('database.connections.pgsql.username');
        $pass = config('database.connections.pgsql.password');

        try {
            // Create direct PDO connection
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 1. Definimos el nombre de la tabla exactamente como Postgres lo necesita
            $tablaContexto = '"utamed.Usuario"."Contexto"';

            // 2. Verificamos si existe usando PDO directo
            $stmt = $pdo->query("SELECT 1 FROM $tablaContexto WHERE contexto_display = 'Global'");
            $existe = $stmt->fetchColumn();

            if (!$existe) {
                $this->command->info("Creando contexto Global...");
                // 3. Insertamos usando PDO directo
                $pdo->exec("INSERT INTO $tablaContexto (contexto_display) VALUES ('Global')");
            } else {
                $this->command->info("El contexto Global ya existe.");
            }

            // 4. Llamamos al siguiente seeder
            $this->call(RoleAndPermissionSeeder::class);

            // 5. Seeders de Referencia
            $this->call(TipoSeccionSeeder::class);

            // 6. Importar datos de negocio (Facultades, Planes, Asignaturas)
            $this->command->info("--- Importando datos masivos ---");
            $this->call(DataImportSeeder::class);

        } catch (\PDOException $e) {
            $this->command->error("Error en DatabaseSeeder: " . $e->getMessage());
            throw $e;
        }
    }
}