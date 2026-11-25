<?php

namespace App\Policies;

use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Policies\Base\BaseCursoPolicy;
use App\Support\Permissions;
use Illuminate\Support\Facades\Log;

/**
 * Policy para autorización de operaciones sobre el modelo Curso.
 *
 * Evalúa permisos efectivos en el contexto del curso o sus componentes
 * mediante {@see \App\Services\Authorization\PermissionValidator}, sin
 * bypasses por nombre de rol ni pertenencia física aislada.
 */
class CursoPolicy extends BaseCursoPolicy
{
    /**
     * Determina si el usuario puede gestionar el equipo o estructura del curso.
     *
     * Requiere permiso efectivo de edición/gestión en el curso (`CURSOS_EDITAR`
     * o `USUARIOS_PERMISOS_INDIVIDUALES_GESTIONAR`), además de la validación de
     * SuperAdmin cubierta por `BaseCursoPolicy::before()`.
     */
    public function manageTeam(Usuario $user, Curso $curso): bool
    {
        $validator = $this->validator();

        if ($validator->isSuperAdmin($user)) {
            return true;
        }

        $autorizado = $validator->validate($user, Permissions::CURSOS_EDITAR, $curso)
            || $validator->validate($user, Permissions::USUARIOS_PERMISOS_INDIVIDUALES_GESTIONAR, $curso);

        if (!$autorizado && $user->docente) {
            Log::channel('seguridad')->warning(
                'Acceso denegado: Intento de gestionar equipo sin permiso cursos:editar en el curso',
                [
                    'evento'               => 'ACCESO_DENEGADO_MANAGEAM_TEAM',
                    'id_usuario'           => $user->id_usuario,
                    'email_usuario'        => $user->email,
                    'id_docente'           => $user->docente->id_docente,
                    'id_curso'             => $curso->id_curso,
                    'cod_curso'            => $curso->cod_curso,
                    'profesor_jefe_actual' => $curso->id_docente_titular,
                    'timestamp'            => now()->toIso8601String(),
                    'ip'                   => request()->ip(),
                    'user_agent'           => request()->userAgent(),
                ]
            );
        }

        return $autorizado;
    }

    /**
     * Determina si el usuario puede acceder a la vista y recursos de lectura del curso.
     *
     * Valida permisos efectivos sobre el curso (`CURSOS_VER`, `CURSOS_PROGRAMAS_VER_TODOS`,
     * `CURSOS_PROGRAMAS_VER_ULTIMO`, `ACTIVIDADES_VER`) o sobre al menos un componente
     * del curso (`COMPONENTES_VER`, `ACTIVIDADES_VER`).
     */
    public function viewPrograma(Usuario $user, Curso $curso): bool
    {
        $validator = $this->validator();

        if ($validator->isSuperAdmin($user)) {
            return true;
        }

        foreach ([
            Permissions::CURSOS_VER,
            Permissions::CURSOS_PROGRAMAS_VER_TODOS,
            Permissions::CURSOS_PROGRAMAS_VER_ULTIMO,
            Permissions::ACTIVIDADES_VER,
        ] as $permisoCurso) {
            if ($validator->validate($user, $permisoCurso, $curso)) {
                return true;
            }
        }

        $curso->loadMissing('componentes');
        foreach ($curso->componentes as $componente) {
            if ($validator->validate($user, Permissions::COMPONENTES_VER, $componente)
                || $validator->validate($user, Permissions::ACTIVIDADES_VER, $componente)) {
                return true;
            }
        }

        if ($user->docente) {
            Log::channel('seguridad')->warning(
                'Acceso denegado: Docente sin permisos efectivos en curso',
                [
                    'evento'        => 'ACCESO_DENEGADO_VIEWPROGRAMA',
                    'id_usuario'    => $user->id_usuario,
                    'email_usuario' => $user->email,
                    'id_docente'    => $user->docente->id_docente,
                    'id_curso'      => $curso->id_curso,
                    'cod_curso'     => $curso->cod_curso,
                    'timestamp'     => now()->toIso8601String(),
                    'ip'            => request()->ip(),
                    'user_agent'    => request()->userAgent(),
                ]
            );
        }

        return false;
    }
}
