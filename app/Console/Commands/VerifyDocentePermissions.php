<?php

namespace App\Console\Commands;

use App\Services\Authorization\RolesPermissionsConfigSyncService;
use Illuminate\Console\Command;

class VerifyDocentePermissions extends Command
{
    protected $signature = 'verify:docente-perms';
    protected $description = 'Audita el alineamiento entre permissions_config.php / roles_config.php y la BD actual';

    public function handle(RolesPermissionsConfigSyncService $syncService): int
    {
        try {
            $audit = $syncService->auditDatabaseDrift();
            $rolesConfig = $syncService->loadAndValidateRolesFromConfig();
        } catch (\Throwable $e) {
            $this->error('❌ Error validando configuración: ' . $e->getMessage());
            return self::FAILURE;
        }

        $docenteRoles = ['Docente Titular', 'Docente Componente', 'Docente Componente Colegiado'];

        $this->line(str_repeat('=', 80));
        $this->line('🔍 AUDITORÍA DE PERMISOS Y ROLES DOCENTES (DESDE scripts/*_config.php)');
        $this->line(str_repeat('=', 80));

        foreach ($docenteRoles as $roleName) {
            $expectedCount = count($rolesConfig[$roleName]['permisos'] ?? []);
            $diff = $audit['roles_diff'][$roleName] ?? null;

            if (in_array($roleName, $audit['missing_roles'], true)) {
                $this->error("❌ {$roleName}: ausente en usuario.rol (esperados {$expectedCount} permisos).");
            } elseif ($diff === null) {
                $this->info("✅ {$roleName}: sincronizado ({$expectedCount} permisos + delegabilidad).");
            } else {
                $this->warn(sprintf(
                    "⚠️  %s: faltan %d, sobran %d, delegabilidad distinta %d.",
                    $roleName,
                    count($diff['missing_in_db']),
                    count($diff['extra_in_db']),
                    count($diff['delegabilidad_mismatch'])
                ));
            }
        }

        if ($audit['in_sync']) {
            $this->info("\n✅ Estado global en BD alineado con los artefactos de permisos.");
            return self::SUCCESS;
        }

        $this->warn("\n⚠️  Se detectó drift. Ejecuta: php artisan permissions:sync --roles");
        return self::FAILURE;
    }
}
