<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseCurso;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
     * Atributos asignables masivamente
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
        'fecha_limite_entrega_basico',
        'fecha_limite_entrega_syllabus',
        'id_asignacion_plan',
        'id_docente_titular',
        'id_curso_padre',
        'version_plantilla',
        'letra_grupo',
        'fecha_ultima_sincronizacion',
    ];

    /**
     * Casts adicionales para fechas límite de entrega de programa y sincronización
     */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_creacion' => 'datetime',
        'fecha_modificacion' => 'datetime',
        'fecha_eliminacion' => 'datetime',
        'fecha_limite_entrega_basico' => 'datetime',
        'fecha_limite_entrega_syllabus' => 'datetime',
        'fecha_ultima_sincronizacion' => 'datetime',
        'es_plantilla' => 'boolean',
        'es_colegiado' => 'boolean',
    ];

    public function sincronizacionesLog(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(IntranetSincronizacionLog::class, 'id_curso', 'id_curso')
            ->orderByDesc('fecha_sincronizacion');
    }

    public function ultimaSincronizacionLog(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(IntranetSincronizacionLog::class, 'id_curso', 'id_curso')
            ->latestOfMany('fecha_sincronizacion');
    }

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

    /**
     * Un curso es colegiado cuando algún componente lo dictan dos o más docentes.
     * Tener un docente distinto en cada componente NO lo hace colegiado.
     *
     * No es una opción manual: se deriva de docente_componente, así que debe
     * recalcularse cada vez que cambian los docentes de un componente.
     */
    public function recalcularColegiado(): bool
    {
        $esColegiado = DocenteComponente::query()
            ->whereHas('componente', fn ($q) => $q->where('id_curso', $this->id_curso))
            ->groupBy('id_componente')
            ->havingRaw('COUNT(DISTINCT id_docente) > 1')
            ->select('id_componente')
            ->exists();

        if ($this->es_colegiado !== $esColegiado) {
            $this->es_colegiado = $esColegiado;
            $this->save();
        }

        return $esColegiado;
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}
