<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuario;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @method bool isAdmin() Verifica si es administrador
 * @method bool isDocente() Verifica si es docente
 * @method bool isStudent() Verifica si es estudiante
 * @method bool isAyudante() Verifica si es ayudante
 * @method bool hasRole(string $roleName) Verifica si tiene un rol específico
 * @method bool hasAnyRole(array $roleNames) Verifica si tiene alguno de los roles
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
     * Check if user has a specific permission in a given context.
     * 
     * NOTA: Método existente refactorizado para usar PermissionValidator.
     * Mantiene firma para retrocompatibilidad.
     * 
     * @param string $slug Slug del permiso (ej: 'facultad:ver')
     * @param int $id_contexto ID del contexto
     * @return bool
     */
    public function hasPermission(string $slug, int $id_contexto): bool
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
        $normalizedRoles = array_map(
            fn($role) => strtolower(trim($role)),
            $roles
        );

        $roleNames = array_column($this->getAllRoles(), 'nombre');
        return !empty(array_intersect($normalizedRoles, $roleNames));
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
     * Verificar permiso con resolución automática de contexto desde un recurso.
     * 
     * @param string $permission Slug del permiso
     * @param HasContext|null $resource Instancia del modelo (opcional)
     * @return bool
     */
    public function hasPermissionFor(string $permission, ?HasContext $resource = null): bool
    {
        return app(PermissionValidator::class)
            ->validate($this, $permission, $resource);
    }

    /**
     * Verificar permiso en contexto explícito.
     * 
     * Alias de hasPermission() con nombre más descriptivo.
     * 
     * @param string $permission Slug del permiso
     * @param int $contextId ID del contexto
     * @return bool
     */
    public function hasPermissionInContext(string $permission, int $contextId): bool
    {
        return $this->hasPermission($permission, $contextId);
    }

    /**
     * Obtener contextos donde el usuario tiene un permiso.
     * 
     * Útil para filtrar queries con whereContext()
     * 
     * @param string $permission Slug del permiso
     * @return array Array de IDs de contexto
     */
    public function getContextsFromPermission(string $permission): array
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
     * @param string $ability Acción simple ('view', 'create') o slug completo ('facultad:ver')
     * @param array|mixed $arguments Argumentos (modelo, contexto, etc.)
     * @return bool
     */
    public function can($ability, $arguments = []): bool
    {
        // Detectar si es slug completo (contiene ':') o wildcard ('*')
        $isSlugCompleto = str_contains($ability, ':');
        $isWildcard = $ability === '*';

        if ($isSlugCompleto || $isWildcard) {
            // SLUG COMPLETO O WILDCARD: Usar PermissionValidator directo
            $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
            $resource = ($model instanceof HasContext) ? $model : null;
            return $this->hasPermissionFor($ability, $resource);
        } else {
            // ACCIÓN SIMPLE: Delegar a Laravel's Policy system
            // Si no hay Policy registrada, retornará false automáticamente
            return $this->authorizableCan($ability, $arguments);
        }
    }

    /**
     * Check if a requested slug matches a user's permission slug using wildcards.
     * 
     * @deprecated Usar WildcardMatcher en su lugar. Mantenido para retrocompatibilidad.
     * @param string $requestedSlug The permission being checked (e.g., 'activity:edit')
     * @param string $userSlug The permission the user has (e.g., 'activity:*')
     * @return bool
     */
    protected function matchesSlug(string $requestedSlug, string $userSlug): bool
    {
        return \App\Services\Authorization\WildcardMatcher::matches($requestedSlug, $userSlug);
    }
}