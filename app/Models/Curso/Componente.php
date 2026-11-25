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
     */
    public const ASISTENCIA_MINIMA_POR_DEFECTO = 70.0;

    /** Aprobación mínima con que nace un componente: el 4,0 con 60 %. */
    public const APROBACION_MINIMA_POR_DEFECTO = 60.0;

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

    /**
     * La asistencia mínima que rige. Las dos columnas son NOT NULL, así que un
     * componente sin configurar quedó guardado con 0: vale el valor por
     * defecto, nunca «mínimo 0 %».
     */
    public function asistenciaMinima(): float
    {
        return (float) $this->porcentaje_asistencia_obligatoria ?: self::ASISTENCIA_MINIMA_POR_DEFECTO;
    }

    /** La aprobación mínima que rige; 0 es «sin configurar», como en la asistencia. */
    public function aprobacionMinima(): float
    {
        return (float) $this->porcentaje_aprobacion ?: self::APROBACION_MINIMA_POR_DEFECTO;
    }
}
