<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseInteraccionMensaje;

/**
 * Modelo InteraccionMensaje
 *
 * Extiende de BaseInteraccionMensaje (auto-generado)
 *
 * Acuse de lectura: existe una fila por (mensaje, lector) cuando ese usuario
 * abrió el mensaje. Si no hay fila, nunca lo vio. Borrar la fila (soft delete)
 * lo vuelve a marcar como no leído.
 */
class InteraccionMensaje extends BaseInteraccionMensaje
{
    // Sin personalizaciones por ahora.
}
