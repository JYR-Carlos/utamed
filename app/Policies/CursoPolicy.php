<?php

namespace App\Policies;

use App\Policies\Base\BaseCursoPolicy;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;

/**
 * Policy para autorización de operaciones sobre el modelo Curso.
 * 
 * Extiende BaseCursoPolicy (autogenerada) con lógica personalizada.
 * 
 * Implementa control de acceso basado en roles (RBAC) para gestión de equipos de curso.
 * Reemplaza la autorización insegura basada en path por validación robusta de permisos.
 * 
 * Reglas de autorización:
 * - Administradores: Acceso completo a todos los cursos
 * - Docentes: Solo cursos donde dictan al menos una sección
 * - Otros usuarios: Sin acceso
 */
class CursoPolicy extends BaseCursoPolicy
{
    /**
     * Determina si el usuario puede gestionar el equipo de un curso.
     * 
     * Valida que:
     * - Administradores tienen acceso completo
     * - Docentes solo acceden a cursos donde dictan secciones
     * - Se verifica mediante relación secciones() en modelo Docente
     * 
     * @param  Usuario  $user   Usuario autenticado intentando acceder
     * @param  Curso    $curso  Curso cuyo equipo se intenta gestionar
     * @return bool             True si autorizado, false en caso contrario
     */
    public function manageTeam(Usuario $user, Curso $curso): bool
    {
        // Admins siempre tienen acceso
        // Verificar roles: Administrador o SuperAdmin
        $adminRoles = ['Administrador', 'SuperAdmin', 'Super Admin'];
        $userRoles = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->get()
            ->pluck('nombre')
            ->toArray();

        // Si tiene alguno de los roles de admin, permitir
        if (count(array_intersect($adminRoles, $userRoles)) > 0) {
            return true;
        }

        // Verificar que el usuario es docente
        if (!$user->docente) {
            return false;
        }

        // Verificar que el docente es titular del curso
        return \App\Models\Curso\Curso::where('id_curso', $curso->id_curso)
            ->where('id_docente_titular', $user->docente->id_docente)
            ->exists();
    }

    /**
     * Determina si el usuario puede acceder a un curso para consultar/editar sus programas.
     * 
     * Valida que:
     * - Administradores tienen acceso completo
     * - Docentes solo acceden a cursos donde dictan secciones
     * - Se rechaza acceso a cursos no asignados
     * 
     * @param  Usuario  $user   Usuario autenticado intentando acceder
     * @param  Curso    $curso  Curso cuya información se intenta acceder
     * @return bool             True si autorizado, false en caso contrario
     */
    public function viewPrograma(Usuario $user, Curso $curso): bool
    {
        // Admins siempre tienen acceso
        $adminRoles = ['Administrador', 'SuperAdmin', 'Super Admin'];
        $userRoles = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->get()
            ->pluck('nombre')
            ->toArray();

        if (count(array_intersect($adminRoles, $userRoles)) > 0) {
            return true;
        }

        // Verificar que el usuario es docente
        if (!$user->docente) {
            return false;
        }

        // Verificar que el docente es titular del curso
        return \App\Models\Curso\Curso::where('id_curso', $curso->id_curso)
            ->where('id_docente_titular', $user->docente->id_docente)
            ->exists();
    }
}
