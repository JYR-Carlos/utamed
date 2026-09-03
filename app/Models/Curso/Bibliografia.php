<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseBibliografia;

/**
 * Modelo Bibliografia
 * 
 * Extiende de BaseBibliografia (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Bibliografia extends BaseBibliografia
{
    /**
     * Filtra los recursos que pertenecen a la biblioteca oficial de la UTA.
     */
    public function scopeBibliografiasUTA($query)
    {
        return $query->where('es_bibliografia_uta', true);
    }

    /**
     * Filtra los recursos locales o externos subidos a la plataforma UTAMED.
     */
    public function scopeBibliografiasUTAMED($query)
    {
        return $query->where('es_bibliografia_uta', false);
    }
}