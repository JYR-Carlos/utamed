<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseDocenteComponente extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'docente_componente';
    protected $primaryKey = 'id_docente_componente';
    public $incrementing = true;

    protected $fillable = [
        'id_docente',
        'id_componente'
    ];


    // Relaciones

    public function docente()
    {
        $instance = new \App\Models\Usuario\Docente();
        return new BelongsTo($instance->newQuery(), $this, 'id_docente', 'id_docente', 'docente');
    }

    public function componente()
    {
        $instance = new \App\Models\Curso\Componente();
        return new BelongsTo($instance->newQuery(), $this, 'id_componente', 'id_componente', 'componente');
    }

}
