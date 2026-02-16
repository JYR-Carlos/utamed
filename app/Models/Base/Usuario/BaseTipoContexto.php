<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseTipoContexto extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Tipo_Contexto';
    protected $primaryKey = ['id_tipo_contexto', 'id_contexto'];
    public $incrementing = false;

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
