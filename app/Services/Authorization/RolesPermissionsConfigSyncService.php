<?php

namespace App\Services\Authorization;

use App\Enums\ContextType;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Servicio central de gestión y sincronización de permisos, tipos de contexto y roles
 * contra los artefactos/scripts fuente del proyecto:
 *
 * - `scripts/permissions_config.php`
 * - `scripts/roles_config.php`
 * - `App\Support\Permissions` + `config/permission-context-metadata.php` + `PermissionContextConstraints`
 *
 * Opera en vivo sobre la base de datos de forma idempotente (sin regenerar SQL
 * en `database-model` y sin hardcodear slugs/matrices en migraciones o comandos),
 * validando estructura, tipos de contexto y compatibilidad para evitar roles malformados.
 */
class RolesPermissionsConfigSyncService
{
    /**
     * Valida `_valid_context_types` y sincroniza `usuario.tipo_contexto` + el contexto global.
     *
     * @return array{total: int, inserted: list<string>, updated: list<string>, global_context_created: bool}
     */
    public function validateAndSyncContextTypesFromConfig(?string $permissionsConfigPath = null): array
    {
        $config = $this->loadPermissionsConfig($permissionsConfigPath);

        if (!isset($config['_valid_context_types']) || !is_array($config['_valid_context_types'])) {
            throw new \RuntimeException(
                "permissions_config.php no tiene '_valid_context_types'."
            );
        }

        $validContextTypes = $config['_valid_context_types'];
        $this->assertValidContextReferences($config, $validContextTypes);

        $existing = DB::table('usuario.tipo_contexto')
            ->get(['id_tipo_contexto', 'categoria', 'tabla_referenciada'])
            ->keyBy('categoria');

        $inserted = [];
        $updated = [];

        foreach ($validContextTypes as $categoria => $tablaRef) {
            if (!$existing->has($categoria)) {
                DB::table('usuario.tipo_contexto')->insert([
                    'categoria'          => $categoria,
                    'tabla_referenciada' => $tablaRef,
                ]);
                $inserted[] = $categoria;
                continue;
            }

            if ($existing[$categoria]->tabla_referenciada !== $tablaRef) {
                DB::table('usuario.tipo_contexto')
                    ->where('id_tipo_contexto', $existing[$categoria]->id_tipo_contexto)
                    ->update(['tabla_referenciada' => $tablaRef]);
                $updated[] = $categoria;
            }
        }

        $globalCreated = false;
        $globalTypeId = DB::table('usuario.tipo_contexto')
            ->where('categoria', 'global')
            ->value('id_tipo_contexto');

        if ($globalTypeId !== null) {
            $hasGlobalCtx = DB::table('usuario.contexto')
                ->where('id_tipo_contexto', $globalTypeId)
                ->exists();

            if (!$hasGlobalCtx) {
                DB::table('usuario.contexto')->insert([
                    'contexto_display' => 'Contexto Global | Solo Permisos Administrativos',
                    'id_tipo_contexto' => $globalTypeId,
                ]);
                $globalCreated = true;
            }
        }

        return [
            'total'                  => count($validContextTypes),
            'inserted'               => $inserted,
            'updated'                => $updated,
            'global_context_created' => $globalCreated,
        ];
    }

    /**
     * Extrae todos los slugs de permisos desde `scripts/permissions_config.php`,
     * incluyendo `_parent_actions`, `_actions`, wildcards `:*` y `*`.
     *
     * @return list<string>
     */
    public function extractPermissionSlugsFromConfig(?string $permissionsConfigPath = null): array
    {
        $config = $this->loadPermissionsConfig($permissionsConfigPath);

        $slugs = ['*' => true];
        foreach ($this->extractTreeSlugs($config) as $slug) {
            $slugs[$slug] = true;
        }

        $result = array_keys($slugs);
        sort($result);

        return $result;
    }

