<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseCurso;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

/**
 * Modelo Curso
 * 
 * Extiende de BaseCurso (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Curso extends BaseCurso
{
    use HasFactory;
    /**
     * Override primary key to use single identity column for Eloquent compatibility
     */
    protected $table = 'Curso';
    protected $primaryKey = 'id_curso';
    public $incrementing = true;
    // Note: fillable is inherited from BaseCurso, but we need to add fields that might be missing in Base
    protected $fillable = [
        'cod_curso',
        'nombre',
        'indice_grupo',
        'fecha_inicio',
        'fecha_fin',
        'agno_real',
        'semestre_real',
        'estado_interno',
        'estado_acta',
        'id_contexto',
        'letra_grupo',
        'id_asignatura',
        'id_plan',
    ];

    protected $casts = [
        'es_plantilla' => 'boolean'
    ];

    /**
     * Relación con Docente
     */
    public function docente()
    {
        return $this->belongsTo(\App\Models\Usuario\Docente::class, 'id_docente', 'id_docente');
    }

    /**
     * Relación con Asignatura
     */
    public function asignatura()
    {
        return $this->belongsTo(\App\Models\Administrativo\Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    /**
     * Relación con Plan
     */
    public function plan()
    {
        return $this->belongsTo(\App\Models\Administrativo\Plan::class, 'id_plan', 'id_plan');
    }

    /**
     * Relación con AsignacionPlan
     * Curso tiene id_asignatura e id_plan que juntos identifican una AsignacionPlan
     */
    public function asignacionPlan()
    {
        return $this->hasOne(\App\Models\Administrativo\AsignacionPlan::class, 'id_asignatura', 'id_asignatura')
            ->whereColumn('Asignacion_Plan.id_plan', 'Curso.id_plan');
    }

    /**
     * Override relaciones para evitar claves compuestas que fallan con boolean types en Postgres
     */
    public function secciones()
    {
        return $this->hasMany(\App\Models\Curso\Seccion::class, 'id_curso', 'id_curso');
    }

    /**
     * Fix for double quoting issue in BaseCurso.
     * Reverts to standard Eloquent behavior.
     */
    public function qualifyColumn($column)
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    public function getQualifiedKeyName()
    {
        return $this->qualifyColumn($this->getKeyName());
    }
}