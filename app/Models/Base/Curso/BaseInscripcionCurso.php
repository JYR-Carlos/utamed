<?php

namespace App\Models\Base\Curso;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseInscripcionCurso extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Inscripcion_Curso';
    protected $primaryKey = 'id_curso';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'cod_inscripcion_uta',
        'num_intento',
        'fecha_inscripcion',
        'estado_inscripcion',
        'promedio_parcial',
        'id_estudiante'
    ];

    // Relaciones

    public function curso()
    {
        return $this->belongsTo(\App\Models\Curso\Curso::class, 'id_curso', 'id_curso');
    }

    public function estudiante()
    {
        return $this->belongsTo(\App\Models\Usuario\Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

}