<?php

namespace App\Models\Base\Curso;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseSeccion extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'utamed.Seccion';
    protected $primaryKey = 'id_seccion';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = [
        'es_plantilla',
        'id_tipo_seccion',
        'id_docente'
    ];

    // Relaciones

    public function tipoSeccion()
    {
        return $this->belongsTo(\App\Models\Curso\TipoSeccion::class, 'id_tipo_seccion', 'id_tipo_seccion');
    }

    public function docente()
    {
        return $this->belongsTo(\App\Models\Usuario\Docente::class, 'id_docente', 'id_docente');
    }

    public function curso()
    {
        return $this->belongsTo(\App\Models\Curso\Curso::class, 'id_curso', 'id_curso');
    }

    // Relaciones inversas

    public function actividades()
    {
        return $this->hasMany(\App\Models\Agenda\Actividad::class, ['id_seccion', 'id_curso', 'es_plantilla'], ['id_seccion', 'id_curso', 'es_plantilla']);
    }

    public function inscripcionSecciones()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionSeccion::class, 'id_seccion', 'id_seccion');
    }

    // Relaciones muchos-a-muchos

    public function estudiantesInscritos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            '\"utamed.Curso\".\"Inscripcion_Seccion\"',
            'id_seccion,id_curso',
            'id_estudiante'
        )
        ->withPivot('nota_seccion');
    }

}