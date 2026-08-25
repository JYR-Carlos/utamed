<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwAlumno extends IntranetBaseModel
{
    use HasFactory;

    protected $table = 'ALUMNO';

    protected $primaryKey = 'ALUM_RUT';

    protected $casts = [
        'ALUM_RUT'              => 'integer',  // number(9) rut sin puntos ni digito verificador
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
     * Normaliza el dígito verificador a mayúscula ('K') y sin espacios.
     */
    protected function alumDigito(): Attribute
    {
        return Attribute::make(
            get: fn($value) => !is_null($value) ? strtoupper(trim($value)) : null
        );
    }

    /**
     * Limpia espacios múltiples y normaliza el nombre a mayúsculas consistentes.
     */
    protected function alumNombre(): Attribute
    {
        return Attribute::make(
            get: fn($value) => !is_null($value) ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($value))) : null
        );
    }

    /**
     * Limpia espacios múltiples y normaliza el apellido paterno a mayúsculas.
     */
    protected function alumApellidoPat(): Attribute
    {
        return Attribute::make(
            get: fn($value) => !is_null($value) ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($value))) : null
        );
    }

    /**
     * Limpia el apellido materno a mayúsculas o retorna null si está vacío/nulo.
     */
    protected function alumApellidoMat(): Attribute
    {
        return Attribute::make(
            get: fn($value) => (!is_null($value) && trim($value) !== '')
                ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($value)))
                : null
        );
    }

    /**
     * Nombre completo formateado y limpio.
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: fn() => trim("{$this->ALUM_NOMBRE} {$this->ALUM_APELLIDO_PAT} " . ($this->ALUM_APELLIDO_MAT ?? ''))
        );
    }

    /**
     * RUT completo formateado con guion (ej: 18401835-K).
     */
    protected function rutCompleto(): Attribute
    {
        return Attribute::make(
            get: fn() => "{$this->ALUM_RUT}-{$this->ALUM_DIGITO}"
        );
    }

    /**
     * Obtener las inscripciones asociadas a este alumno.
     */
    public function inscripciones()
    {
        return $this->hasMany(VwInscripcion::class, 'ALUM_RUT', 'ALUM_RUT');
    }
}