    /**
     * Carga y valida `scripts/roles_config.php` usando el contrato
     * `[Permissions::CASE, bool $puedeDelegar]` y validando compatibilidad de contextos.
     *
     * @return array<string, array{es_administrativo: bool, permisos: array<string, bool>}>
     */
    public function loadAndValidateRolesFromConfig(?string $rolesConfigPath = null): array
    {
        $path = $rolesConfigPath ?? base_path('scripts/roles_config.php');
        if (!file_exists($path)) {
            throw new \RuntimeException("No se encontró roles_config.php en: {$path}");
        }

        $rolesConfig = require $path;
        if (!is_array($rolesConfig) || empty($rolesConfig)) {
            throw new \RuntimeException("roles_config.php debe retornar un array no vacío.");
        }

        $validated = [];
        foreach ($rolesConfig as $roleName => $roleConfig) {
            $validated[$roleName] = $this->validateRoleDefinition($roleName, $roleConfig);
        }

        return $validated;
    }

    /**
     * Inserta en `usuario.permiso` los permisos faltantes definidos en
     * `scripts/permissions_config.php`, verificando coherencia con `Permissions` enum.
     *
     * @return array{total: int, inserted: list<string>}
     */
    public function syncPermissionsFromConfig(?string $permissionsConfigPath = null): array
    {
        $slugs = $this->extractPermissionSlugsFromConfig($permissionsConfigPath);

        $invalidSlugs = array_values(array_filter(
            $slugs,
            fn (string $slug) => Permissions::tryFrom($slug) === null
        ));

        if (!empty($invalidSlugs)) {
            throw new \RuntimeException(
                'Hay permisos en scripts/permissions_config.php que no existen en App\\Support\\Permissions: '
                . implode(', ', $invalidSlugs)
                . '. Regenera los artefactos con `php scripts/generate_models.php`.'
            );
        }

        $existingSlugs = DB::table('usuario.permiso')
            ->whereIn('slug', $slugs)
            ->pluck('slug')
            ->all();
        $existingSet = array_flip($existingSlugs);

        $inserted = [];
        $rowsToInsert = [];
        foreach ($slugs as $slug) {
            if (isset($existingSet[$slug])) {
                continue;
            }
            $rowsToInsert[] = [
                'slug'        => $slug,
                'nombre'      => $slug,
                'descripcion' => $slug,
            ];
            $inserted[] = $slug;
        }

        if (!empty($rowsToInsert)) {
            DB::table('usuario.permiso')->insert($rowsToInsert);
        }

        return [
            'total'    => count($slugs),
            'inserted' => $inserted,
        ];
    }

    /**
     * Valida y sincroniza roles y su `puede_delegar_permiso` desde `scripts/roles_config.php`.
     *
     * @param list<string>|null $onlyRoles Si se especifica, aplica cambios solo a esos roles (tras validar todos).
     * @return array{roles_synced: int, permisos_inserted: int, permisos_updated: int, permisos_deleted: int}
     */
    public function syncRolesFromConfig(
        ?array $onlyRoles = null,
        ?int $creadoPorId = null,
        ?string $rolesConfigPath = null
    ): array {
        $allValidated = $this->loadAndValidateRolesFromConfig($rolesConfigPath);
        $targets = $onlyRoles !== null
            ? array_intersect_key($allValidated, array_flip($onlyRoles))
            : $allValidated;

        $actorId = $creadoPorId ?? $this->resolveFallbackActorId();
        $permisoIdsBySlug = DB::table('usuario.permiso')->pluck('id_permiso', 'slug');

        $stats = [
            'roles_synced'      => 0,
            'permisos_inserted' => 0,
            'permisos_updated'  => 0,
            'permisos_deleted'  => 0,
        ];

        foreach ($targets as $roleName => $roleData) {
            $rolId = $this->ensureRoleRow($roleName, $roleData['es_administrativo'], $actorId);
            $diff = $this->syncRolePermissions($rolId, $roleName, $roleData['permisos'], $permisoIdsBySlug);

            $stats['roles_synced']++;
            $stats['permisos_inserted'] += $diff['inserted'];
            $stats['permisos_updated']  += $diff['updated'];
            $stats['permisos_deleted']  += $diff['deleted'];
        }

        return $stats;
    }

