<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuario;
use App\Support\Permissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\Authorization\GlobalContextService;
use App\Services\Authorization\PermissionValidator;
use App\Contracts\HasContext;
use App\Enums\PermissionTypeEnum;
use App\Traits\AssignsPermissions;

/**
 * Modelo Usuario
 * 
 * Extiende de BaseUsuario (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 * 
 * Relaciones:
 * @property-read \App\Models\Usuario\Docente|null $docente Perfil docente del usuario
 * @property-read \App\Models\Usuario\Estudiante|null $estudiante Perfil estudiante del usuario
 * 
 * Métodos de relación:
 * @method BelongsToMany rolesAsignados() Roles asignados al usuario vía UsuarioRolAsignacion
 * @method BelongsToMany contextosConRolAsignado() Contextos donde el usuario tiene roles asignados
 * @method BelongsToMany usuarioPermisoEspeciales() Permisos especiales del usuario
 * 
 * Métodos auxiliares de roles:
 * @method bool isSuperAdmin() Verifica si es SuperAdmin
 * @method bool hasRole(string $roleName) Verifica si tiene un rol específico (ej: 'Docente Titular')
 * @method bool hasAnyRole(array $roleNames) Verifica si tiene alguno de los roles
 * @method bool hasPermissionFor(Permissions $slug, HasContext|null $resource = null) Verifica permiso
 */
class Usuario extends BaseUsuario implements Authenticatable, AuthorizableContract
{
    use AuthenticatableTrait, HasFactory;
    use AssignsPermissions;
    use Authorizable {
        Authorizable::can as authorizableCan;
    }

    /**
     * Get the password for the user.
     * Overrides default 'password' column.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->passhash;
    }

    /**
     * Verificar permiso con resolución automática de contexto desde un recurso.
     * 
     * @param Permissions $slug Slug del permiso
     * @param HasContext|null $resource Instancia del modelo (opcional)
     * @return bool
     */
    public function hasPermissionFor(Permissions $slug, ?HasContext $resource = null): bool
    {
        return app(PermissionValidator::class)
            ->validate($this, $slug, $resource);
    }

    /**
     * Check if user has a specific permission in a given context.
     * 
     * NOTA: Método existente refactorizado para usar PermissionValidator.
     * Mantiene firma para retrocompatibilidad.
     * 
     * @param Permissions $slug Slug del permiso (ej: 'facultad:ver')
     * @param int $id_contexto ID del contexto
     * @return bool
     */
    public function hasPermission(Permissions $slug, int $id_contexto): bool
    {
        return app(PermissionValidator::class)
            ->validate($this, $slug, null, $id_contexto);
    }

    /**
     * Accessor para is_admin (para compatibilidad con $user->is_admin)
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Verificar si el usuario tiene un rol específico en cualquier contexto.
     * 
     * El nombre del rol se normaliza (trim + lowercase) antes de comparar.
     * Solo considera roles activos y no eliminados.
     * 
     * @param string $role Nombre del rol
     * @return bool True si el usuario tiene el rol en cualquier contexto
     * 
     * @example
     *   $user->hasRole('Ayudante') → true si es ayudante en algún contexto
     *   $user->hasRole('  AYUDANTE  ') → true (trim + lowercase automático)
     * 
     * TODO: Crear enum Roles para estandarizar nombres de roles (RolesEnum::AYUDANTE, etc)
     */
    public function hasRole(string $role): bool
    {
        $normalizedRole = strtolower(trim($role));
        $roleNames = array_column($this->getAllRoles(), 'nombre');
        return in_array($normalizedRole, $roleNames);
    }

    /**
     * Verificar si el usuario tiene CUALQUIERA de los roles especificados.
     * 
     * Retorna true si el usuario tiene al menos uno de los roles en cualquier contexto.
     * Los nombres se normalizan (trim + lowercase) antes de comparar.
     * Solo considera roles activos y no eliminados.
     * 
     * @param array $roles Array de nombres de roles
     * @return bool True si el usuario tiene al menos uno de los roles
     * 
     * @example
     *   $user->hasAnyRole(['Ayudante', 'Docente']) → true si es ayudante O docente
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->hasAnyRoleInContext($roles, null);
    }

    /**
     * Verificar si el usuario tiene alguno de los roles **en un contexto concreto**.
     *
     * `hasRole()`/`hasAnyRole()` responden "en cualquier contexto", lo que convierte
     * un rol acotado (jefe de una carrera, admin de un departamento) en un rol
     * global. Cuando la decisión depende del ámbito hay que usar este método.
     *
     * @param array    $roles      Nombres de rol (se normalizan a minúsculas)
     * @param int|null $contextId  Contexto donde exigir el rol; null = cualquiera
     */
    public function hasAnyRoleInContext(array $roles, ?int $contextId): bool
    {
        $normalizedRoles = array_map(
            fn($role) => strtolower(trim($role)),
            $roles
        );

        $roleNames = array_column($this->getAllRoles($contextId), 'nombre');
        return !empty(array_intersect($normalizedRoles, $roleNames));
    }

