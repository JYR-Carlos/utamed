<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwAlumno extends IntranetBaseModel
{
  use HasFactory;
  protected $table = 'ALUMNO';

  protected $primaryKey = 'ALUM_RUT';

  protected $casts = [
    'ALUM_RUT'              => 'integer',  // number(9)   rut sin puntos ni digito verificador
    'ALUM_DIGITO'           => 'string',   // char(1)
    'ALUM_NOMBRE'           => 'string',   // varchar(35)
    'ALUM_APELLIDO_PAT'     => 'string',   // varchar(25)
    'ALUM_APELLIDO_MAT'     => 'string',   // varchar(25)
    'ALUM_SEXO'             => 'integer',  // number(1)
    'ALUM_PAIS_ORIGEN'      => 'integer',  // number(4)
    'ALUM_ESTADO_CIVIL'     => 'integer',  // number(1)
    'ALUM_FECHA_NACIMIENTO' => 'date',     // date
    'ALUM_LUGAR_NACIMIENTO' => 'string',   // char(0)
    'ALUM_NACIONALIDAD'     => 'string',   // char(0)
    'ALUM_APPATERNO_JEFE'   => 'string',   // char(0)
    'ALUM_APMATERNO_JEFE'   => 'string',   // char(0)
    'ALUM_NOMBRE_JEFE'      => 'string',   // char(0)
    'ETCO_CODIGO'           => 'string',   // char(0)
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
