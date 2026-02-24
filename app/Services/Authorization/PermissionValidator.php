<?php

namespace App\Services\Authorization;

use App\Models\Usuario\Usuario;
use App\Contracts\HasContext;
use App\Enums\PermissionTypeEnum;
use App\Services\Authorization\GlobalContextService;
use App\Services\ContextResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Motor de validación de permisos RelBAC con contextos.
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
    // Para el caché
    // protected int $cacheTtl;
    protected string $globalWildcard;

    /**
     * Constructor - inyectar dependencias y cargar configuración
     */
    public function __construct(
        protected ContextResolver $contextResolver,
        protected GlobalContextService $globalContext
    ) {
        // Para el caché
        // $this->cacheTtl     = config('configFile.cache_ttl');
        $this->globalWildcard = WildcardMatcher::GLOBAL_WILDCARD;
    }

    /**
     * Validar si un usuario puede ejecutar una acción sobre un recurso.
     * 
     * @param Usuario $user Usuario a validar
     * @param string $permission Slug del permiso (ej: 'facultad:editar')
     * @param HasContext|null $resource Instancia del recurso (null para viewAny)
     * @param int|array|null $contextId Contexto(s) explícito(s) (para create con padre)
     * @return bool
     */
    public function validate(
        Usuario $user,
        string $permission,
        ?HasContext $resource = null,
        int|array|null $contextId = null
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
    public function getContextsFromPermission(Usuario $user, string $permission): array
    {
        // TODO: Recuperar de caché: Cache::get("perm:{$user->id_usuario}:contexts:{$permission}:0")

        // 1. Obtener contextos con DENY en UPE
        $deniedContexts = $this->getDeniedContexts($user, $permission);

        // 2. Obtener contextos con GRANT en UPE
        $grantedContextsUPE = $this->getGrantedContextsFromSpecial($user, $permission);

        // 3. Obtener contextos de URA
        $grantedContextsURA = $this->getGrantedContextsFromRoles($user, $permission);

        // 4. Combinar: (UPE + URA) - DENY
        $allGranted = array_unique(array_merge($grantedContextsUPE, $grantedContextsURA));
        $result = array_values(array_diff($allGranted, $deniedContexts));

        // TODO: Guardar en caché: Cache::put("perm:{$user->id_usuario}:contexts:{$permission}:0", $result)

        return $result;
    }

    /**
     * Obtener todos los permisos efectivos de un usuario.
     * 
     * Para debugging y mostrar en UI (permisos del usuario actual)
     * 
     * @param Usuario $user
     * @param int|null $contextId Filtrar por contexto específico
     * @param PermissionTypeEnum|null $permissionType Filtrar por tipo de permiso: ROL o ESPECIAL
     * @return Collection<
     *   int,
     *   array{
     *     id_usuario: int,
     *     id_contexto: int,
     *     id_permiso: int,
     *     slug: string,
     *     esta_permitido: bool,
     *     tipo_asignacion: string,
     *     puede_delegar: bool
     *   }
     * > Colección de permisos con detalles completos
     */
    public function getUserPermissions(Usuario $user, ?int $contextId = null, ?PermissionTypeEnum $permissionType = null): Collection
    {
        $query = DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario);

        if ($contextId !== null) {
            $query->where('id_contexto', $contextId);
        }

        if ($permissionType !== null) {
            $query->where('tipo_asignacion', $permissionType->value);
        }

        $permissions = $query->get()->map(function ($perm) {
            return [
                'id_usuario' => $perm->id_usuario,
                'id_contexto' => $perm->id_contexto,
                'id_permiso' => $perm->id_permiso,
                'slug' => $perm->slug,
                'esta_permitido' => $perm->esta_permitido ?? true,
                'tipo_asignacion' => $perm->tipo_asignacion,
                'puede_delegar' => $perm->puede_delegar ?? false,
            ];
        });

        return $permissions;
    }

    /**
     * Verificar si es SuperAdmin (permiso '*' en contexto global).
     * 
     * Wrapper público para detectar si el usuario es superadmin.
     * No requiere que el llamador conozca el contexto global.
     * 
     * @param Usuario $user
     * @return bool
     */
    public function isSuperAdmin(Usuario $user): bool
    {
        return $this->hasSuperAdminPermission($user);
    }

    // ========== MÉTODOS PROTEGIDOS ==========

    /**
     * Verificar si es SuperAdmin (permiso '*' en contexto global).
     * 
     * Método protegido que realiza la validación real.
     * 
     * @param Usuario $user
     * @return bool
     */
    protected function hasSuperAdminPermission(Usuario $user): bool
    {
        $hasSuperAdmin = DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('slug', $this->globalWildcard)
            ->where('id_contexto', $this->globalContext->getContextId())
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
     * @param int|array|null $contextId
     * @return array Array de IDs de contexto para validar
     */
    protected function resolveTargetContexts(?HasContext $resource, int|array|null $contextId): array
    {
        // Contexto(s) explícito(s) tienen prioridad
        if ($contextId !== null) {
            $explicit = is_array($contextId) ? $contextId : [$contextId];
            return array_values(array_unique(array_merge($explicit, [$this->globalContext->getContextId()])));
        }

        // Resolver desde recurso vía ContextResolver
        if ($resource !== null) {
            $contextIds = $this->contextResolver->getContextId($resource);

            if (!empty($contextIds) && is_array($contextIds)) {
                return $contextIds;
            }
        }

        // Sin contexto → usar contexto global
        return [$this->globalContext->getContextId()];
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
     * Genera todos los patrones posibles (exacto, wildcard recurso, ancestros)
     * y si hay múltiples matches, elige el más específico (menor prioridad).
     * Ejemplo: si existe 'cursos:* (GRANT)' y 'cursos/inscripciones:* (DENY)',
     * para 'cursos/inscripciones:ver' elige DENY porque es más específico.
     *
     * @param Usuario $user
     * @param string $permission
     * @param int $contextId
     * @return bool|null true=GRANT, false=DENY, null=no encontrado
     */
    protected function checkSpecialPermission(Usuario $user, string $permission, int $contextId): ?bool
    {
        // TODO: Recuperar permisos UPE de caché: Cache::get("perm:{$user->id_usuario}:upe:{$permission}:{$contextId}")

        // Generar todos los patrones (exacto, wildcard mismo recurso, ancestros, global)
        $patterns = WildcardMatcher::generatePermissionPatterns($permission);

        // Buscar en UPE con prioridad: exacto > wildcard recurso > wildcard global
        $resourceWildcard = WildcardMatcher::toResourceWildcard($permission);

        $result = DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $contextId)
            ->where('tipo_asignacion', self::TIPO_ESPECIAL)
            ->whereIn('slug', $patterns)
            ->get();

        if ($results->isEmpty()) {
            // TODO: Guardar en caché: Cache::put("perm:{$user->id_usuario}:upe:{$permission}:{$contextId}", 'null')
            return null;
        }

        // Si hay múltiples resultados, elegir el de mayor prioridad (menor número)
        // Esto asegura que lo más específico gana sobre lo general
        $bestResult = null;
        $bestPriority = 999;

        foreach ($results as $result) {
            $priority = WildcardMatcher::getPriority($permission, $result->slug);
            if ($priority < $bestPriority) {
                $bestPriority = $priority;
                $bestResult = $result;
            }
        }

        if ($bestResult === null) {
            // TODO: Guardar en caché: Cache::put("perm:{$user->id_usuario}:upe:{$permission}:{$contextId}", 'null')
            return null;
        }

        $permissionResult = $bestResult->esta_permitido ? true : false;

        // TODO: Guardar en caché: Cache::put("perm:{$user->id_usuario}:upe:{$permission}:{$contextId}", $permissionResult ? 'grant' : 'deny')

        return $permissionResult;
    }

    /**
     * Buscar en Roles Asignados (URA) vía vista vw_permisos_usuario.
     *
     * Genera todos los patrones posibles (exacto, wildcard recurso, ancestros)
     * y verifica si al menos uno existe en URA.
     *
     * @param Usuario $user
     * @param string $permission
     * @param int $contextId
     * @return bool
     */
    protected function checkRolePermission(Usuario $user, string $permission, int $contextId): bool
    {
        // TODO: Recuperar permisos URA de caché: Cache::get("perm:{$user->id_usuario}:ura:{$permission}:{$contextId}")

        // Generar todos los patrones (exacto, wildcard mismo recurso, ancestros, global)
        $patterns = WildcardMatcher::generatePermissionPatterns($permission);

        $hasPermission = DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $contextId)
            ->where('tipo_asignacion', self::TIPO_ROL)
            ->whereIn('slug', $patterns)
            ->exists();

        // TODO: Guardar en caché: Cache::put("perm:{$user->id_usuario}:ura:{$permission}:{$contextId}", $hasPermission ? 'true' : 'false')

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
        $patterns = WildcardMatcher::generatePermissionPatterns($permission);

        return DB::connection('pgsql')
            ->table('usuario.usuario_permiso_especial as upe')
            ->join('usuario.permiso as p', 'upe.id_permiso', '=', 'p.id_permiso')
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
            ->whereIn('p.slug', $patterns)
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
        $patterns = WildcardMatcher::generatePermissionPatterns($permission);

        return DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('tipo_asignacion', self::TIPO_ESPECIAL)
            ->where('esta_permitido', true)
            ->whereIn('slug', $patterns)
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
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('tipo_asignacion', self::TIPO_ROL)
            ->whereIn('slug', [$permission, $resourceWildcard, $this->globalWildcard])
            ->pluck('id_contexto')
            ->toArray();
    }


}
