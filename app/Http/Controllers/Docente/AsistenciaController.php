<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Registro de asistencia por componente (perspectiva del docente).
 *
 * Modelo de datos:
 * - La asistencia se registra por `curso.inscripcion_componente` (la matrícula
 *   de un estudiante en un componente concreto: teoría, práctica, etc.), no por
 *   curso global.
 * - No existe una entidad "sesión": cada fila de `curso.asistencia` lleva su
 *   propio `dia` + `hora_inicio` + `hora_fin`. Una "sesión de clase" es, por
 *   tanto, IMPLÍCITA: el conjunto de filas de las inscripciones de un componente
 *   que comparten exactamente ese trío (dia, hora_inicio, hora_fin).
 *
 * Acceso:
 * - El docente titular del curso, o un docente asignado al componente
 *   (`curso.docente_componente`), pueden ver y registrar asistencia.
 */
class AsistenciaController extends Controller
{
    /**
     * Centro de asistencia (transversal a todos los cursos del docente).
     *
     * Flujo: elegir curso → elegir componente → tomar asistencia. Lista, por
     * curso, los componentes sobre los que el docente puede tomar asistencia:
     * - Si es titular del curso: TODOS los componentes (supervisión).
     * - En cualquier curso: además, los componentes que imparte
     *   (`docente_componente`), sea o no titular del componente.
     *
     * GET docente/asistencia
     */
    public function centro()
    {
        $docente = Auth::user()->docente;
        if (!$docente) {
            return redirect()->route('dashboard')->with('error', 'No tienes un perfil docente asociado.');
        }

        $idDocente = $docente->id_docente;

        $cursos = Curso::where(function ($q) use ($idDocente) {
                $q->where('id_docente_titular', $idDocente)
                    ->orWhereHas('componentes.docenteComponentes', fn ($dq) => $dq->where('id_docente', $idDocente));
            })
            ->whereNull('fecha_eliminacion')
            ->with(['componentes' => function ($q) use ($idDocente) {
                $q->with('tipoComponente')
                    ->withCount('inscripcionComponentes')
                    ->with(['docenteComponentes' => fn ($dq) => $dq->where('id_docente', $idDocente)]);
            }])
            ->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->get();

        $data = $cursos->map(function ($curso) use ($idDocente) {
            $esTitularCurso = $curso->id_docente_titular === $idDocente;

            $componentes = $curso->componentes
                // Titular del curso ve todos; el resto sólo los que imparte.
                ->filter(fn ($c) => $esTitularCurso || $c->docenteComponentes->isNotEmpty())
                ->map(function ($c) {
                    $imparte = $c->docenteComponentes->isNotEmpty();
                    return [
                        'id_componente'         => $c->id_componente,
                        'tipo_componente'       => $c->tipoComponente->tipo ?? 'N/A',
                        'imparte'               => $imparte,
                        'es_titular_componente' => (bool) ($c->docenteComponentes->first()?->es_titular),
                        'total_estudiantes'     => $c->inscripcion_componentes_count,
                    ];
                })
                ->sortBy('tipo_componente')
                ->values();

            return [
                'id_curso'         => $curso->id_curso,
                'nombre'           => $curso->nombre,
                'cod_curso'        => $curso->cod_curso,
                'agno_real'        => $curso->agno_real,
                'semestre_real'    => $curso->semestre_real,
                'es_titular_curso' => $esTitularCurso,
                'componentes'      => $componentes,
            ];
        })
        ->filter(fn ($c) => count($c['componentes']) > 0)
        ->values();

        return Inertia::render('docente/Asistencia', [
            'cursos' => $data,
        ]);
    }

