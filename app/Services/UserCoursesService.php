<?php

namespace App\Services;

use App\Models\Curso\Programa;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;

/**
 * Servicio para obtener los cursos de un usuario según su rol.
 *
 * Centraliza las queries del sidebar (docente_courses, estudiante_courses,
 * ayudante_courses) que antes vivían inline en HandleInertiaRequests.
 *
 * Ventajas:
 * - Middleware más limpio y legible
 * - Lógica testeable de forma independiente (se puede mockear en unit tests)
 * - Un solo lugar para modificar la lógica de cada rol
 */
class UserCoursesService
{
    /**
     * Mapea permisos de un usuario para un contexto específico al formato frontend.
     *
     * @param  Usuario  $user  Usuario autenticado
     * @param  int  $contextId  ID del contexto (id_contexto del curso/componente)
     * @return array<int, array{id_permiso: int, slug: string, esta_permitido: bool}>
     */
    public function getPermissionsForContext(Usuario $user, int $contextId): array
    {
        return collect($user->getAllPermissions($contextId))
            ->map(fn($perm) => [
                'id_permiso'    => $perm['id_permiso'],
                'slug'          => $perm['slug'],
                'esta_permitido' => (bool) $perm['esta_permitido'],
            ])
            ->values()
            ->all();
    }

    /**
     * Cursos asignados a un docente (a través de sus secciones).
     *
     * @return array<int, array{id_curso: int, nombre: string, cod_curso: string, carrera_nombre: string, tiene_programa: bool}>
     */
    public function getDocenteCourses(Docente $docente): array
    {
        return Curso::where('id_docente_titular', $docente->id_docente)
            ->select('id_curso', 'nombre', 'cod_curso')
            ->with(['asignacionPlan.plan.carrera'])
            ->get()
            ->map(fn($curso) => [
                'id_curso'       => $curso->id_curso,
                'nombre'         => $curso->nombre,
                'cod_curso'      => $curso->cod_curso,
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'tiene_programa' => Programa::where('id_curso', $curso->id_curso)->exists(),
            ])
            ->values()
            ->all();
    }

    /**
     * Cursos en los que un estudiante está inscrito.
     *
     * @return array<int, array{id_curso: int, nombre: string, cod_curso: string}>
     */
    public function getEstudianteCourses(Estudiante $estudiante): array
    {
        return InscripcionComponente::where('id_estudiante', $estudiante->id_estudiante)
            ->with('componente.curso')
            ->get()
            ->pluck('componente.curso')
            ->filter()
            ->unique('id_curso')
            ->values()
            ->map(fn($c) => [
                'id_curso' => $c->id_curso,
                'nombre'   => $c->nombre,
                'cod_curso' => $c->cod_curso,
            ])
            ->all();
    }

    /**
     * Cursos donde el usuario actúa como ayudante, con sus permisos por contexto.
     *
     * Usa permisos pre-agrupados (getAllPermissionsGroupedByContext) para evitar N+1.
     *
     * @param  \Illuminate\Support\Collection|null  $allPermsGrouped  Resultado de $user->getAllPermissionsGroupedByContext()
     * @return array<int, array{id_curso: int, nombre: string, cod_curso: string, tiene_programa: bool, userPermissions: array}>
     */
    public function getAyudanteCourses(Usuario $user, ?\Illuminate\Support\Collection $allPermsGrouped): array
    {
        $contextoIds = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->whereRaw('LOWER(nombre) = ?', ['ayudante']))
            ->pluck('id_contexto');

        return Curso::whereIn('id_contexto', $contextoIds)
            ->select('id_curso', 'nombre', 'cod_curso', 'id_contexto')
            ->get()
            ->map(function ($c) use ($allPermsGrouped) {
                $userPermissions = ($allPermsGrouped?->get($c->id_contexto) ?? collect([]))
                    ->map(fn($perm) => [
                        'id_permiso'    => $perm['id_permiso'],
                        'slug'          => $perm['slug'],
                        'esta_permitido' => (bool) $perm['esta_permitido'],
                    ])
                    ->values()
                    ->all();

                return [
                    'id_curso'       => $c->id_curso,
                    'nombre'         => $c->nombre,
                    'cod_curso'      => $c->cod_curso,
                    'tiene_programa' => Programa::where('id_curso', $c->id_curso)->exists(),
                    'userPermissions' => $userPermissions,
                ];
            })
            ->unique('id_curso')
            ->values()
            ->all();
    }
}
