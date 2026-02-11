<?php

namespace App\Models\Curso;

/**
 * Stub de InscripcionCurso para testing
 * Simula una tabla pivot entre Curso y Estudiante
 * Usa nombre StudlyCase para coincidir con mappings
 */
class InscripcionCurso
{
    private $curso;
    private $estudiante;
    private $attributes = [];
    
    public function __construct($curso = null, $estudiante = null)
    {
        $this->curso = $curso;
        $this->estudiante = $estudiante;
    }
    
    /**
     * Relación belongsTo hacia Curso
     */
    public function curso()
    {
        return $this->curso;
    }
    
    /**
     * Relación belongsTo hacia Estudiante
     */
    public function estudiante()
    {
        return $this->estudiante;
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
        if ($name === 'curso') {
            return $this->curso;
        }
        if ($name === 'estudiante') {
            return $this->estudiante;
        }
        return $this->getAttribute($name);
    }
}
