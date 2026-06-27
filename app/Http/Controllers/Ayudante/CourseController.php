<?php

namespace App\Http\Controllers\Ayudante;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cursos en los que el usuario actúa como ayudante.
 *
 * El vínculo ayudante↔curso se resuelve por `usuario.asignacion_rol` (rol
 * "ayudante" activo sobre el contexto del curso), no por inscripción. Cada vista
 * incluye los permisos efectivos del usuario en el contexto del curso.
 */
class CourseController extends Controller
{
    /**
     * Lista los cursos donde el usuario es ayudante.
     *
     * GET ayudante/cursos
     */
    public function index(): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Obtener cursos donde es Ayudante — consulta directa a UsuarioRolAsignacion
        // porque rolesAsignados() no expone id_contexto en el pivot
        $rolAyudante = Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();

        $contextosAsignados = $rolAyudante
            ? UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
                ->where('id_rol', $rolAyudante->id_rol)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->pluck('id_contexto')
            : collect();

        $cursosInscritos = Curso::whereIn('id_contexto', $contextosAsignados)
            ->with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera'])
            ->get();

        $cursosData = $cursosInscritos->map(function ($curso) use ($user) {
            // Obtener permisos del usuario para este contexto
            $userPermissions = $user->getAllPermissions($curso->id_contexto);
            $userPermissions = collect($userPermissions)->map(function ($perm) {
                return [
                    'id_permiso' => $perm['id_permiso'],
                    'slug' => $perm['slug'],
                    'esta_permitido' => (bool)$perm['esta_permitido'],
                    'puede_delegar' => (bool)($perm['puede_delegar'] ?? false),
                ];
            })->values()->toArray();

            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
                'semestre_real' => $curso->semestre_real ?? 1,
                'agno_real' => $curso->agno_real ?? now()->year,
                'letra_grupo' => $curso->letra_grupo,
                'total_estudiantes' => 0,
                'userPermissions' => $userPermissions,
            ];
        });

        return Inertia::render('ayudante/Courses/Index', [
            'cursos' => $cursosData,
        ]);
    }

    /**
     * Muestra el detalle de un curso donde el usuario es ayudante.
     *
     * GET ayudante/cursos/{id}
     */
    public function show(int $id): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Obtener el curso
        $curso = Curso::findOrFail($id);

        // Verificar que el usuario es ayudante en este curso
        $esAyudante = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->whereRaw('LOWER(nombre) = ?', ['ayudante']))
            ->exists();

        if (!$esAyudante) {
            abort(403, 'No tienes permiso para ver este curso');
        }

        $cursoData = [
            'id_curso' => $curso->id_curso,
            'nombre' => $curso->nombre,
            'cod_curso' => $curso->cod_curso,
            'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
            'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
            'fecha_inicio' => $curso->fecha_inicio,
            'fecha_fin' => $curso->fecha_fin,
        ];

        $tienePrograma = Programa::where('id_curso', $id)->exists();

        // Obtener permisos especiales del usuario en el contexto del curso
        $userPermissions = $user->getAllPermissions($curso->id_contexto);
        $userPermissions = collect($userPermissions)->map(function ($perm) {
            return [
                'id_permiso' => $perm['id_permiso'],
                'slug' => $perm['slug'],
                'esta_permitido' => (bool)$perm['esta_permitido'],
                'puede_delegar' => (bool)($perm['puede_delegar'] ?? false),
            ];
        })->values()->toArray();

        return Inertia::render('ayudante/Courses/Show', [
            'id_curso' => $id,
            'curso' => $cursoData,
            'tiene_programa' => $tienePrograma,
            'userPermissions' => $userPermissions,
        ]);
    }
}
