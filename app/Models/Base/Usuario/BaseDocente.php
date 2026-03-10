<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasContext;
use App\Traits\GlobalContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseDocente extends CustomBaseModel implements HasContext
{
    use Compoships;
    use GlobalContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'docente';
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
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario', 'id_usuario', 'usuario');
    }

    // Relaciones inversas

    public function seccionesQueDicta()
    {
        return $this->hasMany(\App\Models\Curso\Seccion::class, 'id_docente', 'id_docente');
    }

}
