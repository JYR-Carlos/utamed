<?php

namespace App\Http\Controllers\Docente\JefeCarrera;

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Support\Facades\Auth;

/**
 * Trait compartido por los controladores de gestión del Jefe de Carrera.
 *
 * Centraliza la resolución de la jefatura activa del usuario autenticado y
 * el filtrado por contexto: todas las operaciones quedan acotadas a la carrera
 * sobre la que el usuario tiene el rol "Jefe de Carrera".
 */
trait ResolvesJefaturaCarrera
{
    /**
     * Resuelve la jefatura de carrera activa del usuario.
     *
     * @return array{id_contexto:int,carrera_id:?int,carrera_nombre:?string}|null
     */
    protected function resolveJefatura(Usuario $user): ?array
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
     * Devuelve la jefatura del usuario autenticado o aborta con 403.
     *
     * @return array{id_contexto:int,carrera_id:?int,carrera_nombre:?string}
     */
    protected function jefaturaOrAbort(): array
    {
        /** @var Usuario|null $user */
        $user = Auth::user();

        $jefatura = ($user && $user->docente) ? $this->resolveJefatura($user) : null;

        if (!$jefatura || !$jefatura['carrera_id']) {
            abort(403, 'No tienes rol de Jefe de Carrera activo.');
        }

        return $jefatura;
    }

    /**
     * Devuelve el id_carrera del jefe autenticado o aborta con 403.
     */
    protected function carreraIdOrAbort(): int
    {
        return (int) $this->jefaturaOrAbort()['carrera_id'];
    }
}
