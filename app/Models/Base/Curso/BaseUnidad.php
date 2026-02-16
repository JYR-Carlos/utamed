<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUnidad extends CustomBaseModel implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Unidad';
    protected $primaryKey = ['id_unidad', 'id_curso'];
    public $incrementing = false;

    protected $fillable = [
        'num_unidad',
        'nombre',
        'descripcion',
        'id_curso',
        'es_plantilla'
    ];


    // Relaciones

    public function curso()
    {
        return $this->belongsTo(\App\Models\Curso\Curso::class, ['id_curso', 'es_plantilla'], ['id_curso', 'es_plantilla']);
    }

    // Relaciones inversas

    public function actividades()
    {
        return $this->hasMany(\App\Models\Agenda\Actividad::class, ['id_unidad', 'id_curso', 'es_plantilla'], ['id_unidad', 'id_curso', 'es_plantilla']);
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
