<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LimitsPageSize;
use App\Http\Controllers\Controller;
use App\Models\Administrativo\Facultad;
use App\Services\FacultadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Controlador para la gestión de facultades.
 *
 * Tablas implicadas:
 * - administrativo.facultad: Facultades de la institución.
 *
 * Las facultades son entidades raíz en la jerarquía organizacional.
 * Contienen departamentos que a su vez contienen carreras.
 */
class FacultadController extends Controller
{
    use LimitsPageSize;

    public function __construct(private readonly FacultadService $facultadService)
    {
    }

    /**
     * Muestra un listado paginado de todas las facultades.
     * Soporta búsqueda por nombre.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Facultad::class);

        $query = Facultad::with(['departamentos' => function($q) {
            $q->withTrashed();
        }]);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('nombre', 'ilike', "%{$search}%");
        }

        $facultades = $query->orderBy('nombre')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $user = Auth::user();

        return Inertia::render('admin/Facultades', [
            'facultades' => $facultades,
            'filters'    => $request->only(['search']),
            'canCreate'  => $user->can('create', Facultad::class),
            'canEdit'    => $user->can('update', new Facultad()),
            'canDelete'  => $user->can('delete', new Facultad()),
        ]);
    }

    /**
     * Crea una nueva facultad y su contexto asociado.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Facultad::class);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        try {
            $this->facultadService->create($validated, Auth::user());

            return redirect()->route('admin.facultades.index')
                ->with('success', 'Facultad creada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear facultad: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene los datos de una facultad específica con sus departamentos.
     */
    public function show(Facultad $facultad)
    {
        $this->authorize('view', $facultad);

        $facultad->load(['departamentos' => function($q) {
            $q->withTrashed();
        }]);

        return response()->json($facultad);
    }

    /**
     * Actualiza los datos de una facultad.
     */
    public function update(Request $request, Facultad $facultad)
    {
        $this->authorize('update', $facultad);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        try {
            $this->facultadService->update($facultad, $validated, Auth::user());

            return redirect()->route('admin.facultades.index')
                ->with('success', 'Facultad actualizada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar facultad: ' . $e->getMessage());
        }
    }

    /**
     * Elimina una facultad y todas sus relaciones en cascada.
     */
    public function destroy(Facultad $facultad)
    {
        $this->authorize('delete', $facultad);

        // Facultad usa SoftDeletes: delete() nunca lanza excepción por FK, así que
        // el catch de abajo no detectaba departamentos asociados. Chequeo explícito
        // (mismo patrón que AsignaturaController::destroy).
        if ($facultad->departamentos()->count() > 0) {
            return redirect()->route('admin.facultades.index')
                ->with('error', 'No se puede eliminar la facultad porque tiene departamentos asociados.');
        }

        try {
            $this->facultadService->delete($facultad, Auth::user());

            return redirect()->route('admin.facultades.index')
                ->with('success', 'Facultad eliminada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.facultades.index')
                ->with('error', 'No se pudo eliminar la facultad: ' . $e->getMessage());
        }
    }
}

