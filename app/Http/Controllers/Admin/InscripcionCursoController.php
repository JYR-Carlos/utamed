<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInscripcionCursoRequest;
use App\Http\Requests\UpdateInscripcionCursoRequest;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use \Illuminate\Support\Facades\Auth;
/**
 * Controlador para la gestión de inscripciones de estudiantes en cursos.
 * 
 * Tablas implicadas:
 * - Inscripcion_Curso: Relación muchos-a-muchos entre Estudiante y Curso
 * - Curso: Cursos disponibles
 * - Estudiante: Estudiantes disponibles para inscribir
 * 
 * Funcionalidades:
 * - Listar inscripciones con filtros y búsqueda
 * - Crear nuevas inscripciones (admin y docentes)
 * - Actualizar estado de inscripción
 * - Eliminar inscripciones (solo admin)
 * 
 * Control de Acceso:
 * - Administradores: Acceso completo
 * - Docentes: Pueden gestionar inscripciones de sus cursos
 * - Estudiantes: Solo lectura de sus propias inscripciones
 */
class InscripcionCursoController extends Controller
{
    /**
     * Muestra un listado paginado de todas las inscripciones con búsqueda y filtros.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Verify user is admin or docente
        // Admin is defined as user without docente nor estudiante profiles
        $isAdmin = !$user->docente && !$user->estudiante;
        if (!$isAdmin && !$user->docente) {
            abort(403, 'No tienes permiso para ver inscripciones');
        }

        $query = InscripcionCurso::query();

        // Si es docente, filtrar solo sus cursos
        if ($user->docente) {
            $cursoIds = DB::table('Seccion')
                ->where('id_docente', $user->docente->id_docente)
                ->distinct()
                ->pluck('id_curso');

            $query->whereIn('id_curso', $cursoIds);
        }

        // Buscar por nombre de estudiante o curso
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('estudiante', function ($q) use ($search) {
                $q->whereHas('usuario', function ($q2) use ($search) {
                    $q2->where(DB::raw("CONCAT(usuario.nombre1, ' ', COALESCE(usuario.apellido1, ''))"), 'ilike', "%{$search}%")
                        ->orWhere('usuario.username', 'ilike', "%{$search}%");
                });
            })
                ->orWhereHas('curso', function ($q) use ($search) {
                    $q->where('Curso.nombre', 'ilike', "%{$search}%")
                        ->orWhere('Curso.cod_curso', 'ilike', "%{$search}%");
                });
        }

        // Filtrar por curso
        if ($request->has('id_curso')) {
            $query->where('id_curso', $request->input('id_curso'));
        }

        // Filtrar por estado
        if ($request->has('estado_inscripcion')) {
            $query->where('estado_inscripcion', $request->input('estado_inscripcion'));
        }

        $inscripciones = $query
            ->with(['curso', 'estudiante.usuario'])
            ->orderBy('fecha_inscripcion', 'desc')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get available cursos for filtering
        $cursosQuery = Curso::query();
        if ($user->docente) {
            $cursoIds = DB::table('Seccion')
                ->where('id_docente', $user->docente->id_docente)
                ->distinct()
                ->pluck('id_curso');
            $cursosQuery->whereIn('id_curso', $cursoIds);
        }
        $cursos = $cursosQuery->orderBy('cod_curso')->get();

        if ($user->docente) {
            return Inertia::render('docente/Inscripciones', [
                'inscripciones' => $inscripciones,
                'cursos' => $cursos,
                'filters' => $request->only(['search', 'id_curso', 'estado_inscripcion'])
            ]);
        }

        return Inertia::render('admin/InscripcionesCursos', [
            'inscripciones' => $inscripciones,
            'cursos' => $cursos,
            'filters' => $request->only(['search', 'id_curso', 'estado_inscripcion'])
        ]);
    }

    /**
     * Muestra el formulario para crear una nueva inscripción.
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        // Verify user is admin or docente
        // Admin is defined as user without docente nor estudiante profiles
        $isAdmin = !$user->docente && !$user->estudiante;
        if (!$isAdmin && !$user->docente) {
            abort(403, 'No tienes permiso para crear inscripciones');
        }

        $idCurso = $request->query('id_curso');

        // Get available cursos
        $cursosQuery = Curso::query();
        if ($user->docente) {
            $cursoIds = DB::table('Seccion')
                ->where('id_docente', $user->docente->id_docente)
                ->distinct()
                ->pluck('id_curso');
            $cursosQuery->whereIn('id_curso', $cursoIds);
        }
        $cursos = $cursosQuery->orderBy('cod_curso')->get();

        // Get available estudiantes
        $estudiantesQuery = Estudiante::query()
            ->with('usuario:id_usuario,nombre1,apellido1,username')
            ->orderBy('id_estudiante');

        $estudiantes = $estudiantesQuery->get();

        if ($user->docente) {
            return Inertia::render('docente/CreateInscripcion', [
                'cursos' => $cursos,
                'estudiantes' => $estudiantes,
                'idCursoSeleccionado' => $idCurso
            ]);
        }

        return Inertia::render('admin/CreateInscripcionCurso', [
            'cursos' => $cursos,
            'estudiantes' => $estudiantes,
            'idCursoSeleccionado' => $idCurso
        ]);
    }

    /**
     * Almacena una nueva inscripción de estudiante en un curso.
     */
    public function store(StoreInscripcionCursoRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Verify authorization
        // Admin is defined as user without docente nor estudiante profiles
        $isAdmin = !$user->docente && !$user->estudiante;
        if (!$isAdmin) {
            if (!$user->docente) {
                abort(403, 'No tienes permiso para crear inscripciones');
            }
            // Docente: Verify they teach in this course
            $dictaSecciones = DB::table('Seccion')
                ->where('id_docente', $user->docente->id_docente)
                ->where('id_curso', $validated['id_curso'])
                ->exists();
            if (!$dictaSecciones) {
                abort(403, 'No enseñas en este curso');
            }
        }

        DB::beginTransaction();
        try {
            // Set default values
            if (empty($validated['fecha_inscripcion'])) {
                $validated['fecha_inscripcion'] = now()->toDateString();
            }

            if (empty($validated['estado_inscripcion'])) {
                $validated['estado_inscripcion'] = 'INSCRITO';
            }

            if (empty($validated['num_intento'])) {
                $validated['num_intento'] = 1;
            }

            // Create the inscription
            $inscripcion = InscripcionCurso::create($validated);

            DB::commit();

            return redirect()->route('admin.inscripciones_cursos.index')
                ->with('success', 'Inscripción creada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating inscripcion: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return back()->withErrors(['error' => 'Error al crear la inscripción: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Muestra los detalles de una inscripción específica.
     */
    public function show(InscripcionCurso $inscripcionCurso)
    {
        $user = Auth::user();

        // Verify authorization
        // Admin is defined as user without docente nor estudiante profiles
        $isAdmin = !$user->docente && !$user->estudiante;
        if (!$isAdmin) {
            if (!$user->docente) {
                abort(403, 'No tienes permiso');
            }
            $dictaSecciones = DB::table('Seccion')
                ->where('id_docente', $user->docente->id_docente)
                ->where('id_curso', $inscripcionCurso->id_curso)
                ->exists();
            if (!$dictaSecciones) {
                abort(403, 'No enseñas en este curso');
            }
        }

        $inscripcionCurso->load([
            'curso',
            'estudiante.usuario',
            'estudiante.carrera'
        ]);

        return response()->json([
            'inscripcion' => $inscripcionCurso
        ]);
    }

    /**
     * Muestra el formulario para editar una inscripción.
     */
    public function edit(InscripcionCurso $inscripcionCurso)
    {
        $user = Auth::user();

        // Verify authorization
        // Admin is defined as user without docente nor estudiante profiles
        $isAdmin = !$user->docente && !$user->estudiante;
        if (!$isAdmin) {
            if (!$user->docente) {
                abort(403, 'No tienes permiso');
            }
            $dictaSecciones = DB::table('Seccion')
                ->where('id_docente', $user->docente->id_docente)
                ->where('id_curso', $inscripcionCurso->id_curso)
                ->exists();
            if (!$dictaSecciones) {
                abort(403, 'No enseñas en este curso');
            }
        }

        $inscripcionCurso->load([
            'curso',
            'estudiante.usuario'
        ]);

        return Inertia::render('admin/EditInscripcionCurso', [
            'inscripcion' => $inscripcionCurso,
            'curso' => $inscripcionCurso->curso
        ]);
    }

    /**
     * Actualiza una inscripción existente.
     */
    public function update(UpdateInscripcionCursoRequest $request, InscripcionCurso $inscripcionCurso)
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Verify authorization
        // Admin is defined as user without docente nor estudiante profiles
        $isAdmin = !$user->docente && !$user->estudiante;
        if (!$isAdmin) {
            if (!$user->docente) {
                abort(403, 'No tienes permiso');
            }
            $dictaSecciones = DB::table('Seccion')
                ->where('id_docente', $user->docente->id_docente)
                ->where('id_curso', $inscripcionCurso->id_curso)
                ->exists();
            if (!$dictaSecciones) {
                abort(403, 'No enseñas en este curso');
            }
        }

        DB::beginTransaction();
        try {
            $inscripcionCurso->update($validated);

            DB::commit();

            return redirect()->route('admin.inscripciones_cursos.index')
                ->with('success', 'Inscripción actualizada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating inscripcion: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return back()->withErrors(['error' => 'Error al actualizar la inscripción: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Elimina una inscripción (solo para admins).
     */
    public function destroy(InscripcionCurso $inscripcionCurso)
    {
        $user = Auth::user();

        // Solo admins pueden eliminar
        // Admin is defined as user without docente nor estudiante profiles
        $isAdmin = !$user->docente && !$user->estudiante;
        if (!$isAdmin) {
            abort(403, 'Solo administradores pueden eliminar inscripciones');
        }

        DB::beginTransaction();
        try {
            $inscripcionCurso->delete();

            DB::commit();

            return redirect()->route('admin.inscripciones_cursos.index')
                ->with('success', 'Inscripción eliminada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting inscripcion: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'id_curso' => $inscripcionCurso->id_curso,
                'id_estudiante' => $inscripcionCurso->id_estudiante
            ]);
            return back()->withErrors(['error' => 'Error al eliminar la inscripción: ' . $e->getMessage()]);
        }
    }

    /**
     * Obtiene estudiantes disponibles para un curso específico (endpoint AJAX).
     * 
     * Retorna solo los estudiantes que aún no están inscritos en el curso.
     */
    public function getEstudiantesDisponibles(Request $request)
    {
        $idCurso = $request->query('id_curso');

        if (!$idCurso) {
            return response()->json(['error' => 'id_curso requerido'], 400);
        }

        // Get estudiante ids already inscribed in this course
        $inscritosIds = InscripcionCurso::where('id_curso', $idCurso)
            ->pluck('id_estudiante')
            ->toArray();

        // Get available estudiantes
        $estudiantes = Estudiante::query()
            ->with('usuario:id_usuario,nombre1,apellido1,username')
            ->whereNotIn('id_estudiante', $inscritosIds)
            ->orderBy('id_estudiante')
            ->get();

        Log::info('getEstudiantesDisponibles', [
            'idCurso' => $idCurso,
            'inscritosIds' => $inscritosIds,
            'count' => $estudiantes->count(),
            'first' => $estudiantes->first()
        ]);

        return response()->json([
            'estudiantes' => $estudiantes
        ]);
    }

    /**
     * Obtiene inscripciones de un curso específico (endpoint AJAX).
     */
    public function getByCurso(Request $request)
    {
        $idCurso = $request->query('id_curso');

        if (!$idCurso) {
            return response()->json(['error' => 'id_curso requerido'], 400);
        }

        $inscripciones = InscripcionCurso::where('id_curso', $idCurso)
            ->with(['estudiante.usuario', 'curso'])
            ->orderBy('fecha_inscripcion', 'desc')
            ->get();

        return response()->json([
            'inscripciones' => $inscripciones
        ]);
    }

    /**
     * Exporta inscripciones de un curso a CSV.
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();

        // Verify user is admin or docente
        // Admin is defined as user without docente nor estudiante profiles
        $isAdmin = !$user->docente && !$user->estudiante;
        if (!$isAdmin && !$user->docente) {
            abort(403, 'No tienes permiso para exportar');
        }

        $idCurso = $request->query('id_curso');

        $query = InscripcionCurso::query();

        // Filter by course if provided
        if ($idCurso) {
            $query->where('id_curso', $idCurso);
        }

        // Filter to user's courses if docente
        if ($user->docente) {
            $cursoIds = DB::table('Seccion')
                ->where('id_docente', $user->docente->id_docente)
                ->distinct()
                ->pluck('id_curso');
            $query->whereIn('id_curso', $cursoIds);
        }

        $inscripciones = $query
            ->with(['curso', 'estudiante.usuario'])
            ->orderBy('fecha_inscripcion', 'desc')
            ->get();

        $filename = 'inscripciones_' . ($idCurso ?? 'todas') . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($inscripciones) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'ID Curso',
                'Código Curso',
                'Nombre Curso',
                'ID Estudiante',
                'Estudiante',
                'Usuario',
                'Código Inscripción',
                'Fecha Inscripción',
                'Estado',
                'Intentos',
                'Promedio Parcial'
            ]);

            // Data
            foreach ($inscripciones as $inscripcion) {
                fputcsv($file, [
                    $inscripcion->id_curso,
                    $inscripcion->curso->cod_curso ?? '',
                    $inscripcion->curso->nombre ?? '',
                    $inscripcion->id_estudiante,
                    $inscripcion->estudiante->usuario->nombre1 . ' ' . ($inscripcion->estudiante->usuario->apellido1 ?? ''),
                    $inscripcion->estudiante->usuario->username ?? '',
                    $inscripcion->cod_inscripcion_uta ?? '',
                    $inscripcion->fecha_inscripcion ?? '',
                    $inscripcion->estado_inscripcion ?? '',
                    $inscripcion->num_intento ?? '1',
                    $inscripcion->promedio_parcial ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
