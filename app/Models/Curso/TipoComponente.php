<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseTipoComponente;

/**
 * Modelo TipoComponente
 * 
 * Extiende de BaseTipoComponente (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class TipoComponente extends BaseTipoComponente
{
    /**
     * Jerarquía de prioridad de componentes.
     * Menor número = mayor prioridad = componente principal.
     * CATEDRA > TALLER > LABORATORIO
     */
    public const PRIORIDAD = [
        'CATEDRA'     => 1,
        'CÁTEDRA'     => 1,
        'TALLER'      => 2,
        'LABORATORIO' => 3,
    ];

    /**
     * Obtiene la prioridad numérica de este tipo de componente.
     * Tipos desconocidos reciben prioridad 99 (la más baja).
     */
    public function getPrioridadAttribute(): int
    {
        $tipoUpper = mb_strtoupper(trim($this->tipo ?? ''));
        return self::PRIORIDAD[$tipoUpper] ?? 99;
    }

    /**
     * Determina si este tipo es el componente principal de un curso,
     * comparándolo con los demás componentes del mismo curso.
     */
    public static function getComponentePrincipal(int $idCurso): ?\App\Models\Curso\Componente
    {
        $componentes = \App\Models\Curso\Componente::where('id_curso', $idCurso)
            ->with('tipoComponente')
            ->get();

        if ($componentes->isEmpty()) {
            return null;
        }

        return $componentes->sortBy(fn($c) => $c->tipoComponente?->prioridad ?? 99)->first();
    }
}
