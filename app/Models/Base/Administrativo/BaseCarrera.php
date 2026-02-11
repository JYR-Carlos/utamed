<?php

namespace App\Models\Base\Administrativo;

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
        'id_facultad',
        'id_contexto'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        $qualified = parent::qualifyColumn($column);
        // Only quote if not already quoted and contains a dot (table.column)
        if (!str_contains($qualified, '\"') && str_contains($qualified, '.')) {
            return '\"' . str_replace('.', '\".\"', $qualified) . '\"';
        }
        return $qualified;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '\"' . $this->getTable() . '\".\"' . $this->getKeyName() . '\"';
    }


    // Relaciones

    public function departamento()
    {
        return $this->belongsTo(\App\Models\Administrativo\Departamento::class, ['id_departamento', 'id_facultad'], ['id_departamento', 'id_facultad']);
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
