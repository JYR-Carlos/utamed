<?php

namespace App\Models\Base\Administrativo;

use App\Models\Administrativo\Departamento;
use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Extensions\Compoships\BelongsTo;
/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseCarrera extends Model
{
    use SoftDeletes;
    use Compoships;                             // ← Muy importante

    protected $connection = 'pgsql';
    protected $table = 'Carrera';
    protected $primaryKey = 'id_carrera';
    public $incrementing = true;

    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'nombre',
        'jornada',
        'sede',
        'modalidad',
        'id_departamento',
        'id_facultad',
        'id_contexto'
    ];

    // Relaciones: Se está usando una opción manual para el eager loading con llave compuesta que falla si no se utiliza el belongsTO.
    public function departamento()
    {
        $instance = new Departamento;

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