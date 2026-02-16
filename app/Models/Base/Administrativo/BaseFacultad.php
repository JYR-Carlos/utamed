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
abstract class BaseFacultad extends CustomBaseModel implements HasContext
{
    use SoftDeletes;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';
    protected $connection = 'pgsql';
    protected $table = 'Facultad';
    protected $primaryKey = 'id_facultad';
    public $incrementing = true;

    protected $fillable = [
        'nombre'
    ];


    // Relaciones

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    // Relaciones inversas

    public function departamentos()
    {
        return $this->hasMany(\App\Models\Administrativo\Departamento::class, 'id_facultad', 'id_facultad');
    }

}
