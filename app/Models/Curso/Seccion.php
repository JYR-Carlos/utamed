<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseSeccion;

/**
 * Modelo Seccion
 * 
 * Extiende de BaseSeccion (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Seccion extends BaseSeccion
{
    /**
     * Override primary key to use single identity column for Eloquent compatibility
     */
    protected $primaryKey = 'id_seccion';
    protected $fillable = [
        'id_tipo_seccion',
        'id_docente',
        'id_curso',
        'es_plantilla'
    ];

    protected $casts = [
        'es_plantilla' => 'boolean'
    ];

    public function getRouteKeyName()
    {
        return 'id_seccion';
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}