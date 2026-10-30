<?php

namespace App\Api\V1\Resources\Auth;

use App\Models\Usuario\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Usuario $resource
 */
class StudentAuthResource extends JsonResource
{
    public function __construct(Usuario $usuario, private readonly ?string $token = null)
    {
        parent::__construct($usuario);
    }

    public function toArray(Request $request): array
    {
        $u = $this->resource;
        $e = $u->estudiante;

        return [
            ...($this->token !== null ? ['token' => $this->token, 'token_type' => 'Bearer'] : []),
            'requiere_cambio_password' => $u->debeCambiarPassword(),
            'motivo_cambio_password' => $u->motivoCambioPasswordObligatorio(),
            'usuario' => [
                'id' => $u->id_usuario,
                'rut' => $u->rut,
                'nombre_completo' => $u->nombre_completo,
                'email' => $u->email,
            ],
            'estudiante' => [
                'id' => $e->id_estudiante,
                'id_carrera' => $e->id_carrera,
                'carrera' => $e->carrera?->nombre,
                'agno_ingreso' => $e->agno_ingreso,
            ],
        ];
    }
}
