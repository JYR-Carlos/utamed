<?php

namespace App\Models\Usuario;

/**
 * Stub de Estudiante para testing
 * Simula un Estudiante que tiene:
 * - una relación con Usuario (belongsTo)
 * - una relación con Carrera (belongsTo)
 * 
 * Esto permite probar contextos con múltiples caminos:
 * - Estudiante → Usuario (global)
 * - Estudiante → Carrera (contexto directo)
 */
class Estudiante
{
    private $usuario;
    private $carrera;
    private $attributes = [];
    
    public function __construct($usuario = null, $carrera = null)
    {
        $this->usuario = $usuario;
        $this->carrera = $carrera;
    }
    
    /**
     * Relación belongsTo hacia Usuario
     */
    public function usuario()
    {
        return $this->usuario;
    }
    
    /**
     * Relación belongsTo hacia Carrera
     */
    public function carrera()
    {
        return $this->carrera;
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
        if ($name === 'usuario') {
            return $this->usuario;
        }
        if ($name === 'carrera') {
            return $this->carrera;
        }
        return $this->getAttribute($name);
    }
}
