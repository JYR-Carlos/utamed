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
     * @param array<int, string> $advertencias Avisos no bloqueantes (p.ej. componentes de
     *        Intranet sin equivalente en UTAMED) que antes se descartaban con un
     *        log silencioso; ahora se reportan al usuario.
     * @param array<int, int> $estudiantes_en_intranet id_estudiante de todos los alumnos
     *        que la Intranet devolvió para este curso. Es la foto contra la que la
     *        sincronización decide a quién retirar, y sale de aquí para no repetir la
     *        consulta a Oracle: dos lecturas separadas podrían ver rosters distintos.
     * @param bool $lectura_completa false si alguna componente no se pudo leer o no tiene
     *        equivalente en UTAMED. Con la lectura incompleta la foto tiene huecos, y
     *        retirar a partir de ella daría de baja a alumnos que sí están inscritos.
     */
    public function __construct(
        public readonly int $total_procesados,
        public readonly int $inscritos_exitosamente,
        public readonly int $alumnos_creados,
        public readonly int $ya_inscritos,
        public readonly array $errores = [],
        public readonly array $componentes_procesadas = [],
        public readonly array $advertencias = [],
        public readonly array $estudiantes_en_intranet = [],
        public readonly bool $lectura_completa = false
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
            'advertencias'           => $this->advertencias,
            'lectura_completa'       => $this->lectura_completa,
        ];
    }
}
