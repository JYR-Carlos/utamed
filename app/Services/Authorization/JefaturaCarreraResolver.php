<?php

namespace App\Services\Authorization;

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;

/**
 * Resuelve la jefatura de carrera activa de un usuario.
 *
 * Vivía dentro de `ResolvesJefaturaCarrera`, un trait de controladores, lo que
 * dejaba la lógica fuera del alcance de las policies —de ahí que
 * `ProgramaPolicy::approve` no pudiera acotar por carrera—. El trait ahora
 * delega aquí, así que controladores y policies comparten una única definición
 * de "la carrera de la que este usuario es jefe".
 */
class JefaturaCarreraResolver
{
    /**
     * @return array{id_contexto:int,carrera_id:?int,carrera_nombre:?string}|null
     */
    public function resolve(Usuario $user): ?array
    {
        $asignacion = UsuarioRolAsignacion::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->where('nombre', 'Jefe de Carrera'))
            ->whereHas('contexto.tipoContexto', fn($q) => $q->where('categoria', 'carrera'))
            ->latest('id_ura')
            ->first();

        if (!$asignacion) {
            return null;
        }

        $carrera = Carrera::query()
            ->select('id_carrera', 'nombre', 'id_contexto')
            ->where('id_contexto', $asignacion->id_contexto)
            ->first();

        return [
            'id_contexto' => $asignacion->id_contexto,
            'carrera_id' => $carrera?->id_carrera,
            'carrera_nombre' => $carrera?->nombre,
        ];
    }

    /**
     * ID de la carrera que jefatura el usuario, o null si no es jefe de carrera.
     */
    public function carreraId(Usuario $user): ?int
    {
        $jefatura = $this->resolve($user);

        return $jefatura['carrera_id'] ?? null;
    }
}
