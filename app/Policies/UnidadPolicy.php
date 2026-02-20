<?php

namespace App\Policies;

use App\Policies\Base\BaseUnidadPolicy;
use App\Models\Usuario\Usuario;
use App\Models\Curso\Unidad;

/**
 * Policy personalizada para Unidad.
 * Creada automáticamente como stub - NO se sobrescribe al regenerar.
 *
 * Patrones disponibles:
 *   1. Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   2. Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   3. Sobrescribir sin parent::      → reemplaza la base completamente
 */
class UnidadPolicy extends BaseUnidadPolicy
{
    // Sobrescribir métodos customXXX() o CRUD según sea necesario
}
