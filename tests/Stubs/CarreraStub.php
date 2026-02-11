<?php

namespace App\Models\Administrativo;

/**
 * Stub de Carrera para testing sin BD
 */
class Carrera
{
    protected $attributes = [];
    
    public function __construct($idContexto = null)
    {
        if ($idContexto !== null) {
            $this->attributes['id_contexto'] = $idContexto;
        }
    }
    
    public function getAttribute($key)
    {
        return $this->attributes[$key] ?? null;
    }
}
