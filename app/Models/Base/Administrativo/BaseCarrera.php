<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseCarrera extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Carrera';
    protected $primaryKey = 'id_carrera';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'nombre',
        'jornada',
        'sede',
        'modalidad',
        'id_departamento',
        'id_facultad'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    public function departamento()
    {
        return $this->belongsTo(\App\Models\Administrativo\Departamento::class, ['id_departamento', 'id_facultad'], ['id_departamento', 'id_facultad']);
    }

    // Relaciones inversas

    public function planes()
    {
        return $this->hasMany(\App\Models\Administrativo\Plan::class, 'id_carrera', 'id_carrera');
    }

    public function estudiantes()
    {
        return $this->hasMany(\App\Models\Usuario\Estudiante::class, 'id_carrera', 'id_carrera');
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}