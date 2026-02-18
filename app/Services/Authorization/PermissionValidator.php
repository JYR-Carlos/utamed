<?php

namespace App\Services\Authorization;

use App\Models\Usuario\Usuario;
use App\Contracts\HasContext;
use App\Services\ContextResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Motor de validación de permisos RBAC con contextos.
 * 
 * Flujo de validación:
 * 1. Verificar si es superadmin global (permiso '*' sin contexto)
 * 2. Buscar en Permisos Especiales (UPE) - mayor prioridad
 * 3. Buscar en Roles Asignados (URA)
 * 4. Retornar false si no hay coincidencias
 * 
 * Prioridad de permisos: UPE > URA
 * Wildcards soportados: '*' (global), 'recurso:*' (recurso completo)
 * 
 * @package App\Services\Authorization
 */
class PermissionValidator
{
    /**
     * Tipos de asignación de permisos
     */
    protected const TIPO_ESPECIAL = 'especial';
    protected const TIPO_ROL = 'rol';

    /**
     * Configuración cargada en constructor
     */
    protected int $globalContextId;
    protected int $cacheTtl;
    protected string $cachePrefix;
    protected bool $cacheEnabled;
    protected string $globalWildcard;

    /**
     * Constructor - inyectar dependencias y cargar configuración
     */
    public function __construct(
        protected ContextResolver $contextResolver
    ) {
        // Cargar ID del contexto global dinámicamente
        $this->globalContextId = $this->loadGlobalContextId();

        // Cargar configuración del sistema
        $this->cacheTtl = config('rbac.cache_ttl');
        $this->cachePrefix = config('rbac.cache_prefix');
        $this->cacheEnabled = config('rbac.cache_enabled');

        $this->globalWildcard = WildcardMatcher::GLOBAL_WILDCARD;
    }

    /**
     * Cargar ID del contexto global desde la BD usando tipo_contexto.tabla_referenciada.
     * 
     * Busca el Tipo_Contexto donde tabla_referenciada = 'GLOBAL',
     * luego obtiene el Contexto asociado.
     * 
     * @return int
     */
    protected function loadGlobalContextId(): int
    {
        $globalContext = DB::connection('pgsql')
            ->table('contexto')
            ->join('tipo_contexto', 'contexto.id_tipo_contexto', '=', 'tipo_contexto.id_tipo_contexto')
            ->where('tipo_contexto.tabla_referenciada', 'GLOBAL')
            ->select('contexto.id_contexto')
            ->first();

        if (!$globalContext) {
            throw new \RuntimeException(
                "Contexto global no encontrado. Verifica que exista un registro en tipo_contexto con tabla_referenciada='GLOBAL' y su contexto asociado en usuario.contexto"
            );
        }

        return $globalContext->id_contexto;
    }

    /**
     * Validar si un usuario puede ejecutar una acción sobre un recurso.
     * 
     * @param Usuario $user Usuario a validar
     * @param string $permission Slug del permiso (ej: 'facultad:editar')
     * @param HasContext|null $resource Instancia del recurso (null para viewAny)
     * @param int|null $contextId Contexto explícito (para create con padre)
     * @return bool
     */
    public function validate(
        Usuario $user,
        string $permission,
        ?HasContext $resource = null,
        ?int $contextId = null
    ): bool {
        // 1. Verificar SuperAdmin global
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // 2. Resolver contextos del recurso (puede ser múltiple)
        $targetContextIds = $this->resolveTargetContexts($resource, $contextId);

        // 3. Validar en cualquiera de los contextos
        return $this->validateInAnyContext($user, $permission, $targetContextIds);
    }

