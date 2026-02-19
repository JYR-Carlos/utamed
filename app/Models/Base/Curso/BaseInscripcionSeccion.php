<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseInscripcionSeccion extends CustomBaseModel implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'inscripcion_seccion';
    protected $primaryKey = 'id_inscripcion_seccion';
    public $incrementing = true;

    protected $fillable = [
        'nota_seccion',
        'id_estudiante',
        'id_seccion'
    ];


    // Relaciones

    public function estudiante()
    {
        $instance = new \App\Models\Usuario\Estudiante();
        return new BelongsTo($instance->newQuery(), $this, 'id_estudiante', 'id_estudiante', 'estudiante');
    }

    public function seccion()
    {
        $instance = new \App\Models\Curso\Seccion();
        return new BelongsTo($instance->newQuery(), $this, 'id_seccion', 'id_seccion', 'seccion');
    }

    // Relaciones inversas

    public function asistencias()
    {
        return $this->hasMany(\App\Models\Curso\Asistencia::class, 'id_inscripcion_seccion', 'id_inscripcion_seccion');
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

        return $query->whereHas('estudiante', function ($q) use ($contextIds) {
                $q->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            })
            ->orWhereHas('seccion', function ($q) use ($contextIds) {
                $q->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
    }
}
