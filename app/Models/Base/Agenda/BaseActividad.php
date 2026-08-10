<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasOwnedContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;
use App\Enums\DB\TipoActividad;

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
    public TipoActividad $tipoActividad;

    protected $connection = 'pgsql';
    protected $table = 'actividad';
    protected $primaryKey = 'id_actividad';
    public $incrementing = true;

    protected $fillable = [
        'nombre',
        'fecha_limite',
        'nro_dias_adicionales_para_bloqueo',
        'visible',
        'ponderacion',
        'exigencia',
        'tipo_actividad',
        'tipo_entrega',
        'es_grupal',
        'max_integrantes',
        'es_plantilla',
        'id_componente',
        'id_unidad',
        'uuid_archivo'
    ];

    protected $casts = [
        'visible' => 'boolean',
        'tipo_actividad' => TipoActividad::class,
        'es_grupal' => 'boolean',
        'es_plantilla' => 'boolean'
    ];

    // Relaciones

    public function unidad()
    {
        $instance = new \App\Models\Curso\Unidad();
        return new BelongsTo($instance->newQuery(), $this, 'id_unidad', 'id_unidad', 'unidad');
    }

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

    public function archivo()
    {
        $instance = new \App\Models\Operaciones\Archivo();
        return new BelongsTo($instance->newQuery(), $this, 'uuid_archivo', 'uuid_archivo', 'archivo');
    }

    // Relaciones inversas

    public function actividadAsignadaGrupos()
    {
        return $this->hasMany(\App\Models\Agenda\ActividadAsignadaGrupo::class, 'id_actividad', 'id_actividad');
    }

    public function rubricas()
    {
        return $this->hasMany(\App\Models\Agenda\Rubrica::class, 'id_actividad', 'id_actividad');
    }

}
