<?php

namespace App\Models\Agenda;

use App\Enums\DB\TipoMensaje;
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
            'fecha_creacion' => $this->archivo?->fecha_creacion,
            'visualizable' => (bool) $this->archivo?->esVisualizableEnNavegador(),
        ];
    }

    /**
     * Si esta entrega ya fue evaluada.
     *
     * La evaluación no cuelga de la fila de la entrega sino de su propia fila
     * «Evaluación» en agenda.agenda; el vínculo con la entrega evaluada es que
     * esa fila repite el `uuid_archivo_subido` de la entrega (ver
     * DocenteActivityController::storeEvaluacion). Para una fila «Evaluación»
     * devuelve si tiene su registro en agenda.evaluacion.
     */
    public function tieneEvaluacion(): bool
    {
        if ($this->tipo_mensaje !== TipoMensaje::ENTREGA_DE_ARCHIVO) {
            return $this->evaluacion !== null;
        }

        return $this->uuid_archivo_subido !== null
            && in_array($this->uuid_archivo_subido, self::uuidsEvaluados([$this->id_actividad_asignada_grupo]), true);
    }

    /**
     * UUIDs de los archivos entregados que ya tienen una evaluación en esos grupos.
     *
     * @param  iterable<int>  $grupoIds
     * @return array<int, string>
     */
    public static function uuidsEvaluados(iterable $grupoIds): array
    {
        $grupoIds = collect($grupoIds)->filter()->values();
        if ($grupoIds->isEmpty()) {
            return [];
        }

        return self::whereIn('id_actividad_asignada_grupo', $grupoIds)
            ->where('tipo_mensaje', TipoMensaje::EVALUACIÓN->value)
            ->whereNotNull('uuid_archivo_subido')
            ->pluck('uuid_archivo_subido')
            ->unique()
            ->values()
            ->all();
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