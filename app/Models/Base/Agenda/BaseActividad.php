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
        'tipo_actividad',
        'tipo_entrega',
        'es_grupal',
        'max_integrantes',
        'es_plantilla',
        'id_seccion',
        'id_unidad'
    ];


    // Relaciones

    public function contexto()
    {
        $instance = new \App\Models\Usuario\Contexto();
        return new BelongsTo($instance->newQuery(), $this, 'id_contexto', 'id_contexto', 'contexto');
    }

    public function seccion()
    {
        $instance = new \App\Models\Curso\Seccion();
        return new BelongsTo($instance->newQuery(), $this, 'id_seccion', 'id_seccion', 'seccion');
    }

    public function unidad()
    {
        $instance = new \App\Models\Curso\Unidad();
        return new BelongsTo($instance->newQuery(), $this, 'id_unidad', 'id_unidad', 'unidad');
    }

    // Relaciones inversas

    public function actividadAsignadas()
    {
        return $this->hasMany(\App\Models\Agenda\ActividadAsignada::class, 'id_actividad', 'id_actividad');
    }

}
