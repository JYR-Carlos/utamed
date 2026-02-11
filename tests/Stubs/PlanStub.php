<?php

namespace App\Models\Administrativo;

/**
 * Stub de Plan para testing sin BD
 */
class Plan
{
    protected $carreraModel;
    
    public function __construct($carrera = null)
    {
        $this->carreraModel = $carrera;
    }
    
    public function carrera()
    {
        return $this->carreraModel;
    }
    
    // Acceso como propiedad (Eloquent magic)
    public function __get($key)
    {
        if ($key === 'carrera' && method_exists($this, 'carrera')) {
            return $this->carrera();
        }
        return null;
    }
}
