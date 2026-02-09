<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignacionPlan extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'Asignacion_Plan';
    protected $primaryKey = ['id_asignatura', 'id_plan'];
    public $incrementing = false;
    const DELETED_AT = 'fecha_eliminacion';

    public $timestamps = false;

    protected $fillable = [
        'agno_planificado',
        'semestre_planificado',
        'tipo_ramo'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        // If already qualified (contains table.column format with quotes), return as-is
        if (preg_match('/^"[^"]+"\."[^"]+"$/', $column)) {
            return $column;
        }
        
        // Remove any existing quotes
        $column = str_replace(['"', "'"], '', $column);
        
        // If column contains a dot, it's already in table.column format
        if (str_contains($column, '.')) {
            [$table, $col] = explode('.', $column, 2);
            return '"' . $table . '"."' . $col . '"';
        }
        
        // Single column, qualify with table name
        return '"' . $this->getTable() . '"."' . $column . '"';
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '"' . $this->getTable() . '"."' . $this->getKeyName() . '"';
    }


    // Relaciones

    public function asignatura()
    {
        return $this->belongsTo(\App\Models\Administrativo\Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    public function plan()
    {
        return $this->belongsTo(\App\Models\Administrativo\Plan::class, 'id_plan', 'id_plan');
    }

    // Relaciones inversas

    public function cursos()
    {
        return $this->hasMany(\App\Models\Curso\Curso::class, ['id_asignatura', 'id_plan'], ['id_asignatura', 'id_plan']);
    }

}
