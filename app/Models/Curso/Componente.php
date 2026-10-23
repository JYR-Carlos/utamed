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
