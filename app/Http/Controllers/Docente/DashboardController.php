<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use Inertia\Inertia;

/**
 * Controlador para el dashboard del docente.
 * 
 * Tablas implicadas:
 * - usuario.usuario: Usuario autenticado (docente)
 * - usuario.docente: Perfil docente del usuario
 * - curso.seccion: Secciones asignadas al docente
 * - curso.curso: Cursos asociados a las secciones
 * - administrativo.asignatura: Asignaturas de los cursos
 * - administrativo.plan: Planes/carreras asociadas
 * 
 * Proporciona vista resumen de todos los cursos del docente con información
 * de asignatura, carrera y fechas.
 */
class DashboardController extends Controller
{
    /**
     * Muestra el dashboard del docente con su lista de cursos.
     * 
     * Obtiene todos los cursos del docente autenticado a través de sus secciones,
     * resuelve información relacionada (asignatura, plan, carrera),
     * y renderiza vista con resumen de cursos y actividades.
     * 
     * @return \Illuminate\Http\RedirectResponse|\Inertia\Response  Redirección si no es docente, o vista dashboard
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

        // Contar cursos activos
        $totalCursos = Curso::whereIn('id_curso', $cursoIds)
            ->whereNull('fecha_eliminacion')
            ->count();

        return Inertia::render('docente/Dashboard', [
            'docente' => [
                'id_docente' => $docente->id_docente,
                'grado' => $docente->grado,
                'titulo' => $docente->titulo,
                'cargo' => $docente->cargo,
                'id_usuario' => $user->id_usuario,
            ],
            'stats' => [
                'total_cursos' => $totalCursos,
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
            ]
        ]);
    }
}
