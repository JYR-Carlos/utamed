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
    protected $table = 'utamed.vw_usuarios_completo';
    protected $primaryKey = 'id';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'id_usuario',
        'rut',
        'username',
        'nombre_completo',
        'email',
        'tipo_usuario',
        'id_estudiante',
        'agno_ingreso',
        'id_carrera',
        'carrera_nombre',
        'id_docente',
        'grado',
        'titulo',
        'cargo'
    ];

}