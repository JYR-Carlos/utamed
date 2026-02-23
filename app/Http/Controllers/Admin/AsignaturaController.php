<?php

namespace App\Http\Controllers\Admin;

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
    /**
     * Muestra un listado paginado de asignaturas con búsqueda por código o nombre.
     */
    public function index(Request $request)
    {
        $query = Asignatura::select(['id_asignatura', 'cod_asignatura', 'nombre', 'creditos_sct']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                -> orWhere('cod_asignatura', 'ilike', "%{$search}%");
            });
        }

        $asignaturas = $query->orderBy(('cod_asignatura'))
        ->paginate($request->integer('per_page', 15))
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
     */
    public function show(Asignatura $asignatura)
    {
        $asignatura->load('asignacionPlanes.plan.carrera');

        return response()->json($asignatura);
    }

    /**
     * Actualiza los datos de una asignatura.
     */
    public function update(Request $request, Asignatura $asignatura)
    {
        $validated = $request->validate([
            'cod_asignatura' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Asignatura::class, 'cod_asignatura')->ignore($asignatura->id_asignatura, 'id_asignatura')
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

        $asignatura->update($validated);

        return redirect()->route('admin.asignaturas.index')
            ->with('success', 'Asignatura actualizada exitosamente.');
    }

    /**
     * Elimina una asignatura del catálogo.
     */
    public function destroy(Asignatura $asignatura)
    {
        try {
            $asignatura->delete();

            return redirect()->route('admin.asignaturas.index')
                ->with('success', 'Asignatura eliminada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.asignaturas.index')
                ->with('error', 'No se puede eliminar la asignatura porque está asignada a planes.');
        }
    }
}
