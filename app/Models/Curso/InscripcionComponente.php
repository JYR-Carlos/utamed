<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseInscripcionComponente;

/**
 * Modelo InscripcionComponente
 * 
 * Extiende de BaseInscripcionComponente (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class InscripcionComponente extends BaseInscripcionComponente
{
    /**
     * Alias del código de inscripción de Intranet (INS_ID).
     */
    public function getInsIdAttribute(): ?int
    {
        return $this->cod_inscripcion_curso_uta !== null && is_numeric($this->cod_inscripcion_curso_uta) && (int) $this->cod_inscripcion_curso_uta > 0
            ? (int) $this->cod_inscripcion_curso_uta
            : null;
    }
}