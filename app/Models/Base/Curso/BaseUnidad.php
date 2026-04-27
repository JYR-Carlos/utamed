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
abstract class BaseUnidad extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'unidad';
    protected $primaryKey = 'id_unidad';
    public $incrementing = true;

    protected $fillable = [
        'num_unidad',
        'nombre',
        'descripcion',
        'id_curso'
    ];

    // Relaciones

    public function curso()
    {
        $instance = new \App\Models\Curso\Curso();
        return new BelongsTo($instance->newQuery(), $this, 'id_curso', 'id_curso', 'curso');
    }

    // Relaciones inversas

    public function actividades()
    {
        return $this->hasMany(\App\Models\Agenda\Actividad::class, 'id_unidad', 'id_unidad');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: curso
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
    }
}
