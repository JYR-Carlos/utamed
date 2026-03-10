<?php

namespace App\Contracts;

use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use Illuminate\Support\Collection;

/**
 * Paso 2 del step builder de permisos: configuración y guardado.
 *
 * Se llega aquí después de invocar un método de contexto en PermissionBuilderStart.
 * El IDE ya NO ofrece on(), onAllCurrentInstances(), onEveryInstance(), etc.
 * Solo quedan métodos de configuración (for, waitFor, revoke, canDelegate, as) y save().
 *
 * @see \App\Contracts\PermissionBuilderStart  Paso 1 (selección de contexto)
 * @see \App\Services\Authorization\PermissionAssignmentBuilder  Implementación concreta
 */
interface PermissionBuilderReady
{
  /**
   * Duración del permiso en días a partir de la fecha de inicio.
   *
   * @param int $days Número de días
   */
  public function for(int $days): static;

  /**
   * Diferir el inicio del permiso N días a partir de hoy.
   *
   * @param int $daysDelay Días de espera antes de activar
   */
  public function waitFor(int $daysDelay): static;

  /**
   * Marcar el permiso como DENY (denegar acceso).
   * Sin llamar este método, el permiso es GRANT por defecto.
   */
  public function revoke(): static;

  /**
   * Permitir que el receptor pueda delegar este permiso a otros.
   * Sin llamar este método, no puede delegar (false por defecto).
   */
  public function canDelegate(): static;

  /**
   * Especificar quién realiza la asignación (sobrescribe Auth::user()).
   *
   * @param Usuario $actor Usuario que realiza la asignación
   */
  public function as(Usuario $actor): static;

  /**
   * Persiste los registros UPE en la base de datos.
   *
   * @return UsuarioPermisoEspecial|Collection<int, UsuarioPermisoEspecial>
   * @throws \InvalidArgumentException Si no se especificó ningún contexto
   * @throws \Illuminate\Database\RecordNotFoundException Si el slug del permiso no existe
   * @throws \App\Exceptions\DontHavePermissionException Si el actor no tiene autorización
   */
  public function save(): UsuarioPermisoEspecial|Collection;
}
