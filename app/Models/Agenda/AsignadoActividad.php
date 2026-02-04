<?php

namespace App\Models\Agenda;

use App\Models\Base\Agenda\BaseAsignadoActividad;

/**
 * Modelo AsignadoActividad
 * 
 * Extiende de BaseAsignadoActividad (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class AsignadoActividad extends BaseAsignadoActividad
{
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