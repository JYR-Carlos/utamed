<?php

namespace App\Policies;

use App\Models\Usuario\Usuario;
use App\Policies\Base\BaseUnidadPolicy;

/**
 * Policy para Unidad basada estrictamente en permisos efectivos (`BaseUnidadPolicy`
 * valida `cursos/unidades:{ver,crear,editar,eliminar}` mediante `PermissionValidator`).
 * Sin fallbacks por pertenencia a `docente_componente`.
 */
class UnidadPolicy extends BaseUnidadPolicy
{
    protected function customView(Usuario $user, $model): bool
    {
        return false;
    }

    protected function customCreate(Usuario $user, $parent = null): bool
    {
        return false;
    }

    protected function customUpdate(Usuario $user, $model): bool
    {
        return false;
    }

    protected function customDelete(Usuario $user, $model): bool
    {
        return false;
    }
}
