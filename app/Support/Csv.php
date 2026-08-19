<?php

namespace App\Support;

/**
 * Utilidades para escribir CSV que van a abrirse en una hoja de cálculo.
 *
 * `fputcsv()` produce CSV válido —escapa comillas y separadores— pero no protege
 * de la **inyección de fórmulas**: Excel y LibreOffice interpretan como fórmula
 * cualquier celda que empiece por `=`, `+`, `-` o `@`, de modo que un valor como
 * `=cmd|'/c calc'!A1` se ejecuta al abrir el archivo, con la máquina de quien lo
 * descarga como objetivo.
 *
 * En este sistema la cadena se cierra sola: la importación masiva de usuarios
 * acepta nombres desde un `.xlsx` sin sanear y la exportación de inscripciones se
 * los devuelve al administrador.
 */
final class Csv
{
    /**
     * Caracteres que convierten una celda en fórmula al abrir el archivo.
     *
     * Se incluyen tabulador y retornos de carro porque algunas versiones de Excel
     * los descartan al principio de la celda y evalúan lo que venga detrás.
     */
    private const PREFIJOS_PELIGROSOS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Neutraliza un valor de celda anteponiendo una comilla simple si empieza por
     * un carácter que dispara la evaluación de fórmulas.
     *
     * La comilla no aparece en la celda: la hoja de cálculo la interpreta como
     * "trata esto como texto".
     *
     * @param  mixed  $valor  Valor de la celda (escalar o null)
     * @return string Valor seguro para escribir con fputcsv()
     */
    public static function celda(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        $texto = (string) $valor;

        if (in_array(substr($texto, 0, 1), self::PREFIJOS_PELIGROSOS, true)) {
            return "'" . $texto;
        }

        return $texto;
    }

    /**
     * Aplica {@see self::celda()} a una fila completa.
     *
     * @param  array<int|string, mixed>  $fila
     * @return array<int|string, string>
     */
    public static function fila(array $fila): array
    {
        return array_map(static fn(mixed $valor): string => self::celda($valor), $fila);
    }
}
