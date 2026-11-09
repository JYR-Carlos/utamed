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
abstract class BaseBibliografia extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;
    protected $connection = 'pgsql';
    protected $table = 'bibliografia';
    protected $primaryKey = 'uuid_bibliografia';
    public $incrementing = false;

    protected $fillable = [
        'uuid_bibliografia',
        'id_programa',
        'id_unidad',
        'titulo',
        'autor',
        'cita',
        'editorial',
        'agno',
        'url',
        'uuid_archivo',
        'es_bibliografia_uta',
        'agregado_por'
    ];

    protected $casts = [
        'es_bibliografia_uta' => 'boolean'
    ];

    // Relaciones

    public function programa()
    {
        $instance = new \App\Models\Curso\Programa();
        return new BelongsTo($instance->newQuery(), $this, 'id_programa', 'id_programa', 'programa');
    }

    public function unidad()
    {
        $instance = new \App\Models\Curso\Unidad();
        return new BelongsTo($instance->newQuery(), $this, 'id_unidad', 'id_unidad', 'unidad');
    }

    public function agregador()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'agregado_por', 'id_usuario', 'agregador');
    }

    public function archivo()
    {
        $instance = new \App\Models\Operaciones\Archivo();
        return new BelongsTo($instance->newQuery(), $this, 'uuid_archivo', 'uuid_archivo', 'archivo');
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

        return $query->whereHas('programa', function ($q) use ($contextIds) {
                $q->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            })
            ->orWhereHas('unidad', function ($q) use ($contextIds) {
                $q->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
    }
}
