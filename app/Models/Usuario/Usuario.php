<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuario;

/**
 * Modelo Usuario
 * 
 * Extiende de BaseUsuario (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

/**
 * Modelo Usuario
 * 
 * Extiende de BaseUsuario (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Usuario extends BaseUsuario implements Authenticatable
{
    use AuthenticatableTrait;

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

    protected function casts(): array
    {
        return [
            'esta_activo' => 'boolean',
        ];
    }

    protected $fillable = [
        'username',
        'passhash',
        'email',
        'nombre1',
        'nombre2',
        'apellido1',
        'apellido2',
        'rut',
        'esta_activo'
    ];

    /**
     * Roles assigned to the user in different contexts.
     */
    public function rolesAsignados()
    {
        return $this->hasMany(UsuarioRolAsignación::class, 'id_usuario_recipiente', 'id_usuario');
    }

    /**
     * Special individual permissions assigned to the user.
     */
    public function permisosEspeciales()
    {
        return $this->hasMany(UsuarioPermisoEspecial::class, 'id_usuario_recipiente', 'id_usuario');
    }

    /**
     * Check if user has a specific permission in a given context.
     * Special permissions override role permissions.
     */
    public function hasPermission(string $slug, int $id_contexto): bool
    {
        // 1. Get all active permissions for this user (Role-based & Special)
        // We'll prioritize special permissions (allow/deny) over roles

        $specialPerms = $this->permisosEspeciales()
            ->where('id_contexto', $id_contexto)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->with('permiso')
            ->get();

        // Check explicit DENY first (special permission with esta_permitido = false)
        foreach ($specialPerms as $special) {
            if ($special->esta_permitido === false && $this->matchesSlug($slug, $special->permiso->slug)) {
                return false; // Explicitly denied
            }
        }

        // Check explicit ALLOW (special permission with esta_permitido = true)
        foreach ($specialPerms as $special) {
            if ($special->esta_permitido === true && $this->matchesSlug($slug, $special->permiso->slug)) {
                return true; // Explicitly allowed
            }
        }

        // 2. Check Role Permissions
        $rolePerms = $this->rolesAsignados()
            ->where('id_contexto', $id_contexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->with(['rol.permisos'])
            ->get()
            ->pluck('rol.permisos')
            ->flatten()
            ->unique('id_permiso');

        foreach ($rolePerms as $perm) {
            if ($this->matchesSlug($slug, $perm->slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a requested slug matches a user's permission slug using wildcards.
     * 
     * @param string $requestedSlug The permission being checked (e.g., 'activity:edit')
     * @param string $userSlug The permission the user has (e.g., 'activity:*')
     * @return bool
     */
    protected function matchesSlug(string $requestedSlug, string $userSlug): bool
    {
        if ($userSlug === '*') {
            return true;
        }

        if ($userSlug === $requestedSlug) {
            return true;
        }

        // Check wildcard: "activity:*" matches "activity:edit"
        if (str_ends_with($userSlug, '*')) {
            $prefix = substr($userSlug, 0, -1);
            if (str_starts_with($requestedSlug, $prefix)) {
                return true;
            }
        }

        return false;
    }
}