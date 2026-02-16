<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseVwUsuariosCompleto extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'vw_usuarios_completo';
    protected $primaryKey = 'id';
    public $incrementing = true;

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
        'cargo',
        'esta_activo'
    ];


}
