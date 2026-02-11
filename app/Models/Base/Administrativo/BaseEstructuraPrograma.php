<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseEstructuraPrograma extends Model
{
    use Compoships;

    protected $connection = 'pgsql';
    protected $table = 'Estructura_Programa';
    protected $primaryKey = 'id_seccion';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'nombre_seccion',
        'numeral_romano',
        'es_lista',
        'orden',
        'id_programa',
        'es_actual',
        'id_curso',
        'es_plantilla'
    ];

    // Relaciones
    public function programa()
    {
        return $this->belongsTo(
            \App\Models\Administrativo\Programa::class,
            ['id_programa', 'es_actual', 'id_curso', 'es_plantilla'],
            ['id_programa', 'es_actual', 'id_curso', 'es_plantilla']
        );
    }

    // Relaciones inversas
    public function contenidos_programa()
    {
        return $this->hasMany(\App\Models\Administrativo\ContenidoPrograma::class, 'id_seccion', 'id_seccion');
    }
}
