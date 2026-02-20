<?php

namespace App\Policies;

use App\Policies\Base\BaseAsignacionPlanPolicy;
use App\Models\Usuario\Usuario;
use App\Models\Administrativo\AsignacionPlan;

/**
 * Policy personalizada para AsignacionPlan.
 * Creada automáticamente como stub - NO se sobrescribe al regenerar.
 *
 * Patrones disponibles:
 *   1. Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   2. Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   3. Sobrescribir sin parent::      → reemplaza la base completamente
 */
class AsignacionPlanPolicy extends BaseAsignacionPlanPolicy
{
    // Sobrescribir métodos customXXX() o CRUD según sea necesario
}
