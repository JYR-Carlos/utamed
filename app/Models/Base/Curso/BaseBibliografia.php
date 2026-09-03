<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseBibliografia extends CustomBaseModel
{
    use Compoships;
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;
    protected $connection = 'pgsql';
    protected $table = 'bibliografia';
    protected $primaryKey = 'uuid_bibliografia';
    public $incrementing = false;

    protected $fillable = [
        'uuid_bibliografia',
        'id_programa',
        'id_unidad',
        'titulo',
        'autor',
        'cita',
        'editorial',
        'agno',
        'url',
        'uuid_archivo',
        'es_bibliografia_uta',
        'agregado_por'
    ];

    protected $casts = [
        'es_bibliografia_uta' => 'boolean'
    ];

    // Relaciones

    public function programa()
    {
        $instance = new \App\Models\Curso\Programa();
        return new BelongsTo($instance->newQuery(), $this, 'id_programa', 'id_programa', 'programa');
    }

    public function unidad()
    {
        $instance = new \App\Models\Curso\Unidad();
        return new BelongsTo($instance->newQuery(), $this, 'id_unidad', 'id_unidad', 'unidad');
    }

    public function agregador()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'agregado_por', 'id_usuario', 'agregador');
    }

    public function archivo()
    {
        $instance = new \App\Models\Operaciones\Archivo();
        return new BelongsTo($instance->newQuery(), $this, 'uuid_archivo', 'uuid_archivo', 'archivo');
    }

}
