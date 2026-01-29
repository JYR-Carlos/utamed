<?php

namespace App\Models\Base\Curso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseCurso extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'utamed.Curso';
    protected $primaryKey = 'id_curso';
    public $incrementing = true;
    const DELETED_AT = 'fecha_eliminacion';

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'cod_curso',
        'nombre',
        'grupo_indice',
        'fecha_inicio',
        'fecha_fin',
        'agno_real',
        'semestre_real',
        'estado_interno',
        'estado_acta',
        'es_plantilla',
        'id_contexto',
        'id_asignatura',
        'id_plan',
        'grupo_letra'
    ];

    // Relaciones

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    public function asignacionPlan()
    {
        // This is tricky because Curso table has id_asignatura and id_plan
        // but Asignacion_Plan has a unique constraint on them.
        // For now, let's use the natural keys.
        return $this->belongsTo(\App\Models\Administrativo\AsignacionPlan::class, 'id_asignatura', 'id_asignatura');
    }

    // Relaciones inversas

    public function programas()
    {
        return $this->hasMany(\App\Models\Administrativo\Programa::class, 'id_curso', 'id_curso');
    }

    public function inscripcionCursos()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionCurso::class, 'id_curso', 'id_curso');
    }

    public function secciones()
    {
        return $this->hasMany(\App\Models\Curso\Seccion::class, 'id_curso', 'id_curso');
    }

    public function unidades()
    {
        return $this->hasMany(\App\Models\Curso\Unidad::class, 'id_curso', 'id_curso');
    }

    // Relaciones muchos-a-muchos

    public function estudiantes()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            '\"utamed.Curso\".\"Inscripcion_Curso\"',
            'id_curso',
            'id_estudiante'
        )
            ->withPivot('cod_inscripcion_uta', 'num_intento', 'fecha_inscripcion', 'estado_inscripcion', 'promedio_parcial');
    }

}