<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseEstudiante;
use App\Models\Usuario\Usuario;

/**
 * Modelo Estudiante
 * 
 * Extiende de BaseEstudiante (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Estudiante extends BaseEstudiante
{
    /**
     * Obtiene el nombre abreviado del estudiante.
     *
     * @example "JPérez"
     * 
     * @return string Nombre abreviado formado por la inicial del nombre y el apellido completo
     */
    public function nombreAbreviado(): string
    {
        /** @var Usuario $usuario */
        $usuario = $this->usuario;

        return $usuario->nombreAbreviado();
    }
}
