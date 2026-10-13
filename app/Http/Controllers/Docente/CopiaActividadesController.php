<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Services\Agenda\CopiaActividadesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * «Copiar a otro curso»: lleva actividades de un curso a sus cursos hermanos
 * (mismo docente titular, asignatura, año y semestre). La lógica vive en
 * CopiaActividadesService; aquí sólo se autoriza y se valida la entrada.
 */
class CopiaActividadesController extends Controller
{
    public function __construct(private CopiaActividadesService $copia)
    {
    }

    /**
     * GET docente/cursos/{curso}/actividades/copiar/opciones
     *
     * Cursos de destino y, para cada actividad del curso, si se puede copiar
     * ahí (y por qué no) y si ya existe una con el mismo nombre.
     */
    public function opciones(Curso $curso): JsonResponse
    {
        $this->authorize('manageTeam', $curso);

        $curso->loadMissing('asignacionPlan');
        $destinos = $this->copia->cursosHermanos($curso);

        return response()->json([
            'destinos' => $this->copia->compatibilidad($this->actividadesDe($curso), $destinos),
        ]);
    }

    /**
     * POST docente/cursos/{curso}/actividades/copiar
     */
    public function copiar(Request $request, Curso $curso): RedirectResponse
    {
        $this->authorize('manageTeam', $curso);

        $validated = $request->validate([
            'actividades'      => 'required|array|min:1',
            'actividades.*'    => 'integer',
            'destinos'         => 'required|array|min:1',
            'destinos.*'       => 'integer',
            'copiar_rubrica'   => 'boolean',
            'copiar_enunciado' => 'boolean',
            'fechas'           => 'array',
            'fechas.*'         => 'nullable|date_format:Y-m-d',
        ]);

        $curso->loadMissing('asignacionPlan');

        $actividades = $this->actividadesDe($curso)->whereIn('id_actividad', $validated['actividades'])->values();
        if ($actividades->count() !== count(array_unique($validated['actividades']))) {
            throw ValidationException::withMessages(['actividades' => 'Alguna actividad no pertenece a este curso.']);
        }

        // Sólo cursos hermanos: así también se garantiza que el docente es
        // titular del destino (mismo id_docente_titular que el origen).
        $hermanos = $this->copia->cursosHermanos($curso)->keyBy('id_curso');
        $destinos = collect($validated['destinos'])->unique()->map(fn ($id) => $hermanos->get((int) $id));
        if ($destinos->contains(null)) {
            throw ValidationException::withMessages(['destinos' => 'Sólo se puede copiar a otros grupos de la misma asignatura y período donde seas titular.']);
        }
        foreach ($destinos as $destino) {
            $this->authorize('manageTeam', $destino);
        }

        $resultado = $this->copia->copiar(
            $actividades,
            $destinos->values(),
            (bool) ($validated['copiar_rubrica'] ?? true),
            (bool) ($validated['copiar_enunciado'] ?? true),
            array_filter($validated['fechas'] ?? []),
        );

        $creadas = count($resultado['creadas']);
        $mensaje = $creadas === 1 ? 'Se copió 1 actividad.' : "Se copiaron {$creadas} actividades.";
        if ($creadas > 0) {
            $mensaje .= ' Quedaron ocultas: revísalas y publícalas en cada curso.';
        }

        return back()
            ->with($creadas > 0 ? 'success' : 'error', $creadas > 0 ? $mensaje : 'No se copió ninguna actividad.')
            // Lo que no se pudo copiar y por qué (página: $page.props.flash.data).
            ->with('flash_data', ['copia_omitidas' => $resultado['omitidas']]);
    }

    /** Actividades del curso con lo que la copia necesita para ubicarlas. */
    private function actividadesDe(Curso $curso): Collection
    {
        return Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
            ->with(['componente.tipoComponente', 'unidad'])
            ->orderBy('id_actividad')
            ->get();
    }
}
