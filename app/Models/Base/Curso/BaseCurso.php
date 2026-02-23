<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasOwnedContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseCurso extends CustomBaseModel implements HasOwnedContext
{
    use SoftDeletes;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';
    protected $connection = 'pgsql';
    protected $table = 'curso';
    protected $primaryKey = 'id_curso';
    public $incrementing = true;

    protected $fillable = [
        'cod_curso',
        'nombre',
        'indice_grupo',
        'fecha_inicio',
        'fecha_fin',
        'agno_real',
        'semestre_real',
        'estado_interno',
        'estado_acta',
        'es_plantilla',
        'id_asignacion_plan',
        'id_curso_padre',
        'version_plantilla',
        'letra_grupo'
    ];


    // Relaciones

    public function contexto()
    {
        $instance = new \App\Models\Usuario\Contexto();
        return new BelongsTo($instance->newQuery(), $this, 'id_contexto', 'id_contexto', 'contexto');
    }

    public function asignacionPlan()
    {
        $instance = new \App\Models\Administrativo\AsignacionPlan();
        return new BelongsTo($instance->newQuery(), $this, 'id_asignacion_plan', 'id_asignacion_plan', 'asignacionPlan');
    }

    public function curso()
    {
        $instance = new \App\Models\Curso\Curso();
        return new BelongsTo($instance->newQuery(), $this, 'id_curso_padre', 'id_curso', 'curso');
    }

    // Relaciones inversas

    public function programas()
    {
        return $this->hasMany(\App\Models\Administrativo\Programa::class, 'id_curso', 'id_curso');
    }

    public function cursos()
    {
        return $this->hasMany(\App\Models\Curso\Curso::class, 'id_curso_padre', 'id_curso');
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

    public function estudiantesInscritos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            'inscripcion_curso',
            'id_curso',
            'id_estudiante'
        )
            ->withPivot('id_inscripcion_curso', 'cod_inscripcion_uta', 'num_intento', 'fecha_inscripcion', 'estado_inscripcion', 'promedio_parcial');
    }

}
