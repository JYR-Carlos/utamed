<?php

namespace App\Http\Controllers\Docente\JefeCarrera;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Gestión de asignaturas para el Jefe de Carrera.
 *
 * Las asignaturas son globales, pero el jefe solo VE y EDITA las que están
 * asignadas a planes de su carrera. La creación de nuevas asignaturas (catálogo
 * global) sí se permite, igual que en admin. Reutiliza la vista admin/Asignaturas.
 */
class AsignaturaController extends Controller
{
    use ResolvesJefaturaCarrera;

    private const PREFIX = '/docente/jefe-carrera';

    public function index(Request $request)
    {
        $carreraId = $this->carreraIdOrAbort();

        $query = Asignatura::select(['id_asignatura', 'cod_asignatura', 'nombre', 'creditos_sct', 'fecha_creacion'])
            ->active()
            ->withCount('asignacionPlanes as planes_count')
            // Solo asignaturas usadas en planes de la carrera del jefe.
            ->whereHas('asignacionPlanes.plan', fn($q) => $q->where('id_carrera', $carreraId))
            ->where(function ($q) {
                $q->whereIn('id_asignatura', function ($subquery) {
                    $subquery->selectRaw('DISTINCT ON (cod_asignatura) id_asignatura')
                        ->from('asignatura')
                        ->whereNull('fecha_eliminacion')
                        ->orderBy('cod_asignatura')
                        ->orderByDesc('fecha_creacion');
                });
            });

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('cod_asignatura', 'ilike', "%{$search}%");
            });
        }

        $asignaturas = $query->orderBy('cod_asignatura')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('admin/Asignaturas', [
            'asignaturas' => $asignaturas,
            'filters' => $request->only(['search']),
            'routePrefix' => self::PREFIX,
        ]);
    }

    public function store(Request $request)
    {
        $this->carreraIdOrAbort();

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
            Asignatura::create($validated);

            return redirect(self::PREFIX . '/asignaturas')
                ->with('success', 'Asignatura creada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear asignatura: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Asignatura $asignatura)
    {
        $carreraId = $this->carreraIdOrAbort();
        $this->assertAsignaturaDeCarrera($asignatura, $carreraId);

        $validated = $request->validate([
            'cod_asignatura' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Asignatura::class, 'cod_asignatura')
                    ->where('fecha_eliminacion', null)
                    ->ignore($asignatura->id_asignatura, 'id_asignatura'),
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

        try {
            // Versionado: soft-delete de la versión anterior + nueva versión.
            $asignatura->delete();
            Asignatura::create($validated);

            return redirect(self::PREFIX . '/asignaturas')
                ->with('success', 'Asignatura actualizada exitosamente. Se ha creado una nueva versión del registro.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar asignatura: ' . $e->getMessage());
        }
    }

    public function destroy(Asignatura $asignatura)
    {
        $carreraId = $this->carreraIdOrAbort();
        $this->assertAsignaturaDeCarrera($asignatura, $carreraId);

        try {
            if ($asignatura->asignacionPlanes()->count() > 0) {
                return redirect(self::PREFIX . '/asignaturas')
                    ->with('error', 'No se puede eliminar la asignatura porque está asignada a planes de estudio activos.');
            }

            $asignatura->delete();

            return redirect(self::PREFIX . '/asignaturas')
                ->with('success', 'Asignatura eliminada exitosamente.');
        } catch (\Exception $e) {
            return redirect(self::PREFIX . '/asignaturas')
                ->with('error', 'Error al eliminar la asignatura: ' . $e->getMessage());
        }
    }

    /**
     * Aborta con 403 si la asignatura no está usada en ningún plan de la carrera del jefe.
     */
    private function assertAsignaturaDeCarrera(Asignatura $asignatura, int $carreraId): void
    {
        $perteneceCarrera = $asignatura->asignacionPlanes()
            ->whereHas('plan', fn($q) => $q->where('id_carrera', $carreraId))
            ->exists();

        if (!$perteneceCarrera) {
            abort(403, 'Esta asignatura no está asociada a tu carrera.');
        }
    }
}
