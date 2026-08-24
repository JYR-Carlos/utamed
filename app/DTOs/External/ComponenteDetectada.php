<?php

namespace App\DTOs\External;

class ComponenteDetectada
{
    /**
     * @param int $id_tipo_componente
     * @param string $tipo Nombre del tipo (Cátedra, Taller, Laboratorio)
     * @param 'INTRANET'|'PLAN' $origen
     * @param int|null $cur_codigo Código de acta en Oracle, sólo cuando origen=INTRANET
     */
    public function __construct(
        public readonly int $id_tipo_componente,
        public readonly string $tipo,
        public readonly string $origen,
        public readonly ?int $cur_codigo = null
    ) {}

    public function toArray(): array
    {
        return [
            'id_tipo_componente' => $this->id_tipo_componente,
            'tipo'               => $this->tipo,
            'origen'             => $this->origen,
            'cur_codigo'         => $this->cur_codigo,
        ];
    }
}
