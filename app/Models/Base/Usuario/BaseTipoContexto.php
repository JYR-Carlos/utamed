<?php

namespace App\Models\Base\Usuario;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseTipoContexto extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Tipo_Contexto';
    protected $primaryKey = ['id_tipo_contexto', 'id_contexto'];
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'categoria',
        'tabla_referenciada'
    ];

    // Relaciones

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

}