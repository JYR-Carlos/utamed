<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwInscripcion extends IntranetBaseModel
{
    use HasFactory;

    protected $table = 'INSCRIPCION';

    protected $primaryKey = 'INS_ID';

    protected $casts = [
        'ASIG_CODIGO'                  => 'string',   // varchar(10) "DM050" o similar
        'CURSO_TIPO_ASIG'              => 'string',   // varchar(1) C,T,L
        'CURSO_GRUPO_ASIG'             => 'string',   // varchar(2) A,B,C
        'CURSO_SEMESTRE_ASIG'          => 'integer',  // number(1)  1 o 2 para el semestre
        'CURSO_ANO'                    => 'integer',  // number(4)
        'ALUM_RUT'                     => 'integer',  // number(9)  rut sin puntos ni digito verificador
        'CARRERA_COD'                  => 'integer',  // number(3)  "527" o similar
        'INSCRIP_FECHA'                => 'datetime', // date
        'INSCRIP_BANDERA_RATIFICACION' => 'integer',  // number(1)
        'INSCRIP_FLAG_PROCESADA'       => 'integer',  // number(1)
        'INSCRIP_OPORTUNIDAD_INS'      => 'integer',  // number
        'INSCRIP_NOTA'                 => 'float',    // number(2,1)
        'INSCRIP_POSICION'             => 'integer',  // number(4)
        'INSCRIP_TER_ASIG'             => 'integer',  // number
        'INSCRIP_CNDIC'                => 'integer',  // number
        'TIPOAPROB_COD'                => 'integer',  // number
        'INSCRIP_ORIGEN'               => 'integer',  // number
        'INSCRIP_PLAN'                 => 'integer',  // number(4)
        'INSCRIP_NIVEL'                => 'integer',  // number
        'SEDE_CODIGO'                  => 'integer',  // number(6)
        'GRUPO_CARRERA'                => 'integer',  // number(3)
        'RUT_DIGITADOR'                => 'integer',  // number
        'SESION_WEB'                   => 'integer',  // number
        'ACTFOLIO_FOLIO'               => 'integer',  // number
        'INS_ID'                       => 'integer',  // number(7)  numero-correlativo IMPORTANTE-GUARDAR
        'CUR_CODIGO'                   => 'integer',  // number(12) [2026][1]0000351 año/semestre/numero-correlativo
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
     * Obtener el alumno al que pertenece esta inscripción.
     */
    public function alumno()
    {
        return $this->belongsTo(VwAlumno::class, 'ALUM_RUT', 'ALUM_RUT');
    }

    /**
     * Obtener el curso de la carrera al que pertenece esta inscripción.
     */
    public function carreraCurso()
    {
        return $this->belongsTo(VwCarreraCurso::class, 'CUR_CODIGO', 'CUR_CODIGO');
    }
}