    /**
     * Alias con los que el rol administrativo aparece en la BD.
     *
     * Existen los cuatro porque la comparación de roles nunca estuvo unificada;
     * mientras no exista el RolesEnum, ésta es la lista canónica y ningún sitio
     * debe escribir la suya.
     *
     * TODO: reemplazar por RolesEnum (ver el TODO de hasRole más arriba).
     */
    public const ROLES_ADMINISTRATIVOS = ['SuperAdmin', 'Super Admin', 'Admin', 'Administrador'];

    /**
     * Verificar si el usuario tiene alguno de los roles en el contexto global.
     */
    public function hasAnyRoleGlobally(array $roles): bool
    {
        return $this->hasAnyRoleInContext(
            $roles,
            app(GlobalContextService::class)->getContextId()
        );
    }

    /**
     * Verificar si el usuario tiene TODOS los roles especificados.
     * 
     * Retorna true solamente si el usuario tiene todos los roles en cualquier contexto.
     * Los nombres se normalizan (trim + lowercase) antes de comparar.
     * Solo considera roles activos y no eliminados.
     * 
     * @param array $roles Array de nombres de roles
     * @return bool True si el usuario tiene todos los roles
     * 
     * @example
     *   $user->hasAllRoles(['Ayudante', 'Docente']) → true si es ambos
     */
    public function hasAllRoles(array $roles): bool
    {
        $normalizedRoles = array_map(
            fn($role) => strtolower(trim($role)),
            $roles
        );

        $roleNames = array_column($this->getAllRoles(), 'nombre');

        // Retornar true si todos los roles buscados están en los roles del usuario
        return count(array_intersect($normalizedRoles, $roleNames)) === count($normalizedRoles);
    }

    /**
     * Verificar permiso en contexto explícito.
     * 
     * Alias de hasPermission() con nombre más descriptivo.
     * 
     * @param Permissions $permission Slug del permiso
     * @param int $contextId ID del contexto
     * @return bool
     */
    public function hasPermissionInContext(Permissions $permission, int $contextId): bool
    {
        return $this->hasPermission($permission, $contextId);
    }

    /**
     * Obtener contextos donde el usuario tiene un permiso.
     * 
     * Útil para filtrar queries con whereContext()
     * 
     * @param Permissions $permission Slug del permiso
     * @return array Array de IDs de contexto
     */
    public function getContextsFromPermission(Permissions $permission): array
    {
        return app(PermissionValidator::class)
            ->getContextsFromPermission($this, $permission);
    }

    /**
     * Verificar si el usuario es SuperAdmin.
     * 
     * Retorna true si el usuario tiene el permiso '*' en el contexto global.
     * Sin necesidad de pasar el contexto explícitamente.
     * 
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return app(PermissionValidator::class)->isSuperAdmin($this);
    }

    /**
     * Obtener todos los permisos efectivos del usuario y sus detalles.
     * 
     * Permite filtrar por contexto específico y tipo de permiso (ROL o ESPECIAL).
     * 
     * @param int|null $contextId El contexto por cual filtrar permisos (null para todos los contextos)
     * @param PermissionTypeEnum|null $permissionType Filtrar por tipo: ROL o ESPECIAL
     * @return array<
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
     * > Permisos efectivos con detalles completos
     */
    public function getAllPermissions(?int $contextId = null, ?PermissionTypeEnum $permissionType = null): array
    {
        return app(PermissionValidator::class)
            ->getUserPermissions($this, $contextId, $permissionType)
            ->toArray();
    }

    /**
     * Obtener todos los permisos del usuario agrupados por contexto en una sola query.
     *
     * Evita el problema N+1 cuando se necesitan permisos para múltiples contextos
     * (p. ej. sidebar de ayudante con varios cursos asignados).
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection> Colección indexada por id_contexto
     */
    public function getAllPermissionsGroupedByContext(): \Illuminate\Support\Collection
    {
        return app(PermissionValidator::class)
            ->getUserPermissions($this)
            ->groupBy('id_contexto');
    }

