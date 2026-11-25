<?php

namespace App\Console\Commands;

use App\Services\Authorization\RolesPermissionsConfigSyncService;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync
        {--roles : Validar y sincronizar roles desde scripts/roles_config.php}
        {--verify : Auditar diferencias entre scripts/*_config.php y la BD sin realizar cambios}';

    protected $description = 'Gestiona y sincroniza tipos de contexto, permisos y roles en vivo desde scripts/*_config.php';

    public function handle(RolesPermissionsConfigSyncService $syncService): int
    {
        try {
            if ($this->option('verify')) {
                $audit = $syncService->auditDatabaseDrift();
                if ($audit['in_sync']) {
                    $this->info('✅ Base de datos alineada con permissions_config.php y roles_config.php.');
                    return self::SUCCESS;
                }

                if (!empty($audit['missing_context_types'])) {
                    $this->warn('⚠️  Tipos de contexto faltantes: ' . implode(', ', $audit['missing_context_types']));
                }
                if (!empty($audit['missing_permissions'])) {
                    $this->warn('⚠️  Permisos faltantes en BD: ' . implode(', ', $audit['missing_permissions']));
                }
                if (!empty($audit['missing_roles'])) {
                    $this->warn('⚠️  Roles faltantes en BD: ' . implode(', ', $audit['missing_roles']));
                }
                foreach ($audit['roles_diff'] as $role => $diff) {
                    $this->warn(sprintf(
                        "⚠️  Drift en rol '%s' → faltan: %d, sobran: %d, delegabilidad distinta: %d",
                        $role,
                        count($diff['missing_in_db']),
                        count($diff['extra_in_db']),
                        count($diff['delegabilidad_mismatch'])
                    ));
                }

                return self::FAILURE;
            }

            $ctxStats = $syncService->validateAndSyncContextTypesFromConfig();
            $this->info(
                "✅ Tipos de contexto validados: {$ctxStats['total']} "
                . '(insertados: ' . count($ctxStats['inserted'])
                . ', actualizados: ' . count($ctxStats['updated']) . ')'
            );

            $permStats = $syncService->syncPermissionsFromConfig();
            $this->info(
                "✅ Permisos procesados desde scripts/permissions_config.php: {$permStats['total']} "
                . '(insertados: ' . count($permStats['inserted']) . ')'
            );

            if ($this->option('roles')) {
                $roleStats = $syncService->syncRolesFromConfig();
                $this->info(
                    "✅ Roles sincronizados desde scripts/roles_config.php: {$roleStats['roles_synced']} "
                    . "(+{$roleStats['permisos_inserted']} / ~{$roleStats['permisos_updated']} / -{$roleStats['permisos_deleted']})"
                );
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error durante la gestión de permisos: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
