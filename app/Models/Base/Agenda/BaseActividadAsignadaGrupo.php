<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasOwnedContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;
use App\Enums\DB\EstadoActividadAsignada;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseActividadAsignadaGrupo extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    public EstadoActividadAsignada $estadoActividadAsignada;

    protected $connection = 'pgsql';
    protected $table = 'actividad_asignada_grupo';
    protected $primaryKey = 'id_actividad_asignada_grupo';
    public $incrementing = true;

    protected $fillable = [
        'nombre_grupo',
        'nota',
        'estado_actividad_asignada',
        'id_actividad',
        'nro_dias_adicionales_para_bloqueo_personal'
    ];

    protected $casts = [
        'estado_actividad_asignada' => EstadoActividadAsignada::class
    ];

    // Relaciones

    public function actividad()
    {
        $instance = new \App\Models\Agenda\Actividad();
        return new BelongsTo($instance->newQuery(), $this, 'id_actividad', 'id_actividad', 'actividad');
    }

    // Relaciones inversas

    public function agendas()
    {
        return $this->hasMany(\App\Models\Agenda\Agenda::class, 'id_actividad_asignada_grupo', 'id_actividad_asignada_grupo');
    }

    public function integranteGrupos()
    {
        return $this->hasMany(\App\Models\Agenda\IntegranteGrupo::class, 'id_actividad_asignada_grupo', 'id_actividad_asignada_grupo');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: actividad
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('actividad', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
    }
}
