<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use PDO;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn("Iniciando seed con PDO directo para máximo rendimiento...");

        // Get database credentials from config
        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');
        $dbname = config('database.connections.pgsql.database');
        $user = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        try {
            // Create direct PDO connection
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 2. Crear Contexto Global (ya se hizo en DatabaseSeeder, pero verificamos)
            $stmt = $pdo->query('SELECT id_contexto FROM "utamed.Usuario"."Contexto" WHERE contexto_display = \'Global\'');
            $contextoId = $stmt->fetchColumn();
            $this->command->info("✓ Contexto ID: $contextoId");

            // 3. Crear Permisos
            $permissions = [
                ['curso:*', 'Control total de cursos', 'Administrativo'],
                ['curso:crear', 'Crear cursos', 'Administrativo'],
                ['actividad:*', 'Gestionar actividades', 'Docencia'],
                ['*', 'Super Admin Access', 'Sistema']
            ];

            foreach ($permissions as [$slug, $nombre, $descripcion]) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO "utamed.Usuario"."Permiso" (slug, nombre, descripcion) 
                                           VALUES (?, ?, ?)');
                    $stmt->execute([$slug, $nombre, $descripcion]);
                } catch (\PDOException $e) {
                    // Ignore duplicate key errors
                    if (strpos($e->getMessage(), 'duplicate key') === false) {
                        throw $e;
                    }
                }
            }
            $this->command->info("✓ Permisos creados");

            // 4. Crear Usuario Admin
            $passHash = password_hash('admin123', PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare('INSERT INTO "utamed.Usuario"."Usuario" (username, passhash, rut, nombre1, apellido1) 
                                       VALUES (?, ?, ?, ?, ?)');
                $stmt->execute(['system_admin', $passHash, '00000000-1', 'System', 'Admin']);
            } catch (\PDOException $e) {
                // Ignore duplicate key errors
                if (strpos($e->getMessage(), 'duplicate key') === false && strpos($e->getMessage(), 'unique') === false) {
                    throw $e;
                }
            }

            $stmt = $pdo->query('SELECT id_usuario FROM "utamed.Usuario"."Usuario" WHERE username = \'system_admin\'');
            $adminId = $stmt->fetchColumn();
            $this->command->info("✓ Usuario ID: $adminId");

            // 5. Crear Rol Super Admin
            try {
                $stmt = $pdo->prepare('INSERT INTO "utamed.Usuario"."Rol" (nombre, id_usuario_autor) 
                                       VALUES (?, ?)');
                $stmt->execute(['Super Admin', $adminId]);
            } catch (\PDOException $e) {
                // Ignore duplicate key errors
                if (strpos($e->getMessage(), 'duplicate key') === false && strpos($e->getMessage(), 'unique') === false) {
                    throw $e;
                }
            }

            $stmt = $pdo->query('SELECT id_rol FROM "utamed.Usuario"."Rol" WHERE nombre = \'Super Admin\'');
            $rolId = $stmt->fetchColumn();
            $this->command->info("✓ Rol ID: $rolId");

            // 6. Asignación de Rol usando SQL directo para máximo rendimiento
            $now = date('Y-m-d H:i:s');
            $future = date('Y-m-d H:i:s', strtotime('+100 years'));

            $stmt = $pdo->prepare('
                INSERT INTO "utamed.Usuario"."Usuario_Rol_Asignación" (
                    id_usuario_recipiente,
                    id_rol,
                    id_contexto,
                    id_usuario_asignador,
                    asignado_por,
                    fecha_inicio_planificada,
                    fecha_fin_planificada,
                    esta_activo,
                    fue_eliminado,
                    fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (id_contexto, id_rol, id_usuario_recipiente, id_usuario_asignador) 
                DO UPDATE SET
                    fecha_inicio_planificada = EXCLUDED.fecha_inicio_planificada,
                    fecha_fin_planificada = EXCLUDED.fecha_fin_planificada,
                    esta_activo = EXCLUDED.esta_activo,
                    fue_eliminado = EXCLUDED.fue_eliminado
            ');

            $stmt->execute([
                $adminId,    // id_usuario_recipiente
                $rolId,      // id_rol
                $contextoId, // id_contexto
                $adminId,    // id_usuario_asignador
                $adminId,    // asignado_por
                $now,        // fecha_inicio_planificada
                $future,     // fecha_fin_planificada
                1,           // esta_activo (true)
                0,           // fue_eliminado (false)
                $now         // fecha_creacion
            ]);

            $this->command->info('✓ Asignación de rol completada');
            $this->command->info('¡Seed completado con éxito usando PDO directo!');

        } catch (\PDOException $e) {
            $this->command->error("Error en el seed: " . $e->getMessage());
            throw $e;
        }
    }
}