<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseEstudiante;

/**
 * Modelo Estudiante
 * 
 * Extiende de BaseEstudiante (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Estudiante extends BaseEstudiante
{
    protected $fillable = [
        'rut',
        'nombre_completo',
        'agno_ingreso',
        'id_carrera',
        'id_usuario',
        'id_contexto'
    ];
}