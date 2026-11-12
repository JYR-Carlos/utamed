<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Clase;
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
 * - Cada sesión de clases es una entidad de primer nivel en `curso.clase` (dia,
 *   hora_inicio, hora_fin, id_componente).
 * - La asistencia se registra por `curso.asistencia` vinculada a cada
 *   `curso.clase` y `curso.inscripcion_componente`.
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
            ->with(['asignacionPlan.asignatura', 'componentes' => function ($q) use ($idDocente) {
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
                // Igual que el menú lateral: «Asignatura Grupo A» y el código
                // de la asignatura; cod_curso es interno.
                'etiqueta'         => $curso->etiqueta(),
                'letra_grupo'      => $curso->letra_grupo,
                'cod_asignatura'   => $curso->asignacionPlan?->asignatura?->cod_asignatura,
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

        // Clases del componente ordenadas descendentemente con sus registros de asistencia
        $clases = Clase::where('id_componente', $componente->id_componente)
            ->with(['asistencias' => function ($q) use ($inscripcionIds) {
                if (!empty($inscripcionIds)) {
                    $q->whereIn('id_inscripcion_componente', $inscripcionIds);
                }
            }])
            ->orderByDesc('dia')
            ->orderByDesc('hora_inicio')
            ->get();

        $totalClases = $clases->count();

        // Calculamos correlativo cronológico (1 = clase más antigua, N = clase más reciente)
        // Como vienen ordenadas DESC, el correlativo de la posición $idx es ($totalClases - $idx)
        $sesiones = $clases->values()->map(function ($clase, $idx) use ($totalClases) {
            $registros = $clase->asistencias;
            $presentes = $registros->where('esta_presente', true)->count();

            return [
                'id_clase'    => $clase->id_clase,
                'correlativo' => $totalClases - $idx,
                'dia'         => $clase->dia,
                'hora_inicio' => substr((string) $clase->hora_inicio, 0, 5),
                'hora_fin'    => substr((string) $clase->hora_fin, 0, 5),
                'asunto'      => $clase->asunto,
                'descripcion' => $clase->descripcion,
                'total'       => $registros->count(),
                'presentes'   => $presentes,
                'registros'   => $registros->map(fn ($r) => [
                    'id_inscripcion_componente' => $r->id_inscripcion_componente,
                    'esta_presente'             => (bool) $r->esta_presente,
                ])->values()->all(),
            ];
        })->values();

        return response()->json([
            'estudiantes'                        => $estudiantes,
            'sesiones'                           => $sesiones,
            'porcentaje_asistencia_obligatoria'  => $componente->porcentaje_asistencia_obligatoria,
        ]);
    }

    /**
     * Crea una nueva clase y registra la asistencia (inserta fila en curso.clase y curso.asistencia).
     *
     * POST docente/cursos/{curso}/componentes/{componente}/asistencia
     */
    public function store(Request $request, Curso $curso, Componente $componente)
    {
        $this->autorizarComponente($curso, $componente);

        $datos = $this->validarSesion($request);
        $inscripcionesValidas = $this->idsInscripcionValidos($componente);

        // Rechaza si ya existe una clase registrada para esa fecha y horario en el componente
        $existe = Clase::where('id_componente', $componente->id_componente)
            ->where('dia', $datos['dia'])
            ->where('hora_inicio', $datos['hora_inicio'])
            ->where('hora_fin', $datos['hora_fin'])
            ->exists();

        if ($existe) {
            return back()->withErrors([
                'dia' => 'Ya existe una sesión registrada para esa fecha y horario.',
            ]);
        }

        DB::transaction(function () use ($componente, $datos, $inscripcionesValidas) {
            $clase = Clase::create([
                'id_componente' => $componente->id_componente,
                'dia'           => $datos['dia'],
                'hora_inicio'   => $datos['hora_inicio'],
                'hora_fin'      => $datos['hora_fin'],
                'asunto'        => $datos['asunto'] ?? null,
                'descripcion'   => $datos['descripcion'] ?? null,
            ]);

            $this->insertarFilas($clase->id_clase, $datos['asistencias'], $inscripcionesValidas);
        });

        return back()->with('success', 'Sesión de asistencia registrada.');
    }

    /**
     * Actualiza una sesión existente: permite corregir dia/horas y sincroniza marcas.
     *
     * PUT docente/cursos/{curso}/componentes/{componente}/asistencia/{clase?}
     */
    public function update(Request $request, Curso $curso, Componente $componente, ?Clase $clase = null)
    {
        $this->autorizarComponente($curso, $componente);

        $clase = $this->resolverClase($request, $componente, $clase);
        $this->assertClaseDeComponente($componente, $clase);

        $datos = $this->validarSesion($request);
        $inscripcionesValidas = $this->idsInscripcionValidos($componente);

        // Validar que no colisione con otra clase del mismo componente
        $colision = Clase::where('id_componente', $componente->id_componente)
            ->where('id_clase', '!=', $clase->id_clase)
            ->where('dia', $datos['dia'])
            ->where('hora_inicio', $datos['hora_inicio'])
            ->where('hora_fin', $datos['hora_fin'])
            ->exists();

        if ($colision) {
            return back()->withErrors([
                'dia' => 'Ya existe otra sesión registrada para esa fecha y horario.',
            ]);
        }

        DB::transaction(function () use ($clase, $datos, $inscripcionesValidas) {
            // Actualizar datos de la clase
            $clase->update([
                'dia'         => $datos['dia'],
                'hora_inicio' => $datos['hora_inicio'],
                'hora_fin'    => $datos['hora_fin'],
                'asunto'      => $datos['asunto'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
            ]);

            // Sincronizar marcas de asistencia vía upsert
            $validas = array_flip($inscripcionesValidas);
            $upsertData = [];

            foreach ($datos['asistencias'] as $a) {
                $idIc = (int) $a['id_inscripcion_componente'];
                if (!isset($validas[$idIc])) {
                    continue;
                }
                $upsertData[] = [
                    'id_clase'                  => $clase->id_clase,
                    'id_inscripcion_componente' => $idIc,
                    'esta_presente'             => (bool) $a['esta_presente'],
                ];
            }

            if (!empty($upsertData)) {
                DB::table('curso.asistencia')->upsert(
                    $upsertData,
                    ['id_clase', 'id_inscripcion_componente'],
                    ['esta_presente']
                );
            }
        });

        return back()->with('success', 'Asistencia actualizada.');
    }

    /**
     * Elimina una sesión de clase completa (y sus asistencias en cascada).
     *
     * DELETE docente/cursos/{curso}/componentes/{componente}/asistencia/{clase?}
     */
    public function destroy(Request $request, Curso $curso, Componente $componente, ?Clase $clase = null)
    {
        $this->autorizarComponente($curso, $componente);

        $clase = $this->resolverClase($request, $componente, $clase);
        $this->assertClaseDeComponente($componente, $clase);

        $clase->delete();

        return back()->with('success', 'Sesión eliminada.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Resuelve la instancia de Clase a partir de la ruta o parámetros del request.
     */
    private function resolverClase(Request $request, Componente $componente, ?Clase $clase): Clase
    {
        if ($clase && $clase->exists) {
            return $clase;
        }

        $idClase = $request->input('id_clase');
        if ($idClase) {
            return Clase::findOrFail($idClase);
        }

        // Fallback por clave temporal (dia, hora_inicio, hora_fin) para compatibilidad
        $dia = $request->input('dia');
        $inicio = $request->input('hora_inicio');
        $fin = $request->input('hora_fin');

        if ($dia && $inicio && $fin) {
            $clase = Clase::where('id_componente', $componente->id_componente)
                ->where('dia', $dia)
                ->where('hora_inicio', $inicio)
                ->where('hora_fin', $fin)
                ->first();

            if ($clase) {
                return $clase;
            }
        }

        abort(404, 'No se pudo identificar la sesión de clase.');
    }

    /**
     * Valida que la clase pertenezca al componente.
     */
    public function assertClaseDeComponente(Componente $componente, Clase $clase): void
    {
        if ((int) $clase->id_componente !== (int) $componente->id_componente) {
            abort(404, 'La clase no pertenece a este componente.');
        }
    }

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
                'nombre'                    => $ic->estudiante->usuario?->nombre_apellidos_primero ?? '',
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
     * @return array{dia:string,hora_inicio:string,hora_fin:string,asunto:?string,descripcion:?string,asistencias:array}
     */
    private function validarSesion(Request $request): array
    {
        return $request->validate([
            'dia'                       => 'required|date',
            'hora_inicio'               => 'required|date_format:H:i',
            'hora_fin'                  => 'required|date_format:H:i|after:hora_inicio',
            'asunto'                    => 'nullable|string|max:150',
            'descripcion'               => 'nullable|string',
            'asistencias'               => 'required|array|min:1',
            'asistencias.*.id_inscripcion_componente' => 'required|integer',
            'asistencias.*.esta_presente'             => 'required|boolean',
        ]);
    }

    /**
     * Inserta las filas de marcas de asistencia para una clase.
     */
    private function insertarFilas(int $idClase, array $asistencias, array $inscripcionesValidas): void
    {
        $validas = array_flip($inscripcionesValidas);

        $filas = [];
        foreach ($asistencias as $a) {
            $idIc = (int) $a['id_inscripcion_componente'];
            if (!isset($validas[$idIc])) {
                continue; // Ignora inscripciones que no son del componente.
            }
            $filas[] = [
                'id_clase'                  => $idClase,
                'esta_presente'             => (bool) $a['esta_presente'],
                'id_inscripcion_componente' => $idIc,
            ];
        }

        if (!empty($filas)) {
            DB::table('curso.asistencia')->insert($filas);
        }
    }
}
