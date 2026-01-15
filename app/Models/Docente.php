<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $table = 'utamed.docente';
    protected $primaryKey = 'id_docente';
    public $timestamps = false;

    protected $fillable = [
        'rut',
        'nombre_completo',
        'grado',
        'cargo',
    ];

    /**
     * Relación: Un docente tiene un usuario (1:1)
     */
    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'id_usuario', 'id_docente');
    }

    /**
     * Relación: Un docente tiene muchos cursos
     */
    public function cursos()
    {
        return $this->hasMany(Curso::class, 'id_docente', 'id_docente');
    }
}
