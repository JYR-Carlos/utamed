<?php

namespace App\Models\Curso;

/**
 * Stub de Curso para testing
 * Simula un Curso que tiene una AsignacionPlan asociada
 */
class Curso
{
    private $asignacionPlan;
    private $attributes = [];
    
    public function __construct($asignacionPlan = null)
    {
        $this->asignacionPlan = $asignacionPlan;
    }
    
    /**
     * Relación belongsTo hacia AsignacionPlan
     */
    public function asignacionPlan()
    {
        return $this->asignacionPlan;
    }
    
    /**
     * Obtener un atributo (simula Eloquent)
     */
    public function getAttribute($name)
    {
        return $this->attributes[$name] ?? null;
    }
    
    /**
     * Establecer un atributo
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }
    
    /**
     * Magic getter para simular acceso como propiedad de Eloquent
     */
    public function __get($name)
    {
        if ($name === 'asignacionPlan') {
            return $this->asignacionPlan;
        }
        return $this->getAttribute($name);
    }
}
