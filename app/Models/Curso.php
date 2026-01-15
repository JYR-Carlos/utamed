<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $table = 'utamed.curso';
    
    // Composite primary key
    protected $primaryKey = ['id_curso', 'id_asignatura'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_curso',
        'id_asignatura',
        'cod_curso',
        'nombre',
        'fecha_inicio',
        'numero_semestre',
        'id_docente',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
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
     * Relación: Un curso pertenece a una asignatura
     */
    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    /**
     * Relación: Un curso pertenece a un docente
     */
    public function docente()
    {
        return $this->belongsTo(Docente::class, 'id_docente', 'id_docente');
    }

    /**
     * Relación: Un curso tiene muchas inscripciones
     */
    public function inscripciones()
    {
        return $this->hasMany(Inscribe::class, 'id_curso', 'id_curso');
    }

    /**
     * Relación: Un curso tiene muchas unidades
     */
    public function unidades()
    {
        return $this->hasMany(Unidad::class, 'id_curso', 'id_curso');
    }

    /**
     * Relación: Un curso tiene muchas actividades
     */
    public function actividades()
    {
        return $this->hasMany(Actividad::class, 'id_curso', 'id_curso');
    }
}
