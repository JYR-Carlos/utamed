<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocenteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Safely get usuario, checking if relationship is loaded
        $usuario = null;
        try {
            if ($this->relationLoaded('usuario')) {
                $usuario = $this->usuario;
            }
        } catch (\Exception $e) {
            $usuario = null;
        }
        
        $nombre_completo = 'Sin nombre';
        if ($usuario) {
            $nombre_completo = trim(($usuario->nombre1 ?? '') . ' ' . ($usuario->apellido1 ?? '')) ?: 'Sin nombre';
        }
        
        return [
            'id_docente' => $this->id_docente,
            'id_usuario' => $this->id_usuario,
            'nombre1' => $usuario?->nombre1,
            'nombre2' => $usuario?->nombre2,
            'apellido1' => $usuario?->apellido1,
            'apellido2' => $usuario?->apellido2,
            'nombre_completo' => $nombre_completo,
            'email' => $usuario?->email,
            'grado' => $this->grado,
            'titulo' => $this->titulo,
            'cargo' => $this->cargo,
            'usuario' => new UsuarioResource($this->whenLoaded('usuario')),
        ];
    }
}
