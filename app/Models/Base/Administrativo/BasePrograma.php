<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePrograma extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Programa';
    protected $primaryKey = 'id_programa';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'unc_programa',
        'es_actual',
        'id_usuario',
        'id_curso',
        'es_plantilla'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function curso()
    {
        return $this->belongsTo(\App\Models\Curso\Curso::class, ['id_curso', 'es_plantilla'], ['id_curso', 'es_plantilla']);
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}