<?php

namespace App\Models\Base\Agenda;

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
abstract class BaseActividad extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'actividad';
    protected $primaryKey = 'id_actividad';
    public $incrementing = true;

    protected $fillable = [
        'nombre',
        'fecha_limite',
        'visible',
        'ponderacion',
        'exigencia',
        'tipo_actividad',
        'tipo_entrega',
        'es_grupal',
        'max_integrantes',
        'es_plantilla',
        'id_componente',
        'id_unidad'
    ];

    protected $casts = [
        'visible' => 'boolean',
        'es_grupal' => 'boolean',
        'es_plantilla' => 'boolean'
    ];

    // Relaciones

    public function contexto()
    {
        $instance = new \App\Models\Usuario\Contexto();
        return new BelongsTo($instance->newQuery(), $this, 'id_contexto', 'id_contexto', 'contexto');
    }

    public function componente()
    {
        $instance = new \App\Models\Curso\Componente();
        return new BelongsTo($instance->newQuery(), $this, 'id_componente', 'id_componente', 'componente');
    }

    public function unidad()
    {
        $instance = new \App\Models\Curso\Unidad();
        return new BelongsTo($instance->newQuery(), $this, 'id_unidad', 'id_unidad', 'unidad');
    }

    // Relaciones inversas

    public function actividadAsignadaGrupos()
    {
        return $this->hasMany(\App\Models\Agenda\ActividadAsignadaGrupo::class, 'id_actividad', 'id_actividad');
    }

}
