<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseEstudiante extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Estudiante';
    protected $primaryKey = 'id_estudiante';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = [
        'agno_ingreso',
        'id_usuario'
    ];

    // Relaciones

    public function carrera()
    {
        return $this->belongsTo(\App\Models\Administrativo\Carrera::class, 'id_carrera', 'id_carrera');
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    // Relaciones inversas

    public function asignadoActividades()
    {
        return $this->hasMany(\App\Models\Agenda\AsignadoActividad::class, 'id_estudiante', 'id_estudiante');
    }

    public function inscripcionCursos()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionCurso::class, 'id_estudiante', 'id_estudiante');
    }

    public function inscripcionSecciones()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionSeccion::class, 'id_estudiante', 'id_estudiante');
    }

    // Relaciones muchos-a-muchos

    public function actividadesAsignadas()
    {
        return $this->belongsToMany(
            \App\Models\Agenda\ActividadAsignada::class,
            '\"utamed.Agenda\".\"Asignado_Actividad\"',
            'id_estudiante',
            'grupo,id_actividad'
        )
        ->withPivot('nota_individual', 'diferencia_decimas');
    }

    public function cursosInscritos()
    {
        return $this->belongsToMany(
            \App\Models\Curso\Curso::class,
            '\"utamed.Curso\".\"Inscripcion_Curso\"',
            'id_estudiante',
            'id_curso'
        )
        ->withPivot('cod_inscripcion_uta', 'num_intento', 'fecha_inscripcion', 'estado_inscripcion', 'promedio_parcial');
    }

    public function seccionesInscritas()
    {
        return $this->belongsToMany(
            \App\Models\Curso\Seccion::class,
            '\"utamed.Curso\".\"Inscripcion_Seccion\"',
            'id_estudiante',
            'id_seccion,id_curso'
        )
        ->withPivot('nota_seccion');
    }

}