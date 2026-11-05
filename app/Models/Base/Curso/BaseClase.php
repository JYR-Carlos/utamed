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
abstract class BaseClase extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'clase';
    protected $primaryKey = 'id_clase';
    public $incrementing = true;

    protected $fillable = [
        'dia',
        'hora_inicio',
        'hora_fin',
        'id_componente'
    ];

    // Relaciones

    public function componente()
    {
        $instance = new \App\Models\Curso\Componente();
        return new BelongsTo($instance->newQuery(), $this, 'id_componente', 'id_componente', 'componente');
    }

    // Relaciones inversas

    public function asistencias()
    {
        return $this->hasMany(\App\Models\Curso\Asistencia::class, 'id_clase', 'id_clase');
    }

}
