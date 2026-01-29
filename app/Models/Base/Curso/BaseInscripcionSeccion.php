<?php

namespace App\Models\Base\Curso;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseInscripcionSeccion extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'utamed.Inscripcion_Seccion';
    protected $primaryKey = 'id_estudiante';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = [
        'nota_seccion',
        'id_seccion'
    ];

    // Relaciones

    public function estudiante()
    {
        return $this->belongsTo(\App\Models\Usuario\Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function seccion()
    {
        return $this->belongsTo(\App\Models\Curso\Seccion::class, 'id_seccion', 'id_seccion');
    }

    // Relaciones inversas

    public function asistencias()
    {
        return $this->hasMany(\App\Models\Curso\Asistencia::class, ['id_estudiante', 'id_seccion', 'id_curso'], ['id_estudiante', 'id_seccion', 'id_curso']);
    }

}