<?php

namespace App\Services;

use App\Models\Curso\Programa;
use App\Models\Curso\Curso;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Support\Collection;

/**
 * Servicio para obtener los cursos de un usuario según su rol.
 *
 * Centraliza las queries del sidebar (docente_courses, estudiante_courses,
 * ayudante_courses) que antes vivían inline en HandleInertiaRequests.
 *
 * **Todo se resuelve en lote.** Estos métodos corren en el `share()` global, o
 * sea en cada navegación: antes cada curso disparaba dos consultas propias —una
 * para `tiene_programa` y otra para sus permisos—, así que un docente con ocho
 * cursos pagaba dieciséis consultas sólo para pintar el sidebar.
 */
class UserCoursesService
{
    /**
     * Mapea permisos de un usuario para un contexto específico al formato frontend.
     *
     * Hace una consulta por llamada: dentro de un bucle usar
     * {@see permisosPorContexto()}.
     *
     * @param  Usuario  $user  Usuario autenticado
     * @param  int  $contextId  ID del contexto (id_contexto del curso/componente)
     * @return array<
     *  int,
     *  array{
     *   id_permiso: int,
     *   slug: string,
     *   esta_permitido: bool
     *  }
     * >
     */
    public function getPermissionsForContext(Usuario $user, int $contextId): array
    {
        return $this->formatearPermisos(collect($user->getAllPermissions($contextId)));
    }

    /**
     * Cursos asignados a un docente (a través de sus secciones).
     *
     * @return array<
     *  int,
     *  array{
     *   id_curso: int,
     *   nombre: string,
     *   cod_curso: string,
     *   carrera_nombre: string,
     *   agno_real: int,
     *   semestre_real: int,
     *   tiene_programa: bool,
     *   permisos: array
     *  }
     * >
     */
    public function getDocenteCourses(Docente $docente): array
    {
        $cursos = Curso::where('id_docente_titular', $docente->id_docente)
            ->select('id_curso', 'nombre', 'cod_curso', 'id_contexto', 'agno_real', 'semestre_real')
            ->with(['asignacionPlan.plan.carrera'])
            ->get();

        $conPrograma = $this->cursosConPrograma($cursos);
        $permisos = $this->permisosPorContexto($docente->usuario);

        return $cursos
            ->map(fn($curso) => [
                'id_curso'       => $curso->id_curso,
                'nombre'         => $curso->nombre,
                'cod_curso'      => $curso->cod_curso,
                'agno_real'      => $curso->agno_real,
                'semestre_real'  => $curso->semestre_real,
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'tiene_programa' => $conPrograma->contains($curso->id_curso),
                'permisos'       => $this->formatearPermisos($permisos->get($curso->id_contexto)),
            ])
            ->values()
            ->all();
    }

    /**
     * Cursos en los que un estudiante está inscrito.
     *
     * @return array<
     *  int,
     *  array{
     *   id_curso: int,
     *   nombre: string,
     *   cod_curso: string,
     *   agno_real: int,
     *   semestre_real: int,
     *   tiene_programa: bool,
     *   permisos: array
     *  }
     * >
     */
    public function getEstudianteCourses(Estudiante $estudiante): array
    {
        // Se consulta Curso directamente en vez de traer las inscripciones y
        // sacar el curso de cada una: así se puede acotar con select() y no
        // viajan modelos completos.
        $cursos = Curso::whereHas(
            'componentes.inscripcionComponentes',
            fn($q) => $q->where('id_estudiante', $estudiante->id_estudiante)
        )
            ->select('id_curso', 'nombre', 'cod_curso', 'id_contexto', 'agno_real', 'semestre_real')
            ->get();

        $conPrograma = $this->cursosConPrograma($cursos);
        $permisos = $this->permisosPorContexto($estudiante->usuario);

        return $cursos
            ->map(fn($curso) => [
                'id_curso'       => $curso->id_curso,
                'nombre'         => $curso->nombre,
                'cod_curso'      => $curso->cod_curso,
                'agno_real'      => $curso->agno_real,
                'semestre_real'  => $curso->semestre_real,
                'tiene_programa' => $conPrograma->contains($curso->id_curso),
                'permisos'       => $this->formatearPermisos($permisos->get($curso->id_contexto)),
            ])
            ->values()
            ->all();
    }

    /**
     * Cursos donde el usuario actúa como ayudante, con sus permisos por contexto.
     *
     * @param  Collection|null  $allPermsGrouped  Resultado de $user->getAllPermissionsGroupedByContext();
     *                                            si es null se resuelve aquí.
     * @return array<
     *  int,
     *  array{
     *   id_curso: int,
     *   nombre: string,
     *   cod_curso: string,
     *   agno_real: int,
     *   semestre_real: int,
     *   tiene_programa: bool,
     *   permisos: array
     *  }
     * >
     */
    public function getAyudanteCourses(Usuario $user, ?Collection $allPermsGrouped = null): array
    {
        $contextoIds = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->whereRaw('LOWER(nombre) = ?', ['ayudante']))
            ->pluck('id_contexto');

        $cursos = Curso::whereIn('id_contexto', $contextoIds)
            ->select('id_curso', 'nombre', 'cod_curso', 'id_contexto', 'agno_real', 'semestre_real')
            ->get()
            ->unique('id_curso');

        $conPrograma = $this->cursosConPrograma($cursos);
        $permisos = $allPermsGrouped ?? $this->permisosPorContexto($user);

        return $cursos
            ->map(fn($curso) => [
                'id_curso'       => $curso->id_curso,
                'nombre'         => $curso->nombre,
                'cod_curso'      => $curso->cod_curso,
                'agno_real'      => $curso->agno_real,
                'semestre_real'  => $curso->semestre_real,
                'tiene_programa' => $conPrograma->contains($curso->id_curso),
                'permisos'       => $this->formatearPermisos($permisos->get($curso->id_contexto)),
            ])
            ->values()
            ->all();
    }

    /**
     * IDs de los cursos que tienen programa, en una sola consulta.
     *
     * @param  Collection<int, Curso>  $cursos
     * @return Collection<int, int>
     */
    private function cursosConPrograma(Collection $cursos): Collection
    {
        $ids = $cursos->pluck('id_curso')->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Programa::whereIn('id_curso', $ids)
            ->distinct()
            ->pluck('id_curso');
    }

    /**
     * Permisos efectivos del usuario agrupados por contexto, en una sola consulta.
     */
    private function permisosPorContexto(?Usuario $user): Collection
    {
        return $user?->getAllPermissionsGroupedByContext() ?? collect();
    }

    /**
     * Da a los permisos de un contexto la forma que espera el frontend.
     *
     * @param  Collection|array|null  $permisos
     * @return array<int, array{id_permiso: int, slug: string, esta_permitido: bool}>
     */
    private function formatearPermisos($permisos): array
    {
        return collect($permisos ?? [])
            ->map(fn($perm) => [
                'id_permiso'     => $perm['id_permiso'],
                'slug'           => $perm['slug'],
                'esta_permitido' => (bool) $perm['esta_permitido'],
            ])
            ->values()
            ->all();
    }
}
