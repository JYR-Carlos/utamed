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
abstract class BasePrograma extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'programa';
    protected $primaryKey = 'id_programa';
    public $incrementing = true;

    protected $fillable = [
        'version_programa',
        'estado',
        'data_syllabus',
        'creado_por',
        'revisado_por',
        'id_curso',
        'es_actual'
    ];

    protected $casts = [
        'data_syllabus' => 'array',
        'es_actual' => 'boolean'
    ];

    // Relaciones

    public function autor()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'creado_por', 'id_usuario', 'autor');
    }

    public function revisor()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'revisado_por', 'id_usuario', 'revisor');
    }

    public function curso()
    {
        $instance = new \App\Models\Curso\Curso();
        return new BelongsTo($instance->newQuery(), $this, 'id_curso', 'id_curso', 'curso');
    }

    // Relaciones inversas

    public function historialCambios()
    {
        return $this->hasMany(\App\Models\Auditoria\ProgramaHistorial::class, 'id_programa', 'id_programa');
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
