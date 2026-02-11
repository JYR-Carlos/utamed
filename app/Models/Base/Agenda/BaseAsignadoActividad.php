<?php

namespace App\Models\Base\Agenda;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignadoActividad extends Model implements HasContext
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
        'diferencia_decimas'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        $qualified = parent::qualifyColumn($column);
        // Only quote if not already quoted and contains a dot (table.column)
        if (!str_contains($qualified, '\"') && str_contains($qualified, '.')) {
            return '\"' . str_replace('.', '\".\"', $qualified) . '\"';
        }
        return $qualified;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '\"' . $this->getTable() . '\".\"' . $this->getKeyName() . '\"';
    }


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
