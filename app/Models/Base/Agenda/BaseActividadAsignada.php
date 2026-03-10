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
abstract class BaseActividadAsignada extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'actividad_asignada';
    protected $primaryKey = 'grupo';
    public $incrementing = true;

    protected $fillable = [
        'grupo',
        'nota',
        'id_actividad',
        'id_estado'
    ];


    // Relaciones

    public function actividad()
    {
        $instance = new \App\Models\Agenda\Actividad();
        return new BelongsTo($instance->newQuery(), $this, 'id_actividad', 'id_actividad', 'actividad');
    }

    public function estadoActividad()
    {
        $instance = new \App\Models\Agenda\EstadoActividad();
        return new BelongsTo($instance->newQuery(), $this, 'id_estado', 'id_estado', 'estadoActividad');
    }

    // Relaciones inversas

    public function asignadoActividades()
    {
        return $this->hasMany(\App\Models\Agenda\AsignadoActividad::class, 'grupo', 'grupo');
    }

    // Relaciones muchos-a-muchos

    public function estudiantesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            'asignado_actividad',
            'grupo',
            'id_estudiante'
        )
            ->withPivot('id_asignado_actividad', 'nota_individual', 'diferencia_decimas', 'grupo', 'id_estudiante');
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
