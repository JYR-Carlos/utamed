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
abstract class BaseEvaluacion extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'evaluacion';
    protected $primaryKey = 'id_evaluacion';
    public $incrementing = true;

    protected $fillable = [
        'puntaje_obtenido',
        'resultado',
        'evaluacion_obtenida',
        'fecha_evaluacion',
        'id_rubrica',
        'id_usuario_evaluador',
        'id_agenda'
    ];

    protected $casts = [
        'resultado' => 'array'
    ];

    // Relaciones

    public function agenda()
    {
        $instance = new \App\Models\Agenda\Agenda();
        return new BelongsTo($instance->newQuery(), $this, 'id_agenda', 'id_agenda', 'agenda');
    }

    public function usuario()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario_evaluador', 'id_usuario', 'usuario');
    }

    public function rubrica()
    {
        $instance = new \App\Models\Agenda\Rubrica();
        return new BelongsTo($instance->newQuery(), $this, 'id_rubrica', 'id_rubrica', 'rubrica');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: agenda
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('agenda', function ($q) use ($contextIds) {
                $q->whereHas('actividadAsignadaGrupo', function ($q) use ($contextIds) {
                $q->whereHas('actividad', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
            });
    }
}
