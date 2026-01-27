<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseFacultad extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Facultad';
    protected $primaryKey = 'id_facultad';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'nombre',
        'id_contexto'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    // Relaciones inversas

    public function departamentos()
    {
        return $this->hasMany(\App\Models\Administrativo\Departamento::class, 'id_facultad', 'id_facultad');
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}