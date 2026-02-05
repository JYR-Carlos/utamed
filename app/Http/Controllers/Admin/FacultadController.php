<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Facultad;
use Illuminate\Http\Request;
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
    /**
     * Muestra un listado paginado de todas las facultades.
     * Soporta búsqueda por nombre.
     */
    public function index(Request $request)
    {
        $query = Facultad::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('nombre', 'ilike', "%{$search}%");
        }

        // Pagination
        $facultades = $query->orderBy('nombre')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('admin/Facultades', [
            'facultades' => $facultades,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Crea una nueva facultad y su contexto asociado.
     * 
     * La facultad genera automáticamente un contexto para gestionar
     * permisos y roles a nivel de facultad.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Create context for this Facultad
            $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                ['contexto_display' => 'Facultad: ' . $validated['nombre']],
                ['descripcion' => 'Contexto para la facultad ' . $validated['nombre']]
            );

            $validated['id_contexto'] = $contexto->id_contexto;
            $facultad = Facultad::create($validated);

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->route('admin.facultades.index')
                ->with('success', 'Facultad creada exitosamente.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->with('error', 'Error al crear facultad: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene los datos de una facultad específica con sus departamentos.
     */
    public function show(Facultad $facultad)
    {
        $facultad->load('departamentos');

        return response()->json($facultad);
    }

    /**
     * Actualiza los datos de una facultad.
     */
    public function update(Request $request, Facultad $facultad)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $facultad->update($validated);

        return redirect()->route('admin.facultades.index')
            ->with('success', 'Facultad actualizada exitosamente.');
    }

    /**
     * Elimina una facultad y todas sus relaciones en cascada.
     */
    public function destroy(Facultad $facultad)
    {
        try {
            $facultad->delete();

            return redirect()->route('admin.facultades.index')
                ->with('success', 'Facultad eliminada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.facultades.index')
                ->with('error', 'No se puede eliminar la facultad porque tiene departamentos asociados.');
        }
    }
}
