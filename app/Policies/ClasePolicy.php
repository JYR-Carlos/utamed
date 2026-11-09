<?php

namespace App\Policies;

use App\Policies\Base\BaseClasePolicy;
use App\Models\Usuario\Usuario;
use App\Models\Curso\Clase;
use App\Support\Permissions;

/**
 * Policy personalizada para Clase.
 * Creada automáticamente como stub - NO se sobrescribe al regenerar.
 *
 * Patrones disponibles:
 *   1. Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   2. Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   3. Sobrescribir sin parent::      → reemplaza la base completamente
 */
class ClasePolicy extends BaseClasePolicy
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
}
