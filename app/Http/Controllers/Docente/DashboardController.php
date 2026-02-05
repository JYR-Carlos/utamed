<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the docente dashboard.
     */
    public function index()
    {
        /** @var Usuario $user */
        $user = auth()->user();
        
        // Verificar que el usuario es docente
        if (!$user->docente) {
            return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        // Obtener los cursos a través de las secciones del docente
        $docente = $user->docente;
        
        // Obtener secciones del docente
        $secciones = Seccion::where('id_docente', $docente->id_docente)
            ->get();

        // Obtener los ids de cursos únicos
        $cursoIds = $secciones->pluck('id_curso')->unique();

        // Consultar cursos con información relacionada
        $cursosData = Curso::whereIn('id_curso', $cursoIds)
            ->whereNull('fecha_eliminacion')
            ->with(['asignatura', 'plan.carrera'])
            ->orderBy('fecha_inicio', 'desc')
            ->get()
            ->map(function ($curso) {
                return [
                    'id_curso' => $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                    'asignatura_nombre' => $curso->asignatura?->nombre ?? 'N/A',
                    'carrera_nombre' => $curso->plan?->carrera?->nombre ?? 'N/A',
                    'fecha_inicio' => $curso->fecha_inicio,
                    'fecha_fin' => $curso->fecha_fin,
                ];
            });

        return Inertia::render('docente/Dashboard', [
            'docente' => [
                'id_docente' => $docente->id_docente,
                'grado' => $docente->grado,
                'titulo' => $docente->titulo,
                'cargo' => $docente->cargo,
                'id_usuario' => $user->id_usuario,
            ],
            'cursos' => $cursosData,
            'stats' => [
                'total_cursos' => $cursosData->count(),
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
            ]
        ]);
    }
}
