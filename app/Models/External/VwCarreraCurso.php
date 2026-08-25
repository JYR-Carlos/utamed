<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwCarreraCurso extends IntranetBaseModel
{
    use HasFactory;

    protected $table = 'CARRERA_CURSO';

    protected $primaryKey = 'CUR_CODIGO';

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
     * Limpia y normaliza el código de asignatura.
     */
    protected function asigCodigo(): Attribute
    {
        return Attribute::make(
            get: fn($value) => !is_null($value) ? strtoupper(trim($value)) : null
        );
    }

    /**
     * Limpia y normaliza el tipo de asignatura (C, T, L).
     */
    protected function cursoTipoAsig(): Attribute
    {
        return Attribute::make(
            get: fn($value) => !is_null($value) ? strtoupper(trim($value)) : null
        );
    }

    /**
     * Limpia y normaliza la letra de sección/grupo (A, B, C...).
     */
    protected function cursoGrupoAsig(): Attribute
    {
        return Attribute::make(
            get: fn($value) => !is_null($value) ? strtoupper(trim($value)) : null
        );
    }

    /**
     * Obtener las inscripciones asociadas a este curso.
     */
    public function inscripciones()
    {
        return $this->hasMany(VwInscripcion::class, 'CUR_CODIGO', 'CUR_CODIGO');
    }
}
