<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';
    protected $description = 'Sincroniza permisos desde permissions_config.php a la base de datos';

    public function handle()
    {
        // Cargar la configuración
        $configPath = base_path('scripts/permissions_config.php');
        if (!file_exists($configPath)) {
            $this->error("❌ No se encontró: {$configPath}");
            return 1;
        }

        $config = require $configPath;
        if (!$config) {
            $this->error("❌ Error al cargar permissions_config.php");
            return 1;
        }

        // Extraer todos los permisos
        $permisos = $this->extractPermissions($config);

        // Agregar permiso wildcard principal
        $permisos = array_merge([
            '*' => ['slug' => '*', 'nombre' => '*']
        ], $permisos);

        // Ordenar por slug
        ksort($permisos);

        $this->info("✅ Se extrajeron " . count($permisos) . " permisos");

        // Sincronizar con BD
        $creados = 0;
        $actualizados = 0;
        $desactivados = 0;

        DB::beginTransaction();

        try {
            // Marcar todos como inactivos primero
            $permisosEnDb = DB::table('usuario.permiso')
                ->select('id_permiso', 'slug')
                ->get()
                ->keyBy('slug');

            // Crear o actualizar permisos desde config
            foreach ($permisos as $slug => $perm) {
                if (isset($permisosEnDb[$slug])) {
                    // Ya existe, solo asegurar que esté activo
                    DB::table('usuario.permiso')
                        ->where('slug', $slug)
                        ->update(['fue_borrado' => false]);
                    $actualizados++;
                } else {
                    // Crear nuevo
                    DB::table('usuario.permiso')->insert([
                        'slug' => $slug,
                        'nombre' => $perm['nombre'],
                        'fue_borrado' => false,
                    ]);
                    $creados++;
                }
            }

            // Desactivar permisos que estén en BD pero no en config
            $slugsEnConfig = array_keys($permisos);
            foreach ($permisosEnDb as $permEnDb) {
                if (!in_array($permEnDb->slug, $slugsEnConfig)) {
                    DB::table('usuario.permiso')
                        ->where('id_permiso', $permEnDb->id_permiso)
                        ->update(['fue_borrado' => true]);
                    $desactivados++;
                }
            }

            DB::commit();

            $this->info("\n📊 Estadísticas:");
            $this->info("  ✅ Creados: {$creados}");
            $this->info("  🔄 Actualizados: {$actualizados}");
            $this->info("  ❌ Desactivados: {$desactivados}");
            $this->info("\n✅ Sincronización completada exitosamente");
            $this->info("📌 Total de permisos activos: " . count($permisos));

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error durante la sincronización: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Extraer recursivamente todos los permisos de la config
     */
    private function extractPermissions($config, $prefix = '')
    {
        $permisos = [];

        foreach ($config as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            // Construir el path completo
            $path = $prefix ? "{$prefix}/{$key}" : $key;

            // Si tiene _actions, agregar los permisos de las acciones
            if (isset($value['_actions']) && is_array($value['_actions'])) {
                // Agregar el permiso de la categoría
                $permisos[$path] = [
                    'slug' => $path,
                    'nombre' => $path
                ];

                // Agregar permisos de las acciones
                foreach ($value['_actions'] as $action) {
                    $slug = "{$path}:{$action}";
                    $permisos[$slug] = [
                        'slug' => $slug,
                        'nombre' => $slug
                    ];
                }

                // Agregar wildcard
                $wildcard = "{$path}:*";
                $permisos[$wildcard] = [
                    'slug' => $wildcard,
                    'nombre' => $wildcard
                ];
            }

            // Procesar sub-elementos recursivamente
            $subElements = array_filter($value, fn($k) => $k !== '_actions', ARRAY_FILTER_USE_KEY);
            if (!empty($subElements)) {
                $subPermisos = $this->extractPermissions($subElements, $path);
                $permisos = array_merge($permisos, $subPermisos);
            }
        }

        return $permisos;
    }
}