    /**
     * ¿La fila pivote de usuario_rol_asignacion está vigente (y, si se pide, en
     * el contexto indicado)?
     *
     * El pivote no declara casts, así que según el driver `esta_activo` puede
     * llegar como bool o como el `t`/`f` de Postgres; `(bool) 'f'` sería `true`,
     * de ahí la comparación explícita.
     */
    private function asignacionVigente($pivot, ?int $contextId): bool
    {
        if (!$pivot) {
            return false;
        }

        $esVerdadero = static fn($v) => $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';

        if (!$esVerdadero($pivot->esta_activo) || $esVerdadero($pivot->fue_eliminado)) {
            return false;
        }

        return $contextId === null || (int) $pivot->id_contexto === $contextId;
    }

    /**
     * Obtener todos los roles (nombre e id) del usuario.
     *
     * Permite filtrar por contexto específico.
     * 
     * @param int|null $contextId El contexto por cual filtrar roles (null para todos los contextos)
     * @return array<
     *   int, 
     *   array{
     *     id: int, 
     *     nombre: string
     *   }
     * > Array de ids y nombres de roles (ej: [['id' => 1, 'nombre' => 'ayudante'], ...])
     */
    public function getAllRoles(?int $contextId = null): array
    {
        // Si la relación ya está cargada, resolver en memoria. `share()` la carga
        // en cada request y aun así cada hasRole()/hasAnyRole() lanzaba su propia
        // consulta: sólo /dashboard encadenaba cinco (A-6).
        if ($this->relationLoaded('rolesAsignados')) {
            return $this->rolesAsignados
                ->filter(fn($role) => $this->asignacionVigente($role->pivot ?? null, $contextId))
                ->map(fn($role) => [
                    'id' => $role->id_rol,
                    'nombre' => strtolower($role->nombre),
                ])
                ->values()
                ->all();
        }

        $query = $this->rolesAsignados()
            ->wherePivot('esta_activo', true)
            ->wherePivot('fue_eliminado', false);

        if ($contextId !== null) {
            $query->wherePivot('id_contexto', $contextId);
        }

        return $query->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id_rol,
                    'nombre' => strtolower($role->nombre),
                ];
            })
            ->toArray();
    }

    /* 
     * ============================================================================
     * SISTEMA DE PERMISOS RBAC CON CONTEXTOS JERÁRQUICOS
     * ============================================================================
     * 
     * Este modelo incluye métodos para validación de permisos basados en:
     * - URA (Usuario-Rol-Asignación): Permisos heredados de roles
     * - UPE (Usuario-Permiso-Especial): Permisos individuales (GRANT/DENY)
     * - Contextos jerárquicos: Permisos limitados a ámbitos específicos
     * 
     * MÉTODOS DISPONIBLES:
     * --------------------
     * 
     * 1. hasRole(role): Verificar si user tiene UN rol específico en cualquier contexto
     *    Ej: $user->hasRole('Ayudante') → true si es ayudante en algún contexto
     *    TODO: Crear enum Roles (app/Enums/RolesEnum.php) para estandarizar nombres
     *          Reemplazar strings por: RolesEnum::AYUDANTE->value, etc.
     * 
     * 2. hasAnyRole(roles): Verificar si user tiene CUALQUIERA de los roles
     *    Ej: $user->hasAnyRole(['Ayudante', 'Docente']) → true si es cualquiera
     * 
     * 3. hasAllRoles(roles): Verificar si user tiene TODOS los roles especificados
     *    Ej: $user->hasAllRoles(['Ayudante', 'Docente']) → true si es ambos
     * 
     * 4. hasPermission(slug, contextId): Verificar permiso en contexto explícito
     *    Ej: $user->hasPermission('facultad:ver', 5)
     * 
     * 5. hasPermissionFor(slug, resource?): Verificar permiso con resolución automática
     *    Ej: $user->hasPermissionFor('facultad:editar', $facultad)
     * 
     * 6. getContextsWithPermission(slug): Obtener contextos donde tiene permiso
     *    Ej: $contextIds = $user->getContextsWithPermission('curso:ver')
     *        $cursos = Curso::whereContext($contextIds)->get()
     * 
     * 7. getAllPermissions(contextId?): Listar todos los permisos efectivos
     *    Ej: $permisos = $user->getAllPermissions(5) // Para debugging/UI
     * 
     * 8. can(ability, arguments): Integración con Laravel Gates/Policies
     *    Ej: $user->can('view', $facultad) // Usa FacultadPolicy si existe
     *        $user->can('facultad:ver', $facultad) // Fallback a PermissionValidator
     */

    /**
     * Override del método can() de Laravel para integrar con el sistema de permisos.
     * 
     * FLUJO DE AUTORIZACIÓN (CORREGIDO):
     * ===================================
     * 
     * 1. Si $ability es una acción simple ('view', 'create', etc.):
     *    - Delegar a Laravel's Authorizable::can() → ejecutará Policy si existe
     *    - Si no hay Policy, retornar false (NO permitir acceso directo)
     *    - RECOMENDACIÓN: Usar Policies siempre para modelos
     * 
     * 2. Si $ability es un slug completo ('facultad:ver', 'curso:crear', etc.):
     *    - Usar PermissionValidator directamente (validación con contextos jerárquicos)
     *    - Para recursos virtuales o validaciones complejas
     * 
     * 3. Si $ability es '*' (wildcard):
     *    - Usar PermissionValidator (verifica permiso super admin)
     * 
     * RESTRICCIÓN: 
     * - Para modelos con Policy, DEBE usarse can() con acciones simples
     * - Para validaciones diretas de permisos, DEBE usarse hasPermissionFor()
     * - Esto previene confusión y asegura consistencia
     * 
     * @param string|Permissions $ability Acción simple ('view', 'create') o slug completo ('facultad:ver')
     * @param array|mixed $arguments Argumentos (modelo, contexto, etc.)
     * @return bool
     */
    public function can($ability, $arguments = []): bool
    {
        // Detectar si es slug completo (contiene ':') o wildcard ('*')
        $isSlugCompleto = str_contains($ability, ':');
        $isGlobalWildcard = $ability === Permissions::GLOBAL_WILDCARD->value;

        if ($isSlugCompleto || $isGlobalWildcard) {
            // tryFrom() returns Permissions|null - type-safe at compile time
            $permission = Permissions::tryFrom($ability);

            if ($permission === null) {
                return false; // Invalid permission slug
                //TODO: hacer exception personalizada para casos de uso incorrecto (ej: usar can() con slug sin contexto o slug invalido)
            }

            // SLUG COMPLETO O WILDCARD: Usar PermissionValidator directo
            $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
            $resource = ($model instanceof HasContext) ? $model : null;
            return $this->hasPermissionFor($permission, $resource);
        } 

        // ACCIÓN SIMPLE: Delegar a Laravel's Policy system
        // Si no hay Policy registrada, retornará false automáticamente
        return $this->authorizableCan($ability, $arguments);
    }

    /**
     * Check if a requested slug matches a user's permission slug using wildcards.
     * 
     * @deprecated Usar WildcardMatcher en su lugar. Mantenido para retrocompatibilidad.
     * @param Permissions $requestedSlug The permission being checked (e.g., 'activity:edit')
     * @param Permissions $userSlug The permission the user has (e.g., 'activity:*')
     * @return bool
     */
    protected function matchesSlug(Permissions $requestedSlug, Permissions $userSlug): bool
    {
        return \App\Services\Authorization\WildcardMatcher::matches($requestedSlug, $userSlug);
    }

    /**
     * Obtiene el nombre abreviado del usuario.
     *
     * @example "JPérez"
     * 
     * @return string Nombre abreviado formado por la inicial del nombre y el apellido completo
     */
    public function nombreAbreviado(): string
    {
        if (empty($this->nombre1)) {
            return 'N/A';
        }

        $inicial = strtoupper($this->nombre1[0]);
        $apellido = $this->apellido1 ?? 'N/A';

        return "$inicial$apellido";
    }

    /**
     * Accessor "fuente de verdad" para el nombre completo del usuario.
     *
     * Concatena nombre1, nombre2, apellido1 y apellido2 (los segundos son
     * opcionales) y normaliza los espacios sobrantes. Este es el formato
     * canónico para mostrar el nombre de un usuario en toda la app (caso
     * Eloquent). Para queries crudas SQL ver NombreUsuario::sqlConcat().
     *
     * @example "Juan Pablo Pérez Soto"
     *
     * @return string Nombre completo normalizado (cadena vacía si no hay datos)
     */
    public function getNombreCompletoAttribute(): string
    {
        $partes = array_filter([
            $this->nombre1,
            $this->nombre2,
            $this->apellido1,
            $this->apellido2,
        ], fn ($p) => $p !== null && trim((string) $p) !== '');

        return trim(implode(' ', $partes));
    }
}