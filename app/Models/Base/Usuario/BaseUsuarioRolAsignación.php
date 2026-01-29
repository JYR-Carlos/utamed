<?php

namespace App\Models\Base\Usuario;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuarioRolAsignación extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Usuario_Rol_Asignación';
    protected $primaryKey = ['id_contexto', 'id_rol', 'id_usuario_recipiente', 'id_usuario_asignador'];
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'asignado_por',
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'fecha_fin_real',
        'fue_eliminado'
    ];

    // Relaciones

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_recipiente', 'id_usuario');
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    public function rol()
    {
        return $this->belongsTo(\App\Models\Usuario\Rol::class, 'id_rol', 'id_rol');
    }

    public function usuario1()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_asignador', 'id_usuario');
    }

}