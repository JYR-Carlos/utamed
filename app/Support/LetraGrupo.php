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

    /**
     * Inverso de fromIndice(): A=1, B=2, ..., Z=26, AA=27. Devuelve null si la
     * letra no tiene esa forma (la Intranet a veces trae grupos vacíos).
     */
    public static function toIndice(?string $letra): ?int
    {
        $letra = strtoupper(trim((string) $letra));
        if ($letra === '' || preg_match('/^[A-Z]+$/', $letra) !== 1) {
            return null;
        }

        $n = 0;
        foreach (str_split($letra) as $c) {
            $n = $n * 26 + (ord($c) - 64);
        }
        return $n;
    }
}
