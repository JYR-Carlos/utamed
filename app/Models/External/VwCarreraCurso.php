<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwCarreraCurso extends IntranetBaseModel
{
    use HasFactory;
    protected $table = 'CARRERA_CURSO';

    protected $casts = [
        'ASIG_CODIGO'         => 'string',  // varchar(10)
        'CURSO_TIPO_ASIG'     => 'string',  // varchar(1) 
        'CURSO_GRUPO_ASIG'    => 'string',  // varchar(1)
        'CURSO_SEMESTRE_ASIG' => 'integer', // number(1)
        'CURSO_ANO'           => 'integer', // number(4)
        'CARRERA_COD'         => 'integer', // number(3)
        'PLAN_ANO'            => 'integer', // number(4)
        'CUR_CODIGO'          => 'integer', // number(12) [2026][1]0000351 año/semestre/numero-correlativo
    ];

    /**
     * Obtener las inscripciones asociadas a este curso.
     */
    public function inscripciones()
    {
        return $this->hasMany(VwInscripcion::class, 'CUR_CODIGO', 'CUR_CODIGO');
    }
}
