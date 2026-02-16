<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuarioPermisoEspecial extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Usuario_Permiso_Especial';
    protected $primaryKey = ['id_permiso', 'id_contexto', 'id_usuario'];
    public $incrementing = false;

    protected $fillable = [
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'esta_permitido',
        'puede_delegar',
        'fecha_fin_real',
        'fue_borrado',
        'creado_por',
        'eliminado_por'
    ];


    // Relaciones

    public function receptor()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function permiso()
    {
        return $this->belongsTo(\App\Models\Usuario\Permiso::class, 'id_permiso', 'id_permiso');
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
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
