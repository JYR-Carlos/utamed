<?php

namespace App\Policies;

use App\Policies\Base\BaseDocentePolicy;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Docente;

/**
 * Policy personalizada para Docente.
 * Creada automáticamente como stub - NO se sobrescribe al regenerar.
 *
 * Patrones disponibles:
 *   1. Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   2. Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   3. Sobrescribir sin parent::      → reemplaza la base completamente
 */
class DocentePolicy extends BaseDocentePolicy
{
    // Sobrescribir métodos customXXX() o CRUD según sea necesario
}
