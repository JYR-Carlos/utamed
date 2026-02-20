<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_usuario' => $this->id_usuario,
            'username' => $this->username,
            'email' => $this->email,
            'nombre1' => $this->nombre1,
            'nombre2' => $this->nombre2,
            'apellido1' => $this->apellido1,
            'apellido2' => $this->apellido2,
            'rut' => $this->rut,
            'genero' => $this->genero,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'telefono' => $this->telefono,
            'celular' => $this->celular,
        ];
    }
}
