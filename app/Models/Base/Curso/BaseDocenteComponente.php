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
abstract class BaseDocenteComponente extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'docente_componente';
    protected $primaryKey = 'id_docente_componente';
    public $incrementing = true;

    protected $fillable = [
        'es_titular',
        'id_docente',
        'id_componente'
    ];

    protected $casts = [
        'es_titular' => 'boolean'
    ];

    // Relaciones

    public function docente()
    {
        $instance = new \App\Models\Usuario\Docente();
        return new BelongsTo($instance->newQuery(), $this, 'id_docente', 'id_docente', 'docente');
    }

    public function componente()
    {
        $instance = new \App\Models\Curso\Componente();
        return new BelongsTo($instance->newQuery(), $this, 'id_componente', 'id_componente', 'componente');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: componente
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('componente', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
    }
}
