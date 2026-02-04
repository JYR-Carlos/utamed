<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseDocente;

/**
 * Modelo Docente
 * 
 * Extiende de BaseDocente (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Docente extends BaseDocente
{
    protected $table = 'Docente';
    protected $fillable = [
        'rut',
        'nombre_completo',
        'grado',
        'titulo',
        'cargo',
        'id_usuario',
        'id_contexto'
    ];

    protected $appends = ['nombre_completo'];

    public function getNombreCompletoAttribute()
    {
        // If attributes are available directly (e.g. via join)
        if ($this->getAttribute('nombre1')) {
            return trim("{$this->nombre1} {$this->nombre2} {$this->apellido1} {$this->apellido2}");
        }

        // If loaded via relation
        if ($this->relationLoaded('usuario') && $this->usuario) {
            return trim("{$this->usuario->nombre1} {$this->usuario->nombre2} {$this->usuario->apellido1} {$this->usuario->apellido2}");
        }

        return 'Docente ' . $this->rut;
    }
}