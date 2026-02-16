<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignadoActividad extends CustomBaseModel implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Asignado_Actividad';
    protected $primaryKey = ['grupo', 'id_actividad', 'id_estudiante'];
    public $incrementing = false;

    protected $fillable = [
        'nota_individual',
        'diferencia_decimas',
        'grupo',
        'id_actividad',
        'id_estudiante'
    ];


    // Relaciones

    public function actividadAsignada()
    {
        return $this->belongsTo(\App\Models\Agenda\ActividadAsignada::class, ['grupo', 'id_actividad'], ['grupo', 'id_actividad']);
    }

    public function estudiante()
    {
        return $this->belongsTo(\App\Models\Usuario\Estudiante::class, 'id_estudiante', 'id_estudiante');
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

        return $query->whereHas('actividadAsignada', function ($q) use ($contextIds) {
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
