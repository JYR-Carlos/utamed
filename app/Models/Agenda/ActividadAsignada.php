<?php

namespace App\Models\Agenda;

use App\Models\Base\Agenda\BaseActividadAsignada;

/**
 * Modelo ActividadAsignada
 * 
 * Extiende de BaseActividadAsignada (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class ActividadAsignada extends BaseActividadAsignada
{
    use \App\Traits\HasCompositeKey;

    public function getRouteKeyName()
    {
        return 'id_actividad';
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}