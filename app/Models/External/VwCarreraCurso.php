<?php

namespace App\Models\External;

class VwCarreraCurso extends IntranetBaseModel
{
    protected $table = 'CARRERA_CURSO';

    protected $casts = [
        'ASIG_CODIGO'         => 'string',
        'CURSO_TIPO_ASIG'     => 'string',
        'CURSO_GRUPO_ASIG'    => 'string',
        'CURSO_SEMESTRE_ASIG' => 'integer',
        'CURSO_ANO'           => 'integer',
        'CARRERA_COD'         => 'integer',
        'PLAN_ANO'            => 'integer',
        'CUR_CODIGO'          => 'integer',
    ];

    /**
     * Obtener las inscripciones asociadas a este curso.
     */
    public function inscripciones()
    {
        return $this->hasMany(VwInscripcion::class, 'CUR_CODIGO', 'CUR_CODIGO');
    }
}
