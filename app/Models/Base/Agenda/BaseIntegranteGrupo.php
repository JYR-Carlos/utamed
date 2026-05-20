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
abstract class BaseIntegranteGrupo extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'integrante_grupo';
    protected $primaryKey = 'id_asignado_actividad';
    public $incrementing = true;

    protected $fillable = [
        'nota_individual',
        'diferencia_decimas',
        'id_actividad_asignada_grupo',
        'id_estudiante'
    ];

    // Relaciones

    public function estudiante()
    {
        $instance = new \App\Models\Usuario\Estudiante();
        return new BelongsTo($instance->newQuery(), $this, 'id_estudiante', 'id_estudiante', 'estudiante');
    }

    public function actividadAsignadaGrupo()
    {
        $instance = new \App\Models\Agenda\ActividadAsignadaGrupo();
        return new BelongsTo($instance->newQuery(), $this, 'id_actividad_asignada_grupo', 'id_actividad_asignada_grupo', 'actividadAsignadaGrupo');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Paths múltiples detectados.
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('actividadAsignadaGrupo', function ($q) use ($contextIds) {
                $q->whereHas('actividad', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            })
            ->orWhereHas('estudiante', function ($q) use ($contextIds) {
                $q->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
    }
}
