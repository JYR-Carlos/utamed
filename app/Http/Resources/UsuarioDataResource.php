<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización plana de un modelo Usuario.
 *
 * Pensado para uso embebido dentro de otros Resources (EstudianteResource,
 * DocenteResource, SeccionResource, etc.) donde solo se necesitan los campos
 * del usuario sin el contrato anidado { usuario, estudiante, docente }.
 *
 * Para la salida top-level del UsuarioController usar UsuarioResource.
 */
class UsuarioDataResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id_usuario' => (int) $this->id_usuario,
      'username' => $this->username,
      'email' => $this->email,
      'rut' => $this->rut,
      'nombre1' => $this->nombre1,
      'nombre2' => $this->nombre2,
      'apellido1' => $this->apellido1,
      'apellido2' => $this->apellido2,
      'esta_activo' => (bool) $this->esta_activo,
      'fecha_verificacion_email' => $this->fecha_verificacion_email,
    ];
  }
}
