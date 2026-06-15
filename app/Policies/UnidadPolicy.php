<?php

namespace App\Policies;

use App\Policies\Base\BaseUnidadPolicy;
use App\Models\Usuario\Usuario;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;

/**
 * Policy personalizada para Unidad.
 *
 * Flujo de autorización (heredado de BaseUnidadPolicy):
 *   1. before()        → SuperAdmin siempre pasa
 *   2. Base CRUD       → PermissionValidator valida cursos/unidades:<acción> en contexto del curso
 *   3. customXXX()     → Fallback: docente asignado a cualquier componente del curso, o admin
 */
class UnidadPolicy extends BaseUnidadPolicy
{
    /**
     * Fallback para ver/listar: docente asignado al curso o admin.
     */
    protected function customView(Usuario $user, $model): bool
    {
        return $this->puedeAccederUnidad($user, $model);
    }

    /**
     * Fallback para crear: docente asignado al curso o admin.
     * $parent es el Curso (pasado como segundo argumento en authorize('create', [Unidad::class, $curso])).
     */
    protected function customCreate(Usuario $user, $parent = null): bool
    {
        if (!($parent instanceof Curso)) {
            return false;
        }

        return $user->is_admin || $this->isDocenteDelCurso($user, $parent);
    }

    /**
     * Fallback para editar: docente asignado al curso o admin.
     */
    protected function customUpdate(Usuario $user, $model): bool
    {
        return $this->puedeAccederUnidad($user, $model);
    }

    /**
     * Fallback para eliminar: docente asignado al curso o admin.
     */
    protected function customDelete(Usuario $user, $model): bool
    {
        return $this->puedeAccederUnidad($user, $model);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function puedeAccederUnidad(Usuario $user, Unidad $unidad): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $curso = $unidad->curso;

        if (!$curso) {
            return false;
        }

        return $this->isDocenteDelCurso($user, $curso);
    }

    private function isDocenteDelCurso(Usuario $user, Curso $curso): bool
    {
        if (!$user->docente) {
            return false;
        }

        return $curso->componentes()
            ->whereHas('docenteComponentes', fn($q) => $q->where('id_docente', $user->docente->id_docente))
            ->exists();
    }
}