    /**
     * Consolida y elimina un rol legado (incluyendo duplicados históricos)
     * migrando sus asignaciones hacia el rol destino.
     *
     * @return int Cantidad de filas de rol legado eliminadas.
     */
    public function mergeAndDeleteLegacyRole(string $legacyRoleName, string $targetRoleName): int
    {
        $targetRolId = DB::table('usuario.rol')
            ->where('nombre', $targetRoleName)
            ->whereNull('fecha_eliminacion')
            ->value('id_rol');

        if ($targetRolId === null) {
            throw new \RuntimeException("No se encontró el rol destino '{$targetRoleName}' en usuario.rol.");
        }

        $legacyIds = DB::table('usuario.rol')
            ->where('nombre', $legacyRoleName)
            ->pluck('id_rol')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($legacyIds)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($legacyIds), '?'));

        DB::statement("
            UPDATE usuario.usuario_rol_asignacion ura_leg
            SET esta_activo = false,
                fue_eliminado = true,
                fecha_fin_real = COALESCE(ura_leg.fecha_fin_real, NOW())
            WHERE ura_leg.id_rol IN ({$placeholders})
              AND ura_leg.esta_activo = true
              AND EXISTS (
                  SELECT 1 FROM usuario.usuario_rol_asignacion ura_target
                  WHERE ura_target.id_usuario = ura_leg.id_usuario
                    AND ura_target.id_contexto = ura_leg.id_contexto
                    AND ura_target.id_rol = ?
                    AND ura_target.esta_activo = true
                    AND COALESCE(ura_target.fue_eliminado, false) = false
              )
        ", [...$legacyIds, (int) $targetRolId]);

        DB::table('usuario.usuario_rol_asignacion')
            ->whereIn('id_rol', $legacyIds)
            ->update(['id_rol' => (int) $targetRolId]);

        DB::table('usuario.asignacion_rol_permiso')->whereIn('id_rol', $legacyIds)->delete();

        return DB::table('usuario.rol')->whereIn('id_rol', $legacyIds)->delete();
    }

    /**
     * Audita sin mutar la BD las diferencias entre `scripts/*_config.php` y las tablas reales.
     *
     * @return array{
     *   in_sync: bool,
     *   missing_context_types: list<string>,
     *   missing_permissions: list<string>,
     *   missing_roles: list<string>,
     *   roles_diff: array<string, array{missing_in_db: list<string>, extra_in_db: list<string>, delegabilidad_mismatch: list<string>}>
     * }
     */
    public function auditDatabaseDrift(
        ?string $permissionsConfigPath = null,
        ?string $rolesConfigPath = null
    ): array {
        $permConfig = $this->loadPermissionsConfig($permissionsConfigPath);
        $validContextTypes = $permConfig['_valid_context_types'] ?? [];
        $this->assertValidContextReferences($permConfig, $validContextTypes);

        $dbContextTypes = DB::table('usuario.tipo_contexto')->pluck('categoria')->all();
        $missingContextTypes = array_values(array_diff(array_keys($validContextTypes), $dbContextTypes));

        $expectedSlugs = $this->extractPermissionSlugsFromConfig($permissionsConfigPath);
        $dbSlugs = DB::table('usuario.permiso')->whereIn('slug', $expectedSlugs)->pluck('slug')->all();
        $missingPermissions = array_values(array_diff($expectedSlugs, $dbSlugs));

        $validatedRoles = $this->loadAndValidateRolesFromConfig($rolesConfigPath);
        $dbRoles = DB::table('usuario.rol')
            ->whereNull('fecha_eliminacion')
            ->pluck('id_rol', 'nombre')
            ->all();

        $missingRoles = [];
        $rolesDiff = [];

        foreach ($validatedRoles as $roleName => $roleData) {
            if (!isset($dbRoles[$roleName])) {
                $missingRoles[] = $roleName;
                continue;
            }

            $expectedPerms = $roleData['permisos'];
            $dbPerms = DB::table('usuario.asignacion_rol_permiso as arp')
                ->join('usuario.permiso as p', 'p.id_permiso', '=', 'arp.id_permiso')
                ->where('arp.id_rol', $dbRoles[$roleName])
                ->pluck('arp.puede_delegar_permiso', 'p.slug')
                ->map(fn ($v) => (bool) $v)
                ->all();

            $missingInDb = array_values(array_diff(array_keys($expectedPerms), array_keys($dbPerms)));
            $extraInDb = array_values(array_diff(array_keys($dbPerms), array_keys($expectedPerms)));
            $delegabilidadMismatch = [];

            foreach ($expectedPerms as $slug => $expectedDelegable) {
                if (array_key_exists($slug, $dbPerms) && $dbPerms[$slug] !== $expectedDelegable) {
                    $delegabilidadMismatch[] = $slug;
                }
            }

            if (!empty($missingInDb) || !empty($extraInDb) || !empty($delegabilidadMismatch)) {
                $rolesDiff[$roleName] = [
                    'missing_in_db'          => $missingInDb,
                    'extra_in_db'            => $extraInDb,
                    'delegabilidad_mismatch' => $delegabilidadMismatch,
                ];
            }
        }

        return [
            'in_sync'               => empty($missingContextTypes) && empty($missingPermissions) && empty($missingRoles) && empty($rolesDiff),
            'missing_context_types' => $missingContextTypes,
            'missing_permissions'   => $missingPermissions,
            'missing_roles'         => $missingRoles,
            'roles_diff'            => $rolesDiff,
        ];
    }

    /**
     * Sincroniza tipos de contexto, permisos (`permissions_config.php`) y roles (`roles_config.php`).
     *
     * @param list<string>|null $onlyRoles
     * @return array{
     *   context_types: array{total: int, inserted: list<string>, updated: list<string>, global_context_created: bool},
     *   permissions: array{total: int, inserted: list<string>},
     *   roles: array{roles_synced: int, permisos_inserted: int, permisos_updated: int, permisos_deleted: int}
     * }
     */
    public function syncAll(?array $onlyRoles = null, ?int $creadoPorId = null): array
    {
        return DB::transaction(function () use ($onlyRoles, $creadoPorId) {
            $contextTypes = $this->validateAndSyncContextTypesFromConfig();
            $permissions = $this->syncPermissionsFromConfig();
            $roles = $this->syncRolesFromConfig($onlyRoles, $creadoPorId);

            return [
                'context_types' => $contextTypes,
                'permissions'   => $permissions,
                'roles'         => $roles,
            ];
        });
    }

    private function loadPermissionsConfig(?string $permissionsConfigPath = null): array
    {
        $path = $permissionsConfigPath ?? base_path('scripts/permissions_config.php');
        if (!file_exists($path)) {
            throw new \RuntimeException("No se encontró permissions_config.php en: {$path}");
        }

        $config = require $path;
        if (!is_array($config) || empty($config)) {
            throw new \RuntimeException('permissions_config.php debe retornar un array no vacío.');
        }

        return $config;
    }

    private function assertValidContextReferences(array $node, array $validContextTypes, string $path = ''): void
    {
        foreach (['_valid_context', '_valid_parent_context'] as $metaKey) {
            if (!isset($node[$metaKey])) {
                continue;
            }
            $val = $node[$metaKey];
            if (!is_string($val) || !isset($validContextTypes[$val])) {
                $allowed = implode(', ', array_keys($validContextTypes));
                throw new \RuntimeException(
                    "Tipo de contexto inválido en '{$path}.{$metaKey}': '{$val}'. Válidos: {$allowed}."
                );
            }
        }

        foreach ($node as $key => $value) {
            if (!is_string($key) || ($key !== '' && $key[0] === '_') || !is_array($value)) {
                continue;
            }
            $nextPath = $path !== '' ? "{$path}.{$key}" : $key;
            $this->assertValidContextReferences($value, $validContextTypes, $nextPath);
        }
    }

    /**
     * @return list<string>
     */
    private function extractTreeSlugs(array $node, string $prefix = ''): array
    {
        $slugs = [];

        foreach ($node as $key => $value) {
            if (!is_string($key) || ($key !== '' && $key[0] === '_') || !is_array($value)) {
                continue;
            }

            $path = $prefix !== '' ? "{$prefix}/{$key}" : $key;

            if (isset($value['_parent_actions']) && is_array($value['_parent_actions'])) {
                foreach ($value['_parent_actions'] as $action) {
                    $slugs[] = "{$path}:{$action}";
                }
            }

            if (isset($value['_actions']) && is_array($value['_actions'])) {
                foreach ($value['_actions'] as $action) {
                    $slugs[] = "{$path}:{$action}";
                }
                $slugs[] = "{$path}:*";
            }

            array_push($slugs, ...$this->extractTreeSlugs($value, $path));
        }

        return $slugs;
    }

    /**
     * @return array{es_administrativo: bool, permisos: array<string, bool>}
     */
    private function validateRoleDefinition(mixed $roleName, mixed $roleConfig): array
    {
        if (!is_string($roleName) || trim($roleName) === '') {
            throw new \RuntimeException('Cada rol en roles_config.php debe tener un nombre string no vacío.');
        }

        if (!is_array($roleConfig)
            || !array_key_exists('es_administrativo', $roleConfig)
            || !is_bool($roleConfig['es_administrativo'])
            || !array_key_exists('permisos', $roleConfig)
            || !is_array($roleConfig['permisos'])
        ) {
            throw new \RuntimeException(
                "Rol '{$roleName}' malformado en roles_config.php: requiere 'es_administrativo' (bool) y 'permisos' (array)."
            );
        }

        $permMap = [];
        $permEnums = [];

        foreach ($roleConfig['permisos'] as $idx => $tuple) {
            if (!is_array($tuple) || count($tuple) !== 2 || !array_is_list($tuple)) {
                throw new \RuntimeException(
                    "Permiso inválido en rol '{$roleName}' (índice {$idx}): esperado [Permissions::CASE, bool]."
                );
            }

            [$permission, $puedeDelegar] = $tuple;

            if (!($permission instanceof Permissions) || !is_bool($puedeDelegar)) {
                throw new \RuntimeException(
                    "Tupla inválida en rol '{$roleName}' (índice {$idx}): esperado [Permissions enum, bool]."
                );
            }

            if (array_key_exists($permission->value, $permMap)) {
                throw new \RuntimeException(
                    "Permiso duplicado '{$permission->value}' en rol '{$roleName}'."
                );
            }

            $permMap[$permission->value] = $puedeDelegar;
            $permEnums[] = $permission;
        }

        if (!empty($permEnums)) {
            $compatibleContexts = PermissionContextConstraints::getCompatibleContexts($permEnums);
            if (empty($compatibleContexts)) {
                throw new \RuntimeException(
                    "Rol '{$roleName}' malformado: no tiene contextos compatibles entre sus permisos."
                );
            }

            // Ninguno es exclusivo de 'global', pero la intersección colapsó a solo 'global':
            // indica mezcla de permisos de ramas incompatibles (ej. curso + componente).
            $allHaveNonGlobalContext = collect($permEnums)->every(function (Permissions $perm): bool {
                $contexts = PermissionContextConstraints::validContextTypesFor($perm);
                return count(array_filter($contexts, fn (ContextType $c) => $c !== ContextType::GLOBAL)) > 0;
            });

            $sharedNonGlobal = array_filter(
                $compatibleContexts,
                fn (ContextType $c) => $c !== ContextType::GLOBAL
            );

            if ($allHaveNonGlobalContext && empty($sharedNonGlobal)) {
                throw new \RuntimeException(
                    "Rol '{$roleName}' malformado: mezcla permisos de jerarquías incompatibles sin contextos no-globales en común."
                );
            }
        }

        return [
            'es_administrativo' => $roleConfig['es_administrativo'],
            'permisos'          => $permMap,
        ];
    }

    private function resolveFallbackActorId(): int
    {
        $superadminId = DB::table('usuario.usuario')->where('username', 'superadmin')->value('id_usuario');
        if ($superadminId !== null) {
            return (int) $superadminId;
        }

        return (int) (DB::table('usuario.usuario')->orderBy('id_usuario')->value('id_usuario') ?? 1);
    }

    private function ensureRoleRow(string $roleName, bool $esAdministrativo, int $actorId): int
    {
        $existing = DB::table('usuario.rol')
            ->where('nombre', $roleName)
            ->whereNull('fecha_eliminacion')
            ->first(['id_rol', 'es_administrativo']);

        if ($existing !== null) {
            if ((bool) $existing->es_administrativo !== $esAdministrativo) {
                DB::table('usuario.rol')
                    ->where('id_rol', $existing->id_rol)
                    ->update(['es_administrativo' => $esAdministrativo]);
            }

            return (int) $existing->id_rol;
        }

        $row = DB::selectOne(
            'INSERT INTO usuario.rol (nombre, es_administrativo, creado_por)
             VALUES (?, ?, ?)
             RETURNING id_rol',
            [$roleName, $esAdministrativo, $actorId]
        );

        return (int) $row->id_rol;
    }

    /**
     * @param array<string, bool>                        $desiredBySlug
     * @param \Illuminate\Support\Collection<string,int> $permisoIdsBySlug
     * @return array{inserted: int, updated: int, deleted: int}
     */
    private function syncRolePermissions(int $rolId, string $roleName, array $desiredBySlug, $permisoIdsBySlug): array
    {
        $desiredById = [];
        foreach ($desiredBySlug as $slug => $puedeDelegar) {
            $idPermiso = $permisoIdsBySlug[$slug] ?? null;
            if ($idPermiso === null) {
                throw new \RuntimeException(
                    "El rol '{$roleName}' referencia el permiso '{$slug}', ausente en usuario.permiso."
                );
            }
            $desiredById[(int) $idPermiso] = $puedeDelegar;
        }

        $current = DB::table('usuario.asignacion_rol_permiso')
            ->where('id_rol', $rolId)
            ->pluck('puede_delegar_permiso', 'id_permiso')
            ->map(fn ($v) => (bool) $v)
            ->all();

        $toDeleteIds = array_values(array_diff(array_keys($current), array_keys($desiredById)));
        $deleted = 0;
        if (!empty($toDeleteIds)) {
            $deleted = DB::table('usuario.asignacion_rol_permiso')
                ->where('id_rol', $rolId)
                ->whereIn('id_permiso', $toDeleteIds)
                ->delete();
        }

        $updated = 0;
        $toInsert = [];
        foreach ($desiredById as $idPermiso => $puedeDelegar) {
            if (!array_key_exists($idPermiso, $current)) {
                $toInsert[] = [
                    'id_rol'                => $rolId,
                    'id_permiso'            => $idPermiso,
                    'puede_delegar_permiso' => $puedeDelegar,
                ];
                continue;
            }

            if ($current[$idPermiso] !== $puedeDelegar) {
                $updated += DB::table('usuario.asignacion_rol_permiso')
                    ->where('id_rol', $rolId)
                    ->where('id_permiso', $idPermiso)
                    ->update(['puede_delegar_permiso' => $puedeDelegar]);
            }
        }

        if (!empty($toInsert)) {
            DB::table('usuario.asignacion_rol_permiso')->insert($toInsert);
        }

        return [
            'inserted' => count($toInsert),
            'updated'  => $updated,
            'deleted'  => $deleted,
        ];
    }
}
