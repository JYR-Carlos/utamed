<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProgramaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Curso $curso)
    {
        $user = Auth::user();

        // If 'secciones' is missing, we initialize with defaults for initial generation
        if (!$request->has('secciones')) {
            $curso->load('asignatura');
            $secciones = [
                ['nombre_seccion' => "Descripción de la Asignatura", 'numeral_romano' => "I", 'orden' => 1, 'contenidos' => [['texto_contenido' => $curso->asignatura->descripcion ?? '', 'orden_item' => 1]]],
                ['nombre_seccion' => "Competencias", 'numeral_romano' => "II", 'orden' => 2, 'contenidos' => []],
                ['nombre_seccion' => "Resultados de Aprendizaje", 'numeral_romano' => "III", 'orden' => 3, 'contenidos' => []],
                ['nombre_seccion' => "Contenidos", 'numeral_romano' => "IV", 'orden' => 4, 'contenidos' => []],
                ['nombre_seccion' => "Metodología", 'numeral_romano' => "V", 'orden' => 5, 'contenidos' => []],
                ['nombre_seccion' => "Evaluación", 'numeral_romano' => "VI", 'orden' => 6, 'contenidos' => []],
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
            return \Illuminate\Support\Facades\DB::transaction(function () use ($secciones, $curso, $user) {
                // Check if program already exists and is active
                $existing = Programa::where('id_curso', $curso->id_curso)
                    ->where('es_plantilla', $curso->es_plantilla)
                    ->where('es_actual', true)
                    ->first();

                if ($existing) {
                    $existing->update(['es_actual' => false]);
                    $newVersion = $existing->version_programa + 1;
                } else {
                    $newVersion = 1;
                }

                // Create the program header
                $programa = Programa::create([
                    'id_curso' => $curso->id_curso,
                    'es_plantilla' => $curso->es_plantilla,
                    'es_actual' => true,
                    'version_programa' => $newVersion,
                    'unc_programa' => 1,
                    'fecha_creacion' => now(),
                    'creado_por' => $user->id_usuario
                ]);

                // Create Sections and Contents
                foreach ($secciones as $seccionData) {
                    $seccion = $programa->secciones()->create([
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
                        foreach ($seccionData['contenidos'] as $contenidoData) {
                            $seccion->contenidos_programa()->create([
                                'texto_contenido' => $contenidoData['texto_contenido'],
                                'valor_numerico' => $contenidoData['valor_numerico'] ?? null,
                                'orden_item' => $contenidoData['orden_item'],
                            ]);
                        }
                    }
                }

                return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
                    ->with('success', 'Programa generado correctamente.');
            });

        } catch (\Exception $e) {
            return Redirect::back()->with('error', 'Error al generar el programa: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Curso $curso)
    {
        $curso->load(['asignatura']);

        // Fetch existing program for this course
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->where('es_actual', true)
            ->with([
                'secciones' => function ($query) {
                    $query->orderBy('orden')->with([
                        'contenidos_programa' => function ($q) {
                            $q->orderBy('orden_item');
                        }
                    ]);
                }
            ])
            ->first();

        // If no program exists, we might want to return a template structure or just null
        // The frontend can handle "Generar" vs "Ver" state

        // Fetch units for this course (might be useful context)
        $unidades = \App\Models\Curso\Unidad::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->orderBy('num_unidad')
            ->get();

        return \Inertia\Inertia::render('docente/Programa', [
            'curso' => $curso,
            'programa' => $programa,
            'asignatura' => $curso->asignatura,
            'unidades' => $unidades
        ]);
    }

    /**
     * Elimina el programa actual de un curso.
     * 
     * @param \App\Models\Curso\Curso $curso
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Curso $curso)
    {
        // Autorizar acceso
        $this->authorizeAccess($curso);

        // Buscar programa actual
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return redirect()->back()->with('error', 'No hay programa para eliminar');
        }

        // Soft delete
        $programa->delete();

        return redirect()->route('docente.cursos.index')
            ->with('success', 'Programa eliminado correctamente');
    }
}
