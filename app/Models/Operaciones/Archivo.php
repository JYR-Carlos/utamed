<?php

namespace App\Models\Operaciones;

use App\Models\Base\Operaciones\BaseArchivo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Modelo Archivo
 * 
 * Extiende de BaseArchivo (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Archivo extends BaseArchivo
{
    use HasUuids;

    /**
     * Le indica a Laravel qué columnas deben recibir un UUID automáticamente.
     */
    public function uniqueIds(): array
    {
        return ['uuid_archivo'];
    }

    /**
     * Fuerza a Laravel a generar un UUID versión 7 en lugar de la versión 4 por defecto.
     */
    public function newUniqueId(): string
    {
        return (string) \Illuminate\Support\Str::uuid7();
    }

    /**
     * Tipos que el navegador puede mostrar sin riesgo. Queda fuera todo lo que
     * pueda ejecutar código en nuestro origen (HTML, SVG, XML…): esos archivos
     * los sube un estudiante y sólo se entregan como descarga.
     */
    public const MIME_VISUALIZABLES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ];

    public function esVisualizableEnNavegador(): bool
    {
        return in_array(strtolower((string) $this->mime_type), self::MIME_VISUALIZABLES, true);
    }

    /**
     * Respuesta HTTP con el archivo: en línea si se pidió y el tipo lo permite,
     * como descarga en cualquier otro caso.
     */
    public function respuestaHttp(string $rutaAbsoluta, bool $enLinea = false): BinaryFileResponse
    {
        if ($enLinea && $this->esVisualizableEnNavegador()) {
            return response()->file($rutaAbsoluta, [
                'Content-Type' => $this->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->download($rutaAbsoluta, $this->nombre_original, [
            'Content-Type' => $this->mime_type,
        ]);
    }
}