<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseVwUsuariosCompleto extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'vw_usuarios_completo';
    protected $primaryKey = 'id';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'rut',
        'username',
        'nombre_completo',
        'email',
        'tipo_usuario',
        'id_estudiante',
        'agno_ingreso',
        'carrera_nombre',
        'id_docente',
        'grado',
        'titulo',
        'cargo'
    ];

}