<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignada;
use App\Models\Agenda\AsignadoActividad;
use App\Models\Agenda\EstadoActividad;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Contexto;
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

        $isDocente = $curso->componentes()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
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
        $estados = EstadoActividad::all();

        return Inertia::render('docente/Actividades', [
            'curso' => $curso,
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
        // Verify the logged-in user is a docente for this course
        $user = Auth::user();
        
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }
        
        $isDocente = $curso->componentes()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para crear actividades en este curso.');
        }

        // Validate the request
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_limite' => 'required|date',
            'tipo_actividad' => 'required|integer',
            'tipo_entrega' => 'required|string|in:online,presencial,hibrido',
            'es_grupal' => 'boolean',
            'max_integrantes' => 'integer|min:1|max:100',
            'visible' => 'boolean',
            'id_componente' => 'required|integer',
            'id_unidad' => 'required|integer',
        ]);

        $validated['es_plantilla'] = false;

        // Create the activity
        try {
            $contexto = Contexto::create([
                'contexto_display' => 'Actividad: ' . $validated['nombre'],
            ]);
            $validated['id_contexto'] = $contexto->id_contexto;

            $actividad = Actividad::create($validated);
            
            return redirect()->back()->with('success', "Actividad '{$actividad->nombre}' creada correctamente.");
        } catch (\Exception $e) {
            Log::error('Error creating activity: ' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Error al crear la actividad: ' . $e->getMessage()])
                ->withInput();
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
        
        $isDocente = $curso->componentes()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para editar esta actividad.');
        }

        // Verify the activity belongs to this course through its componente
        $actividadCursoId = $actividad->componente?->id_curso;
        if ($actividadCursoId && $actividadCursoId !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_limite' => 'required|date',
            'tipo_actividad' => 'required|integer',
            'tipo_entrega' => 'required|string|in:online,presencial,hibrido',
            'es_grupal' => 'boolean',
            'max_integrantes' => 'integer|min:1|max:100',
            'visible' => 'boolean',
            'id_componente' => 'required|integer',
            'id_unidad' => 'required|integer',
        ]);

        try {
            $actividad->update($validated);
            
            return redirect()->back()->with('success', 'Actividad actualizada correctamente.');
        } catch (\Exception $e) {
            Log::error('Error updating activity: ' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Error al actualizar la actividad.'])
                ->withInput();
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
        
        $isDocente = $curso->componentes()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para eliminar esta actividad.');
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
            ->get(['id_actividad', 'nombre', 'fecha_limite']);
            
            return response()->json($actividades);
        }
        
        // Si es docente, verificar que sea responsable del curso
        if (!$user->docente) {
            return response()->json(['error' => 'No tienes un perfil docente o administrativo.'], 403);
        }

        $isDocente = $curso->componentes()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
        if (!$isDocente) {
            return response()->json(['error' => 'No tienes permiso para acceder a este curso.'], 403);
        }

        // Get activities for this course through componente relationship
        $actividades = Actividad::whereHas('componente', function($query) use ($curso) {
            $query->where('id_curso', $curso->id_curso);
        })
        ->orderBy('fecha_limite', 'asc')
        ->get(['id_actividad', 'nombre', 'fecha_limite']);

        return response()->json($actividades);
    }

    // =========================================================================
    // EVALUACIÓN / CALIFICACIÓN
    // =========================================================================

    /**
     * Muestra la vista de evaluación de una actividad:
     * grupos (actividad_asignada) con sus integrantes y notas.
     */
    public function showEvaluacion(Curso $curso, Actividad $actividad)
    {
        $user = Auth::user();

        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente = $curso->componentes()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();

        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para acceder a este curso.');
        }

        $actividad->load(['componente.tipoComponente', 'unidad']);

        // Grupos con sus integrantes cargados
        $grupos = ActividadAsignada::where('id_actividad', $actividad->id_actividad)
            ->with([
                'estadoActividad',
                'asignadoActividades.estudiante.usuario',
            ])
            ->get()
            ->map(fn($g) => [
                'grupo'          => $g->grupo,
                'nota'           => $g->nota,
                'id_estado'      => $g->id_estado,
                'estado'         => $g->estadoActividad ? [
                    'id_estado'  => $g->estadoActividad->id_estado,
                    'titulo'     => $g->estadoActividad->titulo,
                ] : null,
                'integrantes'    => $g->asignadoActividades->map(fn($a) => [
                    'id_asignado_actividad' => $a->id_asignado_actividad,
                    'id_estudiante'         => $a->id_estudiante,
                    'nota_individual'       => $a->nota_individual,
                    'diferencia_decimas'    => $a->diferencia_decimas,
                    'nombre_completo'       => trim(
                        ($a->estudiante?->usuario?->nombre1 ?? '') . ' ' .
                        ($a->estudiante?->usuario?->nombre2 ?? '') . ' ' .
                        ($a->estudiante?->usuario?->apellido1 ?? '') . ' ' .
                        ($a->estudiante?->usuario?->apellido2 ?? '')
                    ),
                ])->values(),
            ])
            ->values();

        // Estudiantes inscritos en el componente de esta actividad (para agregar a grupos)
        $estudiantesDisponibles = collect();
        if ($actividad->id_componente) {
            $estudiantesDisponibles = DB::table('curso.inscripcion_seccion as is')
                ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'is.id_estudiante')
                ->join('usuario.usuario as u', 'u.id_usuario', '=', 'e.id_usuario')
                ->where('is.id_seccion', $actividad->id_seccion)
                ->select(
                    'e.id_estudiante',
                    DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,''))) as nombre_completo"),
                    'u.email'
                )
                ->orderBy('u.apellido1')
                ->get();
        }

        $estados = EstadoActividad::all(['id_estado', 'titulo', 'descripcion']);

        return Inertia::render('docente/ActividadEvaluacion', [
            'curso'                 => $curso,
            'actividad'             => $actividad,
            'grupos'                => $grupos,
            'estudiantesDisponibles' => $estudiantesDisponibles,
            'estados'               => $estados,
        ]);
    }

    /**
     * Crea un grupo (actividad_asignada) para una actividad.
     * Si se envía id_estudiante inicial, lo agrega también.
     */
    public function storeGrupo(Request $request, Curso $curso, Actividad $actividad)
    {
        $this->autorizarDocenteCurso($curso);

        $validated = $request->validate([
            'id_estado'    => 'required|integer|exists:agenda.estado_actividad,id_estado',
            'nota'         => 'nullable|numeric|min:0|max:10',
            'id_estudiante' => 'nullable|integer|exists:usuario.estudiante,id_estudiante',
        ]);

        return DB::transaction(function () use ($validated, $actividad) {
            $grupo = ActividadAsignada::create([
                'id_actividad' => $actividad->id_actividad,
                'id_estado'    => $validated['id_estado'],
                'nota'         => $validated['nota'] ?? null,
            ]);

            if (!empty($validated['id_estudiante'])) {
                AsignadoActividad::create([
                    'grupo'         => $grupo->grupo,
                    'id_estudiante' => $validated['id_estudiante'],
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

        $grupoModel = ActividadAsignada::where('grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        $validated = $request->validate([
            'nota'      => 'nullable|numeric|min:0|max:10',
            'id_estado' => 'required|integer|exists:agenda.estado_actividad,id_estado',
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

        $grupoModel = ActividadAsignada::where('grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        return DB::transaction(function () use ($grupoModel) {
            AsignadoActividad::where('grupo', $grupoModel->grupo)->delete();
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
        ActividadAsignada::where('grupo', $grupo)
            ->where('id_actividad', $actividad->id_actividad)
            ->firstOrFail();

        $validated = $request->validate([
            'id_estudiante' => 'required|integer|exists:usuario.estudiante,id_estudiante',
        ]);

        // Verificar que no está ya en este grupo
        $exists = AsignadoActividad::where('grupo', $grupo)
            ->where('id_estudiante', $validated['id_estudiante'])
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['error' => 'El estudiante ya está en este grupo.']);
        }

        AsignadoActividad::create([
            'grupo'         => $grupo,
            'id_estudiante' => $validated['id_estudiante'],
        ]);

        return redirect()->back()->with('success', 'Integrante agregado al grupo.');
    }

    /**
     * Actualiza la nota individual de un integrante.
     */
    public function updateIntegrante(Request $request, Curso $curso, Actividad $actividad, int $grupo, AsignadoActividad $asignado)
    {
        $this->autorizarDocenteCurso($curso);

        if ($asignado->grupo !== $grupo) {
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
    public function removeIntegrante(Curso $curso, Actividad $actividad, int $grupo, AsignadoActividad $asignado)
    {
        $this->autorizarDocenteCurso($curso);

        if ($asignado->grupo !== $grupo) {
            abort(404, 'Integrante no encontrado en este grupo.');
        }

        $asignado->delete();

        return redirect()->back()->with('success', 'Integrante eliminado del grupo.');
    }

    // =========================================================================
    // HELPER PRIVADO
    // =========================================================================

    /**
     * Verifica que el usuario autenticado es docente del curso (o admin).
     */
    private function autorizarDocenteCurso(Curso $curso): void
    {
        $user = Auth::user();

        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();

        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para gestionar este curso.');
        }
    }
}
