<?php

namespace App\Policies;

use App\Policies\Base\BaseInscripcionCursoPolicy;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Usuario;

/**
 * Policy para autorización de operaciones sobre el modelo InscripcionCurso.
 * 
 * Extiende BaseInscripcionCursoPolicy (autogenerada) con lógica personalizada.
 * 
 * Implementa control de acceso basado en roles (RBAC) para gestión de inscripciones de estudiantes en cursos.
 * 
 * Reglas de autorización:
 * - Administradores: Acceso completo a todas las inscripciones
 * - Docentes: Pueden inscribir/gestionar estudiantes en sus cursos (donde dictan secciones)
 * - Estudiantes: No pueden gestionar inscripciones (solo ver las suyas propias)
 * - Otros usuarios: Sin acceso
 */
class InscripcionCursoPolicy extends BaseInscripcionCursoPolicy
{
    /**
     * Determina si el usuario puede ver todas las inscripciones.
     *
     * @param Usuario $user
     * @return bool
     */
    public function viewAny(Usuario $user): bool
    {
        // Admins siempre pueden ver
        if ($user->is_admin) {
            return true;
        }

        // Docentes pueden ver inscripciones de sus cursos
        if ($user->docente) {
            return true;
        }

        return false;
    }

    /**
     * Determina si el usuario puede ver una inscripción específica.
     *
     * @param Usuario $user
     * @param InscripcionCurso $inscripcion
     * @return bool
     */
    public function view(Usuario $user, InscripcionCurso $inscripcion): bool
    {
        // Admin puede ver todas
        if ($user->is_admin) {
            return true;
        }

        // Docente puede ver si dicta una sección en el curso
        if ($user->docente) {
            $dictaSecciones = $user->docente->seccionesQueDicta()
                ->where('id_curso', $inscripcion->id_curso)
                ->exists();

            if ($dictaSecciones) {
                return true;
            }
        }

        // El estudiante puede ver su propia inscripción
        if ($user->estudiante && $inscripcion->id_estudiante === $user->estudiante->id_estudiante) {
            return true;
        }

        return false;
    }

    /**
     * Determina si el usuario puede crear una inscripción.
     *
     * @param Usuario $user
     * @param mixed $parent
     * @return bool
     */
    public function create(Usuario $user, $parent = null): bool
    {
        // Admins siempre pueden crear
        if ($user->is_admin) {
            return true;
        }

        // Docentes pueden crear inscripciones
        if ($user->docente) {
            return true;
        }

        return false;
    }

    /**
     * Determina si el usuario puede crear inscripciones en un curso específico.
     * 
     * @param Usuario $user
     * @param int $idCurso
     * @return bool
     */
    public function createForCourse(Usuario $user, int $idCurso): bool
    {
        // Admin puede crear para cualquier curso
        if ($user->is_admin) {
            return true;
        }

        // Docente solo puede crear si dicta una sección en el curso
        if ($user->docente) {
            $dictaSecciones = $user->docente->seccionesQueDicta()
                ->where('id_curso', $idCurso)
                ->exists();

            return $dictaSecciones;
        }

        return false;
    }

    /**
     * Determina si el usuario puede actualizar una inscripción.
     *
     * @param Usuario $user
     * @param InscripcionCurso $inscripcion
     * @return bool
     */
    public function update(Usuario $user, InscripcionCurso $inscripcion): bool
    {
        // Admin puede actualizar todas
        if ($user->is_admin) {
            return true;
        }

        // Docente puede actualizar si dicta una sección en el curso
        if ($user->docente) {
            $dictaSecciones = $user->docente->seccionesQueDicta()
                ->where('id_curso', $inscripcion->id_curso)
                ->exists();

            return $dictaSecciones;
        }

        return false;
    }

    /**
     * Determina si el usuario puede eliminar una inscripción.
     *
     * @param Usuario $user
     * @param InscripcionCurso $inscripcion
     * @return bool
     */
    public function delete(Usuario $user, InscripcionCurso $inscripcion): bool
    {
        // Solo admins pueden eliminar inscripciones
        return $user->is_admin;
    }
}
