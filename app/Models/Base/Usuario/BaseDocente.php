<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseDocente extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Docente';
    protected $primaryKey = 'id_docente';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'grado',
        'titulo',
        'cargo',
        'id_usuario'
    ];

    // Relaciones

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    // Relaciones inversas

    public function secciones()
    {
        return $this->hasMany(\App\Models\Curso\Seccion::class, 'id_docente', 'id_docente');
    }

}