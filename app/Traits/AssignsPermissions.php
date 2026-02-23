<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\Authorization\PermissionAssignmentBuilder;
use App\Services\Authorization\RoleAssignmentBuilder;

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
   * @example $user->givePermission($permiso)->on($facultad)->for(30)->canDelegate();
   * @example $user->givePermission($permiso)->on($curso)->for(15)->revoke();
   * @example $user->givePermission($permiso)->onAll(Facultad::class)->for(60);
   *
   * @param  Permiso       $perm  Instancia del permiso a asignar
   * @return PermissionAssignmentBuilder
   * @throws \RuntimeException Si no hay un usuario autenticado
   */
  public function givePermission(Permiso $perm): PermissionAssignmentBuilder
  {
    $actor = Auth::user() ?? $this;

    return new PermissionAssignmentBuilder($this, $perm, $actor);
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
    $actor = Auth::user() ?? $this;

    return new RoleAssignmentBuilder($this, $rol, $actor);
  }

  /**
   * Invalida (cierra) un permiso individual (UPE) existente.
   *
   * Establece fecha_fin_real = ahora, esta_activo = false y registra
   * quien realizo la operacion.
   *
   * @param  int   $upeId  ID del registro UPE (id_upe)
   * @param  \App\Models\Usuario\Usuario|null  $actor Usuario que realiza la accion
   *                              (por defecto: usuario autenticado)
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
   */
  public function invalidatePermission(int $upeId, ?self $actor = null): void
  {
    $actor ??= Auth::user();

    /** @var UsuarioPermisoEspecial $upe */
    $upe = UsuarioPermisoEspecial::findOrFail($upeId);

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
   * @param int $uraId ID del registro URA (id_ura)
   * @param \App\Models\Usuario\Usuario|null $actor Usuario que realiza la accion (por defecto: usuario autenticado)
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
   */
  public function invalidateRole(int $uraId, ?self $actor = null): void
  {
    $actor ??= Auth::user();

    /** @var UsuarioRolAsignacion $ura */
    $ura = UsuarioRolAsignacion::findOrFail($uraId);

    $ura->fecha_fin_real = Carbon::now();
    $ura->esta_activo = false;
    $ura->eliminado_por = $actor?->id_usuario;
    $ura->save();
  }
}
