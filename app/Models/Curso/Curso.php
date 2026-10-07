<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseCurso;

/**
 * Modelo Curso
 * 
 * Extiende de BaseCurso (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Curso extends BaseCurso
{
    /**
     * Casts adicionales para fechas límite de entrega de programa
     */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_creacion' => 'datetime',
        'fecha_modificacion' => 'datetime',
        'fecha_eliminacion' => 'datetime',
        'fecha_limite_entrega_basico' => 'datetime',
        'fecha_limite_entrega_syllabus' => 'datetime',
        'es_plantilla' => 'boolean',
        'es_colegiado' => 'boolean',
    ];

    /**
     * Rótulo del curso para breadcrumbs, menús y encabezados:
     * «Taller de Multimedia II Grupo A».
     *
     * El nombre es el de la asignatura, no `curso.nombre` (que suele repetirlo)
     * ni `cod_curso`, y la letra del grupo es lo que distingue a dos cursos
     * hermanos del mismo docente. Lee `asignacionPlan.asignatura`: en listados,
     * cargarla antes con `with()` para no hacer una consulta por curso.
     */
    public function etiqueta(): string
    {
        $base = $this->asignacionPlan?->asignatura?->nombre ?: ($this->nombre ?: 'Curso');

        return $this->letra_grupo ? "{$base} Grupo {$this->letra_grupo}" : $base;
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}
