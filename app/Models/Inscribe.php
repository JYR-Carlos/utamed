<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inscribe extends Model
{
    protected $table = 'utamed.inscribe';
    
    // Composite primary key
    protected $primaryKey = ['id_asignatura', 'id_estudiante', 'id_curso', 'num_intento'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_asignatura',
        'id_estudiante',
        'id_curso',
        'num_intento',
        'fecha_inscripcion',
        'estado_inscripcion',
        'promedio_parcial',
    ];

    protected $casts = [
        'fecha_inscripcion' => 'date',
    ];

    protected $attributes = [
        'num_intento' => 1,
        'estado_inscripcion' => 'i',
    ];

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * Relación: Una inscripción pertenece a un estudiante
     */
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    /**
     * Relación: Una inscripción pertenece a un curso
     */
    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'id_curso');
    }

    /**
     * Relación: Una inscripción tiene muchos registros de asistencia
     */
    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'id_curso', 'id_curso')
            ->where('id_estudiante', $this->id_estudiante)
            ->where('num_intento', $this->num_intento);
    }
}
