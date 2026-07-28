<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Enums\DB\EstadoActividadAsignada;
use App\Enums\DB\TipoActividad;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\Unidad;
use App\Models\Curso\InscripcionCurso;
use App\Services\Agenda\GrupoIndividualService;
use App\Services\Docente\ConversacionDocenteService;
use App\Services\Docente\NombreUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controlador para gestión de actividades/tareas en los cursos del docente.
 * 
 * Tablas implicadas:
 * - curso.curso: Cursos donde existen actividades
 * - agenda.actividad: Actividades/tareas del curso
 * - curso.seccion: Secciones del curso (para validar acceso docente)
 * - curso.unidad: Unidades temáticas donde se agrupan actividades
 * - usuario.docente: Perfil del docente autenticado
 * 
 * Permite al docente ver, crear, actualizar y eliminar actividades en sus cursos,
 * validando siempre que sea responsable de alguna sección del curso.
 */
class DocenteActivityController extends Controller
{
    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Verifica que la actividad pertenezca al curso indicado; si no, aborta 404.
     *
     * Centraliza el guard repetido a lo largo del controlador (B-12).
     */
    private function assertActividadDeCurso(Curso $curso, Actividad $actividad): void
    {
        if ($actividad->componente?->id_curso === $curso->id_curso) {
            return;
        }

        abort(404, 'Actividad no encontrada en este curso.');
    }

    /**
     * Guard de escritura sobre la evaluación de una actividad.
     *
     * Estas operaciones estaban gobernadas por `viewPrograma`, un permiso de
     * **lectura** que basta con estar asignado a cualquier componente del curso:
     * el docente de un laboratorio podía borrar los grupos y fijar las notas de la
     * cátedra completa (B-5). Aquí el ámbito es el componente del que cuelga la
     * actividad.
     *
     * No se usa `manageTeam` —el modelo de política estricta del informe— porque
     * limita a titular y dejaría al docente de componente sin poder calificar lo
     * que dicta.
     */
    private function assertPuedeEditarEvaluacion(Curso $curso, Actividad $actividad): void
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        if ($user->isSuperAdmin() || $user->hasAnyRoleGlobally(\App\Models\Usuario\Usuario::ROLES_ADMINISTRATIVOS)) {
            return;
        }

        $docente = $user->docente;

        if (!$docente) {
            abort(403, 'Solo un docente puede modificar la evaluación de una actividad.');
        }

        // El titular manda sobre todas las actividades del curso.
        if ($curso->id_docente_titular === $docente->id_docente) {
            return;
        }

        $actividad->loadMissing('componente');

        $esDocenteDelComponente = $actividad->componente
            && $actividad->componente->docenteComponentes()
                ->where('id_docente', $docente->id_docente)
                ->exists();

        if ($esDocenteDelComponente) {
            return;
        }

        Log::channel('seguridad')->warning(
            'Acceso denegado: escritura de evaluación fuera del componente propio',
            [
                'evento'        => 'ESCRITURA_EVALUACION_FUERA_DE_COMPONENTE',
                'id_usuario'    => $user->id_usuario,
                'id_docente'    => $docente->id_docente,
                'id_curso'      => $curso->id_curso,
                'id_actividad'  => $actividad->id_actividad,
                'id_componente' => $actividad->componente?->id_componente,
                'ip'            => request()->ip(),
            ]
        );

