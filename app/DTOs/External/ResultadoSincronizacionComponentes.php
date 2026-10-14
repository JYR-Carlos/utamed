<?php

namespace App\DTOs\External;

class ResultadoSincronizacionComponentes
{
    /**
     * Resultado de EJECUTAR (crear en BD) sólo lo que el usuario aceptó
     * tras revisar el preview.
     *
     * @param 'INTRANET'|'PLAN' $origen
     * @param array<int, string> $componentes_creadas Nombres de tipo creados en este llamado.
     * @param array<int, string> $componentes_existentes Nombres de tipo que ya existían (no se duplicaron).
     * @param array<int, string> $advertencias
     * @param ResultadoInscripcionAutomatica|null $inscripcion Sólo si se pidió inscribir alumnos.
     */
    public function __construct(
        public readonly string $origen,
        public readonly array $componentes_creadas = [],
        public readonly array $componentes_existentes = [],
        public readonly array $advertencias = [],
        public readonly ?ResultadoInscripcionAutomatica $inscripcion = null
    ) {}

    public function toArray(): array
    {
        return [
            'origen'                  => $this->origen,
            'componentes_creadas'     => $this->componentes_creadas,
            'componentes_existentes'  => $this->componentes_existentes,
            'advertencias'            => $this->advertencias,
            'inscripcion'             => $this->inscripcion ? [
                'inscritos'      => $this->inscripcion->inscritos_exitosamente,
                'ya_inscritos'   => $this->inscripcion->ya_inscritos,
                'alumnos_creados'=> $this->inscripcion->alumnos_creados,
                'errores'        => count($this->inscripcion->errores),
            ] : null,
        ];
    }
}
