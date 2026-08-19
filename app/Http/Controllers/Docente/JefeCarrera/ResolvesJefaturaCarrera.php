<?php

namespace App\Http\Controllers\Docente\JefeCarrera;

use App\Models\Usuario\Usuario;
use App\Services\Authorization\JefaturaCarreraResolver;
use Illuminate\Http\RedirectResponse;
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
        return app(JefaturaCarreraResolver::class)->resolve($user);
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

    /**
     * Variante de jefaturaOrAbort() para métodos que renderizan vistas Inertia:
     * en vez de abort(403) devuelve una RedirectResponse con flash de error,
     * mejor UX que una pantalla de error desnuda dentro de Inertia.
     *
     * @return array{id_contexto:int,carrera_id:?int,carrera_nombre:?string}|RedirectResponse
     */
    protected function jefaturaOrRedirect(string $redirectTo = '/docente/dashboard'): array|RedirectResponse
    {
        /** @var Usuario|null $user */
        $user = Auth::user();

        if (!$user || !$user->docente) {
            return redirect($redirectTo)
                ->with('error', 'No tienes acceso a esta sección');
        }

        $jefatura = $this->resolveJefatura($user);

        if (!$jefatura || !$jefatura['carrera_id']) {
            return redirect($redirectTo)
                ->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        return $jefatura;
    }
}
