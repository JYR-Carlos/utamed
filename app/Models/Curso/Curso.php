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
     * Extiende el fillable del base (auto-generado) con campos de fechas límite
     */
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
        'es_plantilla',
        'es_colegiado',
        'id_asignacion_plan',
        'id_docente_titular',
        'id_curso_padre',
        'version_plantilla',
        'letra_grupo',
        'fecha_limite_entrega_basico',
        'fecha_limite_entrega_syllabus',
    ];

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

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}