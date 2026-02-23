<?php

namespace App\Enums;

use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;

/**
 * Enumeración de los modelos con contexto propio de tipo 'direct'.
 *
 * Generada automáticamente por scripts/generate_models.php
 * NO EDITAR MANUALMENTE — se sobrescribe al regenerar.
 *
 * Estos son los "anclas" de contexto: modelos que tienen un id_contexto
 * directo en su tabla y son el destino final de todos los caminos jerárquicos.
 *
 * Úsala en ->onAll() para eliminar strings mágicos y garantizar en tiempo de
 * compilación que sólo se pasan modelos que poseen un contexto real:
 *
 * @example
 *   // Antes (string mágico, sin verificación):
 *   $user->givePermission($perm)->onAll(Carrera::class)->for(30);
 *
 *   // Después (type-safe, IDE-friendly):
 *   $user->givePermission($perm)->onAll(ContextualModelType::CARRERA)->for(30);
 */
enum ContextualModelType: string
{
    case CARRERA = Carrera::class;
    case DEPARTAMENTO = Departamento::class;
    case FACULTAD = Facultad::class;
    case ACTIVIDAD = Actividad::class;
    case CURSO = Curso::class;

    /**
     * Retorna el FQCN del modelo asociado.
     *
     * @return class-string
     */
    public function modelClass(): string
    {
        return $this->value;
    }

    /**
     * Retorna una query builder para el modelo asociado.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return ($this->value)::query();
    }
}