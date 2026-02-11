<?php

namespace App\Traits;

use App\Services\ContextResolver;

/**
 * Trait ContextAware
 * 
 * Implementa la interfaz HasContext para cualquier modelo Eloquent.
 * 
 * Proporciona:
 * - getContextId(): Obtiene el ID de contexto del modelo
 * - getContextType(): Obtiene el tipo de contexto (ej: 'carrera', 'curso')
 * 
 * FUNCIONAMIENTO:
 * 
 * 1. CONTEXTOS DIRECTOS (Direct):
 *    Modelos que tienen una columna id_contexto
 *    Ej: Carrera, Curso, Facultad
 *    → Retorna directamente el valor de id_contexto
 * 
 * 2. CONTEXTOS JERÁRQUICOS (Hierarchical):
 *    Modelos que acceden al contexto a través de relaciones
 *    Ej: Seccion → curso() → Curso (contexto directo)
 *    → Sigue las relaciones hasta encontrar un contexto directo
 * 
 * 3. CONTEXTOS GLOBALES (Global):
 *    Modelos sin contexto (ej: usuario, estudiante, rol)
 *    → Retorna null
 * 
 * USO:
 * 
 *    // En el modelo
 *    class Seccion extends Model {
 *        use ContextAware;
 *    }
 *    
 *    // En la aplicación
 *    $seccion = Seccion::find(1);
 *    $contextId = $seccion->getContextId();      // int (id_contexto del Curso)
 *    $contextType = $seccion->getContextType();  // string ('curso')
 */
trait ContextAware
{
    /**
     * Obtener el ID de contexto del modelo
     * 
     * @return int|null
     */
    public function getContextId(): ?int
    {
        return app(ContextResolver::class)->getContextId($this);
    }

    /**
     * Obtener el tipo de contexto del modelo
     * 
     * @return string|null (ej: 'carrera', 'curso')
     */
    public function getContextType(): ?string
    {
        return app(ContextResolver::class)->getContextType($this);
    }

    /**
     * Obtener el modelo padre que define el contexto
     * 
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getParentContextModel(): ?\Illuminate\Database\Eloquent\Model
    {
        return app(ContextResolver::class)->getParentContextModel($this);
    }
}
