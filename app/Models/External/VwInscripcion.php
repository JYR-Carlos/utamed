<?php

namespace App\Models\External;

class VwInscripcion extends IntranetBaseModel
{
  protected $table = 'INSCRIPCION';

  // Si necesitas hacer joins más adelante, puedes definir la clave primaria
  protected $primaryKey = 'INS_ID';

  protected $casts = [
    'ASIG_CODIGO'                  => 'string',
    'CURSO_TIPO_ASIG'              => 'string',
    'CURSO_GRUPO_ASIG'             => 'string',
    'CURSO_SEMESTRE_ASIG'          => 'integer',
    'CURSO_ANO'                    => 'integer',
    'ALUM_RUT'                     => 'integer',
    'CARRERA_COD'                  => 'integer',
    'INSCRIP_FECHA'                => 'datetime',
    'INSCRIP_BANDERA_RATIFICACION' => 'boolean',
    'INSCRIP_FLAG_PROCESADA'       => 'boolean',
    'INSCRIP_OPORTUNIDAD_INS'      => 'integer',
    'INSCRIP_NOTA'                 => 'float',
    'INSCRIP_POSICION'             => 'integer',
    'INSCRIP_TER_ASIG'             => 'integer',
    'INSCRIP_CNDIC'                => 'integer',
    'TIPOAPROB_COD'                => 'integer',
    'INSCRIP_ORIGEN'               => 'integer',
    'INSCRIP_PLAN'                 => 'integer',
    'INSCRIP_NIVEL'                => 'integer',
    'SEDE_CODIGO'                  => 'integer',
    'GRUPO_CARRERA'                => 'integer',
    'RUT_DIGITADOR'                => 'integer',
    'SESION_WEB'                   => 'integer',
    'ACTFOLIO_FOLIO'               => 'integer',
    'INS_ID'                       => 'integer',
    'CUR_CODIGO'                   => 'integer',
  ];

  /**
   * Obtener el alumno al que pertenece esta inscripción.
   */
  public function alumno()
  {
    // Parámetros: Modelo Padre, Clave Foránea (en Inscripcion), Clave Local (en Alumno)
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
