<?php

namespace App\Models\Base\Administrativo;

use App\Extensions\Compoships\BelongsTo;
use App\Models\Administrativo\Departamento;
use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseCarrera extends Model implements HasContext
{
    use SoftDeletes;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';
    protected $connection = 'pgsql';
    protected $table = 'Carrera';
    protected $primaryKey = 'id_carrera';
    public $incrementing = true;

    protected $fillable = [
        'nombre',
        'jornada',
        'sede',
        'modalidad',
        'id_departamento',
        'id_facultad'
    ];


    // Relaciones

    public function departamento()
    {
        $instance = new Departamento();

        return new BelongsTo(
            $instance->newQuery(),
            $this,
            ['id_departamento', 'id_facultad'],
            ['id_departamento', 'id_facultad'],
            'departamento'
        );
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    // Relaciones inversas

    public function planes()
    {
        return $this->hasMany(\App\Models\Administrativo\Plan::class, 'id_carrera', 'id_carrera');
    }

    public function estudiantes()
    {
        return $this->hasMany(\App\Models\Usuario\Estudiante::class, 'id_carrera', 'id_carrera');
    }

}
