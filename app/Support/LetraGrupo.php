<?php

namespace App\Support;

class LetraGrupo
{
    /**
     * Convierte un índice de grupo (1, 2, 3...) a su letra (A, B, C...),
     * siguiendo la misma convención de columnas estilo Excel usada en
     * `cursoWizardModal.svelte` (intToLetters): 1=A, 2=B, ..., 26=Z, 27=AA...
     *
     * Debe mantenerse idéntica a esa función del frontend.
     */
    public static function fromIndice(?int $indice): string
    {
        $n = $indice ?? 0;
        $result = '';
        while ($n > 0) {
            $n -= 1;
            $result = chr(65 + ($n % 26)) . $result;
            $n = intdiv($n, 26);
        }
        return $result;
    }
}
