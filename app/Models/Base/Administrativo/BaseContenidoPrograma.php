<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseContenidoPrograma extends Model
{
    use Compoships;

    protected $connection = 'pgsql';
    protected $table = 'Contenido_Programa';
    protected $primaryKey = 'id_contenido_programa';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'texto_contenido',
        'valor_numerico',
        'orden_item',
        'id_seccion'
    ];

    // Relaciones
    public function estructura_programa()
    {
        return $this->belongsTo(\App\Models\Administrativo\EstructuraPrograma::class, 'id_seccion', 'id_seccion');
    }
}
