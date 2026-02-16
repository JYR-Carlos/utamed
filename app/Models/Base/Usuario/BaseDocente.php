<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseDocente extends CustomBaseModel implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Docente';
    protected $primaryKey = 'id_docente';
    public $incrementing = true;

    protected $fillable = [
        'grado',
        'titulo',
        'cargo',
        'id_usuario'
    ];


    // Relaciones

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    // Relaciones inversas

    public function seccionesQueDicta()
    {
        return $this->hasMany(\App\Models\Curso\Seccion::class, 'id_docente', 'id_docente');
    }

}
