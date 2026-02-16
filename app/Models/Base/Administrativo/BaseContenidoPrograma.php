<?php

namespace App\Models\Base\Administrativo;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseContenidoPrograma extends CustomBaseModel implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Contenido_Programa';
    protected $primaryKey = 'id_contenido_programa';
    public $incrementing = true;

    protected $fillable = [
        'texto_contenido',
        'valor_numerico',
        'orden_item',
        'id_estructura_programa'
    ];


    // Relaciones

    public function estructuraPrograma()
    {
        return $this->belongsTo(\App\Models\Administrativo\EstructuraPrograma::class, 'id_estructura_programa', 'id_estructura_programa');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: estructuraPrograma
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('estructuraPrograma', function ($q) use ($contextIds) {
                $q->whereHas('programa', function ($q) use ($contextIds) {
                $q->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
            });
    }
}
