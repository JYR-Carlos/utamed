<?php

namespace App\Policies;

use App\Models\Administrativo\Facultad;
use App\Models\Usuario\Usuario;
use App\Policies\Base\BaseFacultadPolicy;

/**
 * FacultadPolicy
 *
 * Las facultades son gestionadas exclusivamente por administradores.
 * Se usan overrides Pattern 3 para evitar dependencia del enum Permissions,
 * que puede no tener slugs de 'facultades:*' registrados.
 *
 * SuperAdmin siempre pasa vía before() del trait HasBasePolicyMethods.
 * Administradores tienen acceso completo a operaciones CRUD de facultades.
 */
class FacultadPolicy extends BaseFacultadPolicy
{
    /**
     * Verifica si el usuario tiene rol de administrador.
     */
    private function isAdmin(Usuario $user): bool
    {
        return $user->hasRole('Administrador')
            || $user->hasRole('Admin')
            || $user->hasRole('administrador')
            || $user->hasRole('admin');
    }

    /**
     * Cualquier admin puede listar facultades.
     */
    public function viewAny(Usuario $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Cualquier admin puede ver una facultad individual.
     */
    public function view(Usuario $user, Facultad $model): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Solo admins pueden crear facultades.
     */
    public function create(Usuario $user, $parent = null): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Solo admins pueden editar facultades.
     */
    public function update(Usuario $user, Facultad $model): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Solo admins pueden eliminar facultades.
     */
    public function delete(Usuario $user, Facultad $model): bool
    {
        return $this->isAdmin($user);
    }
}
