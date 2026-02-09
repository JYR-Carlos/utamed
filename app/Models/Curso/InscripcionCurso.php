<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseInscripcionCurso;

/**
 * Modelo InscripcionCurso
 * 
 * Extiende de BaseInscripcionCurso (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class InscripcionCurso extends BaseInscripcionCurso
{
    use \App\Traits\HasCompositeKey;

    public function getRouteKeyName()
    {
        return 'id_curso';
    }

    // Agrega aquí tus métodos personalizados
    protected $fillable = [
        'id_curso',
        'id_estudiante',
        'cod_inscripcion_uta',
        'num_intento',
        'fecha_inscripcion',
        'estado_inscripcion',
        'promedio_parcial'
    ];

    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}