    /**
     * Obtener todos los IDs de contexto donde el usuario tiene un permiso.
     * 
     * Útil para listados (viewAny) con scope whereContext()
     * 
     * @param Usuario $user
     * @param string $permission Slug del permiso
     * @return array Array de IDs de contexto
     */
    public function getContextsWithPermission(Usuario $user, string $permission): array
    {
        $cacheKey = $this->getCacheKey($user->id_usuario, "contexts:{$permission}", 0);

        if ($this->cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // 1. Obtener contextos con DENY en UPE
        $deniedContexts = $this->getDeniedContexts($user, $permission);

        // 2. Obtener contextos con GRANT en UPE
        $grantedContextsUPE = $this->getGrantedContextsFromSpecial($user, $permission);

        // 3. Obtener contextos de URA
        $grantedContextsURA = $this->getGrantedContextsFromRoles($user, $permission);

        // 4. Combinar: (UPE + URA) - DENY
        $allGranted = array_unique(array_merge($grantedContextsUPE, $grantedContextsURA));
        $result = array_values(array_diff($allGranted, $deniedContexts));

        if ($this->cacheEnabled) {
            Cache::put($cacheKey, $result, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * Obtener todos los permisos efectivos de un usuario.
     * 
     * Para debugging y mostrar en UI (permisos del usuario actual)
     * 
     * @param Usuario $user
     * @param int|null $contextId Filtrar por contexto específico
     * @return Collection Colección de ['slug' => ..., 'contexto' => ..., 'tipo' => ...]
     */
    public function getUserPermissions(Usuario $user, ?int $contextId = null): Collection
    {
        $query = DB::connection('pgsql')
            ->table('vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario);

        if ($contextId !== null) {
            $query->where('id_contexto', $contextId);
        }

        $permissions = $query->get()->map(function ($perm) {
            return [
                'slug' => $perm->slug,
                'contexto' => $perm->id_contexto,
                'tipo' => $perm->tipo_asignacion,
                'permitido' => $perm->esta_permitido ?? true,
            ];
        });

        return $permissions;
    }

    // ========== MÉTODOS PROTEGIDOS ==========

    /**
     * Verificar si es SuperAdmin global (permiso '*').
     * 
     * @param Usuario $user
     * @return bool
     */
    protected function isSuperAdmin(Usuario $user): bool
    {
        $hasSuperAdmin = DB::connection('pgsql')
            ->table('vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('slug', $this->globalWildcard)
            ->where('id_contexto', $this->globalContextId)
            ->where('esta_permitido', true)
            ->exists();

        return $hasSuperAdmin;
    }

    /**
     * Resolver el contexto del recurso.
     * 
     * - Si contextId explícito → retornar array con ese ID
     * - Si recurso → delegar a ContextResolver (puede retornar múltiples contextos)
     * - Si null → contexto global
     * 
     * @param HasContext|null $resource
     * @param int|null $contextId
     * @return array Array de IDs de contexto para validar
     */
    protected function resolveTargetContexts(?HasContext $resource, ?int $contextId): array
    {
        // Contexto explícito tiene prioridad
        if ($contextId !== null) {
            return [$contextId, $this->globalContextId];
        }

        // Resolver desde recurso vía ContextResolver
        if ($resource !== null) {
            $contextIds = $this->contextResolver->getContextId($resource);

            if (!empty($contextIds) && is_array($contextIds)) {
                return $contextIds;
            }
        }

        // Sin contexto → usar contexto global
        return [$this->globalContextId];
    }

    /**
     * Validar permiso contra múltiples contextos.
     * 
     * Retorna true si el usuario tiene permiso en AL MENOS UNO de los contextos.
     * 
     * @param Usuario $user
     * @param string $permission
     * @param array $contextIds
     * @return bool
     */
    protected function validateInAnyContext(Usuario $user, string $permission, array $contextIds): bool
    {
        foreach ($contextIds as $contextId) {
            // Verificar UPE para este contexto
            $specialResult = $this->checkSpecialPermission($user, $permission, $contextId);

            if ($specialResult === false) {
                // DENY explícito en este contexto → saltar al siguiente
                continue;
            }

            if ($specialResult === true) {
                // GRANT explícito en UPE → autorizar inmediatamente
                return true;
            }

            // No hay UPE, verificar URA
            if ($this->checkRolePermission($user, $permission, $contextId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Buscar en Permisos Especiales (UPE) vía vista vw_permisos_usuario.
     * 
     * @param Usuario $user
     * @param string $permission
     * @param int $contextId
     * @return bool|null true=GRANT, false=DENY, null=no encontrado
     */
    protected function checkSpecialPermission(Usuario $user, string $permission, int $contextId): ?bool
    {
        $cacheKey = $this->getCacheKey($user->id_usuario, "upe:{$permission}", $contextId);

        if ($this->cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached === 'grant' ? true : ($cached === 'deny' ? false : null);
            }
        }

        // Buscar en UPE con prioridad: exacto > wildcard recurso > wildcard global
        $resourceWildcard = WildcardMatcher::toResourceWildcard($permission);

        $result = DB::connection('pgsql')
            ->table('vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $contextId)
            ->where('tipo_asignacion', self::TIPO_ESPECIAL)
            ->whereIn('slug', [$permission, $resourceWildcard, $this->globalWildcard])
            ->orderByRaw("CASE 
                WHEN slug = ? THEN 1
                WHEN slug = ? THEN 2
                WHEN slug = ? THEN 3
                ELSE 999
            END", [$permission, $resourceWildcard, $this->globalWildcard])
            ->first();

        if ($result === null) {
            if ($this->cacheEnabled) {
                Cache::put($cacheKey, 'null', $this->cacheTtl);
            }
            return null;
        }

        $permissionResult = $result->esta_permitido ? true : false;

        if ($this->cacheEnabled) {
            Cache::put($cacheKey, $permissionResult ? 'grant' : 'deny', $this->cacheTtl);
        }

        return $permissionResult;
    }

    /**
     * Buscar en Roles Asignados (URA) vía vista vw_permisos_usuario.
     * 
     * @param Usuario $user
     * @param string $permission
     * @param int $contextId
     * @return bool
     */
    protected function checkRolePermission(Usuario $user, string $permission, int $contextId): bool
    {
        $cacheKey = $this->getCacheKey($user->id_usuario, "ura:{$permission}", $contextId);

        if ($this->cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached === 'true';
            }
        }

        // Buscar en URA con prioridad: exacto > wildcard recurso > wildcard global
        $resourceWildcard = WildcardMatcher::toResourceWildcard($permission);

        $hasPermission = DB::connection('pgsql')
            ->table('vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $contextId)
            ->where('tipo_asignacion', self::TIPO_ROL)
            ->whereIn('slug', [$permission, $resourceWildcard, $this->globalWildcard])
            ->exists();

        if ($this->cacheEnabled) {
            Cache::put($cacheKey, $hasPermission ? 'true' : 'false', $this->cacheTtl);
        }

        return $hasPermission;
    }

    /**
     * Obtener contextos donde el usuario tiene DENY en UPE.
     * 
     * @param Usuario $user
     * @param string $permission
     * @return array
     */
    protected function getDeniedContexts(Usuario $user, string $permission): array
    {
        $resourceWildcard = WildcardMatcher::toResourceWildcard($permission);


        return DB::connection('pgsql')
            ->table('Usuario_Permiso_Especial as upe')
            ->join('Permiso as p', 'upe.id_permiso', '=', 'p.id_permiso')
            ->where('upe.id_usuario', $user->id_usuario)
            ->where('upe.esta_activo', true)
            ->where(function ($query) {
                $query->where('upe.fue_borrado', false)
                    ->orWhereNull('upe.fue_borrado');
            })
            ->whereNull('upe.fecha_fin_real')
            ->whereRaw('"upe"."fecha_inicio_planificada" <= NOW()')
            ->whereRaw('"upe"."fecha_fin_planificada" >= NOW()')
            ->where('upe.esta_permitido', false)
            ->whereIn('p.slug', [$permission, $resourceWildcard, $this->globalWildcard])
            ->pluck('upe.id_contexto')
            ->toArray();
    }

    /**
     * Obtener contextos donde el usuario tiene GRANT en UPE.
     * 
     * @param Usuario $user
     * @param string $permission
     * @return array
     */
    protected function getGrantedContextsFromSpecial(Usuario $user, string $permission): array
    {
        $resourceWildcard = WildcardMatcher::toResourceWildcard($permission);

        return DB::connection('pgsql')
            ->table('vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('tipo_asignacion', self::TIPO_ESPECIAL)
            ->where('esta_permitido', true)
            ->whereIn('slug', [$permission, $resourceWildcard, $this->globalWildcard])
            ->pluck('id_contexto')
            ->toArray();
    }

    /**
     * Obtener contextos donde el usuario tiene permiso vía URA.
     * 
     * @param Usuario $user
     * @param string $permission
     * @return array
     */
    protected function getGrantedContextsFromRoles(Usuario $user, string $permission): array
    {
        $resourceWildcard = WildcardMatcher::toResourceWildcard($permission);

        return DB::connection('pgsql')
            ->table('vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('tipo_asignacion', self::TIPO_ROL)
            ->whereIn('slug', [$permission, $resourceWildcard, $this->globalWildcard])
            ->pluck('id_contexto')
            ->toArray();
    }

    /**
     * Generar clave de caché para un permiso.
     * 
     * @param int $userId
     * @param string $permission
     * @param int $contextId
     * @return string
     */
    protected function getCacheKey(int $userId, string $permission, int $contextId): string
    {
        return "{$this->cachePrefix}:{$userId}:{$permission}:{$contextId}";
    }
}
