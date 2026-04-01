<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasOwnedContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseComponente extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'componente';
    protected $primaryKey = 'id_componente';
    public $incrementing = true;

    protected $fillable = [
        'genera_acta',
        'porcentaje_aprobacion',
        'aprobacion_obligatoria',
        'porcentaje_asistencia_obligatoria',
        'id_tipo_componente',
        'id_curso'
    ];


    // Relaciones

    public function tipoComponente()
    {
        $instance = new \App\Models\Curso\TipoComponente();
        return new BelongsTo($instance->newQuery(), $this, 'id_tipo_componente', 'id_tipo_componente', 'tipoComponente');
    }

    public function curso()
    {
        $instance = new \App\Models\Curso\Curso();
        return new BelongsTo($instance->newQuery(), $this, 'id_curso', 'id_curso', 'curso');
    }

    public function contexto()
    {
        $instance = new \App\Models\Usuario\Contexto();
        return new BelongsTo($instance->newQuery(), $this, 'id_contexto', 'id_contexto', 'contexto');
    }

    // Relaciones inversas

    public function actividades()
    {
        return $this->hasMany(\App\Models\Agenda\Actividad::class, 'id_componente', 'id_componente');
    }

    public function docenteComponentes()
    {
        return $this->hasMany(\App\Models\Curso\DocenteComponente::class, 'id_componente', 'id_componente');
    }

    public function inscripcionComponentes()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionComponente::class, 'id_componente', 'id_componente');
    }

    // Relaciones muchos-a-muchos

    public function docentesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Docente::class,
            'docente_componente',
            'id_componente',
            'id_docente'
        )
            ->withPivot('id_docente_componente', 'id_docente', 'id_componente');
    }

    public function estudiantesInscritos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            'inscripcion_componente',
            'id_componente',
            'id_estudiante'
        )
            ->withPivot('id_inscripcion_componente', 'nota_componente', 'id_estudiante', 'id_componente');
    }

}
