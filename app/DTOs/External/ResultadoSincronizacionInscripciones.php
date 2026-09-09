<?php

namespace App\DTOs\External;

/**
 * Resultado de sincronizar el roster de un curso contra la Intranet.
 *
 * Envuelve el resultado de la mitad que inscribe ({@see ResultadoInscripcionAutomatica})
 * y le agrega lo que hace la mitad inversa: reactivar a quien volvió y retirar a
 * quien ya no figura en la Intranet.
 */
class ResultadoSincronizacionInscripciones
{
    /**
     * @param ResultadoInscripcionAutomatica $inscripcion Resultado de la mitad que inscribe.
     * @param array<int, array{rut: string|null, nombre: string|null}> $retirados
     *        Alumnos que dejaron de figurar en la Intranet y quedaron en RETIRADO.
     * @param array<int, array{rut: string|null, nombre: string|null}> $reactivados
     *        Alumnos que estaban RETIRADO/ANULADO y volvieron a figurar.
     * @param int $componentes_sin_respaldo Filas de inscripcion_componente cuyo alumno ya
     *        no figura en la Intranet. Se informan pero no se tocan: esa tabla todavía no
     *        tiene columna de estado y borrarlas perdería el historial de la componente.
     * @param bool $retiro_aplicado Si false, no se retiró a nadie a propósito: la lectura
     *        de la Intranet quedó incompleta y retirar con datos parciales daría de baja a
     *        alumnos que sí están inscritos.
     * @param array<int, string> $advertencias
     */
    public function __construct(
        public readonly ResultadoInscripcionAutomatica $inscripcion,
        public readonly array $retirados = [],
        public readonly array $reactivados = [],
        public readonly int $componentes_sin_respaldo = 0,
        public readonly bool $retiro_aplicado = true,
        public readonly array $advertencias = []
    ) {}

    public function toArray(): array
    {
        return [
            'inscripcion'              => $this->inscripcion->toArray(),
            'retirados'                => $this->retirados,
            'reactivados'              => $this->reactivados,
            'componentes_sin_respaldo' => $this->componentes_sin_respaldo,
            'retiro_aplicado'          => $this->retiro_aplicado,
            'advertencias'             => $this->advertencias,
        ];
    }
}
