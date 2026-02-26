<?php

namespace App\Http\Resources;

use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso unificado para serializar cualquier tipo de usuario (Usuario, Estudiante o Docente).
 *
 * Produce siempre el mismo contrato:
 *   { usuario: {...}, estudiante: {...}|null, docente: {...}|null }
 *
 * Lógica de resolución:
 *  - Si el recurso es Estudiante → usuario = $this->usuario, estudiante = self, docente = usuario->docente
 *  - Si el recurso es Docente    → usuario = $this->usuario, docente   = self, estudiante = usuario->estudiante
 *  - Si el recurso es Usuario    → usuario = self, carga ambas relaciones si existen
 */
class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        if ($resource instanceof Estudiante) {
            /** @var Estudiante $resource */
            $usuario = $resource->usuario;
            $estudiante = $resource;
            $docente = $usuario?->docente ?? null;
        } elseif ($resource instanceof Docente) {
            /** @var Docente $resource */
            $usuario = $resource->usuario;
            $docente = $resource;
            $estudiante = $usuario?->estudiante ?? null;
        } else {
            /** @var Usuario $resource */
            $usuario = $resource;
            $estudiante = $resource->estudiante ?? null;
            $docente = $resource->docente ?? null;
        }

        return [
            'usuario' => $this->serializeUsuario($usuario),
            'estudiante' => $this->serializeEstudiante($estudiante),
            'docente' => $this->serializeDocente($docente),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Serializers privados – evitan recursión circular al no delegar a otros Resources
    // ──────────────────────────────────────────────────────────────────────────

    private function serializeUsuario(?Usuario $usuario): ?array
    {
        if (!$usuario) {
            return null;
        }

        return [
            'id_usuario' => (int) $usuario->id_usuario,
            'username' => $usuario->username,
            'email' => $usuario->email,
            'rut' => $usuario->rut,
            'nombre1' => $usuario->nombre1,
            'nombre2' => $usuario->nombre2,
            'apellido1' => $usuario->apellido1,
            'apellido2' => $usuario->apellido2,
            'esta_activo' => (bool) $usuario->esta_activo,
            'fecha_verificacion_email' => $usuario->fecha_verificacion_email,
        ];
    }

    private function serializeEstudiante(?Estudiante $estudiante): ?array
    {
        if (!$estudiante) {
            return null;
        }

        $carrera = null;
        if ($estudiante->relationLoaded('carrera') && $estudiante->carrera) {
            $carrera = [
                'id_carrera' => (int) $estudiante->carrera->id_carrera,
                'nombre' => $estudiante->carrera->nombre,
            ];
        }

        return [
            'id_estudiante' => (int) $estudiante->id_estudiante,
            'id_usuario' => (int) $estudiante->id_usuario,
            'agno_ingreso' => $estudiante->agno_ingreso !== null ? (int) $estudiante->agno_ingreso : null,
            'id_carrera' => $estudiante->id_carrera !== null ? (int) $estudiante->id_carrera : null,
            'carrera' => $carrera,
        ];
    }

    private function serializeDocente(?Docente $docente): ?array
    {
        if (!$docente) {
            return null;
        }

        return [
            'id_docente' => (int) $docente->id_docente,
            'id_usuario' => (int) $docente->id_usuario,
            'grado' => $docente->grado,
            'titulo' => $docente->titulo,
            'cargo' => $docente->cargo,
        ];
    }
}
