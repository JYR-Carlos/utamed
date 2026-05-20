<?php

namespace App\Models\Base\Operaciones;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasContext;
use App\Traits\GlobalContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseArchivo extends CustomBaseModel implements HasContext
{
    use SoftDeletes;
    use Compoships;
    use GlobalContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';
    protected $connection = 'pgsql';
    protected $table = 'archivo';
    protected $primaryKey = 'uuid_archivo';
    public $incrementing = false;

    protected $fillable = [
        'uuid_archivo',
        'ruta_fisica',
        'nombre_original',
        'extension',
        'mime_type',
        'peso_bytes',
        'pendiente_de_borrado',
        'desaparecio_archivo'
    ];

    protected $casts = [
        'pendiente_de_borrado' => 'boolean',
        'desaparecio_archivo' => 'boolean'
    ];

    // Relaciones

    // Relaciones inversas

    public function actividades()
    {
        return $this->hasMany(\App\Models\Agenda\Actividad::class, 'uuid_archivo', 'uuid_archivo');
    }

    public function agendas()
    {
        return $this->hasMany(\App\Models\Agenda\Agenda::class, 'uuid_archivo_subido', 'uuid_archivo');
    }

}
