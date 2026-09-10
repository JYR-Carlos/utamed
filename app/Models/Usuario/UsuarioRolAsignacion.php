<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuarioRolAsignacion;
use App\Services\Authorization\PermissionCache;

/**
 * Modelo UsuarioRolAsignacion
 *
 * Extiende de BaseUsuarioRolAsignacion (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class UsuarioRolAsignacion extends BaseUsuarioRolAsignacion
{
    /**
     * Toda alta, baja o modificación de una asignación de rol invalida los
     * veredictos cacheados del usuario afectado.
     *
     * Va por eventos de modelo para cubrir cualquier vía de escritura
     * (RoleAssignmentBuilder, invalidateRole, updateOrCreate…) sin tener que
     * acordarse en cada una. **No cubre** las actualizaciones masivas del tipo
     * `Model::where(...)->update(...)`, que no disparan eventos: ésas se
     * invalidan a mano (ver `UsuarioController::syncPermissions`).
     */
    protected static function booted(): void
    {
        // La columna no tiene DEFAULT en la base, asi que toda alta que no la fije
        // la deja en NULL. Una fila asi es un fantasma: concede permisos —la vista
        // usuario.vw_permisos_usuario no la mira— pero desaparece de cualquier
        // consulta que filtre por `fue_eliminado`, incluido el listado y la baja de
        // roles del panel. Se fija aqui, y no en cada punto de escritura, por lo
        // mismo que los demas eventos de este modelo.
        //
        // NO cubre `DB::table(...)->insert/updateOrInsert`, que no pasa por
        // Eloquent: ahi hay que fijarla a mano (ver EquipoDesarrolloSeeder).
        static::creating(function (self $asignacion) {
            if ($asignacion->fue_eliminado === null) {
                $asignacion->fue_eliminado = false;
            }
        });

        $olvidar = fn(self $asignacion) => app(PermissionCache::class)
            ->olvidarUsuario((int) $asignacion->id_usuario);

        static::saved($olvidar);
        static::deleted($olvidar);

        // El wizard de asignación de roles (y sync-permissions, y el equipo
        // de curso) sólo crea esta fila de RBAC; nadie más da de alta el
        // perfil en usuario.docente. Sin él, el usuario tiene el rol pero es
        // invisible para todo lo que lee por Docente (sidebar, selectores de
        // profesor, Jefe de Carrera...). Igual que arriba: va por evento de
        // modelo para cubrir cualquier vía de escritura sin acordarse en cada una.
        static::saved(function (self $asignacion) {
            if (!$asignacion->esta_activo) {
                return;
            }
            $nombreRol = $asignacion->rol?->nombre;
            if ($nombreRol && (str_starts_with($nombreRol, 'Docente') || $nombreRol === 'Jefe de Carrera')) {
                \App\Models\Usuario\Docente::firstOrCreate(['id_usuario' => $asignacion->id_usuario]);
            }
        });
    }
}