<?php

namespace App\Models\Agenda;

use App\Enums\DB\EstadoRubrica;
use App\Models\Base\Agenda\BaseRubrica;

/**
 * Modelo Rubrica
 * 
 * Extiende de BaseRubrica (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Rubrica extends BaseRubrica
{
    protected $fillable = [
        'rubrica',
        'estado_rubrica',
        'id_actividad',
    ];

    /**
     * Determina si esta rúbrica está bloqueada para edición por haber comenzado las evaluaciones.
     */
    public function estaBloqueadaParaEdicion(): bool
    {
        $estado = $this->estado_rubrica instanceof EstadoRubrica
            ? $this->estado_rubrica
            : EstadoRubrica::tryFrom((string) $this->estado_rubrica);

        if ($estado === EstadoRubrica::CERRADA) {
            return true;
        }

        return $this->evaluaciones()->exists();
    }
}