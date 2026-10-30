<?php

namespace App\Api\V1\Resources\Student;

use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Support\Rut;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de perfil estudiantil móvil (Solo Lectura).
 */
class PerfilResource extends JsonResource
{
    public function __construct(
        Usuario $usuario,
        private readonly Estudiante $estudiante,
        private readonly int $totalCursosInscritos,
        private readonly ?string $urlFotoPerfil,
    ) {
        parent::__construct($usuario);
    }

    public function toArray(Request $request): array
    {
        /** @var Usuario $u */
        $u = $this->resource;
        $e = $this->estudiante;

        return [
            'id_usuario' => $u->id_usuario,
            'id_estudiante' => $e->id_estudiante,
            'nombre_completo' => $u->nombre_completo,
            'rut' => $u->rut,
            'rut_formateado' => Rut::formatearConPuntos($u->rut),
            'email' => $u->email,
            'correo_institucional' => $u->email,
            'carrera' => $e->carrera?->nombre,
            'id_carrera' => $e->id_carrera,
            'cohorte' => $e->agno_ingreso,
            'agno_ingreso' => $e->agno_ingreso,
            'total_cursos_inscritos' => $this->totalCursosInscritos,
            'total_cursos' => $this->totalCursosInscritos,
            'url_foto_perfil' => $this->urlFotoPerfil,
        ];
    }
}
