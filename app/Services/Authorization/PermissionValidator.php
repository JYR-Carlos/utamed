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

    /** Valor devuelto por WildcardMatcher::getPriority() cuando el patrón no aplica. */
    protected const PRIORITY_NO_MATCH = 999;

    /**
     * Configuración cargada en constructor
     */
    protected string $globalWildcard;

    protected PermissionCache $cache;

    /**
     * Constructor - inyectar dependencias y cargar configuración
     *
     * `$cache` es opcional para no romper a quien construya el validador a mano
     * (los tests unitarios lo hacen).
     */
    public function __construct(
        protected ContextResolver $contextResolver,
        protected GlobalContextService $globalContext,
        ?PermissionCache $cache = null
    ) {
        $this->globalWildcard = WildcardMatcher::GLOBAL_WILDCARD;
        $this->cache = $cache ?? app(PermissionCache::class);
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
        return $this->cache->recordarContextos(
            $user->id_usuario,
            $permission->value,
            function () use ($user, $permission) {
                // 1. Obtener contextos con DENY en UPE
                $deniedContexts = $this->getDeniedContexts($user, $permission);

                // 2. Obtener contextos con GRANT en UPE
                $grantedContextsUPE = $this->getGrantedContextsFromSpecial($user, $permission);

                // 3. Obtener contextos de URA
                $grantedContextsURA = $this->getGrantedContextsFromRoles($user, $permission);

                // 4. Combinar candidatos únicos
                $allGranted = array_values(array_unique(array_merge($grantedContextsUPE, $grantedContextsURA)));

                if (empty($deniedContexts) || empty($allGranted)) {
                    return $allGranted;
                }

                // Al existir al menos un DENY para patrones de este permiso, revalidar
                // cada contexto candidato para respetar DENY heredados de ancestros y
                // especificidad de wildcards dentro del mismo contexto.
                return array_values(array_filter(
                    $allGranted,
                    fn (int $ctxId) => $this->validate($user, $permission, null, $ctxId)
                ));
            }
        );
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

        $contextRank = null;
        if ($contextId !== null) {
            $contextIds = $this->resolveTargetContextsFromExplicit($contextId);
            $query->whereIn('id_contexto', $contextIds);
            $contextRank = array_flip($contextIds);
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
        })->filter();

        if ($contextRank !== null) {
            // Priorizar DENY (esta_permitido = false) antes de GRANT/ROL para evitar que
            // un permiso concedido en hijo descarte una revocación heredada en unique().
            $permissions = $permissions->sortBy(function ($perm) use ($contextRank) {
                $denyRank = $perm['esta_permitido'] ? 1 : 0;
                $rank = $contextRank[$perm['id_contexto']] ?? 999;
                $typeRank = $perm['tipo_asignacion'] === 'especial' ? 0 : 1;
                return $denyRank * 10000 + $rank * 10 + $typeRank;
            })->unique(fn($perm) => $perm['slug']->value);
        }

        return $permissions->values();
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
     * Expande una lista de permisos o slugs (incluyendo wildcards) a una lista plana
     * de slugs concretos permitidos, usando las definiciones de Permissions enum y WildcardMatcher.
     *
     * Si la lista incluye el wildcard global ('*'), retorna ['*'].
     *
     * @param iterable<string|Permissions|array{slug: string|Permissions, esta_permitido?: bool}> $rawPermissions
     * @return array<string> Slugs concretos expandidos
     */
    public function expandToConcreteSlugs(iterable $rawPermissions): array
    {
        $permissions = [];
        $hasGlobalWildcard = false;

        foreach ($rawPermissions as $perm) {
            $slugValue = is_array($perm) ? ($perm['slug'] ?? null) : $perm;
            $isAllowed = is_array($perm) ? (bool) ($perm['esta_permitido'] ?? true) : true;

            if (!$slugValue) {
                continue;
            }

            if ($slugValue instanceof Permissions) {
                $enum = $slugValue;
            } else {
                $enum = Permissions::tryFrom((string) $slugValue);
            }

            if (!$enum) {
                continue;
            }

            if ($enum === Permissions::GLOBAL_WILDCARD && $isAllowed) {
                $hasGlobalWildcard = true;
            }

            $permissions[] = [
                'enum' => $enum,
                'esta_permitido' => $isAllowed,
            ];
        }

        if ($hasGlobalWildcard) {
            return [WildcardMatcher::GLOBAL_WILDCARD];
        }

        if (empty($permissions)) {
            return [];
        }

        $allConcreteCases = self::concretePermissionCases();

        $expanded = [];
        foreach ($allConcreteCases as $concreteCase) {
            $bestPriority = PHP_INT_MAX;
            $bestVerdict = null;

            foreach ($permissions as $userPerm) {
                if (WildcardMatcher::matches($concreteCase, $userPerm['enum'])) {
                    $priority = WildcardMatcher::getPriority($concreteCase, $userPerm['enum']);
                    $isDeny = !$userPerm['esta_permitido'];

                    if ($priority < $bestPriority || ($priority === $bestPriority && $isDeny)) {
                        $bestPriority = $priority;
                        $bestVerdict = $userPerm['esta_permitido'];
                    }
                }
            }

            if ($bestVerdict === true) {
                $expanded[] = $concreteCase->value;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Resuelve todos los slugs concretos efectivamente permitidos para un usuario
     * en un contexto dado, respetando herencia, wildcards, restricciones de contexto,
     * `no-inherit` y precedencia jerárquica de `DENY`, con paridad 1-a-1 frente a {@see validate()}.
     *
    /**
     * Resuelve los slugs efectivos (concretos, ya expandidos de wildcards y
     * respetando herencia jerárquica y revocaciones) para un usuario sobre un
     * recurso con contexto (`Curso`, `Componente`, `Actividad`, etc.) o sobre
     * el contexto Global si `$resource` es `null`.
     *
     * @return string[]
     */
    public function resolveEffectiveSlugsFor(Usuario $user, ?HasContext $resource = null): array
    {
        if ($resource === null) {
            return $this->resolveEffectiveSlugsForContext($user, $this->globalContext->getContextId());
        }

        $contextIds = $this->contextResolver->getModelContextId($resource);
        if (empty($contextIds)) {
            return [];
        }

        if (count($contextIds) === 1) {
            return $this->resolveEffectiveSlugsForContext($user, (int) $contextIds[0]);
        }

        $byContext = $this->resolveEffectiveSlugsByContexts($user, $contextIds);

        return $this->mergeEffectiveSlugs(array_values($byContext));
    }

    /**
     * Versión batch de {@see resolveEffectiveSlugsFor()} para resolver múltiples
     * recursos `HasContext` en una sola consulta sobre `vw_permisos_usuario`.
     *
     * Preserva las llaves del iterable (ej: `$cursos->keyBy('id_curso')` o
     * `$componentes->keyBy('id_componente')`) para que el caller nunca manipule
     * `id_contexto` directamente.
     *
     * @param iterable<int|string, HasContext> $resources
     * @return array<int|string, string[]>
     */
    public function resolveEffectiveSlugsForResources(Usuario $user, iterable $resources): array
    {
        $contextIdsByKey = [];
        $allContextIds = [];

        foreach ($resources as $key => $resource) {
            if (!$resource instanceof HasContext) {
                continue;
            }
            $ids = $this->contextResolver->getModelContextId($resource);
            $contextIdsByKey[$key] = $ids;
            foreach ($ids as $cid) {
                $allContextIds[] = (int) $cid;
            }
        }

        if (empty($contextIdsByKey)) {
            return [];
        }

        $slugsByContextId = !empty($allContextIds)
            ? $this->resolveEffectiveSlugsByContexts($user, $allContextIds)
            : [];

        $result = [];
        foreach ($contextIdsByKey as $key => $ids) {
            if (empty($ids)) {
                $result[$key] = [];
            } elseif (count($ids) === 1) {
                $result[$key] = $slugsByContextId[(int) $ids[0]] ?? [];
            } else {
                $lists = array_map(
                    static fn (int $cid): array => $slugsByContextId[$cid] ?? [],
                    array_map('intval', $ids)
                );
                $result[$key] = $this->mergeEffectiveSlugs($lists);
            }
        }

        return $result;
    }

    /**
     * Une múltiples listas de slugs efectivos sin duplicados (preservando `['*']`).
     *
     * @param array<int, string[]> $slugLists
     * @return string[]
     */
    protected function mergeEffectiveSlugs(array $slugLists): array
    {
        $merged = [];
        foreach ($slugLists as $list) {
            foreach ($list as $slug) {
                if ($slug === WildcardMatcher::GLOBAL_WILDCARD) {
                    return [WildcardMatcher::GLOBAL_WILDCARD];
                }
                $merged[$slug] = true;
            }
        }

        return array_keys($merged);
    }

    /**
     * Helper interno por `id_contexto` crudo. Los callers externos deben usar
     * {@see resolveEffectiveSlugsFor()}.
     *
     * @return string[]
     */
    protected function resolveEffectiveSlugsForContext(Usuario $user, int $contextId): array
    {
        return $this->resolveEffectiveSlugsByContexts($user, [$contextId])[$contextId] ?? [];
    }

    /**
     * Versión batch interna de {@see resolveEffectiveSlugsForContext()} para resolver
     * múltiples contextos en una sola consulta sobre `vw_permisos_usuario`.
     * Los callers externos deben usar {@see resolveEffectiveSlugsForResources()}.
     *
     * @param int[] $contextIds
     * @return array<int, string[]>
     */
    protected function resolveEffectiveSlugsByContexts(Usuario $user, array $contextIds): array
    {
        $uniqueContextIds = array_values(array_unique(array_map('intval', $contextIds)));
        if (empty($uniqueContextIds)) {
            return [];
        }

        if ($this->isSuperAdmin($user)) {
            return array_fill_keys($uniqueContextIds, [WildcardMatcher::GLOBAL_WILDCARD]);
        }

        [$globalContextId, $ancestorsByContext, $upeByContext, $uraByContext] =
            $this->loadChainRowsForContexts($user, $uniqueContextIds);

        $concretePermissions = self::concretePermissionCases();
        $result = [];

        foreach ($uniqueContextIds as $cid) {
            $effective = [];
            $chain = $ancestorsByContext[$cid];

            foreach ($concretePermissions as $perm) {
                if ($this->evaluateEffectivePermOnChain($perm, $cid, $globalContextId, $chain, $upeByContext, $uraByContext)) {
                    $effective[] = $perm->value;
                }
            }

            $result[$cid] = $effective;
        }

        return $result;
    }

    /**
     * Determina si un usuario puede delegar un permiso específico en un contexto dado
     * (debe poseerlo activo sin revocaciones y con `puede_delegar = true`).
     */
    public function canDelegatePermission(Usuario $user, Permissions $permission, int $contextId): bool
    {
        $byContext = $this->resolveDelegableSlugsByContexts($user, [$contextId], [$permission]);

        return in_array($permission->value, $byContext[$contextId] ?? [], true);
    }

    /**
     * Versión batch para determinar qué permisos candidatos puede delegar el usuario
     * en cada uno de los contextos indicados (en una sola consulta sobre `vw_permisos_usuario`).
     *
     * @param int[]         $contextIds
     * @param Permissions[] $candidatePermissions
     * @return array<int, string[]>
     */
    public function resolveDelegableSlugsByContexts(
        Usuario $user,
        array $contextIds,
        array $candidatePermissions
    ): array {
        $uniqueContextIds = array_values(array_unique(array_map('intval', $contextIds)));
        if (empty($uniqueContextIds) || empty($candidatePermissions)) {
            return array_fill_keys($uniqueContextIds, []);
        }

        $concreteCandidates = array_values(array_filter(
            $candidatePermissions,
            static fn (Permissions $p) => !WildcardMatcher::isGlobalWildcard($p) && !WildcardMatcher::isResourceWildcard($p)
        ));

        if ($this->isSuperAdmin($user)) {
            $result = [];
            foreach ($uniqueContextIds as $cid) {
                $chain = $this->contextResolver->getAncestorContextsWithType($cid);
                $targetType = ($chain[0]['categoria'] ?? null) instanceof ContextType ? $chain[0]['categoria'] : null;
                $result[$cid] = array_values(array_map(
                    static fn (Permissions $p) => $p->value,
                    array_filter($concreteCandidates, function (Permissions $perm) use ($targetType): bool {
                        if ($targetType === null) {
                            return true;
                        }
                        return \in_array($targetType, $this->getPermissionContextTypes($perm), true);
                    })
                ));
            }

            return $result;
        }

        [$globalContextId, $ancestorsByContext, $upeByContext, $uraByContext] =
            $this->loadChainRowsForContexts($user, $uniqueContextIds);

        $result = [];
        foreach ($uniqueContextIds as $cid) {
            $chain = $ancestorsByContext[$cid];
            $targetType = ($chain[0]['categoria'] ?? null) instanceof ContextType ? $chain[0]['categoria'] : null;
            $delegable = [];

            foreach ($concreteCandidates as $perm) {
                if ($this->evaluateDelegablePermOnChain($perm, $cid, $targetType, $globalContextId, $chain, $upeByContext, $uraByContext)) {
                    $delegable[] = $perm->value;
                }
            }

            $result[$cid] = $delegable;
        }

        return $result;
    }

    /**
     * Carga en una sola consulta las filas UPE y URA aplicables a los contextos indicados y su cadena ancestral.
     *
     * @param int[] $uniqueContextIds
     * @return array{int, array<int, array>, array<int, array>, array<int, array>}
     */
    private function loadChainRowsForContexts(Usuario $user, array $uniqueContextIds): array
    {
        $globalContextId = $this->globalContext->getContextId();
        $ancestorsByContext = [];
        $allRelevantContextIds = [$globalContextId];

        foreach ($uniqueContextIds as $cid) {
            $chain = $this->contextResolver->getAncestorContextsWithType($cid);
            $ancestorsByContext[$cid] = $chain;
            foreach ($chain as $ctx) {
                $allRelevantContextIds[] = (int) $ctx['id_contexto'];
            }
            $allRelevantContextIds[] = $cid;
        }

        $rows = DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->select('id_contexto', 'slug', 'esta_permitido', 'tipo_asignacion', 'puede_delegar')
            ->where('id_usuario', $user->id_usuario)
            ->whereIn('id_contexto', array_values(array_unique($allRelevantContextIds)))
            ->get();

        $upeByContext = [];
        $uraByContext = [];
        foreach ($rows as $row) {
            $ctxId = (int) $row->id_contexto;
            if ($row->tipo_asignacion === self::TIPO_ESPECIAL) {
                $upeByContext[$ctxId][] = $row;
            } elseif ($row->tipo_asignacion === self::TIPO_ROL) {
                $uraByContext[$ctxId][] = $row;
            }
        }

        return [$globalContextId, $ancestorsByContext, $upeByContext, $uraByContext];
    }

    /**
     * Evalúa si un permiso es efectivo Y delegable (`puede_delegar = true`, sin revocaciones en la cadena).
     */
    private function evaluateDelegablePermOnChain(
        Permissions $perm,
        int $explicitContextId,
        ?ContextType $targetType,
        int $globalContextId,
        array $chain,
        array $upeByContext,
        array $uraByContext
    ): bool {
        $validTypes = $this->getPermissionContextTypes($perm);
        if ($targetType !== null && !empty($validTypes) && !\in_array($targetType, $validTypes, true)) {
            return false;
        }

        $targetContexts = $this->filterAncestorsForPermission($chain, $explicitContextId, $globalContextId, $perm);
        if (empty($targetContexts)) {
            return false;
        }

        $hasDelegableGrant = false;
        $hasActiveGrant = false;

        foreach ($targetContexts as $ctxId) {
            $bestUpe = $this->bestUpeMatch($perm, $upeByContext[$ctxId] ?? []);
            if ($bestUpe !== null) {
                if (!(bool) $bestUpe->esta_permitido) {
                    return false;
                }
                $hasActiveGrant = true;
                if ((bool) ($bestUpe->puede_delegar ?? false)) {
                    $hasDelegableGrant = true;
                }
            }

            foreach ($uraByContext[$ctxId] ?? [] as $uraRow) {
                $uraEnum = Permissions::tryFrom($uraRow->slug);
                if ($uraEnum && WildcardMatcher::matches($perm, $uraEnum)) {
                    $hasActiveGrant = true;
                    if ((bool) ($uraRow->puede_delegar ?? false)) {
                        $hasDelegableGrant = true;
                    }
                }
            }
        }

        return $hasActiveGrant && $hasDelegableGrant;
    }

    /**
     * Evalúa si un permiso concreto queda efectivo en una cadena precomputada de ancestros.
     */
    private function evaluateEffectivePermOnChain(
        Permissions $perm,
        int $explicitContextId,
        int $globalContextId,
        array $chain,
        array $upeByContext,
        array $uraByContext
    ): bool {
        $targetContexts = $this->filterAncestorsForPermission($chain, $explicitContextId, $globalContextId, $perm);
        $grantedBySpecial = false;

        foreach ($targetContexts as $ctxId) {
            $upeRows = $upeByContext[$ctxId] ?? [];
            if (empty($upeRows)) {
                continue;
            }

            $verdict = $this->evaluateUpeRows($perm, $upeRows);
            if ($verdict === false) {
                return false;
            }
            if ($verdict === true) {
                $grantedBySpecial = true;
            }
        }

        if ($grantedBySpecial) {
            return true;
        }

        foreach ($targetContexts as $ctxId) {
            foreach ($uraByContext[$ctxId] ?? [] as $uraRow) {
                $uraEnum = Permissions::tryFrom($uraRow->slug);
                if ($uraEnum && WildcardMatcher::matches($perm, $uraEnum)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retorna todos los casos concretos de {@see Permissions} excluyendo wildcards.
     *
     * @return Permissions[]
     */
    private static function concretePermissionCases(): array
    {
        return array_values(array_filter(
            Permissions::cases(),
            static fn(Permissions $p) => !WildcardMatcher::isGlobalWildcard($p) && !WildcardMatcher::isResourceWildcard($p)
        ));
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
        return $this->cache->recordarSuperAdmin(
            $user->id_usuario,
            fn() => DB::connection('pgsql')
                ->table('usuario.vw_permisos_usuario')
                ->where('id_usuario', $user->id_usuario)
                ->where('slug', $this->globalWildcard)
                ->where('id_contexto', $this->globalContext->getContextId())
                ->where('esta_permitido', true)
                ->exists()
        );
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

        foreach ($contextIds as $id) {
            // Obtener cadena completa con tipos de contexto desde la función SQL (tipificados como ContextType enum)
            $ancestorsWithTypes = $this->contextResolver->getAncestorContextsWithType($id);

            foreach ($this->filterAncestorsForPermission($ancestorsWithTypes, (int) $id, $globalContextId, $permission) as $ctxId) {
                $all[] = $ctxId;
            }
        }

        // Retornar array único (la CTE ya retorna ordenado desde más cercano a más lejano)
        // y el global contexto ya está incluido en la cadena SQL
        return array_values(array_unique($all));
    }

    /**
     * Filtra la cadena de ancestros aplicando `no-inherit` y `PermissionContextConstraints`.
     *
     * @param array<int, array{id_contexto: int|string, categoria: mixed}> $ancestorsWithTypes
     * @return int[]
     */
    protected function filterAncestorsForPermission(
        array $ancestorsWithTypes,
        int $explicitContextId,
        int $globalContextId,
        ?Permissions $permission = null
    ): array {
        if ($permission !== null && $this->isNoInheritPermission($permission)) {
            return array_values(array_unique([$explicitContextId, $globalContextId]));
        }

        $validTypes = $permission !== null ? $this->getPermissionContextTypes($permission) : [];
        if (!empty($validTypes)) {
            $ancestorsWithTypes = array_filter(
                $ancestorsWithTypes,
                fn($ctx) => \in_array($ctx['categoria'], $validTypes, true)
            );
        }

        return array_values(array_unique(array_map(
            static fn($ctx) => (int) $ctx['id_contexto'],
            $ancestorsWithTypes
        )));
    }

    /**
     * Validar permiso contra múltiples contextos.
     *
     * Regla de precedencia jerárquica:
     * 1. Si cualquier contexto en la cadena (propio o ancestro) tiene un DENY neto en UPE,
     *    revoca cualquier GRANT o ROL en un nivel inferior (padre > hijo para DENY).
     * 2. Sin DENY en la cadena, evalúa de hijo a padre si existe algún GRANT en UPE o ROL en URA.
     *
     * @param Usuario $user
     * @param Permissions $permission
     * @param array $contextIds Contextos ordenados de más específico a más general
     * @return bool
     */
    protected function validateInAnyContext(Usuario $user, Permissions $permission, array $contextIds): bool
    {
        $specialResults = [];

        // Paso 1: barrido completo de DENY en toda la cadena antes de aceptar un GRANT/ROL hijo
        foreach ($contextIds as $contextId) {
            $specialResult = $this->checkSpecialPermission($user, $permission, $contextId);

            if ($specialResult === false) {
                return false;
            }

            $specialResults[$contextId] = $specialResult;
        }

        // Paso 2: sin DENY en la cadena, autorizar si existe GRANT en UPE o permiso vía URA
        foreach ($contextIds as $contextId) {
            if (($specialResults[$contextId] ?? null) === true) {
                return true;
            }

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
        return $this->cache->recordarUpe(
            $user->id_usuario,
            $permission->value,
            $contextId,
            fn() => $this->resolveSpecialPermission($user, $permission, $contextId)
        );
    }

    /**
     * Resolución sin caché de {@see checkSpecialPermission()}.
     */
    protected function resolveSpecialPermission(Usuario $user, Permissions $permission, int $contextId): ?bool
    {
        // Generar todos los patrones (exacto, wildcard mismo recurso, ancestros, global)
        $patterns = WildcardMatcher::generatePermissionPatterns($permission);

        $results = DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $contextId)
            ->where('tipo_asignacion', self::TIPO_ESPECIAL)
            ->whereIn('slug', $patterns)
            ->get();

        if ($results->isEmpty()) {
            return null;
        }

        return $this->evaluateUpeRows($permission, $results);
    }

    /**
     * Evalúa una colección de filas UPE contra un permiso dado aplicando especificidad
     * de wildcards (`exacto > subrecurso:* > recurso:* > *`, empate gana DENY).
     *
     * @param Permissions $permission
     * @param iterable<object> $rows
     * @return bool|null true=GRANT, false=DENY, null=sin coincidencias aplicables
     */
    protected function evaluateUpeRows(Permissions $permission, iterable $rows): ?bool
    {
        $bestResult = $this->bestUpeMatch($permission, $rows);

        return $bestResult !== null ? (bool) $bestResult->esta_permitido : null;
    }

    /**
     * Retorna la fila UPE más específica aplicable al permiso (empate favorece DENY).
     */
    private function bestUpeMatch(Permissions $permission, iterable $rows): ?object
    {
        $bestPriority = PHP_INT_MAX;
        $bestResult = null;

        foreach ($rows as $result) {
            $castedPermission = Permissions::tryFrom($result->slug);

            if (!$castedPermission) {
                Log::error("Permiso en la BD malformado: {$result->slug}");
                continue;
            }

            $priority = WildcardMatcher::getPriority($permission, $castedPermission);

            // 999 = el patrón no aplica realmente a este permiso
            if ($priority >= self::PRIORITY_NO_MATCH) {
                continue;
            }

            $isDeny = !$result->esta_permitido;

            if ($priority < $bestPriority || ($priority === $bestPriority && $isDeny)) {
                $bestPriority = $priority;
                $bestResult = $result;
            }
        }

        return $bestResult;
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
        return $this->cache->recordarUra(
            $user->id_usuario,
            $permission->value,
            $contextId,
            function () use ($user, $permission, $contextId) {
                // Patrones: exacto, wildcard mismo recurso, ancestros, global
                $patterns = WildcardMatcher::generatePermissionPatterns($permission);

                return DB::connection('pgsql')
                    ->table('usuario.vw_permisos_usuario')
                    ->where('id_usuario', $user->id_usuario)
                    ->where('id_contexto', $contextId)
                    ->where('tipo_asignacion', self::TIPO_ROL)
                    ->whereIn('slug', $patterns)
                    ->exists();
            }
        );
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
        $patterns = WildcardMatcher::generatePermissionPatterns($permission);

        return DB::connection('pgsql')
            ->table('usuario.vw_permisos_usuario')
            ->where('id_usuario', $user->id_usuario)
            ->where('tipo_asignacion', self::TIPO_ROL)
            ->whereIn('slug', $patterns)
            ->pluck('id_contexto')
            ->toArray();
    }


}
