<?php

namespace App\Models\Agenda;

use App\Models\Base\Agenda\BaseAgenda;

/**
 * Modelo Agenda
 * 
 * Extiende de BaseAgenda (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Agenda extends BaseAgenda
{
    /**
     * Obtiene el archivo asociado con la entrega
     */
    public function getArchivoInfo()
    {
        if (!$this->uuid_archivo_subido) {
            return null;
        }

        return [
            'uuid' => $this->archivo?->uuid_archivo,
            'nombre_original' => $this->archivo?->nombre_original,
            'extension' => $this->archivo?->extension,
            'mime_type' => $this->archivo?->mime_type,
            'peso_bytes' => $this->archivo?->peso_bytes,
            'ruta_fisica' => $this->archivo?->ruta_fisica,
            'fecha_creacion' => $this->archivo?->fecha_creacion,
        ];
    }

    /**
     * Obtiene evaluaciones relacionadas
     */
    public function tieneEvaluacion()
    {
        return $this->evaluacion !== null;
    }

    /**
     * Obtiene detalles completos de la entrega para el docente
     */
    public function getDetallesEntrega()
    {
        return [
            'id_agenda' => $this->id_agenda,
            'fecha_envio' => $this->fecha_envio,
            'mensaje' => $this->mensaje,
            'tipo_registro' => $this->tipo_mensaje?->value,
            'archivo' => $this->getArchivoInfo(),
            'usuario_emisor' => [
                'nombre' => trim(
                    ($this->usuario?->nombre1 ?? '') . ' ' .
                    ($this->usuario?->nombre2 ?? '') . ' ' .
                    ($this->usuario?->apellido1 ?? '') . ' ' .
                    ($this->usuario?->apellido2 ?? '')
                ),
                'rut' => $this->usuario?->rut,
            ],
            'evaluada' => $this->tieneEvaluacion(),
        ];
    }
}