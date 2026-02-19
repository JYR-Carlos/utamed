<?php

namespace App\Models\Base\Administrativo;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseDepartamento extends CustomBaseModel implements HasContext
{
    use SoftDeletes;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';
    protected $connection = 'pgsql';
    protected $table = 'departamento';
    protected $primaryKey = ['id_departamento', 'id_facultad'];
    public $incrementing = false;

    protected $fillable = [
        'nombre',
        'id_facultad'
    ];



    // return a simple key instead of composite key for wayfinder (that's bad)
    public function getRouteKeyName()
    {
        return 'id_departamento';
    }
    // Relaciones

    public function facultad()
    {
        $instance = new \App\Models\Administrativo\Facultad();
        return new BelongsTo($instance->newQuery(), $this, 'id_facultad', 'id_facultad', 'facultad');
    }

    public function contexto()
    {
        $instance = new \App\Models\Usuario\Contexto();
        return new BelongsTo($instance->newQuery(), $this, 'id_contexto', 'id_contexto', 'contexto');
    }

    // Relaciones inversas

    public function carreras()
    {
        return $this->hasMany(\App\Models\Administrativo\Carrera::class, ['id_departamento', 'id_facultad'], ['id_departamento', 'id_facultad']);
    }

}
