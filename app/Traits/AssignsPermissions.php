<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\Authorization\PermissionAssignmentBuilder;
use App\Contracts\PermissionBuilderStart;
use App\Services\Authorization\RoleAssignmentBuilder;
use App\Services\Authorization\PermissionValidator;
use App\Exceptions\DontHavePermissionException;
use App\Support\Permissions;

/**
 * Trait que agrega metodos de gestion de asignaciones de permisos y roles
 * a un usuario. Debe usarse en el modelo Usuario.
 *
 * @mixin \App\Models\Usuario\Usuario
 */
trait AssignsPermissions
{
  /**
   * Inicia un builder fluido para asignar un permiso individual (UPE) al usuario.
   *
   * Devuelve PermissionBuilderStart. El IDE ofrece solo los métodos de contexto.
   * Tras elegir contexto, transita a PermissionBuilderReady para configurar y guardar.
   *
   * @example $user->givePermission(Permissions::CURSOS_VER)->on($facultad)->for(30)->canDelegate();
   * @example $user->givePermission(Permissions::CURSOS_EDITAR)->on($curso)->for(15)->revoke();
   * @example $user->givePermission(Permissions::CURSOS_CREAR)->onAllCurrentInstances(ContextualModelType::FACULTAD)->for(60);
   * @example $user->givePermission(Permissions::USUARIOS_VER)->onEveryInstance()->for(30);
   *
   * @param  Permissions $permissionSlug Enum del permiso desde App\Support\Permissions (ej: Permissions::CURSOS_VER)
   * @return PermissionBuilderStart
   * @throws \RuntimeException Si no hay un usuario autenticado
   */
  public function givePermission(Permissions $permissionSlug): PermissionBuilderStart
  {
    /** @var \App\Models\Usuario\Usuario $actor */
    $actor = Auth::user();

    if (!$actor) {
      throw new \RuntimeException('No authenticated user found for permission assignment');
    }

    return new PermissionAssignmentBuilder($this, $permissionSlug, $actor);
  }

  /**
   * Inicia un builder fluido para asignar un rol (URA) al usuario.
   *
   * @example $user->giveRole($rol)->on($facultad)->for(60);
   * @example $user->giveRole($rol)->onAll(Facultad::class)->for(30)->waitFor(5);
   *
   * @param  Rol            $rol   Instancia del rol a asignar
   * @return RoleAssignmentBuilder
   * @throws \RuntimeException Si no hay un usuario autenticado
   */
  public function giveRole(Rol $rol): RoleAssignmentBuilder
  {
    /** @var \App\Models\Usuario\Usuario $actor */
    $actor = Auth::user();

    if (!$actor) {
      throw new \RuntimeException('No authenticated user found for permission assignment');
    }

    return new RoleAssignmentBuilder($this, $rol, $actor);
  }

  /**
   * Invalida (cierra) un permiso individual (UPE) existente.
   *
   * Establece fecha_fin_real = ahora, esta_activo = false y registra
   * quien realizo la operacion.
   *
   * Solo el admin o quien lo asignó originalmente pueden revocar.
   *
   * @param  int   $upeId  ID del registro UPE (id_upe)
   * @param  \App\Models\Usuario\Usuario|null  $actor Usuario que realiza la accion
   *                              (por defecto: usuario autenticado)
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
   * @throws DontHavePermissionException Si el actor no tiene autorización
   */
  public function invalidatePermission(int $upeId, ?self $actor = null): void
  {
    /** @var \App\Models\Usuario\Usuario $actor */
    $actor ??= Auth::user();

    /** @var UsuarioPermisoEspecial $upe */
    $upe = UsuarioPermisoEspecial::findOrFail($upeId);

    // Validar autorización:
    // - Admin (permiso '*') puede revocar cualquier cosa
    // - Solo quien lo asignó originalmente (creado_por) puede revocarlo
    $validator = app(PermissionValidator::class);
    $isAdmin = $validator->isSuperAdmin($actor);
    $isOriginalAssignor = $upe->creado_por === $actor->id_usuario;

    if (!$isAdmin && !$isOriginalAssignor) {
      $permission = $upe->permiso ? Permissions::tryFrom($upe->permiso->slug) : null;
      throw new DontHavePermissionException(
        permission: $permission,
        objects: [$upe->id_contexto],
        message: 'No eres el asignador original y no eres administrador para revocar este permiso.'
      );
    }

    $upe->fecha_fin_real = Carbon::now();
    $upe->esta_activo = false;
    $upe->eliminado_por = $actor?->id_usuario;
    $upe->save();
  }

  /**
   * Invalida (cierra) una asignacion de rol (URA) existente.
   *
   * Establece fecha_fin_real = ahora, esta_activo = false y registra
   * quien realizo la operacion.
   *
   * Solo el admin o quien lo asignó originalmente pueden revocar.
   *
   * @param int $uraId ID del registro URA (id_ura)
   * @param \App\Models\Usuario\Usuario|null $actor Usuario que realiza la accion (por defecto: usuario autenticado)
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
   * @throws DontHavePermissionException Si el actor no tiene autorización
   */
  public function invalidateRole(int $uraId, ?self $actor = null): void
  {
    /** @var \App\Models\Usuario\Usuario $actor */
    $actor ??= Auth::user();

    /** @var UsuarioRolAsignacion $ura */
    $ura = UsuarioRolAsignacion::findOrFail($uraId);

    // Validar autorización:
    // - Admin (permiso '*') puede revocar cualquier cosa
    // - Solo quien lo asignó originalmente (creado_por) puede revocarlo
    $validator = app(PermissionValidator::class);
    $isAdmin = $validator->isSuperAdmin($actor);
    $isOriginalAssignor = $ura->creado_por === $actor->id_usuario;

    if (!$isAdmin && !$isOriginalAssignor) {
      throw new DontHavePermissionException(
        objects: [$ura->id_contexto],
        message: 'No eres el asignador original y no eres administrador para revocar este rol.'
      );
    }

    $ura->fecha_fin_real = Carbon::now();
    $ura->esta_activo = false;
    $ura->eliminado_por = $actor?->id_usuario;
    $ura->save();
  }
}
