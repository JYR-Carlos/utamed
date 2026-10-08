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
     * Si esta entrega fue cancelada por el estudiante.
     *
     * La cancelación es una fila «Cancelación de entrega» del mismo grupo que
     * repite el `uuid_archivo_subido` de la entrega (ver
     * Student\AgendaController::destroyEntrega).
     */
    public function fueCancelada(): bool
    {
        return $this->tipo_mensaje === TipoMensaje::ENTREGA_DE_ARCHIVO
            && $this->uuid_archivo_subido !== null
            && in_array($this->uuid_archivo_subido, self::uuidsCancelados([$this->id_actividad_asignada_grupo]), true);
    }

    /**
     * UUIDs de los archivos entregados que fueron cancelados en esos grupos.
     *
     * @param  iterable<int>  $grupoIds
     * @return array<int, string>
     */
    public static function uuidsCancelados(iterable $grupoIds): array
    {
        $grupoIds = collect($grupoIds)->filter()->values();
        if ($grupoIds->isEmpty()) {
            return [];
        }

        return self::whereIn('id_actividad_asignada_grupo', $grupoIds)
            ->where('tipo_mensaje', TipoMensaje::CANCELACIÓN_DE_ENTREGA->value)
            ->whereNotNull('uuid_archivo_subido')
            ->pluck('uuid_archivo_subido')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Entregas de archivo que no fueron canceladas.
     *
     * `$tabla` es el nombre o alias con el que la consulta referencia
     * agenda.agenda, para poder usarlo también desde el query builder
     * (por ejemplo `DB::table('agenda.agenda as a')` → `'a'`).
     */
    public static function soloEntregasVigentes($query, string $tabla = 'agenda'): void
    {
        // El nombre de la tabla sale del modelo (lo fija el generador en BaseAgenda)
        // para que la subconsulta siga al esquema si la tabla cambia de nombre.
        $tablaAgenda = (new self)->getTable();

        $query->where("{$tabla}.tipo_mensaje", TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            ->whereNotExists(function ($sub) use ($tabla, $tablaAgenda) {
                $sub->selectRaw('1')
                    ->from("{$tablaAgenda} as cancelacion")
                    ->whereColumn('cancelacion.id_actividad_asignada_grupo', "{$tabla}.id_actividad_asignada_grupo")
                    ->whereColumn('cancelacion.uuid_archivo_subido', "{$tabla}.uuid_archivo_subido")
                    ->where('cancelacion.tipo_mensaje', TipoMensaje::CANCELACIÓN_DE_ENTREGA->value);
            });
    }

    /**
     * Scope Eloquent de {@see soloEntregasVigentes()}.
     */
    public function scopeEntregasVigentes($query)
    {
        self::soloEntregasVigentes($query, $this->getTable());

        return $query;
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