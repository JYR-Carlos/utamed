<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseCurso;
use Illuminate\Support\Facades\DB;

/**
 * Modelo Curso
 * 
 * Extiende de BaseCurso (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Curso extends BaseCurso
{
    protected $fillable = [
        'cod_curso',
        'nombre',
        'fecha_inicio',
        'numero_semestre',
        'id_asignatura',
        'id_plan',
        'id_docente',
        'id_contexto'
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
            ->whereColumn('utamed.Asignacion_Plan.id_plan', 'utamed.Curso.id_plan');
    }
}