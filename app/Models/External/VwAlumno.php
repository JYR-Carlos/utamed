<?php

namespace App\Models\External;

class VwAlumno extends IntranetBaseModel
{
  protected $table = 'ALUMNO';

  protected $primaryKey = 'ALUM_RUT';

  protected $casts = [
    'ALUM_RUT'              => 'integer',
    'ALUM_DIGITO'           => 'string',
    'ALUM_NOMBRE'           => 'string',
    'ALUM_APELLIDO_PAT'     => 'string',
    'ALUM_APELLIDO_MAT'     => 'string',
    'ALUM_SEXO'             => 'integer',
    'ALUM_PAIS_ORIGEN'      => 'integer',
    'ALUM_ESTADO_CIVIL'     => 'integer',
    'ALUM_FECHA_NACIMIENTO' => 'date',
    'ALUM_LUGAR_NACIMIENTO' => 'string',
    'ALUM_NACIONALIDAD'     => 'string',
    'ALUM_APPATERNO_JEFE'   => 'string',
    'ALUM_APMATERNO_JEFE'   => 'string',
    'ALUM_NOMBRE_JEFE'      => 'string',
    'ETCO_CODIGO'           => 'string',
  ];

  /**
   * Obtener las inscripciones asociadas a este alumno.
   */
  public function inscripciones()
  {
    // Parámetros: Modelo Destino, Clave Foránea (en Inscripcion), Clave Local (en Alumno)
    return $this->hasMany(VwInscripcion::class, 'ALUM_RUT', 'ALUM_RUT');
  }
}
