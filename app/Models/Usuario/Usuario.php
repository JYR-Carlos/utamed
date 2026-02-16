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

/**
 * Modelo Usuario
 * 
 * Extiende de BaseUsuario (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */

/**
 * Modelo Usuario
 * 
 * Extiende de BaseUsuario (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc . */
class Usuario extends BaseUsuario implements Authenticatable, AuthorizableContract
{
    use AuthenticatableTrait, HasFactory;
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
     * Get the column name for the "remember me" token.
     * Overrides default 'remember_token' column.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'token_recuerdame_sesion';
    }

    /**
     * Roles assigned to the user in different contexts.
     * @deprecated Usar relaciones directas del modelo base
     */
    public function rolesAsignados()
    {
        return $this->hasMany(UsuarioRolAsignación::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Special individual permissions assigned to the user.
     * @deprecated Usar relaciones directas del modelo base
     */
    public function permisosEspeciales()
    {
        return $this->hasMany(UsuarioPermisoEspecial::class, 'id_usuario', 'id_usuario');
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
    public function getContextsWithPermission(string $permission): array
    {
        return app(PermissionValidator::class)
            ->getContextsWithPermission($this, $permission);
    }

    /**
     * Obtener todos los permisos efectivos del usuario.
     * 
     * Para debugging y mostrar en UI.
     * 
     * @param int|null $contextId Filtrar por contexto
     * @return \Illuminate\Support\Collection
     */
    public function getAllPermissions(?int $contextId = null): \Illuminate\Support\Collection
    {
        return app(PermissionValidator::class)
            ->getUserPermissions($this, $contextId);
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
     * 1. hasPermission(slug, contextId): Verificar permiso en contexto explícito
     *    Ej: $user->hasPermission('facultad:ver', 5)
     * 
     * 2. hasPermissionFor(slug, resource?): Verificar permiso con resolución automática
     *    Ej: $user->hasPermissionFor('facultad:editar', $facultad)
     * 
     * 3. getContextsWithPermission(slug): Obtener contextos donde tiene permiso
     *    Ej: $contextIds = $user->getContextsWithPermission('curso:ver')
     *        $cursos = Curso::whereContext($contextIds)->get()
     * 
     * 4. getAllPermissions(contextId?): Listar todos los permisos efectivos
     *    Ej: $permisos = $user->getAllPermissions(5) // Para debugging/UI
     * 
     * 5. can(ability, arguments): Integración con Laravel Gates/Policies
     *    Ej: $user->can('view', $facultad) // Usa FacultadPolicy si existe
     *        $user->can('facultad:ver', $facultad) // Fallback a PermissionValidator
     * 
     * USO CON POLICIES (FASE 4 - Pendiente de implementación):
     * ----------------------------------------------------------
     * 
     * Las Policies deben registrarse en AuthServiceProvider y usar PermissionValidator:
     * 
     * // app/Policies/FacultadPolicy.php (EJEMPLO FUTURO)
     * class FacultadPolicy
     * {
     *     public function __construct(
     *         protected PermissionValidator $validator
     *     ) {}
     * 
     *     public function view(Usuario $user, Facultad $facultad): bool
     *     {
     *         return $this->validator->validate($user, 'facultad:ver', $facultad);
     *     }
     * 
     *     public function create(Usuario $user, ?HasContext $parent = null): bool
     *     {
     *         $contextId = $parent?->getContextId()[0] ?? null;
     *         return $this->validator->validate($user, 'facultad:crear', null, $contextId);
     *     }
     * }
     * 
     * // app/Providers/AuthServiceProvider.php
     * protected $policies = [
     *     Facultad::class => FacultadPolicy::class,
     * ];
     * 
     * // En Controllers
     * $this->authorize('view', $facultad); // Usa FacultadPolicy::view()
     * 
     * ============================================================================
     */

    /**
     * Override del método can() de Laravel para integrar con el sistema de permisos.
     * 
     * FLUJO DE AUTORIZACIÓN:
     * 1. PRIORIDAD: Policies registradas en AuthServiceProvider
     *    - Si existe Policy para el modelo, Laravel la ejecuta automáticamente
     *    - La Policy internamente debe usar PermissionValidator
     * 
     * 2. FALLBACK: PermissionValidator directo (solo si NO hay Policy)
     *    - Para slugs de permisos ('recurso:accion') sin Policy registrada
     *    - Permite usar $user->can('facultad:ver', $facultad) sin Policy
     * 
     * RECOMENDACIÓN: Usar Policies para modelos con validaciones complejas.
     * Para verificaciones directas, preferir: $user->hasPermissionFor($slug, $resource)
     * 
     * @param string $ability Nombre de la habilidad ('view', 'create') o slug ('facultad:ver')
     * @param array|mixed $arguments Argumentos (modelo, contexto, etc.)
     * @return bool
     */
    public function can($ability, $arguments = []): bool
    {
        // PASO 1: Delegar a Laravel's Gate/Policy system
        // Esto ejecutará Policies registradas automáticamente
        
        // PASO 2: Fallback para slugs de permisos SIN Policy registrada
        // parent::can() retornó false, podría ser porque:
        // a) Una Policy denegó explícitamente (respetar esa decisión)
        // b) No hay Policy registrada (usar PermissionValidator)
        
        if (!(str_contains($ability, ':') && $ability === '*')) {
            return $this->authorizableCan($ability, $arguments);

        } else {
            $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
            $resource = ($model instanceof HasContext) ? $model : null;
            return $this->hasPermissionFor($ability, $resource);
        }

        // Para habilidades estándar sin Policy, retornar false
        return false;
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

    /**
     * Fix for double quoting issue in BaseUsuario.
     * Reverts to standard Eloquent behavior.
     */
    public function qualifyColumn($column)
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    public function getQualifiedKeyName()
    {
        return $this->getTable() . '.' . $this->getKeyName();
    }
}