<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

use App\Enums\ContextType;

/**
 * Interfaz para modelos con soporte de contextos jerárquicos
 * 
 * Los modelos que implementan esta interfaz pueden participar en una jerarquía de contextos
 * donde cada modelo puede:
 * - Tener un contexto directo (id_contexto en la tabla)
 * - Heredar contexto de modelos relacionados (vía relaciones)
 * - No tener contexto (modelos globales)
 */
interface HasContext
{
    /**
     * Obtiene el ID del contexto para este modelo
     * 
     * Puede ser:
     * - El id_contexto directo de la tabla
     * - Un ID heredado a través de relaciones (ej: Inscripcion_Seccion → Carrera → id_contexto)
     * - array vacío si el modelo no tiene contexto (modelos globales)
     *
     * Retorna array porque un modelo puede tener múltiples rutas de contexto
     * (ej: InscripcionCurso → Carrera y también → Curso).
     *
     * @return array<int>
     */
    public function getContextId(): array;

    /**
     * Obtiene los tipos de contexto para este modelo.
     *
     * Para modelos jerárquicos con múltiples rutas puede retornar más de un tipo.
     * Para modelos globales retorna [].
     *
     * @return array<ContextType> (ej: [ContextType::CARRERA], [ContextType::CURSO, ContextType::CARRERA])
     */
    public function getContextTypes(): array;

    /**
     * Obtiene el modelo padre que define el contexto
     * 
     * Útil para navegación y para obtener el modelo raíz de la jerarquía
     * 
     * @return Model|null
     */
    public function getParentContextModel(): ?Model;
}
