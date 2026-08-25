<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LimitsPageSize;
use App\Http\Controllers\Controller;
use App\Models\Administrativo\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;



/**
 * Controlador para la gestión de asignaturas.
 * 
 * Tablas implicadas:
 * - administrativo.asignatura: Catálogo de asignaturas disponibles.
 * - administrativo.asignacion_plan: Instancias de asignaturas en planes específicos.
 * 
 * Las asignaturas son entidades globales que pueden ser asignadas a múltiples planes.
 * Contienen información como código, nombre, créditos SCT y otros datos académicos.
 */
class AsignaturaController extends Controller
{
    use LimitsPageSize;

    /**
     * Muestra un listado paginado de asignaturas con búsqueda por código o nombre.
     * 
     * Solo muestra las versiones ACTIVAS (no eliminadas) de las asignaturas.
     * En caso de que existan múltiples versiones del mismo código, muestra la más reciente.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Asignatura::class);
        $query = Asignatura::select([
            'id_asignatura', 'cod_asignatura', 'nombre', 'descripcion', 'creditos_sct',
            'horas_catedra', 'horas_taller', 'horas_laboratorio', 'horas_dirigidas', 'horas_autonomas',
            'fecha_creacion',
        ])
            ->active() // Solo versiones no eliminadas
            ->withCount('asignacionPlanes as planes_count')
            ->where(function ($q) {
                // Para cada cod_asignatura, quedarse con la fecha_creacion más reciente
                $q->whereIn('id_asignatura', function ($subquery) {
                    $subquery->selectRaw('DISTINCT ON (cod_asignatura) id_asignatura')
                        ->from('asignatura')
                        ->whereNull('fecha_eliminacion')
                        ->orderBy('cod_asignatura')
                        ->orderByDesc('fecha_creacion');
                });
            });

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                -> orWhere('cod_asignatura', 'ilike', "%{$search}%");
            });
        }

        $asignaturas = $query->orderBy(('cod_asignatura'))
        ->paginate($this->perPage($request))
        ->withQueryString();

        return Inertia::render('admin/Asignaturas', [
            'asignaturas' => $asignaturas,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Crea una nueva asignatura en el catálogo global.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Asignatura::class);
        $validated = $request->validate([
            'cod_asignatura' => ['required', 'string', 'max:50', Rule::unique(Asignatura::class, 'cod_asignatura')],
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'creditos_sct' => 'nullable|integer|min:0',
            'horas_catedra' => 'nullable|integer|min:0',
            'horas_taller' => 'nullable|integer|min:0',
            'horas_laboratorio' => 'nullable|integer|min:0',
            'horas_dirigidas' => 'nullable|integer|min:0',
            'horas_autonomas' => 'nullable|integer|min:0',
        ]);

        try {
            $asignatura = Asignatura::create($validated);

            return redirect()->route('admin.asignaturas.index')
                ->with('success', 'Asignatura creada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear asignatura: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene una asignatura con sus asignaciones a planes.
     * 
     * Solo retorna la versión activa (no eliminada).
     */
    public function show(Asignatura $asignatura)
    {
        $this->authorize('view', $asignatura);
        // Verificar que la asignatura no está eliminada
        if ($asignatura->fecha_eliminacion !== null) {
            abort(404, 'Esta versión de la asignatura ha sido reemplazada por una más reciente.');
        }

        $asignatura->load('asignacionPlanes.plan.carrera');

        return response()->json($asignatura);
    }

    /**
     * Edita una asignatura según el tipo de edición indicado:
     *
     * - 'correctiva': corrige datos mal escritos (typos, errores menores).
     *   Actualiza el registro existente en el sitio, sin versionar.
     * - 'version': cambio grande que implica una nueva asignatura.
     *   1. Marca la versión anterior como eliminada (soft delete)
     *   2. Crea una NUEVA versión con los datos modificados
     *
     * El versionado preserva el historial completo de cambios y mantiene
     * la trazabilidad de qué versión estaba activa en cada momento.
     *
     * La versión "activa" es aquella que:
     * - No está eliminada (fecha_eliminacion IS NULL)
     * - Tiene el fecha_creacion más reciente
     */
    public function update(Request $request, Asignatura $asignatura)
    {
        $this->authorize('update', $asignatura);
        $validated = $request->validate([
            'tipo_edicion' => ['required', Rule::in(['correctiva', 'version'])],
            'cod_asignatura' => [
                'required',
                'string',
                'max:50',
                // Permitir el mismo código (para versionado) pero no otro código en uso sin eliminar
                Rule::unique(Asignatura::class, 'cod_asignatura')
                    ->where('fecha_eliminacion', null)
                    ->ignore($asignatura->id_asignatura, 'id_asignatura')
            ],
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'creditos_sct' => 'nullable|integer|min:0',
            'horas_catedra' => 'nullable|integer|min:0',
            'horas_taller' => 'nullable|integer|min:0',
            'horas_laboratorio' => 'nullable|integer|min:0',
            'horas_dirigidas' => 'nullable|integer|min:0',
            'horas_autonomas' => 'nullable|integer|min:0',
        ]);

        $tipoEdicion = $validated['tipo_edicion'];
        unset($validated['tipo_edicion']);

        try {
            if ($tipoEdicion === 'correctiva') {
                // Edición correctiva: actualiza el registro existente, sin versionar.
                $asignatura->update($validated);
                $message = 'Asignatura corregida exitosamente.';
            } else {
                // Edición versionada: soft delete de la versión anterior + nueva versión.
                $asignatura->delete();
                Asignatura::create($validated);
                $message = 'Asignatura actualizada exitosamente. Se ha creado una nueva versión del registro.';
            }

            return redirect()->route('admin.asignaturas.index')->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar asignatura: ' . $e->getMessage());
        }
    }

    /**
     * Elimina (soft delete) una asignatura del catálogo.
     * 
     * El registro no se elimina físicamente, solo se marca como eliminado
     * mediante fecha_eliminacion para mantener la integridad histórica.
     * 
     * No se puede eliminar si la asignatura está actualmente asignada a planes.
     */
    public function destroy(Asignatura $asignatura)
    {
        $this->authorize('delete', $asignatura);
        try {
            // Verificar que no está actualmente asignada a planes
            if ($asignatura->asignacionPlanes()->count() > 0) {
                return redirect()->route('admin.asignaturas.index')
                    ->with('error', 'No se puede eliminar la asignatura porque está asignada a planes de estudio activos.');
            }

            $asignatura->delete();

            return redirect()->route('admin.asignaturas.index')
                ->with('success', 'Asignatura eliminada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.asignaturas.index')
                ->with('error', 'Error al eliminar la asignatura: ' . $e->getMessage());
        }
    }
}
