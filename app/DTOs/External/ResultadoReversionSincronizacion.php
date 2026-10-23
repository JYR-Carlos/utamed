<?php

namespace App\DTOs\External;

class ResultadoReversionSincronizacion
{
    /**
     * @param int $id_log ID del log de sincronización revertido.
     * @param bool $exitosa
     * @param array<int, string> $componentes_eliminadas Tipos de componentes creadas que fueron eliminadas.
     * @param array<int, string> $componentes_restauradas Tipos de componentes cuyo cur_codigo volvió a NULL/anterior.
     * @param int $inscripciones_eliminadas Total de alumnos inscritos en la sincronización que fueron retirados/anulados.
     * @param int $inscripciones_restauradas Total de alumnos retirados o reactivados que volvieron a su estado previo.
     * @param array<int, string> $advertencias
     * @param string|null $mensaje
     */
    public function __construct(
        public readonly int $id_log,
        public readonly bool $exitosa,
        public readonly array $componentes_eliminadas = [],
        public readonly array $componentes_restauradas = [],
        public readonly int $inscripciones_eliminadas = 0,
        public readonly int $inscripciones_restauradas = 0,
        public readonly array $advertencias = [],
        public readonly ?string $mensaje = null
    ) {}

    public function toArray(): array
    {
        return [
            'id_log'                    => $this->id_log,
            'exitosa'                   => $this->exitosa,
            'componentes_eliminadas'    => $this->componentes_eliminadas,
            'componentes_restauradas'   => $this->componentes_restauradas,
            'inscripciones_eliminadas'  => $this->inscripciones_eliminadas,
            'inscripciones_restauradas' => $this->inscripciones_restauradas,
            'advertencias'              => $this->advertencias,
            'mensaje'                   => $this->mensaje,
        ];
    }
}
