<?php

namespace App\Policies\Base;

use App\Models\Usuario\Usuario;
use App\Models\Usuario\AsignacionRolPermiso;
use App\Policies\Base\Traits\HasBasePolicyMethods;

/**
 * Policy Base para AsignacionRolPermiso - AUTOGENERADA
 * ⚠️ NO EDITAR - Se sobrescribe al regenerar
 *
 * Para personalizaciones, extender en app/Policies/AsignacionRolPermisoPolicy.php:
 *   - Patrón 1: Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   - Patrón 2: Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   - Patrón 3: Sobrescribir sin parent::      → reemplaza la base completamente
 */
abstract class BaseAsignacionRolPermisoPolicy
{
    use HasBasePolicyMethods;

    protected string $resource = 'asignacion_rol_permiso';

    public function viewAny(Usuario $user): bool
    {
        if ($this->validator()->validate($user, $this->buildPermissionSlug($this->resource, 'ver'))) {
            return true;
        }

        return $this->customViewAny($user);
    }

    public function view(Usuario $user, AsignacionRolPermiso $model): bool
    {
        $contextId = $this->resolveContextId($model);

        if ($this->validator()->validate($user, $this->buildPermissionSlug($this->resource, 'ver'), $model, $contextId)) {
            return true;
        }

        return $this->customView($user, $model);
    }

    public function create(Usuario $user, $parent = null): bool
    {
        $contextId = $parent?->getContextId();

        if ($this->validator()->validate($user, $this->buildPermissionSlug($this->resource, 'crear'), null, $contextId)) {
            return true;
        }

        return $this->customCreate($user, $parent);
    }

    public function update(Usuario $user, AsignacionRolPermiso $model): bool
    {
        if ($this->validator()->validate($user, $this->buildPermissionSlug($this->resource, 'editar'), $model)) {
            return true;
        }

        return $this->customUpdate($user, $model);
    }

    public function delete(Usuario $user, AsignacionRolPermiso $model): bool
    {
        if ($this->validator()->validate($user, $this->buildPermissionSlug($this->resource, 'eliminar'), $model)) {
            return true;
        }

        return $this->customDelete($user, $model);
    }
}
