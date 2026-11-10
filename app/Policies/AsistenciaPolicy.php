<?php

namespace App\Policies;

use App\Policies\Base\BaseAsistenciaPolicy;
use App\Models\Usuario\Usuario;
use App\Models\Curso\Asistencia;
use App\Support\Permissions;

/**
 * Policy personalizada para Asistencia.
 * Creada automáticamente como stub - NO se sobrescribe al regenerar.
 *
 * Patrones disponibles:
 *   1. Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   2. Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   3. Sobrescribir sin parent::      → reemplaza la base completamente
 */
class AsistenciaPolicy extends BaseAsistenciaPolicy
{
    /**
     * Patrón 3: Usa el verbo de dominio ':registrar' (en lugar de ':crear').
     */
    public function create(Usuario $user, $parent = null): bool
    {
        $contextId = $parent?->getContextId();

        if ($this->validator()->validate($user, Permissions::COMPONENTES_ASISTENCIA_REGISTRAR, null, $contextId)) {
            return true;
        }

        return $this->customCreate($user, $parent);
    }

    /**
     * Patrón 3: Deshabilita el borrado individual de marcas de asistencia;
     * la eliminación se gestiona a nivel de sesión (ClasePolicy::delete).
     */
    public function delete(Usuario $user, Asistencia $model): bool
    {
        return false;
    }
}
