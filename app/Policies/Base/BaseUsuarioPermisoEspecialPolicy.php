<?php

namespace App\Policies\Base;

use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Policies\Base\Traits\HasBasePolicyMethods;

/**
 * Policy Base para UsuarioPermisoEspecial - AUTOGENERADA
 * ⚠️ NO EDITAR - Se sobrescribe al regenerar
 *
 * Para personalizaciones, extender en app/Policies/UsuarioPermisoEspecialPolicy.php:
 *   - Patrón 1: Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   - Patrón 2: Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   - Patrón 3: Sobrescribir sin parent::      → reemplaza la base completamente
 */
abstract class BaseUsuarioPermisoEspecialPolicy
{
    use HasBasePolicyMethods;

    protected string $resource = 'usuarios/permisos/individuales';

    public function viewAny(Usuario $user): bool
    {
        // 'usuarios/permisos/individuales:ver' no existe en permissions_config.php; delega al hook/override
        return $this->customViewAny($user);
    }

    public function view(Usuario $user, UsuarioPermisoEspecial $model): bool
    {
        // 'usuarios/permisos/individuales:ver' no existe en permissions_config.php; delega al hook/override
        return $this->customView($user, $model);
    }

    public function create(Usuario $user, $parent = null): bool
    {
        // 'usuarios/permisos/individuales:crear' no existe en permissions_config.php; delega al hook/override
        return $this->customCreate($user, $parent);
    }

    public function update(Usuario $user, UsuarioPermisoEspecial $model): bool
    {
        // 'usuarios/permisos/individuales:editar' no existe en permissions_config.php; delega al hook/override
        return $this->customUpdate($user, $model);
    }

    public function delete(Usuario $user, UsuarioPermisoEspecial $model): bool
    {
        // 'usuarios/permisos/individuales:eliminar' no existe en permissions_config.php; delega al hook/override
        return $this->customDelete($user, $model);
    }
}
