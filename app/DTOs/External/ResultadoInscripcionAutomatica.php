<?php

namespace App\DTOs\External;

class ResultadoInscripcionAutomatica
{
    /**
     * @param int $total_procesados
     * @param int $inscritos_exitosamente
     * @param int $alumnos_creados
     * @param int $ya_inscritos
     * @param array<int, array{rut: int, motivo: string}> $errores
     * @param array<int, array{cur_codigo: int, tipo: string, grupo: string, inscritos: int}> $componentes_procesadas
     */
    public function __construct(
        public readonly int $total_procesados,
        public readonly int $inscritos_exitosamente,
        public readonly int $alumnos_creados,
        public readonly int $ya_inscritos,
        public readonly array $errores = [],
        public readonly array $componentes_procesadas = []
    ) {}

    public function toArray(): array
    {
        return [
            'total_procesados'       => $this->total_procesados,
            'inscritos_exitosamente' => $this->inscritos_exitosamente,
            'alumnos_creados'        => $this->alumnos_creados,
            'ya_inscritos'           => $this->ya_inscritos,
            'errores'                => $this->errores,
            'componentes_procesadas' => $this->componentes_procesadas,
        ];
    }
}
