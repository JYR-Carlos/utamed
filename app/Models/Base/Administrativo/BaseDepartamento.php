<?php

namespace App\Models\Base\Administrativo;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
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
    protected $table = 'Departamento';
    protected $primaryKey = ['id_departamento', 'id_facultad'];
    public $incrementing = false;

    protected $fillable = [
        'nombre',
        'id_facultad'
    ];


    // Relaciones

    public function facultad()
    {
        return $this->belongsTo(\App\Models\Administrativo\Facultad::class, 'id_facultad', 'id_facultad');
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    // Relaciones inversas

    public function carreras()
    {
        return $this->hasMany(\App\Models\Administrativo\Carrera::class, ['id_departamento', 'id_facultad'], ['id_departamento', 'id_facultad']);
    }

}
