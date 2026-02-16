<?php

namespace App\Models\Base\Administrativo;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseVwUsuariosCompleto extends CustomBaseModel
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
        'id_carrera',
        'carrera_nombre',
        'id_docente',
        'grado',
        'titulo',
        'cargo',
        'esta_activo'
    ];


}
