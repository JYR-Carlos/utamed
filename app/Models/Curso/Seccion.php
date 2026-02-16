<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseSeccion;

/**
 * Modelo Seccion
 * 
 * Extiende de BaseSeccion (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Seccion extends BaseSeccion
{
    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
<<<<<<< HEAD

    /**
     * Override de la relación curso() de BaseSeccion.
     *
     * BaseSeccion usa Compoships con claves compuestas ['id_curso', 'es_plantilla'],
     * pero 'es_plantilla' NO es parte de la PK de Curso. Eso genera SQL inválido
     * en PostgreSQL: (Curso.id_curso, Curso.es_plantilla) IN (...) sin alias de tabla.
     *
     * Solución: usar belongsTo estándar de Eloquent con solo id_curso.
     */
    public function curso()
    {
        return $this->belongsTo(\App\Models\Curso\Curso::class, 'id_curso', 'id_curso');
    }

    /**
     * Relación con TipoSeccion
     */
    public function tipoSeccion()
    {
        return $this->belongsTo(\App\Models\Curso\TipoSeccion::class, 'id_tipo_seccion', 'id_tipo_seccion');
    }

    /**
     * Relación con InscripcionSeccion
     */
    public function inscripcionSecciones()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionSeccion::class, 'id_seccion', 'id_seccion');
    }
=======
>>>>>>> de6f84c (reseteando modelos temporalmente)
}