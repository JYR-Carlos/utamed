<?php

namespace App\Services\Authorization;

use App\Models\Usuario\Usuario;
use App\Enums\ContextType;
use App\Contracts\HasContext;
use App\Enums\PermissionTypeEnum;
use App\Services\Authorization\GlobalContextService;
use App\Services\ContextResolver;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * @param Permissions $permission Slug del permiso (ej: 'facultad:editar')
     * @param HasContext|null $resource Instancia del recurso (null para viewAny)
     * @param int|array|null $contextId Contexto(s) explícito(s) (para create con padre)
     * @return bool
     */
    public function validate(
        Usuario $user,
        Permissions $permission,
        ?HasContext $resource = null,
        int|array|null $contextId = null
    ): bool {
        // 1. Verificar SuperAdmin global
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // 2. Resolver contextos del recurso (puede ser múltiple)
        /** 
         * Usuario solicita acceder a un Curso
         * 1. resolveTargetContexts(Curso, null, 'cursos:ver')
         * 2. getContextId(Curso) -> [id_curso=42]
         * 3. expandWithAncestors([42], globalId, 'cursos:ver')
         * 4. getPermissionContextTypes('cursos:ver') -> ['curso', 'carrera'] (tipos válidos)
         * 5. fn_obtener_ids_contexto_ancestros(42) -> [(42, 0, 'curso'), (15, 1, 'carrera'), ...]
         * 6. Filtra solo categorías en ['curso', 'carrera'] -> [(42, 0, 'curso'), (15, 1, 'carrera')]
         * 7. Retorna [42, 15]
         */
        $targetContextIds = $this->resolveTargetContexts($resource, $contextId, $permission);

        // 3. Validar en cualquiera de los contextos
        /**
         * Con targetContextIds=[42, 15] para usuario_id=5 y permiso 'cursos:ver':
         * 1. validateInAnyContext(user_5, 'cursos:ver', [42, 15])
         * 
         * Por cada contexto [42, 15]:
         * 
         * checkSpecialPermission(user_5, 'cursos:ver', ctx):
         *   a. generatePermissionPatterns('cursos:ver') -> ['cursos:ver', 'cursos:*', '*']
         *   b. SELECT * FROM vw_permisos_usuario WHERE tipo_asignacion='especial' AND slug IN (...)
         *   c. Si hay resultados, elige el más específico por prioridad (exacto > wildcard recurso > global)
         *   d. Retorna true (GRANT), false (DENY), o null (no encontrado)
         *      - DENY  → saltar al siguiente contexto
         *      - GRANT → retornar true inmediatamente
         *      - null  → continuar a checkRolePermission
         * 
         * checkRolePermission(user_5, 'cursos:ver', ctx):
         *   a. generatePermissionPatterns('cursos:ver') -> ['cursos:ver', 'cursos:*', '*']
         *   b. SELECT EXISTS(...) FROM vw_permisos_usuario WHERE tipo_asignacion='rol' AND slug IN (...)
         *   c. Retorna true si al menos un patrón coincide, false si ninguno
         * 
         * Ejemplo real: ctx=42 → UPE null, URA false; luego ctx=15 → UPE null, URA true → retorna true
         */
        return $this->validateInAnyContext($user, $permission, $targetContextIds);
    }

    /**
     * Obtener los tipos de contexto válidos para un permiso.
     *
     * Wrapper tipado que delega a PermissionContextConstraints.
     * Retorna enum instances para validación con type-safety.
     *
     * @param Permissions $permission
     * @return ContextType[] Array de tipos de contexto válidos tipado
     */
    public function getPermissionContextTypes(Permissions $permission): array
    {
        // Wrapper: delega a PermissionContextConstraints (fuente de verdad)
        return PermissionContextConstraints::validContextTypesFor($permission);
    }

    /**
     * Obtener todos los IDs de contexto donde el usuario tiene un permiso.
     * 
     * Útil para listados (viewAny) con scope whereContext()
     * 
     * @param Usuario $user
     * @param Permissions $permission Slug del permiso
     * @return array Array de IDs de contexto
     */
    public function getContextsFromPermission(Usuario $user, Permissions $permission): array
    {
        // TODO: Recuperar de caché: Cache::get("perm:{$user->id_usuario}:contexts:{$permission->value}:0")

        // 1. Obtener contextos con DENY en UPE
        $deniedContexts = $this->getDeniedContexts($user, $permission);

        // 2. Obtener contextos con GRANT en UPE
        $grantedContextsUPE = $this->getGrantedContextsFromSpecial($user, $permission);

        // 3. Obtener contextos de URA
        $grantedContextsURA = $this->getGrantedContextsFromRoles($user, $permission);

        // 4. Combinar: (UPE + URA) - DENY
        $allGranted = array_unique(...$grantedContextsUPE, ...$grantedContextsURA);
        $result = array_values(array_diff($allGranted, $deniedContexts));

        // TODO: Guardar en caché: Cache::put("perm:{$user->id_usuario}:contexts:{$permission->value}:0", $result)

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
            ->table('vw_permisos_usuario')
            ->select('id_usuario', 'id_contexto', 'id_permiso', 'slug', 'esta_permitido', 'tipo_asignacion', 'puede_delegar')
            ->where('id_usuario', $user->id_usuario);

        if ($contextId !== null) {
            $query->where('id_contexto', $contextId);
        }

        if ($permissionType !== null) {
            $query->where('tipo_asignacion', $permissionType->value);
        }

        $permissions = $query->get()->map(function ($perm) {
            $castedPermission = Permissions::tryFrom($perm->slug);

            if (!$castedPermission) {
                // no agregarlo a la lista y continuar con el siguiente permiso
                Log::error("Permiso en la BD malformado: {$perm->slug}");
                // TODO: tratar este error
                return null;
            }

            return [
                'id_usuario' => $perm->id_usuario,
                'id_contexto' => $perm->id_contexto,
                'id_permiso' => $perm->id_permiso,
                'slug' => $castedPermission,
                'esta_permitido' => $perm->esta_permitido ?? true,
                'tipo_asignacion' => $perm->tipo_asignacion,
                'puede_delegar' => $perm->puede_delegar ?? false,
            ];
        });

        return $permissions->filter(
            fn($perm) => $perm !== null
        )->values();
    }

    /**
     * Obtener contextos donde un usuario puede delegar un permiso específico.
     * 
     * Retorna los IDs de contexto donde el usuario tiene el permiso con
     * puede_delegar=true y esta_permitido=true.
     * 
     * Útil para validar delegación de permisos en PermissionAssignmentBuilder.
     * 
     * @param Usuario $user Usuario a verificar
     * @param Permissions $permissionSlug Enum del permiso desde Permissions:: (ej: Permissions::CURSOS_VER)
     * @return array Array de IDs de contexto donde puede delegar el permiso
     */
    public function getContextsWhereDelegablePermission(Usuario $user, Permissions $permissionSlug): array
    {
        return $this->getUserPermissions($user)
            ->filter(
                fn($perm) =>
                $perm['slug'] === $permissionSlug
                && $perm['esta_permitido'] === true
                && $perm['puede_delegar'] === true
            )
            ->pluck('id_contexto')
            ->all();
    }

    /**
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
     * Delegador que determina el flujo según los parámetros:
     * - Si contextId explícito → delegar a resolveTargetContextsFromExplicit()
     * - Si recurso → delegar a resolveTargetContextsFromResource()
     * - Si null → retornar contexto global
     * 
     * @param HasContext|null $resource
     * @param int|array|null $contextId
     * @param Permissions|null $permission Permiso que se está validando (para chequeo no-inherit)
     * @return array Array de IDs de contexto para validar (incluye ancestros salvo no-inherit)
     */
    protected function resolveTargetContexts(
        ?HasContext $resource,
        int|array|null $contextId,
        ?Permissions $permission = null
    ): array {
        // Contexto(s) explícito(s) tienen prioridad
        if ($contextId !== null) {
            return $this->resolveTargetContextsFromExplicit($contextId, $permission);
        }

        // Resolver desde recurso vía ContextResolver
        if ($resource !== null) {
            return $this->resolveTargetContextsFromResource($resource, $permission);
        }

        // Sin contexto → usar contexto global
        return [$this->globalContext->getContextId()];
    }

    /**
     * Resolver contextos a partir de un contextId explícito.
     * 
     * Maneja tanto valores simples (int) como arrays de contextos.
     * Respeta la configuración no-inherit del permiso.
     * 
     * @param int|array $contextId ID(s) de contexto explícito(s)
     * @param Permissions|null $permission Permiso que se está validando (para chequeo no-inherit)
     * @return array Array de IDs de contexto para validar (incluye ancestros salvo no-inherit)
     */
    protected function resolveTargetContextsFromExplicit(int|array $contextId, ?Permissions $permission = null): array
    {
        $globalContextId = $this->globalContext->getContextId();
        $noInherit = $permission !== null && $this->isNoInheritPermission($permission);

        // Tratar el contexto explícito como array 
        $explicit = \is_array($contextId) ? $contextId : [$contextId];

        if ($noInherit) {
            // Si el permiso es no-inherit, no expandir con ancestros, solo usar los explícitos + global
            return array_values(array_unique([...$explicit, $globalContextId]));
        } else {
            // Si no, expandir con ancestros usando la función SQL
            return $this->expandWithAncestors($explicit, $globalContextId, $permission);
        }
    }

    /**
     * Resolver contextos a partir de un objeto recurso.
     * 
     * Delega al ContextResolver para obtener los contextos del recurso,
     * luego expande con ancestros si aplica.
     * 
     * @param HasContext $resource Objeto del recurso que implementa HasContext
     * @param Permissions|null $permission Permiso que se está validando (para chequeo no-inherit)
     * @return array Array de IDs de contexto para validar (incluye ancestros salvo no-inherit)
     */
    protected function resolveTargetContextsFromResource(HasContext $resource, ?Permissions $permission = null): array
    {
        $globalContextId = $this->globalContext->getContextId();
        $noInherit = $permission !== null && $this->isNoInheritPermission($permission);

        // Resolver contextos del recurso (puede ser múltiple)
        $contextIds = $this->contextResolver->getModelContextId($resource);

        if (empty($contextIds)) {
            // Si el recurso no tiene contexto, usar el global
            return [$globalContextId];
        }

        if ($noInherit) {
            // Si el permiso es no-inherit, no expandir con ancestros, solo usar los de recurso + global
            return array_values(array_unique([...$contextIds, $globalContextId]));
        } else {
            // Si no, expandir con ancestros usando la función SQL
            return $this->expandWithAncestors($contextIds, $globalContextId, $permission);
        }
    }

    /**
     * Determina si un permiso está en la lista de no-inherit (no expande cadena de ancestros).
     *
     * Lee config/permission-no-inherit.php generado por scripts/generate_models.php.
     *
     * @param Permissions $permission
     * @return bool
     */
    protected function isNoInheritPermission(Permissions $permission): bool
    {
        static $noInheritSlugs = null;
        if ($noInheritSlugs === null) {
            $noInheritSlugs = config('permission-no-inherit', []);
        }
        return \in_array($permission->value, $noInheritSlugs, strict: true);
    }

    /**
     * Expande un array de context IDs con todos sus ancestros.
     *
     * Usa la función SQL fn_obtener_ids_contexto_ancestros que retorna
     * toda la cadena de ancestros con sus tipos de contexto.
     * Filtra por los tipos válidos del permiso si están especificados.
     * Ya incluye el contexto global en el resultado.
     *
     * @param int[] $contextIds IDs de contexto a expandir
     * @param int $globalContextId ID del contexto global (para referencia)
     * @param Permissions|null $permission Permiso para filtrar tipos válidos
     * @return int[] Array único de IDs de contexto ordenados de más cercano a más lejano
     */
    protected function expandWithAncestors(array $contextIds, int $globalContextId, ?Permissions $permission = null): array
    {
        $all = [];
        $validTypes = $permission !== null ? $this->getPermissionContextTypes($permission) : [];
        $shouldFilterTypes = !empty($validTypes);

        foreach ($contextIds as $id) {
            // Obtener cadena completa con tipos de contexto desde la función SQL (tipificados como ContextType enum)
            $ancestorsWithTypes = $this->contextResolver->getAncestorContextsWithType($id);

            // Filtrar por tipos válidos si el permiso especifica restricciones
            if ($shouldFilterTypes) {
                $ancestorsWithTypes = array_filter(
                    $ancestorsWithTypes,
                    fn($ctx) => \in_array($ctx['categoria'], $validTypes, true)
                );
            }

            // Extraer solo los IDs ordenados
            foreach ($ancestorsWithTypes as $ctx) {
                $all[] = $ctx['id_contexto'];
            }
        }

        // Retornar array único (la CTE ya retorna ordenado desde más cercano a más lejano)
        // y el global contexto ya está incluido en la cadena SQL
        return array_values(array_unique($all));
    }

    /**
     * Validar permiso contra múltiples contextos.
     * 
     * Retorna true si el usuario tiene permiso en AL MENOS UNO de los contextos.
     * 
     * @param Usuario $user
     * @param Permissions $permission
     * @param array $contextIds
     * @return bool
     */
    protected function validateInAnyContext(Usuario $user, Permissions $permission, array $contextIds): bool
    {
        foreach ($contextIds as $contextId) {
            // Verificar UPE para este contexto
            $specialResult = $this->checkSpecialPermission($user, $permission, $contextId);

            if ($specialResult === false) {
                // DENY explícito en este contexto → revoca cualquier GRANT heredado del padre
                // Semántica: el contexto más específico gana (hijo > padre)
                return false;
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
     * @param Permissions $permission
     * @param int $contextId
     * @return bool|null true=GRANT, false=DENY, null=no encontrado
     */
    protected function checkSpecialPermission(Usuario $user, Permissions $permission, int $contextId): ?bool
    {
        // TODO: Recuperar permisos UPE de caché: Cache::get("perm:{$user->id_usuario}:upe:{$permission->value}:{$contextId}")

        // Generar todos los patrones (exacto, wildcard mismo recurso, ancestros, global)
        $patterns = WildcardMatcher::generatePermissionPatterns($permission);

        // Buscar en UPE con prioridad: exacto > wildcard recurso > wildcard global
        $resourceWildcard = WildcardMatcher::toResourceWildcard($permission);

        $results = DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $contextId)
            ->where('tipo_asignacion', self::TIPO_ESPECIAL)
            ->whereIn('slug', $patterns)
            ->get();

        if ($results->isEmpty()) {
            // TODO: Guardar en caché: Cache::put("perm:{$user->id_usuario}:upe:{$permission->value}:{$contextId}", 'null')
            return null;
        }

        // Transformar slugs a Permissions enum para mantener consistencia tipado
        /** @var Permissions[] $transformedSlugs */
        $transformedSlugs = [];

        foreach ($results as $result) {
            $castedPermission = Permissions::tryFrom($result->slug);

            if (!$castedPermission) {
                Log::error("Permiso en la BD malformado: {$result->slug}");
                continue;
            }

            $transformedSlugs[] = $castedPermission;
        }

        // Si hay múltiples resultados, elegir el de mayor prioridad (menor número)
        // Esto asegura que lo más específico gana sobre lo general
        $bestResult = null;
        $bestPriority = 999;

        foreach ($transformedSlugs as $slug) {
            $priority = WildcardMatcher::getPriority($permission, $slug);
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
     * @param Permissions $permission
     * @param int $contextId
     * @return bool
     */
    protected function checkRolePermission(Usuario $user, Permissions $permission, int $contextId): bool
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
     * @param Permissions $permission
     * @return array
     */
    protected function getDeniedContexts(Usuario $user, Permissions $permission): array
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
     * @param Permissions $permission
     * @return array
     */
    protected function getGrantedContextsFromSpecial(Usuario $user, Permissions $permission): array
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
     * @param Permissions $permission
     * @return array
     */
    protected function getGrantedContextsFromRoles(Usuario $user, Permissions $permission): array
    {
        $resourceWildcard = WildcardMatcher::toResourceWildcard($permission);

        return DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('tipo_asignacion', self::TIPO_ROL)
            ->whereIn('slug', [$permission->value, $resourceWildcard, $this->globalWildcard])
            ->pluck('id_contexto')
            ->toArray();
    }


}
