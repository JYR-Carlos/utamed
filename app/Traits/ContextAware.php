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
 * - getContextTypes(): Obtiene los tipos de contexto del modelo (ej: ['carrera'])
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
 *    → Retorna [$id_contexto_global] para que la validación de permisos use el contexto global como fallback
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
 *    $contextId    = $seccion->getContextId();    // array (ej: [42])
 *    $contextTypes = $seccion->getContextTypes(); // array (ej: ['curso'])
 */
trait ContextAware
{
    /**
     * Obtener todos los IDs de contexto del modelo.
     *
     * Retorna el array completo resuelto por el ContextResolver:
     * - Modelos con contexto directo (id_contexto): retorna [$id_contexto].
     * - Modelos jerárquicos: retorna todos los contextos de cada ruta.
     * - Modelos globales (sin contexto): retorna [$id_contexto_global].
     *
     * @return array<int>
     */
    public function getContextId(): array
    {
        return app(ContextResolver::class)->getModelContextId($this);
    }

    /**
     * Obtener todos los tipos de contexto del modelo.
     *
     * Para modelos jerárquicos con múltiples rutas puede retornar más de un tipo.
     * Para modelos globales retorna [].
     *
     * @return array<string> (ej: ['carrera'], ['curso', 'carrera'])
     */
    public function getContextTypes(): array
    {
        return app(ContextResolver::class)->getModelContextTypes($this);
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
