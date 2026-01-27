<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseDepartamento extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Departamento';
    protected $primaryKey = 'id_departamento';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'nombre',
        'id_facultad'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    public function facultad()
    {
        return $this->belongsTo(\App\Models\Administrativo\Facultad::class, 'id_facultad', 'id_facultad');
    }

    // Relaciones inversas

    public function carreras()
    {
        return $this->hasMany(\App\Models\Administrativo\Carrera::class, ['id_departamento', 'id_facultad'], ['id_departamento', 'id_facultad']);
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}