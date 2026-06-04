<?php

namespace App\Http\Controllers\Docente;

use App\Exceptions\Archive\FileValidationException;
use App\Exceptions\Archive\VirusDetectedException;
use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\StorageException;
use App\Exceptions\Archive\ArchiveException;
use InvalidArgumentException;
use App\Http\Controllers\Controller;
use App\Enums\DB\EstadoActividadAsignada;
use App\Enums\DB\TipoActividad;
use App\Models\Agenda\Actividad;
use App\Http\Requests\Archive\ActivityFileRequest;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\Componente;
use App\Models\Curso\Unidad;
use App\Models\Curso\InscripcionCurso;
use App\Services\Archive\Handlers\ActivityArchiveHandler;
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
        // Verify the logged-in user is a docente for this course
        $user = Auth::user();
        
        // Check if user is associated with this course as a docente (through sections)
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }
        
        $isDocente =  self::isDocente($user->docente->id_docente);

        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para acceder a este curso.');
        }

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

        // Permisos granulares del docente en el contexto de este curso
        $esTitular = $curso->id_docente_titular === $user->docente->id_docente;
        $userPermissions = [];

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
        Log::info('[DocenteActivity::store] Inicio', [
            'id_curso' => $curso->id_curso,
            'payload'  => $request->except(['_token']),
        ]);

        // Verify the logged-in user is a docente for this course
        $user = Auth::user();
        
        if (!$user->docente) {
            Log::warning('[DocenteActivity::store] Usuario sin perfil docente', ['id_user' => $user->id]);
            abort(403, 'No tienes un perfil docente.');
        }
        
        $isDocente = self::isDocente($user->docente->id_docente);

        Log::info('[DocenteActivity::store] Verificación permisos', [
            'id_docente'           => $user->docente->id_docente,
            'is_admin'             => $user->is_admin,
            'isDocente'            => $isDocente,
            'id_docente_titular'   => $curso->id_docente_titular,
            'es_titular'           => $curso->id_docente_titular === $user->docente->id_docente,
        ]);
        
        if (!$isDocente && !$user->is_admin) {
            Log::warning('[DocenteActivity::store] Bloqueado: no es docente', ['id_docente' => $user->docente->id_docente]);
            abort(403, 'No tienes permiso para crear actividades en este curso.');
        }

        // Si no es titular ni admin, no permite crear
        $esTitularStore = $curso->id_docente_titular === $user->docente->id_docente;
        if (!$esTitularStore && !$user->is_admin) {
            Log::warning('[DocenteActivity::store] Bloqueado: no es titular del curso', [
                'id_docente'         => $user->docente->id_docente,
                'id_docente_titular' => $curso->id_docente_titular,
            ]);
            abort(403, 'No tienes permiso para crear actividades en este curso.');
        }

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
                ->with('error', 'Error al crear la actividad: ' . $e->getMessage());
        }
    }

    public function storeFile(ActivityFileRequest $request, Actividad $actividad)
    {
        // Valida: Usa tu ActivityFileRequest para verificar peso y mimes.

        // Guarda en disco: El ActivityArchiveService guarda el archivo físico.
        try {
            $storedFile = ActivityArchiveHandler::store(
                actividad: $actividad,
                file: $request->getFile(),
            );
            $uuid = $storedFile->uuidArchivo; // UUID v7 generado por el servicio de archivos
            //...

            // Transacción DB (Inicio):

            // Crea Archivo: Inserta el registro en operaciones.archivo (genera el UUID v7, extrae el peso real, mime, etc.).

            // Enlaza: Hace un UPDATE agenda.actividad SET uuid_archivo = 'el-nuevo-uuid' WHERE id_actividad = 205.

            $actividad->uuid_archivo = $uuid;
            $actividad->saveOrFail();

            // Transacción DB (Fin/Commit).

        } catch (FileValidationException) {
            // Maneja validación de archivo (peso, tipo, etc)            
        } catch (VirusDetectedException) {
            // Maneja virus detectado
        } catch (CompressionException) {
            // Maneja error de compresión
        } catch (StorageException) {
            // Maneja error de almacenamiento
        } catch (ArchiveException) {
            // Maneja error genérico de archivo
        } catch (InvalidArgumentException) {
            // Maneja error de relaciones faltantes
        } catch (\Throwable $e) {
            // Maneja cualquier otro error inesperado
            // TODO: $storedFile->deleteFromDisk(); // Opcional: eliminar archivo si ya se subió pero hubo error en DB
        }

        
    }

    /**
     * Update an activity
     */
    public function update(Request $request, Curso $curso, Actividad $actividad)
    {
        // Verify the logged-in user is a docente for this course
        $user = Auth::user();
        
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }
        
        $isDocente =  self::isDocente($user->docente->id_docente);
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para editar esta actividad.');
        }

        // Si no es titular ni admin, no permite editar
        $esTitularUpdate = $curso->id_docente_titular === $user->docente->id_docente;
        if (!$esTitularUpdate && !$user->is_admin) {
            abort(403, 'No tienes permiso para editar actividades en este curso.');
        }

        // Verify the activity belongs to this course through its componente
        $actividadCursoId = $actividad->componente?->id_curso;
        if ($actividadCursoId && $actividadCursoId !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

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
                ->with('error', 'Error al actualizar la actividad: ' . $e->getMessage());
        }
    }

    /**
     * Delete an activity
     */
    public function destroy(Curso $curso, Actividad $actividad)
    {
        // Verify the logged-in user is a docente for this course
        $user = Auth::user();
        
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }
        
        $isDocente =  self::isDocente($user->docente->id_docente);
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para eliminar esta actividad.');
        }

        // Si no es titular ni admin, no permite eliminar
        $esTitularDestroy = $curso->id_docente_titular === $user->docente->id_docente;
        if (!$esTitularDestroy && !$user->is_admin) {
            abort(403, 'No tienes permiso para eliminar actividades en este curso.');
        }

        // Verify the activity belongs to this course through its componente
        $actividadCursoId = $actividad->componente?->id_curso;
        if ($actividadCursoId && $actividadCursoId !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

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
    public function getBysCursoJson(Curso $curso)
    {
        // Verify the logged-in user has permission
        $user = Auth::user();
        
        // Si es admin, permitir acceso directo
        if ($user->is_admin) {
            $actividades = Actividad::whereHas('componente', function($query) use ($curso) {
                $query->where('id_curso', $curso->id_curso);
            })
            ->orderBy('fecha_limite', 'asc')
            ->get(['id_actividad', 'nombre', 'fecha_limite', 'es_grupal']);
            
            return response()->json($actividades);
        }
        
        // Si es docente, verificar que sea responsable del curso
        if (!$user->docente) {
            return response()->json(['error' => 'No tienes un perfil docente o administrativo.'], 403);
        }

        $isDocente =  self::isDocente($user->docente->id_docente);
        
        if (!$isDocente) {
            return response()->json(['error' => 'No tienes permiso para acceder a este curso.'], 403);
        }

        // Get activities for this course through componente relationship
        $actividades = Actividad::whereHas('componente', function($query) use ($curso) {
            $query->where('id_curso', $curso->id_curso);
        })
        ->orderBy('fecha_limite', 'asc')
        ->get(['id_actividad', 'nombre', 'fecha_limite', 'es_grupal']);

        return response()->json($actividades);
    }

    // =========================================================================
    // EVALUACIÓN / CALIFICACIÓN
    // =========================================================================

    /**
     * Muestra la vista de detalle/evaluación de una actividad (nueva UI):
     * grupos (actividad_asignada_grupo) con sus integrantes, notas e interacciones lazy.
     */
    public function showEvaluacion(Curso $curso, Actividad $actividad)
    {
        $user = Auth::user();

        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente = self::isDocente($user->docente->id_docente);

        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para acceder a este curso.');
        }

        $actividad->load(['componente', 'unidad']);

        // Grupos con sus integrantes (modelo nuevo: actividad_asignada_grupo)
        $grupos = ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
            ->with(['integranteGrupos.estudiante.usuario'])
            ->get()
            ->map(fn($g) => [
                'grupo'      => $g->id_actividad_asignada_grupo,
                'nota'       => $g->nota,
                'estado_actividad_asignada' => $g->estado_actividad_asignada?->value,
                'integrantes' => $g->integranteGrupos->map(fn($m) => [
                    'id_estudiante'  => $m->id_estudiante,
                    'nombre_completo' => trim(
                        ($m->estudiante?->usuario?->nombre1  ?? '') . ' ' .
                        ($m->estudiante?->usuario?->nombre2  ?? '') . ' ' .
                        ($m->estudiante?->usuario?->apellido1 ?? '') . ' ' .
                        ($m->estudiante?->usuario?->apellido2 ?? '')
                    ),
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

        $esTitular = $curso->id_docente_titular === $user->docente->id_docente;

        // Estudiantes inscritos en el curso (para asignación de grupos)
        $estudiantesInscritos = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->with('estudiante.usuario')
            ->get()
            ->map(fn($i) => [
                'id_estudiante'   => $i->id_estudiante,
                'nombre_completo' => trim(
                    ($i->estudiante?->usuario?->nombre1  ?? '') . ' ' .
                    ($i->estudiante?->usuario?->nombre2  ?? '') . ' ' .
                    ($i->estudiante?->usuario?->apellido1 ?? '') . ' ' .
                    ($i->estudiante?->usuario?->apellido2 ?? '')
                ),
            ])
            ->sortBy('nombre_completo')
            ->values();

        return Inertia::render('docente/Activities/Index', [
            'curso'     => [
                'id_curso'  => $curso->id_curso,
                'nombre'    => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
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
            'interaccionesGrupo'   => Inertia::lazy(function () use ($actividad) {
                $grupoId = request('grupo_id');
                if (!$grupoId) return [];
                
                return DB::table('agenda.agenda as a')
                    ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
                    ->leftJoin('agenda.evaluacion as ev', 'ev.id_agenda', '=', 'a.id_agenda')
                    ->where('a.id_actividad_asignada_grupo', $grupoId)
                    ->whereIn('a.tipo_mensaje', ['Mensaje al profesor', 'Feedback', 'Entrega de archivo', 'Evaluación'])
                    ->orderBy('a.fecha_envio', 'asc')
                    ->select(
                        'a.id_agenda',
                        'a.fecha_envio',
                        'a.mensaje',
                        'a.tipo_mensaje as tipo_registro',
                        'u.id_usuario as emisor_id_usuario',
                        DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,''))) as emisor_nombre"),
                        'ev.puntaje_obtenido',
                        'ev.evaluacion_obtenida',
                        'ev.id_evaluacion',
                        'ev.resultado',
                    )
                    ->get()
                    ->map(fn($m) => array_merge((array) $m, [
                        'id_interaccion'       => $m->id_agenda,
                        'fecha_emision'        => $m->fecha_envio,
                        'tipo_interaccion'     => $m->tipo_registro,
                        'emisor'               => $m->emisor_nombre,
                        'es_de_docente'        => in_array($m->tipo_registro, ['Feedback', 'Evaluación']),
                        'es_retroalimentacion' => $m->tipo_registro === 'Feedback',
                        'es_entrega'           => $m->tipo_registro === 'Entrega de archivo',
                        'tiene_evaluacion'     => $m->id_evaluacion !== null,
                        'adjunta_rubrica'      => $m->id_evaluacion !== null,
                    ]))
                    ->values();
            }),
        ]);
    }

    /**
     * Crea una rúbrica (esqueleto de evaluación) en el contexto del curso.
     * La rúbrica NO se vincula a una actividad específica; se usa al generar evaluaciones.
     */
    public function storeRubrica(Request $request, Curso $curso)
    {
        $this->autorizarDocenteCurso($curso);

        $request->validate([
            'rubrica'                     => 'required|array',
            'rubrica.niveles'             => 'required|array|min:1',
            'rubrica.detalles_evaluacion' => 'required|array',
            'id_actividad'               => 'required|integer|exists:actividad,id_actividad',
        ]);

        try {
            \App\Models\Agenda\Rubrica::create([
                'rubrica'        => $request->input('rubrica'),
                'estado_rubrica' => 'POSTULADA',
                'id_actividad'   => $request->input('id_actividad'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear rúbrica: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Error al guardar la rúbrica.']);
        }

        return redirect()->back()->with('success', 'Rúbrica creada correctamente.');
    }

    /**
     * Elimina un grupo (ActividadAsignadaGrupo) y todos sus integrantes.
     */
    public function deleteGroup(Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

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
    public function removeStudentFromGroup(Curso $curso, Actividad $actividad, int $grupo, int $estudiante)
    {
        $this->autorizarDocenteCurso($curso);

        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

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
     * Crea un grupo (actividad_asignada) para una actividad.
     * Si se envía id_estudiante inicial, lo agrega también.
     */
    public function storeGrupo(Request $request, Curso $curso, Actividad $actividad)
    {
        $this->autorizarDocenteCurso($curso);

        $validated = $request->validate([
            'estado_actividad_asignada' => 'required|string|in:PLANIFICADA,ACTIVA,CERRADA',
            'nota'         => 'nullable|numeric|min:0|max:10',
            'id_estudiante' => 'nullable|integer|exists:usuario.estudiante,id_estudiante',
        ]);

        return DB::transaction(function () use ($validated, $actividad) {
            $grupo = ActividadAsignadaGrupo::create([
                'id_actividad' => $actividad->id_actividad,
                'estado_actividad_asignada' => $validated['estado_actividad_asignada'],
                'nota'         => $validated['nota'] ?? null,
            ]);

            if (!empty($validated['id_estudiante'])) {
                IntegranteGrupo::create([
                    'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
                    'id_estudiante'              => $validated['id_estudiante'],
                ]);
            }

            return redirect()->back()->with('success', 'Grupo creado correctamente.');
        });
    }

    /**
     * Actualiza la nota grupal y/o el estado de un grupo.
     */
    public function updateGrupo(Request $request, Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        $grupoModel = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        $validated = $request->validate([
            'nota'      => 'nullable|numeric|min:0|max:10',
            'estado_actividad_asignada' => 'required|string|in:PLANIFICADA,ACTIVA,CERRADA',
        ]);

        $grupoModel->update($validated);

        return redirect()->back()->with('success', 'Grupo actualizado correctamente.');
    }

    /**
     * Elimina un grupo y todos sus integrantes asignados.
     */
    public function deleteGrupo(Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        $grupoModel = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        return DB::transaction(function () use ($grupoModel) {
            IntegranteGrupo::where('id_actividad_asignada_grupo', $grupoModel->id_actividad_asignada_grupo)->delete();
            $grupoModel->delete();
            return redirect()->back()->with('success', 'Grupo eliminado.');
        });
    }

    /**
     * Agrega un integrante a un grupo.
     */
    public function addIntegrante(Request $request, Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que el grupo pertenece a esta actividad
        ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        $validated = $request->validate([
            'id_estudiante' => 'required|integer|exists:usuario.estudiante,id_estudiante',
        ]);

        // Verificar que no está ya en este grupo
        $exists = IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_estudiante', $validated['id_estudiante'])
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['error' => 'El estudiante ya está en este grupo.']);
        }

        IntegranteGrupo::create([
            'id_actividad_asignada_grupo' => $grupo,
            'id_estudiante'              => $validated['id_estudiante'],
        ]);

        return redirect()->back()->with('success', 'Integrante agregado al grupo.');
    }

    /**
     * Actualiza la nota individual de un integrante.
     */
    public function updateIntegrante(Request $request, Curso $curso, Actividad $actividad, int $grupo, IntegranteGrupo $asignado)
    {
        $this->autorizarDocenteCurso($curso);

        if ($asignado->id_actividad_asignada_grupo !== $grupo) {
            abort(404, 'Integrante no encontrado en este grupo.');
        }

        $validated = $request->validate([
            'nota_individual'    => 'nullable|numeric|min:0|max:10',
            'diferencia_decimas' => 'nullable|integer|min:-10|max:10',
        ]);

        $asignado->update($validated);

        return redirect()->back()->with('success', 'Nota individual actualizada.');
    }

    /**
     * Elimina un integrante de un grupo.
     */
    public function removeIntegrante(Curso $curso, Actividad $actividad, int $grupo, IntegranteGrupo $asignado)
    {
        $this->autorizarDocenteCurso($curso);

        if ($asignado->id_actividad_asignada_grupo !== $grupo) {
            abort(404, 'Integrante no encontrado en este grupo.');
        }

        $asignado->delete();

        return redirect()->back()->with('success', 'Integrante eliminado del grupo.');
    }

    // =========================================================================
    // GESTIÓN DE GRUPOS
    // =========================================================================

    /**
     * Crea un nuevo grupo para una actividad grupal
     * Solo acepta estudiantes inscritos en el curso
     */
    public function storeGroup(Request $request, Curso $curso, Actividad $actividad)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que la actividad pertenece a este curso
        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        // Verificar que la actividad es grupal
        if (!$actividad->es_grupal) {
            return response()->json(['error' => 'Esta actividad no es grupal.'], 422);
        }

        $validated = $request->validate([
            'nombre_grupo' => 'nullable|string|max:100',
            'estudiantes' => 'required|array|min:1',
            'estudiantes.*' => 'required|integer',
        ]);

        // Verificar que no haya más integrantes que el máximo permitido
        if (count($validated['estudiantes']) > $actividad->max_integrantes) {
            return response()->json(
                ['error' => "No se pueden agregar más de {$actividad->max_integrantes} estudiantes."],
                422
            );
        }

        // Verificar que todos los estudiantes están inscritos en el curso
        $estudiantesInscritos = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->whereIn('id_estudiante', $validated['estudiantes'])
            ->count();

        if ($estudiantesInscritos !== count($validated['estudiantes'])) {
            return response()->json(
                ['error' => 'Algunos estudiantes no están inscritos en este curso.'],
                422
            );
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
    public function addStudentToGroup(Request $request, Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que la actividad pertenece a este curso
        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        // Obtener el grupo
        $actividadGrupo = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->first();

        if (!$actividadGrupo) {
            return response()->json(['error' => 'Grupo no encontrado.'], 404);
        }

        $validated = $request->validate([
            'id_estudiante' => 'required|integer',
        ]);

        // Verificar que el estudiante está inscrito en el curso
        $estaInscrito = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('id_estudiante', $validated['id_estudiante'])
            ->exists();

        if (!$estaInscrito) {
            return response()->json(
                ['error' => 'El estudiante no está inscrito en este curso.'],
                422
            );
        }

        // Verificar que el estudiante no esté ya en el grupo
        $yaEnGrupo = IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_estudiante', $validated['id_estudiante'])
            ->exists();

        if ($yaEnGrupo) {
            return response()->json(
                ['error' => 'El estudiante ya está en este grupo.'],
                422
            );
        }

        // Verificar que no se exceda el máximo de integrantes
        $miembrosActuales = IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo)->count();
        if ($miembrosActuales >= $actividad->max_integrantes) {
            return response()->json(
                ['error' => "El grupo ya tiene el máximo de {$actividad->max_integrantes} integrantes."],
                422
            );
        }

        try {
            IntegranteGrupo::create([
                'id_actividad_asignada_grupo' => $grupo,
                'id_estudiante'              => $validated['id_estudiante'],
            ]);

            return response()->json([
                'success' => 'Estudiante agregado al grupo.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error adding student to group: ' . $e->getMessage());

            return response()->json(
                ['error' => 'Error al agregar el estudiante: ' . $e->getMessage()],
                500
            );
        }
    }

    /**
     * Obtiene los grupos de una actividad con sus integrantes
     */
    public function getGroupsByActivity(Curso $curso, Actividad $actividad)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que la actividad pertenece a este curso
        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

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
    public function copyGroupsFromActivity(Request $request, Curso $curso, Actividad $actividad)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que la actividad actual pertenece a este curso
        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        // Verificar que la actividad es grupal
        if (!$actividad->es_grupal) {
            return response()->json(['error' => 'Esta actividad no es grupal.'], 422);
        }

        $validated = $request->validate([
            'id_actividad_origen' => 'required|integer|exists:agenda.actividad,id_actividad',
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
                ['error' => 'Error al copiar los grupos: ' . $e->getMessage()],
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
    public function getSubmissionsByActivity(Curso $curso, Actividad $actividad)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que la actividad pertenece a este curso
        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        $entregas = Agenda::whereHas('actividadAsignadaGrupo', function ($query) use ($actividad) {
            $query->where('id_actividad', $actividad->id_actividad);
        })
        ->with(['usuario', 'archivo', 'tipoRegistroAgenda', 'evaluacion'])
        ->orderBy('fecha_envio', 'desc')
        ->get()
        ->map(function ($entrega) {
            return [
                'id_agenda' => $entrega->id_agenda,
                'fecha_envio' => $entrega->fecha_envio,
                'mensaje' => $entrega->mensaje,
                'tipo_registro' => $entrega->tipoRegistroAgenda?->titulo,
                'archivo' => $entrega->uuid_archivo_subido ? [
                    'uuid' => $entrega->archivo?->uuid_archivo,
                    'nombre_original' => $entrega->archivo?->nombre_original,
                    'extension' => $entrega->archivo?->extension,
                    'mime_type' => $entrega->archivo?->mime_type,
                    'peso_bytes' => $entrega->archivo?->peso_bytes,
                    'fecha_creacion' => $entrega->archivo?->fecha_creacion,
                ] : null,
                'usuario_emisor' => [
                    'nombre' => trim(
                        ($entrega->usuario?->nombre1 ?? '') . ' ' .
                        ($entrega->usuario?->nombre2 ?? '') . ' ' .
                        ($entrega->usuario?->apellido1 ?? '') . ' ' .
                        ($entrega->usuario?->apellido2 ?? '')
                    ),
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
    public function getSubmissionsByGroup(Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que la actividad pertenece a este curso
        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        // Verificar que el grupo pertenece a la actividad
        $grupoExiste = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->exists();

        if (!$grupoExiste) {
            return response()->json(['error' => 'Grupo no encontrado.'], 404);
        }

        $entregas = Agenda::where('id_actividad_asignada_grupo', $grupo)
            ->with(['usuario', 'archivo', 'tipoRegistroAgenda', 'evaluacion'])
            ->orderBy('fecha_envio', 'desc')
            ->get()
            ->map(function ($entrega) {
                return [
                    'id_agenda' => $entrega->id_agenda,
                    'fecha_envio' => $entrega->fecha_envio,
                    'mensaje' => $entrega->mensaje,
                    'tipo_registro' => $entrega->tipoRegistroAgenda?->titulo,
                    'archivo' => $entrega->uuid_archivo_subido ? [
                        'uuid' => $entrega->archivo?->uuid_archivo,
                        'nombre_original' => $entrega->archivo?->nombre_original,
                        'extension' => $entrega->archivo?->extension,
                        'mime_type' => $entrega->archivo?->mime_type,
                        'peso_bytes' => $entrega->archivo?->peso_bytes,
                        'fecha_creacion' => $entrega->archivo?->fecha_creacion,
                    ] : null,
                    'usuario_emisor' => [
                        'nombre' => trim(
                            ($entrega->usuario?->nombre1 ?? '') . ' ' .
                            ($entrega->usuario?->nombre2 ?? '') . ' ' .
                            ($entrega->usuario?->apellido1 ?? '') . ' ' .
                            ($entrega->usuario?->apellido2 ?? '')
                        ),
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
    public function downloadSubmissionFile(Curso $curso, Actividad $actividad, int $grupo, Agenda $agenda)
    {
        $this->autorizarDocenteCurso($curso);

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
        $this->autorizarDocenteCurso($curso);

        // Estudiantes inscritos en el curso (vía inscripcion_componente)
        $estudiantes = DB::table('curso.inscripcion_componente as ic')
            ->join('curso.componente as c', 'c.id_componente', '=', 'ic.id_componente')
            ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'ic.id_estudiante')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('c.id_curso', $curso->id_curso)
            ->select(
                'e.id_estudiante',
                'u.id_usuario',
                DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,''))) as nombre"),
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

                $gruposIds = DB::table('agenda.integrante_grupo as ig')
                    ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'ig.id_actividad_asignada_grupo')
                    ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
                    ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
                    ->where('ig.id_estudiante', $idEstudiante)
                    ->where('c.id_curso', $curso->id_curso)
                    ->pluck('ig.id_actividad_asignada_grupo');

                if ($gruposIds->isEmpty()) {
                    return [];
                }

                return DB::table('agenda.agenda as a')
                    ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
                    ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'a.id_actividad_asignada_grupo')
                    ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
                    ->whereIn('a.id_actividad_asignada_grupo', $gruposIds)
                    ->whereIn('a.tipo_mensaje', ['Mensaje al profesor', 'Feedback'])
                    ->orderBy('a.fecha_envio', 'asc')
                    ->select(
                        'a.id_agenda',
                        'a.fecha_envio',
                        'a.mensaje',
                        'a.tipo_mensaje as tipo_registro',
                        DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,''))) as emisor_nombre"),
                        'u.id_usuario as emisor_id_usuario',
                        'act.nombre as actividad_nombre',
                        'act.id_actividad',
                        'aag.id_actividad_asignada_grupo as grupo'
                    )
                    ->get();
            }),
        ]);
    }

    /**
     * Devuelve la conversación de un estudiante en el contexto de un curso:
     * registros de tipo "Mensaje al profesor" y "Feedback" de todos los grupos
     * en los que el estudiante participa dentro del curso.
     */
    public function getMensajesEstudiante(Curso $curso, int $idEstudiante)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que el estudiante esté inscrito en el curso
        $inscrito = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('id_estudiante', $idEstudiante)
            ->exists();

        if (!$inscrito) {
            abort(404, 'Estudiante no encontrado en este curso.');
        }

        // Grupos donde el estudiante es integrante, dentro del curso
        $gruposIds = DB::table('agenda.integrante_grupo as ig')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'ig.id_actividad_asignada_grupo')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
            ->where('ig.id_estudiante', $idEstudiante)
            ->where('c.id_curso', $curso->id_curso)
            ->pluck('ig.id_actividad_asignada_grupo');

        if ($gruposIds->isEmpty()) {
            return response()->json([]);
        }

        $mensajes = DB::table('agenda.agenda as a')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'a.id_actividad_asignada_grupo')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->whereIn('a.id_actividad_asignada_grupo', $gruposIds)
            ->whereIn('a.tipo_mensaje', ['Mensaje al profesor', 'Feedback'])
            ->orderBy('a.fecha_envio', 'asc')
            ->select(
                'a.id_agenda',
                'a.fecha_envio',
                'a.mensaje',
                'a.tipo_mensaje as tipo_registro',
                DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,''))) as emisor_nombre"),
                'u.id_usuario as emisor_id_usuario',
                'act.nombre as actividad_nombre',
                'act.id_actividad',
            )
            ->get();

        return response()->json($mensajes);
    }

    // 1. Autor: Juan Y.
    // 2. Fecha: 04/06/2025
    // 3. Se agrega método storeEvaluacion: crea atómicamente el mensaje de tipo
    //    "Evaluación" en agenda.agenda y el registro en agenda.evaluacion, luego
    //    actualiza la nota y cierra el grupo. También cierra la rúbrica si estaba
    //    en estado POSTULADA (primera evaluación).

    /**
     * Crea una evaluación para un grupo de una actividad.
     *
     * Flujo (en transacción):
     * 1. Valida permisos y que el grupo pertenece al curso/actividad.
     * 2. Verifica que no exista ya una evaluación para el id_agenda_entrega indicado.
     * 3. Inserta un registro en agenda.agenda con tipo "Evaluación".
     * 4. Inserta un registro en agenda.evaluacion vinculado al agenda anterior.
     * 5. Actualiza nota y estado del grupo a CERRADA.
     * 6. Si la rúbrica estaba POSTULADA, la cierra.
     *
     * POST docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/evaluacion
     */
    public function storeEvaluacion(Request $request, Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        $grupoModel = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        $validated = $request->validate([
            'id_agenda_entrega'  => 'nullable|integer|exists:agenda.agenda,id_agenda',
            'id_rubrica'         => 'required|integer|exists:agenda.rubrica,id_rubrica',
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
                ]);

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

                // 3. Actualizar nota y cerrar el grupo
                $grupoModel->update([
                    'nota'                      => $validated['nota'] ?? null,
                    'estado_actividad_asignada' => EstadoActividadAsignada::CERRADA,
                ]);

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
            return response()->json(['error' => 'Error al registrar la evaluación: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Docente envía un feedback a un grupo (respuesta al mensaje del alumno).
     */
    public function sendFeedback(Request $request, Curso $curso, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        $validated = $request->validate([
            'mensaje' => 'required|string|max:2000',
        ]);

        // Verificar que el grupo pertenece al curso
        $grupoExiste = DB::table('agenda.actividad_asignada_grupo as aag')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
            ->where('aag.id_actividad_asignada_grupo', $grupo)
            ->where('c.id_curso', $curso->id_curso)
            ->exists();

        if (!$grupoExiste) {
            abort(404, 'Grupo no encontrado en este curso.');
        }

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
     * Usado en el panel de mensajes de la vista ActividadEvaluacion (nivel 2).
     */
    public function getGrupoMensajes(Curso $curso, Actividad $actividad, int $grupo)
    {
        $this->autorizarDocenteCurso($curso);

        // Verificar que la actividad pertenece al curso
        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        // Verificar que el grupo pertenece a la actividad
        $grupoExiste = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->exists();

        if (!$grupoExiste) {
            return response()->json(['error' => 'Grupo no encontrado.'], 404);
        }

        // 1. Autor: Juan Y.
        // 2. Fecha: 02/06/2026
        // 3. agenda.agenda usa la columna ENUM tipo_mensaje directamente
        //    (no existe la tabla tipo_registro_agenda). Se consulta el hilo
        //    completo del grupo: mensajes, feedback, entregas y evaluaciones.
        $mensajes = DB::table('agenda.agenda as a')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
            ->leftJoin('agenda.evaluacion as ev', 'ev.id_agenda', '=', 'a.id_agenda')
            ->where('a.id_actividad_asignada_grupo', $grupo)
            ->whereIn('a.tipo_mensaje', ['Mensaje al profesor', 'Feedback', 'Entrega de archivo', 'Evaluación'])
            ->orderBy('a.fecha_envio', 'asc')
            ->select(
                'a.id_agenda',
                'a.fecha_envio',
                'a.mensaje',
                'a.tipo_mensaje as tipo_registro',
                'u.id_usuario as emisor_id_usuario',
                DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,''))) as emisor_nombre"),
                'ev.puntaje_obtenido',
                'ev.evaluacion_obtenida',
                'ev.id_evaluacion',
            )
            ->get()
            ->map(fn($m) => array_merge((array) $m, [
                'id_interaccion'       => $m->id_agenda,
                'fecha_emision'        => $m->fecha_envio,
                'tipo_interaccion'     => $m->tipo_registro,
                'emisor'               => $m->emisor_nombre,
                'es_de_docente'        => in_array($m->tipo_registro, ['Feedback', 'Evaluación']),
                'es_retroalimentacion' => $m->tipo_registro === 'Feedback',
                'es_entrega'           => $m->tipo_registro === 'Entrega de archivo',
                'tiene_evaluacion'     => $m->id_evaluacion !== null,
                'adjunta_rubrica'      => $m->id_evaluacion !== null,
            ]));

        return response()->json($mensajes);
    }


    /**
     * Verifica que el usuario autenticado es docente del curso (o admin).
     */
    private function autorizarDocenteCurso(Curso $curso): void
    {
        $user = Auth::user();

        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente =  self::isDocente($user->docente->id_docente);
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para gestionar este curso.');
        }
    }

    private function isDocente($id_docente): bool
    {
        return DocenteComponente::
            where('id_docente', $id_docente)
            ->exists();
    }
}
