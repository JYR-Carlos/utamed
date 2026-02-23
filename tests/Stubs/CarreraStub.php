<?php

namespace App\Models\Administrativo;

use App\Contracts\HasOwnedContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Stub de Carrera para testing sin BD
 */
class Carrera implements HasOwnedContext
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

    /**
     * Obtiene el ID del contexto para este modelo
     */
    public function getContextId(): array
    {
        $id = $this->attributes['id_contexto'] ?? null;
        return $id !== null ? [$id] : [];
    }

    /**
     * Obtiene el tipo de contexto para este modelo
     */
    public function getContextType(): ?string
    {
        return 'carrera';
    }

    /**
     * Obtiene el modelo padre que define el contexto
     */
    public function getParentContextModel(): ?Model
    {
        return null; // Para testing, Carrera es el contexto raíz
    }
}
