<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'utamed.usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'fecha_creacion',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'fecha_creacion' => 'date',
    ];

    /**
     * Relación: Un usuario puede ser un estudiante (1:1)
     */
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_usuario', 'id_estudiante');
    }

    /**
     * Relación: Un usuario puede ser un docente (1:1)
     */
    public function docente()
    {
        return $this->belongsTo(Docente::class, 'id_usuario', 'id_docente');
    }

    /**
     * Relación: Un usuario tiene muchos registros de agenda
     */
    public function registrosAgenda()
    {
        return $this->hasMany(RegistroAgenda::class, 'autor', 'id_usuario');
    }
}
