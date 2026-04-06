<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseDocenteComponente;

/**
 * Modelo DocenteComponente
 * 
 * Extiende de BaseDocenteComponente (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class DocenteComponente extends BaseDocenteComponente
{
    protected $fillable = [
        'id_docente',
        'id_componente',
        'es_titular',
    ];
}