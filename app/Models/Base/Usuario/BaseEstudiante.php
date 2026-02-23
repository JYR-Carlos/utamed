<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasContext;
use App\Traits\GlobalContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseEstudiante extends CustomBaseModel implements HasContext
{
    use Compoships;
    use GlobalContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'estudiante';
    protected $primaryKey = 'id_estudiante';
    public $incrementing = true;

    protected $fillable = [
        'agno_ingreso',
        'id_carrera',
        'id_usuario'
    ];


    // Relaciones

    public function carrera()
    {
        $instance = new \App\Models\Administrativo\Carrera();
        return new BelongsTo($instance->newQuery(), $this, 'id_carrera', 'id_carrera', 'carrera');
    }

    public function usuario()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario', 'id_usuario', 'usuario');
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
            'asignado_actividad',
            'id_estudiante',
            'grupo'
        )
            ->withPivot('id_asignado_actividad', 'nota_individual', 'diferencia_decimas');
    }

    public function cursosInscritos()
    {
        return $this->belongsToMany(
            \App\Models\Curso\Curso::class,
            'inscripcion_curso',
            'id_estudiante',
            'id_curso'
        )
            ->withPivot('id_inscripcion_curso', 'cod_inscripcion_uta', 'num_intento', 'fecha_inscripcion', 'estado_inscripcion', 'promedio_parcial');
    }

    public function seccionesInscritas()
    {
        return $this->belongsToMany(
            \App\Models\Curso\Seccion::class,
            'inscripcion_seccion',
            'id_estudiante',
            'id_seccion'
        )
            ->withPivot('id_inscripcion_seccion', 'nota_seccion');
    }

}
