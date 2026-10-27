<?php

namespace App\Services\Student;

use App\Models\Curso\Curso;
use App\Services\MensajeriaService;

/**
 * Lo que muestran las tarjetas de curso del estudiante en el Inicio y en
 * «Mis cursos»: el docente de su sección y los mensajes de curso sin leer.
 */
class ResumenCursosEstudiante
{
    public function __construct(private MensajeriaService $mensajeria)
    {
    }

    /**
     * Relaciones que necesita {@see docenteDeSeccion()}, para el `with()` de
     * las inscripciones del estudiante.
     *
     * @return array<int|string, mixed>
     */
    public static function relacionesDocente(int $idEstudiante): array
    {
        return [
            // Docentes de cada componente, con es_titular para poder priorizar.
            'curso.componentes.docenteComponentes.docente.usuario',
            // Marca qué componentes son la sección del alumno.
            'curso.componentes.inscripcionComponentes' => fn ($q) => $q->where('id_estudiante', $idEstudiante),
        ];
    }

    /**
     * El titular de la sección del alumno (mismo criterio que
     * StudentSyllabusPresenter). Si todavía no hay inscripción por componente,
     * se cae a los componentes del curso.
     */
    public function docenteDeSeccion(Curso $curso): string
    {
        $componentes = $curso->componentes ?? collect();
        $propios = $componentes->filter(fn ($componente) => $componente->inscripcionComponentes->isNotEmpty());
        $relevantes = $propios->isNotEmpty() ? $propios : $componentes;

        $primerDocente = $relevantes
            ->sortBy('id_componente')
            ->flatMap(fn ($componente) => $componente->docenteComponentes
                ->sortBy(fn ($dc) => [!$dc->es_titular, $dc->id_docente_componente]))
            ->first()?->docente?->usuario;

        return $primerDocente
            ? trim("{$primerDocente->nombre1} {$primerDocente->apellido1}")
            : '(sin docente asignado)';
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
    public function mensajesSinLeer(int $idUsuario): array
    {
        $componentes = $this->mensajeria->componentesDeEstudiante($idUsuario);

        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
            esStaff: false,
        );

        $cursos = $componentes
            ->map(fn($componente) => [
                'id_curso'  => (int) $componente->id_curso,
                'nombre'    => $componente->curso_nombre,
                'cod_curso' => $componente->cod_curso,
                'cod_asignatura' => $componente->cod_asignatura,
                'no_leidos' => $noLeidos[(int) $componente->id_componente] ?? 0,
            ])
            ->filter(fn(array $fila) => $fila['no_leidos'] > 0)
            ->groupBy('id_curso')
            ->map(fn($delCurso) => [
                'id_curso'  => $delCurso->first()['id_curso'],
                'nombre'    => $delCurso->first()['nombre'],
                'cod_curso' => $delCurso->first()['cod_curso'],
                'cod_asignatura' => $delCurso->first()['cod_asignatura'],
                'no_leidos' => $delCurso->sum('no_leidos'),
            ])
            ->sortByDesc('no_leidos')
            ->values();

        return [
            'no_leidos' => (int) $cursos->sum('no_leidos'),
            'cursos'    => $cursos->all(),
        ];
    }
}