    /**
     * Lista los estudiantes del componente y sus sesiones de asistencia.
     *
     * GET docente/cursos/{curso}/componentes/{componente}/asistencia
     */
    public function index(Curso $curso, Componente $componente)
    {
        $this->autorizarComponente($curso, $componente);

        $estudiantes = $this->estudiantesDelComponente($componente);
        $inscripcionIds = array_column($estudiantes, 'id_inscripcion_componente');

        // Todas las filas de asistencia de las inscripciones del componente.
        $filas = empty($inscripcionIds) ? collect() : DB::table('curso.asistencia')
            ->whereIn('id_inscripcion_componente', $inscripcionIds)
            ->orderByDesc('dia')
            ->orderByDesc('hora_inicio')
            ->get();

        // Agrupa por (dia, hora_inicio, hora_fin) → sesión implícita.
        $sesiones = $filas
            ->groupBy(fn ($r) => $r->dia . '|' . $r->hora_inicio . '|' . $r->hora_fin)
            ->map(function ($registros) {
                $primero = $registros->first();
                $presentes = $registros->where('esta_presente', true)->count();

                return [
                    'dia'         => $primero->dia,
                    'hora_inicio' => substr((string) $primero->hora_inicio, 0, 5),
                    'hora_fin'    => substr((string) $primero->hora_fin, 0, 5),
                    'total'       => $registros->count(),
                    'presentes'   => $presentes,
                    'registros'   => $registros->map(fn ($r) => [
                        'id_inscripcion_componente' => $r->id_inscripcion_componente,
                        'esta_presente'             => (bool) $r->esta_presente,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json([
            'estudiantes'                        => $estudiantes,
            'sesiones'                           => $sesiones,
            'porcentaje_asistencia_obligatoria'  => $componente->porcentaje_asistencia_obligatoria,
        ]);
    }

    /**
     * Crea una nueva sesión de asistencia (inserta una fila por estudiante).
     *
     * POST docente/cursos/{curso}/componentes/{componente}/asistencia
     */
    public function store(Request $request, Curso $curso, Componente $componente)
    {
        $this->autorizarComponente($curso, $componente);

        $datos = $this->validarSesion($request);

        $inscripcionesValidas = $this->idsInscripcionValidos($componente);

        // Rechaza una sesión duplicada (mismo dia + horas).
        $existe = DB::table('curso.asistencia')
            ->whereIn('id_inscripcion_componente', $inscripcionesValidas)
            ->where('dia', $datos['dia'])
            ->where('hora_inicio', $datos['hora_inicio'])
            ->where('hora_fin', $datos['hora_fin'])
            ->exists();

        if ($existe) {
            return back()->withErrors([
                'dia' => 'Ya existe una sesión registrada para esa fecha y horario.',
            ]);
        }

        $this->guardarRegistros($datos, $inscripcionesValidas);

        return back()->with('success', 'Sesión de asistencia registrada.');
    }

    /**
     * Actualiza una sesión existente: reemplaza las marcas presente/ausente.
     *
     * PUT docente/cursos/{curso}/componentes/{componente}/asistencia
     */
    public function update(Request $request, Curso $curso, Componente $componente)
    {
        $this->autorizarComponente($curso, $componente);

        $datos = $this->validarSesion($request);
        $inscripcionesValidas = $this->idsInscripcionValidos($componente);

        DB::transaction(function () use ($datos, $inscripcionesValidas) {
            // Borra la sesión previa (mismo trío dia/horas) y reinserta.
            DB::table('curso.asistencia')
                ->whereIn('id_inscripcion_componente', $inscripcionesValidas)
                ->where('dia', $datos['dia'])
                ->where('hora_inicio', $datos['hora_inicio'])
                ->where('hora_fin', $datos['hora_fin'])
                ->delete();

            $this->insertarFilas($datos, $inscripcionesValidas);
        });

        return back()->with('success', 'Asistencia actualizada.');
    }

    /**
     * Elimina una sesión completa.
     *
     * DELETE docente/cursos/{curso}/componentes/{componente}/asistencia
     */
    public function destroy(Request $request, Curso $curso, Componente $componente)
    {
        $this->autorizarComponente($curso, $componente);

        $validado = $request->validate([
            'dia'         => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin'    => 'required|date_format:H:i',
        ]);

        DB::table('curso.asistencia')
            ->whereIn('id_inscripcion_componente', $this->idsInscripcionValidos($componente))
            ->where('dia', $validado['dia'])
            ->where('hora_inicio', $validado['hora_inicio'])
            ->where('hora_fin', $validado['hora_fin'])
            ->delete();

        return back()->with('success', 'Sesión eliminada.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Verifica que el docente autenticado pueda gestionar la asistencia del
     * componente: titular del curso o docente asignado al componente. Además
     * valida que el componente pertenezca al curso.
     */
    private function autorizarComponente(Curso $curso, Componente $componente): void
    {
        $docente = Auth::user()->docente;
        if (!$docente) {
            abort(403, 'No tienes un perfil docente asociado.');
        }

        if ($componente->id_curso !== $curso->id_curso) {
            abort(404, 'El componente no pertenece a este curso.');
        }

        $esTitular = $curso->id_docente_titular === $docente->id_docente;
        $esAsignado = $componente->docenteComponentes()
            ->where('id_docente', $docente->id_docente)
            ->exists();

        if (!$esTitular && !$esAsignado) {
            abort(403, 'No tienes acceso a la asistencia de este componente.');
        }
    }

    /** Estudiantes inscritos en el componente (para la grilla de asistencia). */
    private function estudiantesDelComponente(Componente $componente): array
    {
        return InscripcionComponente::where('id_componente', $componente->id_componente)
            ->with('estudiante.usuario')
            ->get()
            ->map(fn ($ic) => [
                'id_inscripcion_componente' => $ic->id_inscripcion_componente,
                'id_estudiante'             => $ic->id_estudiante,
                'nombre'                    => trim(
                    ($ic->estudiante->usuario->nombre1   ?? '') . ' ' .
                    ($ic->estudiante->usuario->apellido1 ?? '') . ' ' .
                    ($ic->estudiante->usuario->apellido2 ?? '')
                ),
                'username'                  => $ic->estudiante->usuario->username ?? '',
            ])
            ->sortBy('nombre')
            ->values()
            ->all();
    }

    /** @return array<int,int> IDs de inscripción que sí pertenecen al componente. */
    private function idsInscripcionValidos(Componente $componente): array
    {
        return InscripcionComponente::where('id_componente', $componente->id_componente)
            ->pluck('id_inscripcion_componente')
            ->all();
    }

    /**
     * Valida el payload de una sesión.
     *
     * @return array{dia:string,hora_inicio:string,hora_fin:string,asistencias:array}
     */
    private function validarSesion(Request $request): array
    {
        return $request->validate([
            'dia'                       => 'required|date',
            'hora_inicio'               => 'required|date_format:H:i',
            'hora_fin'                  => 'required|date_format:H:i|after:hora_inicio',
            'asistencias'               => 'required|array|min:1',
            'asistencias.*.id_inscripcion_componente' => 'required|integer',
            'asistencias.*.esta_presente'             => 'required|boolean',
        ]);
    }

    /** Inserta las filas de una sesión, filtrando inscripciones ajenas al componente. */
    private function guardarRegistros(array $datos, array $inscripcionesValidas): void
    {
        DB::transaction(fn () => $this->insertarFilas($datos, $inscripcionesValidas));
    }

    private function insertarFilas(array $datos, array $inscripcionesValidas): void
    {
        $validas = array_flip($inscripcionesValidas);

        $filas = [];
        foreach ($datos['asistencias'] as $a) {
            $idIc = (int) $a['id_inscripcion_componente'];
            if (!isset($validas[$idIc])) {
                continue; // Ignora inscripciones que no son del componente.
            }
            $filas[] = [
                'dia'                       => $datos['dia'],
                'hora_inicio'               => $datos['hora_inicio'],
                'hora_fin'                  => $datos['hora_fin'],
                'esta_presente'             => (bool) $a['esta_presente'],
                'id_inscripcion_componente' => $idIc,
            ];
        }

        if (!empty($filas)) {
            DB::table('curso.asistencia')->insert($filas);
        }
    }
}
