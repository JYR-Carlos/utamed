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
abstract class BaseEstructuraPrograma extends CustomBaseModel implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Estructura_Programa';
    protected $primaryKey = 'id_seccion';
    public $incrementing = true;

    protected $fillable = [
        'nombre_seccion',
        'numeral_romano',
        'es_lista',
        'orden',
        'id_programa',
        'es_actual',
        'id_curso',
        'es_plantilla'
    ];


    // Relaciones

    public function programa()
    {
        return $this->belongsTo(\App\Models\Administrativo\Programa::class, ['id_programa', 'es_actual', 'id_curso', 'es_plantilla'], ['id_programa', 'es_actual', 'id_curso', 'es_plantilla']);
    }

    // Relaciones inversas

    public function contenidoProgramas()
    {
        return $this->hasMany(\App\Models\Administrativo\ContenidoPrograma::class, 'id_seccion', 'id_seccion');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: programa
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
            });
    }
}
