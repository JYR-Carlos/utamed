<?php

namespace App\DTOs\External;

class ResultadoPreviewComponentes
{
    /**
     * Reporte de "mirar antes de tocar": no crea nada en la base de datos,
     * sólo arma lo que se le mostrará al usuario para que decida (revisar,
     * aceptar lo correcto o cancelar) antes de sincronizar de verdad.
     *
     * @param 'INTRANET'|'PLAN' $origen Fuente que finalmente se usará (Intranet manda si respondió).
     * @param array<int, ComponenteDetectada> $componentes
     * @param int|null $id_tipo_componente_principal Sugerido por prioridad (Cátedra > Taller > Laboratorio).
     * @param array<int, string> $advertencias
     */
    public function __construct(
        public readonly string $origen,
        public readonly array $componentes,
        public readonly ?int $id_tipo_componente_principal,
        public readonly array $advertencias = []
    ) {}

    public function toArray(): array
    {
        return [
            'origen'                        => $this->origen,
            'componentes'                   => array_map(fn(ComponenteDetectada $c) => $c->toArray(), $this->componentes),
            'id_tipo_componente_principal'  => $this->id_tipo_componente_principal,
            'advertencias'                  => $this->advertencias,
        ];
    }
}
