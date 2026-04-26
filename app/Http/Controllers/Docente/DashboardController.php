<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Curso\Curso;
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::user();

        // Verificar que el usuario es docente
        if (!$user->docente) {
            return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        // Obtener los cursos a través de las secciones del docente
        $docente = $user->docente;

        $asignacionJefatura = UsuarioRolAsignacion::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->where('nombre', 'Jefe de Carrera'))
            ->whereHas('contexto.tipoContexto', fn($q) => $q->where('categoria', 'carrera'))
            ->latest('id_ura')
            ->first();

        $carreraJefatura = $asignacionJefatura
            ? Carrera::query()
                ->select('id_carrera', 'nombre', 'id_contexto')
                ->where('id_contexto', $asignacionJefatura->id_contexto)
                ->first()
            : null;

        // Obtener cursos donde el docente es titular
        $cursos = Curso::where('id_docente_titular', $docente->id_docente)
            ->whereNull('fecha_eliminacion')
            ->get()
            ->map(function ($curso) {
                $tienePrograma = \App\Models\Curso\Programa::where('id_curso', $curso->id_curso)
                    ->where('es_actual', true)
                    ->exists();

                return [
                    'id_curso' => $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                    'tiene_programa' => $tienePrograma,
                ];
            })
            ->values();

        return Inertia::render('docente/Dashboard', [
            'docente' => [
                'id_docente' => $docente->id_docente,
                'grado' => $docente->grado,
                'titulo' => $docente->titulo,
                'cargo' => $docente->cargo,
                'id_usuario' => $user->id_usuario,
            ],
            'stats' => [
                'total_cursos' => $cursos->count(),
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
            ],
            'cursos' => $cursos,
            'jefatura' => [
                'has_access' => (bool) $asignacionJefatura,
                'id_contexto' => $asignacionJefatura?->id_contexto,
                'carrera' => $carreraJefatura ? [
                    'id_carrera' => $carreraJefatura->id_carrera,
                    'nombre' => $carreraJefatura->nombre,
                ] : null,
            ],
        ]);
    }
}
