<?php

namespace App\Policies;

use App\Policies\Base\BaseCursoPolicy;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Log;

/**
 * Policy para autorización de operaciones sobre el modelo Curso.
 * 
 * Extiende BaseCursoPolicy (autogenerada) con lógica personalizada.
 * 
 * Implementa control de acceso basado en roles (RBAC) para gestión de equipos de curso.
 * Reemplaza la autorización insegura basada en path por validación robusta de permisos.
 * 
 * AUDIT LOGGING: Registra intentos denegados cuando un usuario que fue profesor jefe
 * intenta acceder después de ser reemplazado. Esto detecta intentos de acceso no autorizado.
 * 
 * Reglas de autorización:
 * - Administradores: Acceso completo a todos los cursos
 * - Docentes: SOLO cursos donde son el profesor jefe ACTUAL
 * - Otros usuarios: Sin acceso (acceso revocado si eran jefes anteriormente)
 */
class CursoPolicy extends BaseCursoPolicy
{
    /**
     * Determina si el usuario puede gestionar el equipo de un curso.
     * 
     * Valida que:
     * - Administradores tienen acceso completo
     * - Docentes SOLO acceden si son el profesor jefe ACTUAL del curso
     * - Si el docente fue reemplazado como jefe, pierde acceso inmediatamente
     * 
     * AUDIT: Registra accesos denegados de ex-jefes de curso (potencial IDOR)
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

        // Verificar que el docente es el ACTUAL profesor jefe del curso
        // This is explicit and strict: only the current titular professor can manage
        $isCurrentTitular = $curso->id_docente_titular === $user->docente->id_docente;
        
        // AUDIT LOG: Si es docente pero NO es titular actual, log potential IDOR attempt
        if (!$isCurrentTitular) {
            // Check if user WAS a previous titular (for detecting revocation)
            Log::channel('seguridad')->warning(
                'Acceso denegado: Intento de gestionar equipo sin ser profesor jefe actual',
                [
                    'evento' => 'ACCESO_DENEGADO_MANAGEAM_TEAM',
                    'id_usuario' => $user->id_usuario,
                    'email_usuario' => $user->email,
                    'id_docente' => $user->docente->id_docente,
                    'id_curso' => $curso->id_curso,
                    'cod_curso' => $curso->cod_curso,
                    'profesor_jefe_actual' => $curso->id_docente_titular,
                    'timestamp' => now()->toIso8601String(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            );
        }
        
        return $isCurrentTitular;
    }

    /**
     * Determina si el usuario puede acceder a un curso para consultar/editar sus programas.
     * 
     * Valida que:
     * - Administradores tienen acceso completo
     * - Docentes SOLO acceden si son el profesor jefe ACTUAL del curso
     * - Si el docente fue reemplazado como jefe, pierde acceso inmediatamente
     * 
     * AUDIT: Registra accesos denegados de ex-jefes de curso (potencial IDOR)
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

        // Verificar que el docente es el ACTUAL profesor jefe del curso
        // This is explicit and strict: only the current titular professor can view/edit
        $isCurrentTitular = $curso->id_docente_titular === $user->docente->id_docente;
        
        // AUDIT LOG: Si es docente pero NO es titular actual, log potential IDOR attempt
        if (!$isCurrentTitular) {
            Log::channel('seguridad')->warning(
                'Acceso denegado: Intento de ver/editar programa sin ser profesor jefe actual',
                [
                    'evento' => 'ACCESO_DENEGADO_VIEWPROGRAMA',
                    'id_usuario' => $user->id_usuario,
                    'email_usuario' => $user->email,
                    'id_docente' => $user->docente->id_docente,
                    'id_curso' => $curso->id_curso,
                    'cod_curso' => $curso->cod_curso,
                    'profesor_jefe_actual' => $curso->id_docente_titular,
                    'timestamp' => now()->toIso8601String(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            );
        }
        
        return $isCurrentTitular;
    }
}
