<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuarioPermisoEspecial extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Usuario_Permiso_Especial';
    protected $primaryKey = 'id_permiso';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'esta_permitido',
        'puede_delegar',
        'fecha_fin_real',
        'fue_borrado',
        'id_contexto',
        'id_usuario_recipiente',
        'id_usuario_asignador'
    ];

    // Relaciones

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_recipiente', 'id_usuario');
    }

    public function permiso()
    {
        return $this->belongsTo(\App\Models\Usuario\Permiso::class, 'id_permiso', 'id_permiso');
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    public function usuario1()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_asignador', 'id_usuario');
    }

}