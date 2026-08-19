<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Lectura por bloques del archivo de importación masiva de usuarios.
 *
 * Sustituye a `Excel::toArray()`, que cargaba el archivo entero en memoria antes
 * de procesar la primera fila: un `.xlsx` de 5 MB (el máximo que acepta la ruta)
 * descomprime a cientos de miles de filas. Aquí:
 *
 * - **Lectura por bloques** (`WithChunkReading`): en memoria sólo hay
 *   {@see self::TAMANO_BLOQUE} filas a la vez, sea cual sea el tamaño del archivo.
 * - **Tope duro de filas** (`WithLimit`): el lector se detiene en `maxFilas + 1`,
 *   y esa fila extra es la que permite distinguir "archivo completo" de "archivo
 *   demasiado grande" sin haberlo leído entero.
 * - **Rechazo, no truncado**: superar el tope lanza `ValidationException`, que
 *   deshace la transacción del controlador. Importar en silencio sólo las
 *   primeras N filas sería peor que fallar.
 *
 * El procesamiento de cada fila lo aporta quien construye la clase, de modo que
 * el mapeo, la validación y la inserción siguen viviendo en el controlador.
 */
class UsuariosImport implements ToCollection, WithChunkReading, WithStartRow, WithLimit
{
    /**
     * Filas que se leen y procesan por vuelta.
     */
    private const TAMANO_BLOQUE = 100;

    /**
     * Filas ya procesadas, para numerar los errores como los ve el usuario en Excel.
     */
    private int $procesadas = 0;

    /**
     * @param  \Closure(array, int): void  $procesarFila  Recibe la fila y su número en Excel
     * @param  int  $maxFilas  Tope de filas de datos admitidas
     */
    public function __construct(
        private readonly \Closure $procesarFila,
        private readonly int $maxFilas
    ) {
    }

    /**
     * Procesa un bloque de filas.
     *
     * @param  Collection<int, Collection<int, mixed>>  $rows
     *
     * @throws ValidationException Si el archivo supera el tope de filas
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $fila) {
            $fila = $fila instanceof Collection ? $fila->toArray() : (array) $fila;

            // Filas totalmente vacías (habituales al final de una hoja) no cuentan
            // ni para el tope ni para el contador de importados.
            if (empty(array_filter($fila, static fn($valor) => $valor !== null && $valor !== ''))) {
                continue;
            }

            $this->procesadas++;

            if ($this->procesadas > $this->maxFilas) {
                throw ValidationException::withMessages([
                    'file' => "El archivo supera el máximo de {$this->maxFilas} filas por importación. "
                        . 'Divídelo en varios archivos.',
                ]);
            }

            // +1 por la fila de encabezados que descarta startRow().
            ($this->procesarFila)($fila, $this->procesadas + 1);
        }
    }

    /**
     * Filas de datos efectivamente importadas.
     */
    public function totalProcesadas(): int
    {
        return $this->procesadas;
    }

    /**
     * La primera fila son los encabezados.
     */
    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return self::TAMANO_BLOQUE;
    }

    /**
     * Una fila más que el tope: leer esa fila extra es lo que permite detectar el
     * exceso en vez de truncar sin avisar.
     */
    public function limit(): int
    {
        return $this->maxFilas + 1;
    }
}
