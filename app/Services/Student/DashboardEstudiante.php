<?php

namespace App\Services\Student;

use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Estudiante;
use App\Services\MensajeriaService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos del inicio del estudiante compartidos por el portal web y la API móvil.
 */
class DashboardEstudiante
{
    public const DEFAULT_COURSE_IMAGE = '/img/utamed.webp';

    public function __construct(private readonly MensajeriaService $mensajeria) {}

    /** Período vigente: mismo criterio que el portal web. */
    public static function periodoActual(): array
    {
        return ['semestre' => Carbon::now()->month > 6 ? 2 : 1, 'agno' => Carbon::now()->year];
    }

    /**
     * Cursos INSCRITO del alumno con el docente titular de su sección.
     * Sin período = todos los cursos inscritos (comportamiento del portal web).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function cursos(Estudiante $estudiante, ?int $semestre = null, ?int $agno = null)
    {
        $inscripciones = InscripcionCurso::where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado_inscripcion', 'INSCRITO')
            ->when($semestre !== null, fn ($q) => $q->whereHas('curso', fn ($c) => $c->where('semestre_real', $semestre)))
            ->when($agno !== null, fn ($q) => $q->whereHas('curso', fn ($c) => $c->where('agno_real', $agno)))
            ->with([
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera',
                // Programas de estudio vigentes para conocer si tiene syllabus aprobado
                'curso.programas' => fn ($q) => $q->where('es_actual', true)
                    ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO']),
                // Docentes de cada componente, con es_titular para poder priorizar.
                'curso.componentes.docenteComponentes.docente.usuario',
                // Marca qué componentes son la sección del alumno.
                'curso.componentes.inscripcionComponentes' => fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante),
            ])
            ->get();

        return $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;

            // El profesor es el titular de la sección del alumno (mismo criterio
            // que StudentSyllabusPresenter). Sin inscripción por componente se
            // cae a los componentes del curso.
            $componentes = $curso->componentes ?? collect();
            $propios = $componentes->filter(fn ($componente) => $componente->inscripcionComponentes->isNotEmpty());
            $relevantes = $propios->isNotEmpty() ? $propios : $componentes;

            $primerDocente = $relevantes
                ->sortBy('id_componente')
                ->flatMap(fn ($componente) => $componente->docenteComponentes
                    ->sortBy(fn ($dc) => [!$dc->es_titular, $dc->id_docente_componente]))
                ->first()?->docente?->usuario;

            $tienePrograma = $curso->relationLoaded('programas')
                ? $curso->programas->isNotEmpty()
                : false;

            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'letra_grupo' => $curso->letra_grupo,
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
                'semestre_real' => $curso->semestre_real,
                'agno_real' => $curso->agno_real,
                'profesor' => $primerDocente ? trim("{$primerDocente->nombre1} {$primerDocente->apellido1}") : null,
                'tiene_programa' => $tienePrograma,
                'imagen_url' => $curso->imagen_url ?? self::DEFAULT_COURSE_IMAGE,
            ];
        });
    }

    /**
     * Mensajes de nivel curso (`curso.mensaje`) que el alumno todavía no abre,
     * agrupados por curso.
     *
     * Va agrupado por curso y no por componente porque la bandeja se entra desde
     * el curso: dentro, Cátedra y Laboratorio son pestañas. Los mensajes de
     * agenda (consultas y feedback de una entrega) no entran aquí — esos cuelgan
     * de una actividad y se ven en la actividad.
     *
     * @return array{no_leidos:int, cursos:array<int,array{id_curso:int, nombre:string, cod_curso:string, cod_asignatura:?string, no_leidos:int}>}
     */
    public function mensajesNoLeidosPorCurso(int $idUsuario): array
    {
        $componentes = $this->mensajeria->componentesDeEstudiante($idUsuario);

        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
            esStaff: false,
        );

        $cursos = $componentes
            ->map(fn($componente) => [
                'id_curso' => (int) $componente->id_curso,
                'nombre' => $componente->curso_nombre,
                'cod_curso' => $componente->cod_curso,
                'cod_asignatura' => $componente->cod_asignatura,
                'no_leidos' => $noLeidos[(int) $componente->id_componente] ?? 0,
            ])
            ->filter(fn(array $fila) => $fila['no_leidos'] > 0)
            ->groupBy('id_curso')
            ->map(fn($delCurso) => [
                'id_curso' => $delCurso->first()['id_curso'],
                'nombre' => $delCurso->first()['nombre'],
                'cod_curso' => $delCurso->first()['cod_curso'],
                'cod_asignatura' => $delCurso->first()['cod_asignatura'],
                'no_leidos' => $delCurso->sum('no_leidos'),
            ])
            ->sortByDesc('no_leidos')
            ->values();

        return [
            'no_leidos' => (int) $cursos->sum('no_leidos'),
            'cursos' => $cursos->all(),
        ];
    }
}
