<?php

namespace App\Models\Base\Curso;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseInscripcionSeccion extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Inscripcion_Seccion';
    protected $primaryKey = 'id_estudiante';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'nota_seccion',
        'AQUI AGREGAR ASISTENCIA',
        'id_seccion',
        'id_curso'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    public function estudiante()
    {
        return $this->belongsTo(\App\Models\Usuario\Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function seccion()
    {
        return $this->belongsTo(\App\Models\Curso\Seccion::class, ['id_seccion', 'id_curso'], ['id_seccion', 'id_curso']);
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}