        abort(403, 'No puedes modificar la evaluación de un componente que no dictas.');
    }

    /**
     * Construye el arreglo de permisos granulares del docente en el contexto del
     * curso, replicando el patrón de DocenteCursoController::show (B-02).
     *
     * El titular del curso no necesita permisos explícitos (acceso completo),
     * por lo que se devuelve un arreglo vacío en ese caso.
     *
     * @return array<int, array{id_permiso: int, slug: string, esta_permitido: bool}>
     */
    private function userPermissionsParaCurso(Curso $curso, bool $esTitular): array
    {
        if ($esTitular) {
            return [];
        }

        return collect(Auth::user()->getAllPermissions($curso->id_contexto))
            ->map(fn ($perm) => [
                'id_permiso'     => $perm['id_permiso'],
                'slug'           => $perm['slug'],
                'esta_permitido' => (bool) $perm['esta_permitido'],
            ])
            ->values()
            ->all();
    }

    /**
     * Muestra todas las actividades de un curso específico.
     * 
     * Valida que el docente autenticado sea responsable de alguna sección del curso.
     * Obtiene actividades ordenadas por fecha límite y secciones/unidades para edición.
     * 
     * @param  Curso  $curso  Curso cuyas actividades se solicitan
     * @return \Illuminate\Http\Response|\Inertia\Response  Redirección si no autorizado, o vista con actividades
     */
    public function show(Curso $curso)
    {
        $this->authorize('viewPrograma', $curso);

        $user = Auth::user();
        $esTitular = $curso->id_docente_titular === $user->docente->id_docente;

        // Get activities for this course via componente relationship (actividad has no id_curso column)
        $actividades = Actividad::whereHas('componente', fn($q) => $q->where('id_curso', $curso->id_curso))
            ->with(['componente.tipoComponente', 'unidad'])
            ->orderBy('fecha_limite', 'asc')
            ->get();

        // Get componentes for dropdown
        $componentes = Componente::where('id_curso', $curso->id_curso)
            ->with('tipoComponente')
            ->get();

        // Get units for dropdown
        $unidades = Unidad::where('id_curso', $curso->id_curso)
            ->get();

        // Get estados for display
        $estados = collect(EstadoActividadAsignada::cases())
            ->map(fn($e) => ['value' => $e->value, 'label' => $e->value])
            ->values()->all();

        // Permisos granulares del docente en el contexto de este curso (B-02)
        $userPermissions = $this->userPermissionsParaCurso($curso, $esTitular);

        return Inertia::render('docente/Actividades', [
            'curso' => array_merge($curso->toArray(), ['userPermissions' => $userPermissions, 'es_titular_curso' => $esTitular]),
            'actividades' => $actividades,
            'componentes' => $componentes,
            'unidades' => $unidades,
            'estados' => $estados,
        ]);
    }

    /**
     * Store a newly created activity
     */
    public function store(Request $request, Curso $curso)
    {
        $this->authorize('manageTeam', $curso);

        Log::info('[DocenteActivity::store] Inicio', [
            'id_curso' => $curso->id_curso,
            'payload'  => $request->except(['_token']),
        ]);

        // Validate the request
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_limite' => 'required|date',
            'tipo_actividad' => 'required|string|in:SUMATIVA,FORMATIVA',
            'tipo_entrega' => 'required|string|in:online,presencial,hibrido',
            'es_grupal' => 'boolean',
            'max_integrantes' => 'integer|min:1|max:100',
            'visible' => 'boolean',
            'ponderacion' => 'required|integer|min:0|max:100',
            'exigencia' => 'required|integer|min:0|max:100',
            'id_componente' => 'required|integer|min:1',
            'id_unidad' => 'required|integer|min:1',
        ]);

        $validated['es_plantilla'] = false;

        Log::info('[DocenteActivity::store] Datos validados, intentando crear', ['validated' => $validated]);

        // Create the activity
        // id_contexto is handled automatically by the DB trigger tr_actividad_pre_insert
        try {
            $actividad = Actividad::create($validated);

            // Las actividades individuales no pasan por el flujo manual de
            // creación de grupos: cada estudiante inscrito necesita su propio
            // grupo de 1 integrante para que agenda/evaluación tengan dónde
            // colgarse (ver GrupoIndividualService).
            if (!$actividad->es_grupal) {
                (new GrupoIndividualService())->asegurarGruposDelCurso($curso, $actividad);
            }

            Log::info('[DocenteActivity::store] Actividad creada', [
                'id_actividad' => $actividad->id_actividad,
                'nombre'       => $actividad->nombre,
                'id_contexto'  => $actividad->id_contexto ?? null,
            ]);

            return redirect()->back()->with('success', "Actividad '{$actividad->nombre}' creada correctamente.");
        } catch (\Exception $e) {
            Log::error('[DocenteActivity::store] Error al crear actividad', [
                'message'   => $e->getMessage(),
                'exception' => get_class($e),
                'trace'     => $e->getTraceAsString(),
                'validated' => $validated,
            ]);
            
            return redirect()->back()
                ->with('error', 'No se pudo crear la actividad. Por favor, inténtalo nuevamente.');
        }
    }

    /**
     * Update an activity
     */
    public function update(Request $request, Curso $curso, Actividad $actividad)
    {
        $this->authorize('manageTeam', $curso);
        $this->assertActividadDeCurso($curso, $actividad);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_limite' => 'required|date',
            'tipo_actividad' => 'required|string|in:SUMATIVA,FORMATIVA',
            'tipo_entrega' => 'required|string|in:online,presencial,hibrido',
            'es_grupal' => 'boolean',
            'max_integrantes' => 'integer|min:1|max:100',
            'visible' => 'boolean',
            'ponderacion' => 'required|integer|min:0|max:100',
            'exigencia' => 'required|integer|min:0|max:100',
            'id_componente' => 'required|integer|min:1',
            'id_unidad' => 'required|integer|min:1',
        ]);

        try {
            $actividad->update($validated);
            
            return redirect()->back()->with('success', 'Actividad actualizada correctamente.');
        } catch (\Exception $e) {
            Log::error('Error updating activity: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'No se pudo actualizar la actividad. Por favor, inténtalo nuevamente.');
        }
    }

    /**
     * Alterna la visibilidad de una actividad para los estudiantes, sin pasar
     * por el formulario completo de edición (toggle del ojito en la lista).
     */
    public function toggleVisibilidad(Curso $curso, Actividad $actividad)
    {
        $this->authorize('manageTeam', $curso);

        $this->assertActividadDeCurso($curso, $actividad);

        try {
            $actividad->update(['visible' => !$actividad->visible]);

            $mensaje = $actividad->visible
                ? "Actividad '{$actividad->nombre}' visible para los estudiantes."
                : "Actividad '{$actividad->nombre}' oculta para los estudiantes.";

            return redirect()->back()->with('success', $mensaje);
        } catch (\Exception $e) {
            Log::error('Error toggling activity visibility: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'No se pudo cambiar la visibilidad de la actividad.');
        }
    }

    /**
     * Delete an activity
     */
    public function destroy(Curso $curso, Actividad $actividad)
    {
        $this->authorize('manageTeam', $curso);
        $this->assertActividadDeCurso($curso, $actividad);

        try {
            $nombreActividad = $actividad->nombre;
            $actividad->delete();
            
            return redirect()->back()->with('success', "Actividad '{$nombreActividad}' eliminada correctamente.");
        } catch (\Exception $e) {
            Log::error('Error deleting activity: ' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Error al eliminar la actividad.']);
        }
    }

    /**
     * Retorna las actividades de un curso en formato JSON para el modal de programa.
     * 
     * Nota: Las actividades se relacionan con cursos a través de la tabla seccion.
     * 
     * @param  Curso  $curso  Curso cuyas actividades se solicitan
     * @return \Illuminate\Http\JsonResponse  Array de actividades
     */
    public function actividadesJson(Curso $curso)
    {
        $this->authorize('viewPrograma', $curso);

        $actividades = Actividad::whereHas('componente', fn($q) => $q->where('id_curso', $curso->id_curso))
            ->orderBy('fecha_limite', 'asc')
            ->get(['id_actividad', 'nombre', 'fecha_limite', 'es_grupal']);

        return response()->json($actividades);
    }

    // =========================================================================
    // EVALUACIÓN / CALIFICACIÓN
    // =========================================================================

    /**
     * Centro de calificaciones (transversal a los cursos del docente).
     *
     * Lista los cursos del docente (titular o que imparte), sus componentes
     * visibles y, dentro de cada uno, las actividades evaluables. Desde aquí el
     * docente navega curso → componente → actividad → evaluar grupo.
     *
     * GET docente/calificaciones
     */
    public function centroCalificaciones()
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
                $q->with(['tipoComponente', 'docenteComponentes' => fn ($dq) => $dq->where('id_docente', $idDocente)]);
            }])
            ->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->get();

        // Una sola consulta para las actividades de todos los componentes. Antes
        // se lanzaba una por componente de cada curso, anidada dentro del map
        // (B-8): un docente con seis cursos de tres componentes pagaba dieciocho.
        $componenteIds = $cursos
            ->flatMap(fn ($curso) => $curso->componentes->pluck('id_componente'))
            ->unique()
            ->values();

        $actividadesPorComponente = $componenteIds->isEmpty()
            ? collect()
            : Actividad::whereIn('id_componente', $componenteIds)
                ->withCount('actividadAsignadaGrupos')
                ->orderBy('fecha_limite', 'asc')
                ->get()
                ->groupBy('id_componente');

        $data = $cursos->map(function ($curso) use ($idDocente, $actividadesPorComponente) {
            $esTitularCurso = $curso->id_docente_titular === $idDocente;

            $componentes = $curso->componentes
                // Titular del curso ve todos; el resto sólo los que imparte.
                ->filter(fn ($c) => $esTitularCurso || $c->docenteComponentes->isNotEmpty())
                ->map(function ($c) use ($actividadesPorComponente) {
                    $actividades = $actividadesPorComponente
                        ->get($c->id_componente, collect())
                        ->map(fn ($a) => [
                            'id_actividad'   => $a->id_actividad,
                            'nombre'         => $a->nombre,
                            'tipo_actividad' => $a->tipo_actividad instanceof \BackedEnum ? $a->tipo_actividad->value : $a->tipo_actividad,
                            'es_grupal'      => (bool) $a->es_grupal,
                            'fecha_limite'   => $a->fecha_limite,
                            'tiene_grupos'   => $a->actividad_asignada_grupos_count > 0,
                        ])
                        ->values();

                    return [
                        'id_componente'         => $c->id_componente,
                        'tipo_componente'       => $c->tipoComponente->tipo ?? 'N/A',
                        'imparte'               => $c->docenteComponentes->isNotEmpty(),
                        'es_titular_componente' => (bool) ($c->docenteComponentes->first()?->es_titular),
                        'actividades'           => $actividades,
                    ];
                })
                ->filter(fn ($c) => count($c['actividades']) > 0)
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

        return Inertia::render('docente/Calificaciones', [
            'cursos' => $data,
        ]);
    }

    /**
     * Muestra la vista de detalle/evaluación de una actividad (nueva UI):
     * grupos (actividad_asignada_grupo) con sus integrantes, notas e interacciones lazy.
     */
    public function showEvaluacion(Curso $curso, Actividad $actividad)
    {
        $this->authorize('viewPrograma', $curso);
        $this->assertActividadDeCurso($curso, $actividad);

        $user = Auth::user();
        $esTitular = $curso->id_docente_titular === $user->docente->id_docente;

        $actividad->load(['componente', 'unidad']);

        // Aquí se reparaban al vuelo los grupos individuales faltantes, con un
        // bucle de consultas en cada carga de la pantalla (B-7). Ahora los crea
        // la escritura: al crear la actividad y al inscribir al estudiante
        // (evento en InscripcionCurso). Para los datos anteriores está el comando
        // `agenda:backfill-grupos-individuales`.

        // Grupos con sus integrantes (modelo nuevo: actividad_asignada_grupo)
        $grupos = ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
            ->with(['integranteGrupos.estudiante.usuario'])
            ->get()
            ->map(fn($g) => [
                'grupo'      => $g->id_actividad_asignada_grupo,
                'nota'       => $g->nota,
                'estado_actividad_asignada' => $g->estado_actividad_asignada?->value,
                'integrantes' => $g->integranteGrupos->map(fn($m) => [
                    'id_asignado_actividad' => $m->id_asignado_actividad,
                    'id_estudiante'  => $m->id_estudiante,
                    'nota_individual' => $m->nota_individual !== null ? (float) $m->nota_individual : null,
                    'diferencia_decimas' => (int) ($m->diferencia_decimas ?? 0),
                    'nombre_completo' => $m->estudiante?->usuario?->nombre_completo ?? '',
                ])->values(),
            ])
            ->values();

        // Campos calculados de la actividad
        $esSumativa  = $actividad->tipo_actividad === TipoActividad::SUMATIVA;
        $traeArchivo = $actividad->tipo_entrega !== 'presencial';

        // 1. Autor: GitHub Copilot
        // 2. Fecha: 04/06/2025
        // 3. Se expone también id_rubrica para que el frontend pueda enviarlo al
        //    crear una evaluación sin necesidad de una consulta adicional.

        // Rúbrica: la más reciente asociada directamente a esta actividad
        $rubricaData = null;
        $rubricaId = null;
        try {
            $rubricaModel = \App\Models\Agenda\Rubrica::where('id_actividad', $actividad->id_actividad)
                ->orderByDesc('id_rubrica')
                ->first();
            $rubricaData = $rubricaModel?->rubrica;
            $rubricaId   = $rubricaModel?->id_rubrica;
        } catch (\Exception $e) {
            Log::warning('No se pudo cargar rúbrica para actividad ' . $actividad->id_actividad . ': ' . $e->getMessage());
        }

        // Estudiantes inscritos en el curso (para asignación de grupos)
        $estudiantesInscritos = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->with('estudiante.usuario')
            ->get()
            ->map(fn($i) => [
                'id_estudiante'   => $i->id_estudiante,
                'nombre_completo' => $i->estudiante?->usuario?->nombre_completo ?? '',
            ])
            ->sortBy('nombre_completo')
            ->values();

        return Inertia::render('docente/Activities/Index', [
            'curso'     => [
                'id_curso'        => $curso->id_curso,
                'nombre'          => $curso->nombre,
                'cod_curso'       => $curso->cod_curso,
                'es_titular_curso' => $esTitular,
                'userPermissions' => $this->userPermissionsParaCurso($curso, $esTitular),
            ],
            'actividad' => [
                'id_actividad'    => $actividad->id_actividad,
                'nombre'          => $actividad->nombre,
                'descripcion'     => $actividad->descripcion ?? '',
                'fecha_limite'    => $actividad->fecha_limite,
                'es_sumativa'     => $esSumativa,
                'trae_archivo'    => $traeArchivo,
                'es_grupal'       => (bool) $actividad->es_grupal,
                'max_integrantes' => $actividad->max_integrantes,
                'es_titular'      => $esTitular,
            ],
            'grupos'               => $grupos,
            'rubrica'              => $rubricaData,
            'rubrica_id'           => $rubricaId,
            'estudiantesInscritos' => $estudiantesInscritos,
            'interaccionesGrupo'   => Inertia::lazy(function () {
                $grupoId = request('grupo_id');
                if (!$grupoId) return [];

                return (new ConversacionDocenteService())->hiloCompletoGrupo((int) $grupoId);
            }),
        ]);
    }

    /**
     * Crea una rúbrica (esqueleto de evaluación) en el contexto del curso.
     * La rúbrica NO se vincula a una actividad específica; se usa al generar evaluaciones.
     */
    public function storeRubrica(Request $request, Curso $curso)
    {
        $this->authorize('viewPrograma', $curso);

        $request->validate([
            'rubrica'                     => 'required|array',
            'rubrica.niveles'             => 'required|array|min:1',
            'rubrica.detalles_evaluacion' => 'required|array',
            'id_actividad'               => 'required|integer|exists:actividad,id_actividad',
        ]);

        // `exists:actividad` sólo comprobaba que el ID existiera en el sistema: la
        // actividad llega por el cuerpo del request y no estaba acotada al curso,
        // así que se podía escribir la rúbrica de cualquier actividad (B-2).
        $actividad = Actividad::findOrFail($request->integer('id_actividad'));
        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        $idActividad = $actividad->id_actividad;

        try {
            $existente = \App\Models\Agenda\Rubrica::where('id_actividad', $idActividad)
                ->where('estado_rubrica', 'POSTULADA')
                ->orderByDesc('id_rubrica')
                ->first();

            if ($existente) {
                $existente->update(['rubrica' => $request->input('rubrica')]);
            } else {
                \App\Models\Agenda\Rubrica::create([
                    'rubrica'        => $request->input('rubrica'),
                    'estado_rubrica' => 'POSTULADA',
                    'id_actividad'   => $idActividad,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error al guardar rúbrica: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Error al guardar la rúbrica.']);
        }

        return redirect()->back()->with('success', 'Rúbrica guardada correctamente.');
    }

    /**
     * Elimina un grupo (ActividadAsignadaGrupo) y todos sus integrantes.
     */
    public function eliminarGrupo(Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->authorize('viewPrograma', $curso);

        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        $grupoModel = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        return DB::transaction(function () use ($grupoModel) {
            IntegranteGrupo::where('id_actividad_asignada_grupo', $grupoModel->id_actividad_asignada_grupo)->delete();
            $grupoModel->delete();

            return redirect()->back()->with('success', 'Grupo eliminado correctamente.');
        });
    }

    /**
     * Quita un estudiante de un grupo (IntegranteGrupo).
     */
    public function quitarEstudianteDeGrupo(Curso $curso, Actividad $actividad, int $grupo, int $estudiante)
    {
        $this->authorize('viewPrograma', $curso);

        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        $integrante = IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_estudiante', $estudiante)
            ->firstOrFail();

        $integrante->delete();

        return redirect()->back()->with('success', 'Estudiante quitado del grupo.');
    }

    /**
     * Calcula la nota individual a partir de la nota grupal y las décimas de ajuste.
     *
     * Regla de negocio: nota_individual = nota_grupal + diferencia_decimas, acotada
     * al rango [1.0, 7.0]. `diferencia_decimas` (numeric(2,1)) guarda el delta decimal
     * real (ej. 0.3 = +tres décimas, -0.2 = -dos décimas). Si la nota grupal aún no
     * existe, la individual queda nula.
     */
    private function calcularNotaIndividual(?float $notaGrupal, ?float $decimas): ?float
    {
        if ($notaGrupal === null) {
            return null;
        }

        $nota = $notaGrupal + (float) ($decimas ?? 0);

        return round(max(1.0, min(7.0, $nota)), 1);
    }

    /**
     * Actualiza las décimas de un integrante y recalcula su nota individual.
     *
     * La nota individual se deriva siempre de la nota grupal vigente más el ajuste
     * de décimas (no se edita a mano), con tope 1.0–7.0.
     */
    public function updateIntegrante(Request $request, Curso $curso, Actividad $actividad, int $grupo, IntegranteGrupo $asignado)
    {
        $this->authorize('viewPrograma', $curso);
        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        if ($asignado->id_actividad_asignada_grupo !== $grupo) {
            abort(404, 'Integrante no encontrado en este grupo.');
        }

        $validated = $request->validate([
            'diferencia_decimas' => 'required|numeric|min:-9.9|max:9.9',
        ]);

        $grupoModel = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        $asignado->update([
            'diferencia_decimas' => $validated['diferencia_decimas'],
            'nota_individual'    => $this->calcularNotaIndividual($grupoModel->nota, (float) $validated['diferencia_decimas']),
        ]);

        return redirect()->back()->with('success', 'Nota individual actualizada.');
    }

    /**
     * Recalcula la nota individual de todos los integrantes de un grupo a partir
     * de la nota grupal vigente y las décimas ya registradas de cada uno.
     *
     * Sirve para refrescar las notas individuales (snapshot) cuando la nota grupal
     * cambió después de haberlas calculado.
     */
    public function recalcularNotasIndividuales(Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->authorize('viewPrograma', $curso);
        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        $grupoModel = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        DB::transaction(function () use ($grupoModel) {
            foreach ($grupoModel->miembros as $miembro) {
                $miembro->update([
                    'nota_individual' => $this->calcularNotaIndividual($grupoModel->nota, $miembro->diferencia_decimas),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Notas individuales recalculadas desde la nota grupal.');
    }

    // =========================================================================
    // GESTIÓN DE GRUPOS
    // =========================================================================

    /**
     * Crea un nuevo grupo para una actividad grupal
     * Solo acepta estudiantes inscritos en el curso
     */
    public function crearGrupo(Request $request, Curso $curso, Actividad $actividad)
    {
        $this->authorize('viewPrograma', $curso);

        // Verificar que la actividad pertenece a este curso
        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        // Verificar que la actividad es grupal
        if (!$actividad->es_grupal) {
            return redirect()->back()->withErrors(['error' => 'Esta actividad no es grupal.']);
        }

        $validated = $request->validate([
            'nombre_grupo' => 'nullable|string|max:100',
            'estudiantes' => 'required|array|min:1',
            'estudiantes.*' => 'required|integer',
        ]);

        // Verificar que no haya más integrantes que el máximo permitido
        if (count($validated['estudiantes']) > $actividad->max_integrantes) {
            return redirect()->back()->withErrors([
                'error' => "No se pueden agregar más de {$actividad->max_integrantes} estudiantes.",
            ]);
        }

        // Verificar que todos los estudiantes están inscritos en el curso
        $estudiantesInscritos = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->whereIn('id_estudiante', $validated['estudiantes'])
            ->count();

        if ($estudiantesInscritos !== count($validated['estudiantes'])) {
            return redirect()->back()->withErrors([
                'error' => 'Algunos estudiantes no están inscritos en este curso.',
            ]);
        }

        try {
            DB::beginTransaction();

            // Crear grupo
            $grupo = ActividadAsignadaGrupo::create([
                'id_actividad'              => $actividad->id_actividad,
                'estado_actividad_asignada' => 'PLANIFICADA',
            ]);

            // Agregar integrantes al grupo
            foreach ($validated['estudiantes'] as $id_estudiante) {
                IntegranteGrupo::create([
                    'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
                    'id_estudiante'              => $id_estudiante,
                ]);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Grupo creado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating group: ' . $e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Error al crear el grupo.']);
        }
    }

    /**
     * Agrega un estudiante a un grupo existente
     */
    public function agregarEstudianteAGrupo(Request $request, Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->authorize('viewPrograma', $curso);

        // Verificar que la actividad pertenece a este curso
        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        // Obtener el grupo
        $actividadGrupo = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->first();

        if (!$actividadGrupo) {
            return redirect()->back()->withErrors(['error' => 'Grupo no encontrado.']);
        }

        $validated = $request->validate([
            'id_estudiante' => 'required|integer',
        ]);

        // Verificar que el estudiante está inscrito en el curso
        $estaInscrito = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('id_estudiante', $validated['id_estudiante'])
            ->exists();

        if (!$estaInscrito) {
            return redirect()->back()->withErrors(['error' => 'El estudiante no está inscrito en este curso.']);
        }

        // Verificar que el estudiante no esté ya en el grupo
        $yaEnGrupo = IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_estudiante', $validated['id_estudiante'])
            ->exists();

        if ($yaEnGrupo) {
            return redirect()->back()->withErrors(['error' => 'El estudiante ya está en este grupo.']);
        }

        // Verificar que no se exceda el máximo de integrantes
        $miembrosActuales = IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo)->count();
        if ($miembrosActuales >= $actividad->max_integrantes) {
            return redirect()->back()->withErrors([
                'error' => "El grupo ya tiene el máximo de {$actividad->max_integrantes} integrantes.",
            ]);
        }

        try {
            IntegranteGrupo::create([
                'id_actividad_asignada_grupo' => $grupo,
                'id_estudiante'              => $validated['id_estudiante'],
            ]);

            return redirect()->back()->with('success', 'Estudiante agregado al grupo.');
        } catch (\Exception $e) {
            Log::error('Error adding student to group: ' . $e->getMessage());

            return redirect()->back()->withErrors(['error' => 'No se pudo agregar el estudiante.']);
        }
    }

    /**
     * Obtiene los grupos de una actividad con sus integrantes
     */
    public function gruposPorActividad(Curso $curso, Actividad $actividad)
    {
        $this->authorize('viewPrograma', $curso);

        // Verificar que la actividad pertenece a este curso
        $this->assertActividadDeCurso($curso, $actividad);

        /** @var \Illuminate\Database\Eloquent\Collection<ActividadAsignadaGrupo> */
        $grupos = ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
            ->with(['miembros.estudiante.usuario'])
            ->get();

        $gruposFormateados = $grupos->map(function ($grupo) {
            return [
                'grupo'                    => $grupo->id_actividad_asignada_grupo,
                'nota'                     => $grupo->nota,
                'estado_actividad_asignada' => $grupo->estado_actividad_asignada?->value,
                'integrantes'              => $grupo->getMiembrosConDetalles(),
                'cantidad_integrantes'     => $grupo->miembros()->count(),
            ];
        });

        return response()->json($gruposFormateados);
    }

    /**
     * Copia los grupos de una actividad anterior a la actividad actual
     */
    public function copiarGruposDeActividad(Request $request, Curso $curso, Actividad $actividad)
    {
        $this->authorize('viewPrograma', $curso);

        // Verificar que la actividad actual pertenece a este curso
        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        // Verificar que la actividad es grupal
        if (!$actividad->es_grupal) {
            return response()->json(['error' => 'Esta actividad no es grupal.'], 422);
        }

        $validated = $request->validate([
            'id_actividad_origen' => 'required|integer|exists:actividad,id_actividad',
        ]);

        $actividadOrigen = Actividad::find($validated['id_actividad_origen']);

        // Verificar que la actividad origen pertenece al mismo curso
        if ($actividadOrigen->componente?->id_curso !== $curso->id_curso) {
            return response()->json(
                ['error' => 'La actividad origen no pertenece a este curso.'],
                422
            );
        }

        if (!$actividadOrigen->es_grupal) {
            return response()->json(
                ['error' => 'La actividad origen no es grupal.'],
                422
            );
        }

        try {
            DB::beginTransaction();

            // Obtener grupos de la actividad origen
            $gruposOrigen = ActividadAsignadaGrupo::where('id_actividad', $actividadOrigen->id_actividad)
                ->with('miembros')
                ->get();

            $gruposCreados = 0;

            foreach ($gruposOrigen as $grupoOrigen) {
                // Obtener los estudiantes del grupo origen
                $estudiantes = $grupoOrigen->miembros->pluck('id_estudiante')->toArray();

                // Verificar que todos siguen inscritos en el curso
                $estudiantesInscritos = InscripcionCurso::where('id_curso', $curso->id_curso)
                    ->whereIn('id_estudiante', $estudiantes)
                    ->count();

                // Solo crear el grupo si todos los estudiantes están inscritos
                if ($estudiantesInscritos === count($estudiantes) && count($estudiantes) > 0) {
                    // Crear nuevo grupo
                    $nuevoGrupo = ActividadAsignadaGrupo::create([
                        'id_actividad'              => $actividad->id_actividad,
                        'estado_actividad_asignada' => 'PLANIFICADA',
                    ]);

                    // Agregar integrantes
                    foreach ($estudiantes as $id_estudiante) {
                        IntegranteGrupo::create([
                            'id_actividad_asignada_grupo' => $nuevoGrupo->id_actividad_asignada_grupo,
                            'id_estudiante'              => $id_estudiante,
                        ]);
                    }

                    $gruposCreados++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => "Se copiaron {$gruposCreados} grupos correctamente.",
                'grupos_creados' => $gruposCreados,
                'grupos_total_origen' => count($gruposOrigen),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error copying groups: ' . $e->getMessage());

            return response()->json(
                ['error' => 'No se pudieron copiar los grupos.'],
                500
            );
        }
    }

    // =========================================================================
    // GESTIÓN DE ENTREGAS/ARCHIVOS
    // =========================================================================

    /**
     * Obtiene todas las entregas de una actividad
     */
    public function entregasPorActividad(Curso $curso, Actividad $actividad)
    {
        $this->authorize('viewPrograma', $curso);

        // Verificar que la actividad pertenece a este curso
        $this->assertActividadDeCurso($curso, $actividad);

        $entregas = Agenda::whereHas('actividadAsignadaGrupo', function ($query) use ($actividad) {
            $query->where('id_actividad', $actividad->id_actividad);
        })
        ->with(['usuario', 'archivo', 'evaluacion'])
        ->orderBy('fecha_envio', 'desc')
        ->get()
        ->map(function ($entrega) {
            return [
                'id_agenda' => $entrega->id_agenda,
                'fecha_envio' => $entrega->fecha_envio,
                'mensaje' => $entrega->mensaje,
                'tipo_registro' => $entrega->tipo_mensaje?->value,
                'archivo' => $entrega->uuid_archivo_subido ? [
                    'uuid' => $entrega->archivo?->uuid_archivo,
                    'nombre_original' => $entrega->archivo?->nombre_original,
                    'extension' => $entrega->archivo?->extension,
                    'mime_type' => $entrega->archivo?->mime_type,
                    'peso_bytes' => $entrega->archivo?->peso_bytes,
                    'fecha_creacion' => $entrega->archivo?->fecha_creacion,
                ] : null,
                'usuario_emisor' => [
                    'nombre' => $entrega->usuario?->nombre_completo ?? '',
                    'rut' => $entrega->usuario?->rut,
                ],
                'evaluada' => $entrega->evaluacion !== null,
            ];
        });

        return response()->json($entregas);
    }

    /**
     * Obtiene las entregas de un grupo específico
     */
    public function entregasPorGrupo(Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->authorize('viewPrograma', $curso);

        // Verificar que la actividad pertenece a este curso
        $this->assertActividadDeCurso($curso, $actividad);

        // Verificar que el grupo pertenece a la actividad
        $grupoExiste = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->exists();

        if (!$grupoExiste) {
            return response()->json(['error' => 'Grupo no encontrado.'], 404);
        }

        $entregas = Agenda::where('id_actividad_asignada_grupo', $grupo)
            ->with(['usuario', 'archivo', 'evaluacion'])
            ->orderBy('fecha_envio', 'desc')
            ->get()
            ->map(function ($entrega) {
                return [
                    'id_agenda' => $entrega->id_agenda,
                    'fecha_envio' => $entrega->fecha_envio,
                    'mensaje' => $entrega->mensaje,
                    'tipo_registro' => $entrega->tipo_mensaje?->value,
                    'archivo' => $entrega->uuid_archivo_subido ? [
                        'uuid' => $entrega->archivo?->uuid_archivo,
                        'nombre_original' => $entrega->archivo?->nombre_original,
                        'extension' => $entrega->archivo?->extension,
                        'mime_type' => $entrega->archivo?->mime_type,
                        'peso_bytes' => $entrega->archivo?->peso_bytes,
                        'fecha_creacion' => $entrega->archivo?->fecha_creacion,
                    ] : null,
                    'usuario_emisor' => [
                        'nombre' => $entrega->usuario?->nombre_completo ?? '',
                        'rut' => $entrega->usuario?->rut,
                    ],
                    'evaluada' => $entrega->evaluacion !== null,
                ];
            });

        return response()->json($entregas);
    }

    /**
     * Descarga un archivo enviado
     */
    public function descargarEntrega(Curso $curso, Actividad $actividad, int $grupo, Agenda $agenda)
    {
        $this->authorize('viewPrograma', $curso);
        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        // Verificar que la agenda pertenece al grupo y actividad correctos
        if ($agenda->id_actividad_asignada_grupo !== $grupo) {
            abort(404, 'Entrega no encontrada.');
        }

        // Obtener el grupo para verificar que pertenece a la actividad
        $grupoExiste = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->exists();

        if (!$grupoExiste) {
            abort(404, 'Grupo no encontrado en esta actividad.');
        }

        // Verificar que el archivo existe
        if (!$agenda->uuid_archivo_subido || !$agenda->archivo) {
            return response()->json(['error' => 'No hay archivo asociado a esta entrega.'], 404);
        }

        $rutaArchivo = storage_path('app/' . $agenda->archivo->ruta_fisica);

        if (!file_exists($rutaArchivo)) {
            Log::warning("Archivo no encontrado en: {$rutaArchivo}");
            return response()->json(['error' => 'El archivo no existe en el servidor.'], 404);
        }

        return response()->download(
            $rutaArchivo,
            $agenda->archivo->nombre_original,
            ['Content-Type' => $agenda->archivo->mime_type]
        );
    }

    // =========================================================================
    // MENSAJERÍA DOCENTE ↔ ESTUDIANTE
    // =========================================================================

    /**
     * Renderiza la página de mensajería del curso (nivel 1 — vista general).
     * Lista todos los estudiantes inscritos con su último mensaje y cantidad de
     * mensajes pendientes (filtra agenda.agenda por tipos de mensaje).
     */
    public function showMensajesCurso(Curso $curso)
    {
        $this->authorize('viewPrograma', $curso);

        // Estudiantes inscritos en el curso (vía inscripcion_componente)
        $estudiantes = DB::table('curso.inscripcion_componente as ic')
            ->join('curso.componente as c', 'c.id_componente', '=', 'ic.id_componente')
            ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'ic.id_estudiante')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('c.id_curso', $curso->id_curso)
            ->select(
                'e.id_estudiante',
                'u.id_usuario',
                NombreUsuario::sqlConcat('u', 'nombre'),
                'u.email',
            )
            ->distinct()
            ->orderBy('u.apellido1')
            ->get();

        // Para cada estudiante: contar mensajes enviados (tipo "Mensaje al profesor")
        // en cualquier grupo del curso
        $mensajesCount = DB::table('agenda.agenda as a')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'a.id_actividad_asignada_grupo')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
            ->join('usuario.estudiante as e', 'e.id_usuario', '=', 'u.id_usuario')
            ->where('c.id_curso', $curso->id_curso)
            ->where('a.tipo_mensaje', 'Mensaje al profesor')
            ->select('e.id_estudiante', DB::raw('COUNT(*) as total_mensajes'), DB::raw('MAX(a.fecha_envio) as ultima_fecha'))
            ->groupBy('e.id_estudiante')
            ->pluck('total_mensajes', 'id_estudiante');

        $ultimasFechas = DB::table('agenda.agenda as a')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'a.id_actividad_asignada_grupo')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
            ->join('usuario.estudiante as e', 'e.id_usuario', '=', 'u.id_usuario')
            ->where('c.id_curso', $curso->id_curso)
            ->whereIn('a.tipo_mensaje', ['Mensaje al profesor', 'Feedback'])
            ->select('e.id_estudiante', DB::raw('MAX(a.fecha_envio) as ultima_fecha'))
            ->groupBy('e.id_estudiante')
            ->pluck('ultima_fecha', 'id_estudiante');

        $estudiantesConMeta = $estudiantes->map(fn($e) => array_merge((array) $e, [
            'total_mensajes' => $mensajesCount[$e->id_estudiante] ?? 0,
            'ultima_fecha'   => $ultimasFechas[$e->id_estudiante] ?? null,
        ]));

        return Inertia::render('docente/MensajesCurso', [
            'curso'       => $curso,
            'estudiantes' => $estudiantesConMeta->values(),
            'mensajesEstudiante' => Inertia::lazy(function () use ($curso) {
                $idEstudiante = request('estudiante_id');
                if (!$idEstudiante) return [];

                $service   = new ConversacionDocenteService();
                $gruposIds = $service->gruposDeEstudianteEnCurso($curso->id_curso, (int) $idEstudiante);

                return $service->conversacionEstudiante($gruposIds);
            }),
        ]);
    }

    /**
     * Devuelve la conversación de un estudiante en el contexto de un curso:
     * registros de tipo "Mensaje al profesor" y "Feedback" de todos los grupos
     * en los que el estudiante participa dentro del curso.
     */
    public function mensajesEstudiante(Curso $curso, int $idEstudiante)
    {
        $this->authorize('viewPrograma', $curso);

        // Verificar que el estudiante esté inscrito en el curso
        $inscrito = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('id_estudiante', $idEstudiante)
            ->exists();

        if (!$inscrito) {
            abort(404, 'Estudiante no encontrado en este curso.');
        }

        $service   = new ConversacionDocenteService();
        $gruposIds = $service->gruposDeEstudianteEnCurso($curso->id_curso, $idEstudiante);

        return response()->json($service->conversacionEstudiante($gruposIds));
    }

    // 1. Autor: Juan Y.
    // 2. Fecha: 04/06/2025
    // 3. Se agrega método storeEvaluacion: crea atómicamente el mensaje de tipo
    //    "Evaluación" en agenda.agenda y el registro en agenda.evaluacion, luego
    //    actualiza la nota del grupo. También cierra la rúbrica si estaba
    //    en estado POSTULADA (primera evaluación).

    /**
     * Crea una evaluación para un grupo de una actividad.
     *
     * Flujo (en transacción):
     * 1. Valida permisos y que el grupo pertenece al curso/actividad.
     * 2. Verifica que no exista ya una evaluación para el id_agenda_entrega indicado.
     * 3. Inserta un registro en agenda.agenda con tipo "Evaluación".
     * 4. Inserta un registro en agenda.evaluacion vinculado al agenda anterior.
     * 5. Actualiza la nota del grupo. El grupo NO se cierra: se deja ACTIVA para
     *    poder implementar apelación/reevaluación más adelante.
     * 6. Si la rúbrica estaba POSTULADA, la cierra.
     *
     * POST docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/evaluacion
     */
    public function storeEvaluacion(Request $request, Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->authorize('viewPrograma', $curso);

        $this->assertActividadDeCurso($curso, $actividad);
        $this->assertPuedeEditarEvaluacion($curso, $actividad);

        $grupoModel = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        $validated = $request->validate([
            'id_agenda_entrega'  => 'nullable|integer|exists:agenda,id_agenda',
            'id_rubrica'         => 'required|integer|exists:rubrica,id_rubrica',
            'resultado'          => 'nullable|array',
            'resultado_rubrica'  => 'nullable|array',
            'puntaje_obtenido'   => 'nullable|numeric|min:0|max:999',
            'evaluacion_obtenida'=> 'nullable|string|max:500',
            'mensaje'            => 'nullable|string|max:2000',
            'nota'               => 'nullable|numeric|min:1|max:7',
        ]);

        // Verificar que la entrega referenciada pertenece al mismo grupo
        if (!empty($validated['id_agenda_entrega'])) {
            $entregaValida = DB::table('agenda.agenda')
                ->where('id_agenda', $validated['id_agenda_entrega'])
                ->where('id_actividad_asignada_grupo', $grupo)
                ->exists();

            if (!$entregaValida) {
                return response()->json(['error' => 'La entrega no pertenece a este grupo.'], 422);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $grupo, $grupoModel) {

                // 1. Insertar mensaje de evaluación en agenda
                // 1. Autor: Juan Y.
                // 2. Fecha: 02/06/2026
                // 3. agenda.agenda usa tipo_mensaje ENUM directamente; no existe tipo_registro_agenda.
                $idAgendaEvaluacion = DB::table('agenda.agenda')->insertGetId([
                    'mensaje'                     => $validated['mensaje'] ?? '',
                    'id_usuario_emisor'           => Auth::id(),
                    'id_actividad_asignada_grupo' => $grupo,
                    'tipo_mensaje'                => 'Evaluación',
                    'fecha_envio'                 => now(),
                ], 'id_agenda');

                // 2. Crear registro en evaluacion vinculado al mensaje anterior
                \App\Models\Agenda\Evaluacion::create([
                    'puntaje_obtenido'    => $validated['puntaje_obtenido'] ?? null,
                    'resultado'           => $validated['resultado_rubrica'] ?? $validated['resultado'] ?? null,
                    'evaluacion_obtenida' => isset($validated['nota']) ? (string)$validated['nota'] : ($validated['evaluacion_obtenida'] ?? null),
                    'fecha_evaluacion'    => now(),
                    'id_rubrica'          => $validated['id_rubrica'],
                    'id_usuario_evaluador'=> Auth::id(),
                    'id_agenda'           => $idAgendaEvaluacion,
                ]);

                // 3. Actualizar nota. El grupo se mantiene ACTIVA (no se cierra al
                //    evaluar) para dejar espacio a apelación/reevaluación a futuro.
                $grupoModel->update([
                    'nota' => $validated['nota'] ?? null,
                ]);

                // 3b. Sembrar/refrescar la nota individual de cada integrante a partir
                //     de la nota grupal recién registrada, respetando las décimas ya
                //     fijadas para cada estudiante (snapshot, tope 1.0–7.0).
                foreach (IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo)->get() as $miembro) {
                    $miembro->update([
                        'nota_individual' => $this->calcularNotaIndividual($validated['nota'] ?? null, $miembro->diferencia_decimas),
                    ]);
                }

                // 4. Cerrar la rúbrica si estaba POSTULADA (primera evaluación que la usa)
                DB::table('agenda.rubrica')
                    ->where('id_rubrica', $validated['id_rubrica'])
                    ->where('estado_rubrica', 'POSTULADA')
                    ->update(['estado_rubrica' => 'CERRADA']);

                // 1. Autor: GitHub Copilot
                // 2. Fecha: 02/06/2026
                // 3. Devuelve redirect()->back() para compatibilidad con Inertia.js.
                return redirect()->back()->with('success', 'Evaluación registrada correctamente.');
            });
        } catch (\Exception $e) {
            Log::error('[storeEvaluacion] Error al registrar evaluación: ' . $e->getMessage(), [
                'grupo'  => $grupoModel->id_actividad_asignada_grupo,
                'trace'  => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'No se pudo registrar la evaluación. Por favor, inténtalo nuevamente.');
        }
    }

    /**
     * Docente envía un feedback a un grupo (respuesta al mensaje del alumno).
     */
    public function enviarFeedback(Request $request, Curso $curso, int $grupo)
    {
        $this->authorize('viewPrograma', $curso);

        $validated = $request->validate([
            'mensaje' => 'required|string|max:2000',
        ]);

        // Verificar que el grupo pertenece al curso
        $grupoModel = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->whereHas('actividad.componente', fn($q) => $q->where('id_curso', $curso->id_curso))
            ->first();

        if (!$grupoModel) {
            abort(404, 'Grupo no encontrado en este curso.');
        }

        // …y que el docente pueda escribir sobre el componente de esa actividad.
        $this->assertPuedeEditarEvaluacion($curso, $grupoModel->actividad);

        // 1. Autor: Juan Y.
        // 2. Fecha: 02/06/2026
        // 3. agenda.agenda usa columna ENUM tipo_mensaje directamente.
        //    Devuelve redirect()->back() para compatibilidad con Inertia.js.
        DB::table('agenda.agenda')->insert([
            'mensaje'                     => $validated['mensaje'],
            'id_usuario_emisor'           => Auth::id(),
            'id_actividad_asignada_grupo' => $grupo,
            'tipo_mensaje'                => 'Feedback',
            'fecha_envio'                 => now(),
        ]);

        return redirect()->back()->with('success', 'Feedback enviado correctamente.');
    }

    /**
     * Devuelve SÓLO los mensajes/feedback de un grupo específico de una actividad
     * (filtra por tipo "Mensaje al profesor" y "Feedback"), sin incluir entregas de archivos.
     * Usado en el panel de mensajes de la vista Activities/Index (nivel 2).
     */
    public function mensajesGrupo(Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->authorize('viewPrograma', $curso);

        // Verificar que la actividad pertenece al curso
        $this->assertActividadDeCurso($curso, $actividad);

        // Verificar que el grupo pertenece a la actividad
        $grupoExiste = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->exists();

        if (!$grupoExiste) {
            return response()->json(['error' => 'Grupo no encontrado.'], 404);
        }

        // Hilo completo del grupo (mensajes, feedback, entregas y evaluaciones).
        return response()->json((new ConversacionDocenteService())->hiloCompletoGrupo($grupo));
    }


}
