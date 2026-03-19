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
abstract class BaseInscripcionComponente extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'inscripcion_componente';
    protected $primaryKey = 'id_inscripcion_componente';
    public $incrementing = true;

    protected $fillable = [
        'nota_componente',
        'id_estudiante',
        'id_componente'
    ];


    // Relaciones

    public function estudiante()
    {
        $instance = new \App\Models\Usuario\Estudiante();
        return new BelongsTo($instance->newQuery(), $this, 'id_estudiante', 'id_estudiante', 'estudiante');
    }

    public function componente()
    {
        $instance = new \App\Models\Curso\Componente();
        return new BelongsTo($instance->newQuery(), $this, 'id_componente', 'id_componente', 'componente');
    }

    // Relaciones inversas

    public function asistencias()
    {
        return $this->hasMany(\App\Models\Curso\Asistencia::class, 'id_inscripcion_componente', 'id_inscripcion_componente');
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

        return $query->whereHas('componente', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            })
            ->orWhereHas('estudiante', function ($q) use ($contextIds) {
                $q->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
    }
}
