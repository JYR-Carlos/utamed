<?php

namespace App\Policies;

use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;

/**
 * Policy para autorización de operaciones sobre el modelo Curso.
 * 
 * Implementa control de acceso basado en roles (RBAC) para gestión de equipos de curso.
 * Reemplaza la autorización insegura basada en path por validación robusta de permisos.
 * 
 * Reglas de autorización:
 * - Administradores: Acceso completo a todos los cursos
 * - Docentes: Solo cursos donde dictan al menos una sección
 * - Otros usuarios: Sin acceso
 */
class CursoPolicy
{
    /**
     * Determina si el usuario puede gestionar el equipo de un curso.
     * 
     * Valida que:
     * - Administradores tienen acceso completo
     * - Docentes solo acceden a cursos donde dictan secciones
     * - Se verifica mediante relación seccionesQueDicta() en modelo Docente
     * 
     * @param  Usuario  $user   Usuario autenticado intentando acceder
     * @param  Curso    $curso  Curso cuyo equipo se intenta gestionar
     * @return bool             True si autorizado, false en caso contrario
     */
    public function manageTeam(Usuario $user, Curso $curso): bool
    {
        // Admins siempre tienen acceso
        if ($user->is_admin) {
            return true;
        }

        // Verificar que el usuario es docente
        if (!$user->docente) {
            return false;
        }

        // Verificar que el docente dicta al menos una sección en este curso
        $ownsCourse = $user->docente->seccionesQueDicta()
            ->where('id_curso', $curso->id_curso)
            ->exists();

        return $ownsCourse;
    }
}
