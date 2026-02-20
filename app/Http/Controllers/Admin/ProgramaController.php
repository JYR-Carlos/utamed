<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\EstructuraPrograma;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin wrapper around Programa generation.
 * Returns JSON (for axios from SyllabusModal) instead of Inertia redirects.
 */
class ProgramaController extends Controller
{
    /**
     * Return the active programa (with sections + content) for a given Curso.
     * Queries EstructuraPrograma directly to avoid Compoships composite-key issues.
     */
    public function show(Curso $curso)
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return response()->json(['programa' => null]);
        }

        // Load sections + content directly — avoids Compoships composite key mismatch
        $secciones = EstructuraPrograma::where('id_programa', $programa->id_programa)
            ->where('id_curso', $programa->id_curso)
            ->where('es_actual', true)
            ->orderBy('orden')
            ->with(['contenidos_programa' => fn($q) => $q->orderBy('orden_item')])
            ->get();

        return response()->json([
            'programa' => array_merge($programa->toArray(), [
                'secciones' => $secciones->toArray(),
            ]),
        ]);
    }

    /**
     * Generate (or regenerate) the active Programa for a given Curso.
     * Accepts an optional `secciones` array; if omitted, uses default structure.
     */
    public function store(Request $request, Curso $curso)
    {
        $user = Auth::user();

        // Build secciones payload (defaults if not provided)
        if (!$request->has('secciones')) {
            $curso->load('asignatura');
            $secciones = [
                ['nombre_seccion' => 'Descripción de la Asignatura', 'numeral_romano' => 'I', 'orden' => 1, 'contenidos' => [['texto_contenido' => $curso->asignatura->descripcion ?? '', 'orden_item' => 1]]],
                ['nombre_seccion' => 'Competencias', 'numeral_romano' => 'II', 'orden' => 2, 'contenidos' => []],
                ['nombre_seccion' => 'Resultados de Aprendizaje', 'numeral_romano' => 'III', 'orden' => 3, 'contenidos' => []],
                ['nombre_seccion' => 'Contenidos', 'numeral_romano' => 'IV', 'orden' => 4, 'contenidos' => []],
                ['nombre_seccion' => 'Metodología', 'numeral_romano' => 'V', 'orden' => 5, 'contenidos' => []],
                ['nombre_seccion' => 'Evaluación', 'numeral_romano' => 'VI', 'orden' => 6, 'contenidos' => []],
            ];
        } else {
            $request->validate([
                'secciones' => 'required|array',
                'secciones.*.nombre_seccion' => 'required|string',
                'secciones.*.orden' => 'required|integer',
                'secciones.*.contenidos' => 'nullable|array',
                'secciones.*.contenidos.*.texto_contenido' => 'nullable|string',
                'secciones.*.contenidos.*.orden_item' => 'required|integer',
            ]);
            $secciones = $request->secciones;
        }

        try {
            $programa = DB::transaction(function () use ($secciones, $curso, $user) {
                // Deactivate any existing active programa
                $existing = Programa::where('id_curso', $curso->id_curso)
                    ->where('es_plantilla', $curso->es_plantilla)
                    ->where('es_actual', true)
                    ->first();

                $newVersion = $existing ? $existing->version_programa + 1 : 1;
                if ($existing) {
                    $existing->update(['es_actual' => false]);
                }

                // Create program header — always starts as plantilla (draft)
                // The admin must explicitly approve it to make it definitivo.
                $programa = Programa::create([
                    'id_curso' => $curso->id_curso,
                    'es_plantilla' => $curso->es_plantilla, // Maintain the FK structure invariant
                    'estado' => 'BORRADOR', // Track the workflow status
                    'es_actual' => true,
                    'version_programa' => $newVersion,
                    'unc_programa' => 1,
                    'fecha_creacion' => now(),
                    'creado_por' => $user->id_usuario,
                ]);

                // Create sections using EstructuraPrograma directly
                foreach ($secciones as $seccionData) {
                    $seccion = EstructuraPrograma::create([
                        'nombre_seccion' => $seccionData['nombre_seccion'],
                        'numeral_romano' => $seccionData['numeral_romano'] ?? null,
                        'es_lista' => $seccionData['es_lista'] ?? false,
                        'orden' => $seccionData['orden'],
                        'id_programa' => $programa->id_programa,
                        'es_actual' => true,
                        'id_curso' => $programa->id_curso,
                        'es_plantilla' => $programa->es_plantilla,
                    ]);

                    if (!empty($seccionData['contenidos'])) {
                        foreach ($seccionData['contenidos'] as $item) {
                            $seccion->contenidos_programa()->create([
                                'texto_contenido' => $item['texto_contenido'] ?? null,
                                'valor_numerico' => $item['valor_numerico'] ?? null,
                                'orden_item' => $item['orden_item'],
                            ]);
                        }
                    }
                }

                return $programa;
            });

            return response()->json([
                'message' => 'Programa generado correctamente.',
                'programa' => [
                    'id_programa' => $programa->id_programa,
                    'id_curso' => $programa->id_curso,
                    'estado' => $programa->estado,
                    'es_actual' => true,
                    'version_programa' => $programa->version_programa,
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
     * Approve (mark as definitivo) the current active programa for a Curso.
     * Flips es_plantilla from true → false on both Programa and Estructura_Programa rows.
     * Uses raw DB updates to avoid Compoships composite-key conflicts.
     */
    public function approve(Curso $curso)
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return response()->json(['error' => 'No hay programa activo para este curso.'], 404);
        }

        if ($programa->estado === 'APROBADO') {
            return response()->json(['message' => 'El programa ya está aprobado.', 'already_approved' => true]);
        }

        try {
            DB::transaction(function () use ($programa) {
                // Flip Programa
                DB::table('Programa')
                    ->where('id_programa', $programa->id_programa)
                    ->update(['estado' => 'APROBADO']);
            });

            return response()->json([
                'message' => 'Programa aprobado correctamente.',
                'id_programa' => $programa->id_programa,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al aprobar: ' . $e->getMessage()], 500);
        }
    }
}