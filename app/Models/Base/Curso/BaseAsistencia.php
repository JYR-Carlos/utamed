<?php

namespace App\Models\Base\Curso;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsistencia extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Asistencia';
    protected $primaryKey = 'id_asistencia';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = [
        'dia',
        'hora_inicio',
        'hora_fin',
        'esta_presente',
        'id_estudiante',
        'id_seccion',
        'id_curso'
    ];

    // Relaciones

    public function inscripcionSeccion()
    {
        return $this->belongsTo(\App\Models\Curso\InscripcionSeccion::class, ['id_estudiante', 'id_seccion', 'id_curso'], ['id_estudiante', 'id_seccion', 'id_curso']);
    }

}