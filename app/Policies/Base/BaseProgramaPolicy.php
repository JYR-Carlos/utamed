<?php

namespace App\Policies\Base;

use App\Models\Usuario\Usuario;
use App\Models\Curso\Programa;
use App\Policies\Base\Traits\HasBasePolicyMethods;

/**
 * Policy Base para Programa - AUTOGENERADA
 * ⚠️ NO EDITAR - Se sobrescribe al regenerar
 *
 * Para personalizaciones, extender en app/Policies/ProgramaPolicy.php:
 *   - Patrón 1: Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   - Patrón 2: Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   - Patrón 3: Sobrescribir sin parent::      → reemplaza la base completamente
 */
abstract class BaseProgramaPolicy
{
    use HasBasePolicyMethods;

    protected string $resource = 'cursos/programas';

    public function viewAny(Usuario $user): bool
    {
        // 'cursos/programas:ver' no existe en permissions_config.php; delega al hook/override
        return $this->customViewAny($user);
    }

    public function view(Usuario $user, Programa $model): bool
    {
        // 'cursos/programas:ver' no existe en permissions_config.php; delega al hook/override
        return $this->customView($user, $model);
    }

    public function create(Usuario $user, $parent = null): bool
    {
        // 'cursos/programas:crear' no existe en permissions_config.php; delega al hook/override
        return $this->customCreate($user, $parent);
    }

    public function update(Usuario $user, Programa $model): bool
    {
        // 'cursos/programas:editar' no existe en permissions_config.php; delega al hook/override
        return $this->customUpdate($user, $model);
    }

    public function delete(Usuario $user, Programa $model): bool
    {
        if ($this->validator()->validate($user, $this->buildPermissionSlug($this->resource, 'eliminar'), $model)) {
            return true;
        }

        return $this->customDelete($user, $model);
    }
}
