<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuarioRolAsignación extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Usuario_Rol_Asignación';
    protected $primaryKey = ['id_contexto', 'id_rol', 'id_usuario'];
    public $incrementing = false;

    protected $fillable = [
        'asignado_por',
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'fecha_fin_real',
        'fue_eliminado',
        'creado_por',
        'eliminado_por'
    ];


    // Relaciones

    public function receptor()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    public function rol()
    {
        return $this->belongsTo(\App\Models\Usuario\Rol::class, 'id_rol', 'id_rol');
    }

    public function asignador()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'creado_por', 'id_usuario');
    }

    public function borrador()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'eliminado_por', 'id_usuario');
    }

}
