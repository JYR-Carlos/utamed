<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Services\ProgramaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin Programa Controller
 * 
 * Retorna JSON responses para AJAX/Axios
 * Guarda estructura JSONB en data_syllabus
 */
class ProgramaController extends Controller
{
    /**
     * Retorna el programa activo con estructura JSONB
     */
    public function show(Curso $curso)
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return response()->json(['programa' => null]);
        }

        return response()->json([
            'programa' => [
                'id_programa' => $programa->id_programa,
                'version_programa' => $programa->version_programa,
                'estado' => $programa->estado,
                'data_syllabus' => $programa->data_syllabus,
                'fecha_creacion' => $programa->fecha_creacion,
            ]
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

        try {
            ProgramaService::changeStatus($programa, 'APROBADO');

            return response()->json([
                'message' => 'Programa aprobado correctamente.',
                'programa' => [
                    'id_programa' => $programa->id_programa,
                    'estado' => $programa->estado,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}