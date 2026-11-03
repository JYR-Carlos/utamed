<?php

namespace App\Services\Student;

use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Estudiante;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DetalleCursoEstudiante
{
    private const DEFAULT_COURSE_IMAGE = '/img/utamed.webp';

    /**
     * Valida inscripción activa y arma la ficha completa del curso para el estudiante.
     *
     * @throws HttpException 403 si el alumno no está inscrito o su estado no es INSCRITO.
     */
    public function resolver(Curso $curso, Estudiante $estudiante): array
    {
        $this->asegurarInscripcionActiva($curso, $estudiante);

        $curso->loadMissing([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'docenteTitular.usuario',
            'programas' => fn ($q) => $q->where('es_actual', true)
                ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO']),
        ]);

        $asignatura = $curso->asignacionPlan?->asignatura;
        $componentes = $this->resolverComponentes($curso, $estudiante);
        $unidades = $this->resolverUnidades($curso);

        $primerDocente = $componentes->flatMap(fn ($c) => $c['docentes'])
            ->firstWhere('es_titular', true)['nombre']
            ?? $curso->docenteTitular?->usuario?->nombre_completo;

        $tienePrograma = $curso->relationLoaded('programas')
            ? $curso->programas->isNotEmpty()
            : Programa::where('id_curso', $curso->id_curso)
                ->where('es_actual', true)
                ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
                ->exists();

        return [
            'curso' => [
                'id_curso' => (int) $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'cod_asignatura' => $asignatura?->cod_asignatura ?? '',
                'asignatura_nombre' => $asignatura?->nombre ?? 'N/A',
                'etiqueta' => $curso->etiqueta(),
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'letra_grupo' => $curso->letra_grupo,
                'semestre_real' => (int) $curso->semestre_real,
                'agno_real' => (int) $curso->agno_real,
                'fecha_inicio' => $curso->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $curso->fecha_fin?->format('Y-m-d'),
                'docente_titular' => $primerDocente,
                'tiene_programa' => $tienePrograma,
                'imagen_url' => $curso->imagen_url ?? self::DEFAULT_COURSE_IMAGE,
            ],
            'asignatura' => [
                'cod_asignatura' => $asignatura?->cod_asignatura ?? '',
                'nombre' => $asignatura?->nombre ?? 'N/A',
                'creditos_sct' => $asignatura?->creditos_sct ? (int) $asignatura->creditos_sct : null,
                'horas_catedra' => $asignatura?->horas_catedra !== null ? (int) $asignatura->horas_catedra : null,
                'horas_taller' => $asignatura?->horas_taller !== null ? (int) $asignatura->horas_taller : null,
                'horas_laboratorio' => $asignatura?->horas_laboratorio !== null ? (int) $asignatura->horas_laboratorio : null,
                'horas_dirigidas' => $asignatura?->horas_dirigidas !== null ? (int) $asignatura->horas_dirigidas : null,
                'horas_autonomas' => $asignatura?->horas_autonomas !== null ? (int) $asignatura->horas_autonomas : null,
                'descripcion' => $asignatura?->descripcion,
            ],
            'componentes' => $componentes->values()->all(),
            'unidades' => $unidades->values()->all(),
        ];
    }

    private function asegurarInscripcionActiva(Curso $curso, Estudiante $estudiante): void
    {
        $inscrito = $estudiante->inscripcionCursos()
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->exists();

        if (!$inscrito) {
            abort(403, 'No estás inscrito en este curso.');
        }
    }

    private function resolverComponentes(Curso $curso, Estudiante $estudiante): Collection
    {
        $query = Componente::where('id_curso', $curso->id_curso)
            ->with([
                'tipoComponente',
                'docenteComponentes.docente.usuario',
                'inscripcionComponentes' => fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante),
            ]);

        $todos = $query->get();
        $propios = $todos->filter(fn ($c) => $c->inscripcionComponentes->isNotEmpty());
        $relevantes = $propios->isNotEmpty() ? $propios : $todos;

        return $relevantes->sortBy('id_componente')->map(function (Componente $componente) {
            $docentes = $componente->docenteComponentes
                ->sortBy(fn ($dc) => [!$dc->es_titular, $dc->id_docente_componente])
                ->map(function ($dc) {
                    $u = $dc->docente?->usuario;
                    return $u ? [
                        'nombre' => $u->nombre_completo,
                        'email' => $u->email,
                        'es_titular' => (bool) $dc->es_titular,
                    ] : null;
                })
                ->filter()
                ->values()
                ->all();

            return [
                'id_componente' => (int) $componente->id_componente,
                'tipo' => $componente->tipoComponente?->tipo ?? 'Componente',
                'porcentaje_aprobacion' => $componente->porcentaje_aprobacion !== null ? (float) $componente->porcentaje_aprobacion : null,
                'aprobacion_obligatoria' => (bool) $componente->aprobacion_obligatoria,
                'porcentaje_asistencia_obligatoria' => $componente->porcentaje_asistencia_obligatoria !== null ? (float) $componente->porcentaje_asistencia_obligatoria : null,
                'genera_acta' => (bool) $componente->genera_acta,
                'docentes' => $docentes,
            ];
        });
    }

    private function resolverUnidades(Curso $curso): Collection
    {
        $unidades = Unidad::where('id_curso', $curso->id_curso)
            ->orderBy('num_unidad', 'asc')
            ->get();

        if ($unidades->isNotEmpty()) {
            return $unidades->map(fn ($u) => [
                'id_unidad' => (int) $u->id_unidad,
                'num_unidad' => (int) $u->num_unidad,
                'nombre' => $u->nombre,
                'descripcion' => $u->descripcion,
            ]);
        }

        // Fallback al syllabus vigente si existe
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
            ->first();

        if (!$programa) {
            return collect();
        }

        $data = is_array($programa->data_syllabus)
            ? $programa->data_syllabus
            : json_decode($programa->data_syllabus ?? '[]', true);

        $unidadesSyllabus = $data['secciones']['seccionVI']['unidades'] ?? [];

        return collect($unidadesSyllabus)->map(function ($u, $idx) {
            return [
                'id_unidad' => null,
                'num_unidad' => (int) ($u['num_unidad'] ?? ($idx + 1)),
                'nombre' => $u['titulo'] ?? $u['nombre'] ?? 'Unidad ' . ($idx + 1),
                'descripcion' => $u['descripcion'] ?? null,
            ];
        });
    }
}
