<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInscripcionCursoRequest;
use App\Http\Requests\UpdateInscripcionCursoRequest;
use App\Http\Resources\InscripcionCursoResource;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Estudiante;
use App\Services\InscripcionCursoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
/**
 * Controlador para la gestión de inscripciones de estudiantes en cursos.
 * 
 * Utiliza:
 * - InscripcionCursoService para lógica de negocio
 * - InscripcionCursoPolicy para autorización
 * - InscripcionCursoResource para serialización de datos
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
    private InscripcionCursoService $inscripcionService;

    public function __construct(InscripcionCursoService $inscripcionService)
    {
        $this->inscripcionService = $inscripcionService;
    }

    /**
     * Muestra un listado paginado de inscripciones con búsqueda y filtros.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Autorizar vista
        $this->authorize('viewAny', InscripcionCurso::class);

        $filters = $request->only(['search', 'id_curso', 'estado_inscripcion', 'page']);
        $idDocente = $user->docente?->id_docente;

        $inscripciones = $this->inscripcionService->getFiltered(
            $filters,
            $idDocente,
            $request->input('per_page', 15)
        );

        // Obtener cursos disponibles
        $cursos = $idDocente
            ? $this->inscripcionService->getCursosByDocente($idDocente)
            : Curso::orderBy('cod_curso')->get();

        // Renderizar según rol
        if ($user->docente) {
            return Inertia::render('docente/Inscripciones', [
                'inscripciones' => $inscripciones,
                'cursos' => $cursos,
                'filters' => $filters
            ]);
        }

        return Inertia::render('admin/InscripcionesCursos', [
            'inscripciones' => $inscripciones,
            'cursos' => $cursos,
            'filters' => $filters
        ]);
    }

    /**
     * Muestra el formulario para crear una nueva inscripción.
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        // Autorizar creación
        $this->authorize('create', InscripcionCurso::class);

        $idCursoSelected = $request->query('id_curso');
        $idDocente = $user->docente?->id_docente;

        // Obtener cursos disponibles
        $cursos = $idDocente
            ? $this->inscripcionService->getCursosByDocente($idDocente)
            : Curso::orderBy('cod_curso')->get();

        // Obtener estudiantes disponibles
        $estudiantes = Estudiante::with('usuario:id_usuario,nombre1,apellido1,username')
            ->orderBy('id_estudiante')
            ->get();

        if ($user->docente) {
            return Inertia::render('docente/CreateInscripcion', [
                'cursos' => $cursos,
                'estudiantes' => $estudiantes,
                'idCursoSeleccionado' => $idCursoSelected
            ]);
        }

        return Inertia::render('admin/CreateInscripcionCurso', [
            'cursos' => $cursos,
            'estudiantes' => $estudiantes,
            'idCursoSeleccionado' => $idCursoSelected
        ]);
    }

    /**
     * Almacena una nueva inscripción de estudiante en un curso.
     */
    public function store(StoreInscripcionCursoRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Autorizar para este curso específico
        $this->authorize('createForCurso', [InscripcionCurso::class, $validated['id_curso']]);

        try {
            $this->inscripcionService->create($validated);

            return redirect()->route('admin.inscripciones_cursos.index')
                ->with('success', 'Inscripción creada exitosamente.');
        } catch (\Exception $e) {
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
        // Autorizar vista
        $this->authorize('view', $inscripcionCurso);

        $inscripcionCurso->load([
            'curso',
            'estudiante.usuario',
            'estudiante.carrera'
        ]);

        return response()->json([
            'inscripcion' => new InscripcionCursoResource($inscripcionCurso)
        ]);
    }

    /**
     * Muestra el formulario para editar una inscripción.
     */
    public function edit(InscripcionCurso $inscripcionCurso)
    {
        // Autorizar actualización
        $this->authorize('update', $inscripcionCurso);

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
        // Autorizar actualización
        $this->authorize('update', $inscripcionCurso);

        $validated = $request->validated();

        try {
            $this->inscripcionService->update($inscripcionCurso, $validated);

            return redirect()->route('admin.inscripciones_cursos.index')
                ->with('success', 'Inscripción actualizada exitosamente.');
        } catch (\Exception $e) {
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
        // Autorizar eliminación (solo admins)
        $this->authorize('delete', $inscripcionCurso);

        try {
            $this->inscripcionService->delete($inscripcionCurso);

            return redirect()->route('admin.inscripciones_cursos.index')
                ->with('success', 'Inscripción eliminada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error deleting inscripcion: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'id_curso' => $inscripcionCurso->id_curso,
                'id_estudiante' => $inscripcionCurso->id_estudiante
            ]);
            return back()->withErrors(['error' => 'Error al eliminar la inscripción: ' . $e->getMessage()]);
        }
    }

    /**
     * Obtiene estudiantes disponibles para un curso (endpoint AJAX).
     * Retorna solo estudiantes no inscritos. Auto-crea perfiles faltantes.
     */
    public function getEstudiantesDisponibles(Request $request)
    {
        $idCurso = $request->query('id_curso');

        if (!$idCurso) {
            return response()->json(['error' => 'id_curso requerido'], 400);
        }

        try {
            $estudiantes = $this->inscripcionService->getEstudiantesDisponibles($idCurso);
            return response()->json(['estudiantes' => $estudiantes]);
        } catch (\Exception $e) {
            Log::error('Error getting available students: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener estudiantes disponibles'], 500);
        }
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

        try {
            $inscripciones = $this->inscripcionService->getInscripcionesByCurso($idCurso);
            return response()->json([
                'inscripciones' => InscripcionCursoResource::collection($inscripciones)
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting inscripciones by curso: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener inscripciones'], 500);
        }
    }

    /**
     * Exporta inscripciones a CSV.
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();

        // Autorizar exportación
        $this->authorize('export', InscripcionCurso::class);

        $idCurso = $request->query('id_curso');
        $filters = ['id_curso' => $idCurso];
        $idDocente = $user->docente?->id_docente;

        try {
            $inscripciones = $this->inscripcionService->getFiltered($filters, $idDocente, 999999);

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
                    $estudiante = $inscripcion->estudiante;
                    fputcsv($file, [
                        $inscripcion->id_curso,
                        $inscripcion->curso->cod_curso ?? '',
                        $inscripcion->curso->nombre ?? '',
                        $inscripcion->id_estudiante,
                        $estudiante->usuario->nombre1 . ' ' . ($estudiante->usuario->apellido1 ?? ''),
                        $estudiante->usuario->username ?? '',
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
        } catch (\Exception $e) {
            Log::error('Error exporting inscripciones: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Error al exportar inscripciones']);
        }
    }
}
