<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseTipoContexto extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'utamed.Tipo_Contexto';
    protected $primaryKey = 'id_tipo_contexto';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'categoria',
        'tabla_referenciada',
        'id_contexto'
    ];

    // Relaciones

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

}