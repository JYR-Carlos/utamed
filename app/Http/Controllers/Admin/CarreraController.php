<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LimitsPageSize;
use App\Http\Controllers\Controller;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Services\CarreraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Controlador para la gestión de carreras.
 * 
 * Tablas implicadas:
 * - administrativo.carrera: Carreras académicas.
 * - administrativo.departamento: Departamentos que contienen carreras.
 * - administrativo.facultad: Facultades que contienen departamentos.
 * 
 * Las carreras pertenecen a departamentos y contienen planes de estudio.
 * Forman parte de la jerarquía: Facultad → Departamento → Carrera → Plan.
 */
class CarreraController extends Controller
{
    use LimitsPageSize;

    public function __construct(private readonly CarreraService $carreraService)
    {
    }

    /**
     * Muestra un listado paginado de carreras con búsqueda y filtros.
     * Display a listing of carreras.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Carrera::class);
        // Filtro de estado: 'active' (por defecto) solo muestra activas; 'all' incluye discontinuadas
        $status = $request->input('status', 'active');

        $query = $status === 'all' ? Carrera::withTrashed() : Carrera::query();

        $query->select([
                'id_carrera',
                'nombre',
                'id_departamento',
                'id_contexto',
                'jornada',
                'sede',
                'modalidad',
                'fecha_eliminacion',
            ])
            ->withCount([
                // Planes activos — SoftDeletes global scope ya excluye las eliminadas
                'planes as planes_activos_count',
            ])
            ->withCount([
                // has_director: existe un usuario con rol 'Jefe de Carrera' activo
                // en el contexto de tipo 'carrera' de esta carrera específica.
                'jefesDeCarreraActivos as has_director',
            ])
            ->with([
                'departamento' => function($q) {
                    $q->withTrashed()
                        ->select('id_departamento', 'nombre', 'id_facultad', 'fecha_eliminacion');
                },
                'departamento.facultad:id_facultad,nombre',
            ]);

        // Búsqueda con ilike
        if ($request->filled('search')) {
            $query->where('nombre', 'ilike', "%{$request->search}%");
        }

        // Filtro de jerarquía
        if ($request->filled('id_departamento')) {
            $query->where('id_departamento', $request->id_departamento);
        }

        if ($request->filled('id_facultad')) {
            $query->whereHas(
                'departamento',
                fn($q) => $q->where('id_facultad', $request->id_facultad)
            );
        }

        $carreras = $query->orderBy('nombre')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('admin/Carreras', [
            'carreras'   => $carreras,
            'facultades' => Facultad::select(['id_facultad', 'nombre'])->orderBy('nombre')->get(),
            'filters'    => $request->only(['search', 'id_departamento', 'id_facultad', 'status']),
        ]);
    }

    /**
     * Obtiene carreras para un select en cascada (por departamento).
     */
    public function byDepartamento(Departamento $departamento)
    {
        $this->authorize('viewAny', Carrera::class);
        $carreras = $departamento->carreras()
            ->orderBy('nombre')
            ->get();

        return response()->json($carreras);
    }

    /**
     * Crea una nueva carrera.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Carrera::class);
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'jornada' => 'nullable|string|max:100',
            'sede' => 'nullable|string|max:100',
            'modalidad' => 'nullable|string|max:100',
            'id_departamento' => ['required', Rule::exists(Departamento::class, 'id_departamento')],
            'id_facultad' => ['required', Rule::exists(Facultad::class, 'id_facultad')],
        ]);

        try {
            /** @var \App\Models\Usuario\Usuario $actor */
            $actor = Auth::user();
            $this->carreraService->create($validated, $actor);

            return redirect()->route('admin.carreras.index')
                ->with('success', 'Carrera creada. El contexto de permisos RBAC se ha generado automáticamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear carrera: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene una carrera con sus relaciones (departamento, facultad, planes).
     */
    public function show(Carrera $carrera)
    {
        $this->authorize('view', $carrera);
        $carrera->load([
            'departamento' => function($q) {
                $q->withTrashed();
            },
            'departamento.facultad',
            'planes'
        ]);

        return response()->json($carrera);
    }

    /**
     * Actualiza los datos mutables de una carrera.
     * id_departamento e id_contexto son inmutables post-creación:
     * cambiarlos rompería la constraint uq_carrera_departamento y la herencia RBAC.
     */
    public function update(Request $request, Carrera $carrera)
    {
        $this->authorize('update', $carrera);
        $validated = $request->validate([
            'nombre'    => 'required|string|max:255',
            'jornada'   => 'nullable|string|max:100',
            'sede'      => 'nullable|string|max:100',
            'modalidad' => 'nullable|string|max:100',
            // id_departamento y id_facultad son intencionalmente omitidos (read-only post-creación)
        ]);

        $carrera->update($validated);

        return redirect()->route('admin.carreras.index')
            ->with('success', 'Carrera actualizada exitosamente.');
    }

    /**
     * Discontinua (soft-delete) una carrera.
     * Establece fecha_eliminacion = now(). El historial académico se preserva.
     */
    public function destroy(Carrera $carrera)
    {
        $this->authorize('delete', $carrera);
        try {
            $carrera->delete(); // SoftDeletes → sets fecha_eliminacion = now()

            return redirect()->route('admin.carreras.index')
                ->with('success', 'Carrera discontinuada. El historial académico se mantiene intacto.');
        } catch (\Exception $e) {
            return redirect()->route('admin.carreras.index')
                ->with('error', 'No se pudo discontinuar la carrera: ' . $e->getMessage());
        }
    }
}
