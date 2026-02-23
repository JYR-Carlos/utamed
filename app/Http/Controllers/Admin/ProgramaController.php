<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Services\ProgramaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Admin Programa Controller
 * 
 * Retorna JSON responses para AJAX/Axios
 * Guarda estructura JSONB en data_syllabus
 */
class ProgramaController extends Controller
{
    /**
     * Retorna el programa activo en vista Inertia para revisión
     */
    public function show(Curso $curso)
    {
        // Cargar relaciones necesarias
        $curso->load('asignacionPlan.asignatura', 'asignacionPlan.plan.carrera');

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        // Validar autorización si existe programa
        if ($programa) {
            $this->authorize('approve', $programa);
        }

        if (!$programa) {
            return redirect()->route('admin.cursos.index')
                ->with('error', 'No hay programa para este curso');
        }

        return Inertia::render('admin/Programas/ReviewPrograma', [
            'programa' => [
                'id_programa' => $programa->id_programa,
                'version_programa' => $programa->version_programa,
                'estado' => $programa->estado,
                'data_syllabus' => $programa->data_syllabus,
                'fecha_creacion' => $programa->fecha_creacion,
            ],
            'curso' => [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre,
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre,
            ],
        ]);
    }

    /**
     * Genera o regenera el programa activo
     * 
     * Si se envían secciones customizadas, las usa.
     * Si no, genera estructura base automáticamente.
     */
    public function store(Request $request, Curso $curso)
    {
        $user = Auth::user();

        // Validar que el usuario tiene permiso para crear/editar programa
        $this->authorize('create', [Programa::class, $curso]);

        // Validar si se envían secciones
        if ($request->has('secciones')) {
            $request->validate([
                'secciones' => 'required|array',
                'secciones.*.nombre_seccion' => 'required|string',
                'secciones.*.orden' => 'required|integer',
                'secciones.*.contenidos' => 'nullable|array',
                'secciones.*.contenidos.*.texto_contenido' => 'nullable|string',
                'secciones.*.contenidos.*.orden_item' => 'required|integer',
            ]);
            
            $overrides = ['secciones' => $request->secciones];
        } else {
            $overrides = null;
        }

        try {
            $programa = ProgramaService::generateProgramaWithSyllabus(
                $curso,
                $user,
                $overrides
            );

            return response()->json([
                'message' => 'Programa generado correctamente.',
                'programa' => [
                    'id_programa' => $programa->id_programa,
                    'version_programa' => $programa->version_programa,
                    'estado' => $programa->estado,
                    'data_syllabus' => $programa->data_syllabus,
                    'fecha_creacion' => $programa->fecha_creacion,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al generar el programa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprueba el programa (cambia estado a APROBADO)
     */
    public function approve(Request $request, Curso $curso)
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return response()->json(['error' => 'No hay programa activo.'], 404);
        }

        // Validar autorización para actualizar/aprobar programa
        $this->authorize('approve', $programa);

        try {
            ProgramaService::changeStatus($programa, 'APROBADO');

            return redirect()->route('admin.cursos.index')
                ->with('success', 'Programa aprobado correctamente');

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar programas con filtro por estado
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Programa::class);

        $estado = $request->query('estado', 'PENDIENTE');
        $page = $request->query('page', 1);

        $query = Programa::query()
            ->with([
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera',
                'autor'
            ])
            ->whereNull('fecha_eliminacion');

        // Filtro por estado
        if ($estado && in_array($estado, ['BORRADOR', 'PENDIENTE', 'APROBADO', 'RECHAZADO'])) {
            $query->where('estado', $estado);
        }

        $programas = $query->orderBy('fecha_creacion', 'desc')->paginate(15, ['*'], 'page', $page);

        // Mapear datos
        $programasData = $programas->map(function ($p) {
            return [
                'id_programa' => $p->id_programa,
                'id_curso' => $p->id_curso,
                'version' => $p->version_programa,
                'estado' => $p->estado,
                'asignatura' => $p->curso?->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera' => $p->curso?->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'docente' => $p->autor?->nombre ?? 'N/A',
                'fecha_creacion' => $p->fecha_creacion,
            ];
        });

        // Estadísticas
        $stats = [
            'pendientes' => Programa::where('estado', 'PENDIENTE')->whereNull('fecha_eliminacion')->count(),
            'aprobados' => Programa::where('estado', 'APROBADO')->whereNull('fecha_eliminacion')->count(),
            'rechazados' => Programa::where('estado', 'RECHAZADO')->whereNull('fecha_eliminacion')->count(),
            'borradores' => Programa::where('estado', 'BORRADOR')->whereNull('fecha_eliminacion')->count(),
        ];

        return Inertia::render('admin/Programas', [
            'programas' => $programasData,
            'pagination' => [
                'current_page' => $programas->currentPage(),
                'last_page' => $programas->lastPage(),
                'total' => $programas->total(),
                'per_page' => $programas->perPage(),
            ],
            'stats' => $stats,
            'estado_filtro' => $estado,
        ]);
    }

    /**
     * Rechazar programa - Cambiar estado a RECHAZADO
     */
    public function reject(Request $request, Curso $curso)
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        $this->authorize('reject', $programa);

        // Validar estado actual
        if (!in_array($programa->estado, ['PENDIENTE'])) {
            return redirect()->back()->with('error', "No se puede rechazar un programa en estado {$programa->estado}");
        }

        $programa->update([
            'estado' => 'RECHAZADO',
        ]);

        return redirect()->route('admin.programas.index', ['estado' => 'RECHAZADO'])
            ->with('warning', "Programa de {$curso->nombre} rechazado. El docente puede editarlo nuevamente");
    }
}