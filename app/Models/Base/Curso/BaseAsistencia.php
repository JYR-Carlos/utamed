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
abstract class BaseAsistencia extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'asistencia';
    protected $primaryKey = 'id_asistencia';
    public $incrementing = true;

    protected $fillable = [
        'dia',
        'hora_inicio',
        'hora_fin',
        'esta_presente',
        'id_inscripcion_seccion'
    ];


    // Relaciones

    public function inscripcionSeccion()
    {
        $instance = new \App\Models\Curso\InscripcionSeccion();
        return new BelongsTo($instance->newQuery(), $this, 'id_inscripcion_seccion', 'id_inscripcion_seccion', 'inscripcionSeccion');
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

        return $query->whereHas('inscripcionSeccion', function ($q) use ($contextIds) {
                $q->whereHas('estudiante', function ($q) use ($contextIds) {
                $q->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
            })
            ->orWhereHas('inscripcionSeccion', function ($q) use ($contextIds) {
                $q->whereHas('seccion', function ($q) use ($contextIds) {
                $q->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
            });
    }
}
