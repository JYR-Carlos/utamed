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
abstract class BaseInscripcionCurso extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'inscripcion_curso';
    protected $primaryKey = 'id_inscripcion_curso';
    public $incrementing = true;

    protected $fillable = [
        'cod_inscripcion_uta',
        'num_intento',
        'fecha_inscripcion',
        'estado_inscripcion',
        'promedio_parcial',
        'id_curso',
        'id_estudiante'
    ];


    // Relaciones

    public function curso()
    {
        $instance = new \App\Models\Curso\Curso();
        return new BelongsTo($instance->newQuery(), $this, 'id_curso', 'id_curso', 'curso');
    }

    public function estudiante()
    {
        $instance = new \App\Models\Usuario\Estudiante();
        return new BelongsTo($instance->newQuery(), $this, 'id_estudiante', 'id_estudiante', 'estudiante');
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

        return $query->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            })
            ->orWhereHas('estudiante', function ($q) use ($contextIds) {
                $q->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
    }
}
