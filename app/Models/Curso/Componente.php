<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseComponente;

/**
 * Modelo Componente
 * 
 * Extiende de BaseComponente (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Componente extends BaseComponente
{
    /**
     * Asistencia mínima con que nace un componente (OBS-45): 70 %, la regla
     * acordada para toda la plataforma. Después manda lo que tenga el
     * componente en `porcentaje_asistencia_obligatoria`, que es la única
     * fuente: la usan el resumen de notas del alumno, la pestaña de
     * asistencia del docente y lo que precarga el asistente del syllabus.
     * 0 es un valor válido: el componente no exige asistencia.
     */
    public const ASISTENCIA_MINIMA_POR_DEFECTO = 70.0;

    protected $fillable = [
        'genera_acta',
        'porcentaje_aprobacion',
        'aprobacion_obligatoria',
        'porcentaje_asistencia_obligatoria',
        'id_tipo_componente',
        'id_curso',
        'intranet_cur_codigo',
    ];

    protected $casts = [
        'genera_acta'            => 'boolean',
        'aprobacion_obligatoria' => 'boolean',
        'intranet_cur_codigo'    => 'integer',
    ];
}
