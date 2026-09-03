<?php

namespace App\Services\Archive\Handlers;

use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Services\Archive\ArchiveHandlerRequest;
use App\Services\Archive\ArchiveStorageResult;
use App\Services\Archive\SyllabusArchiveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Handler for Syllabus and Bibliography archive operations.
 *
 * Adapts Curso, Programa, and Bibliografia domain models to the generic Archive workflow.
 * Builds structured, deterministic paths organized by course and syllabus version.
 *
 * Paths shape:
 * - {periodo}/{curso}/{programa}/bibliografias/{unidad}/
 *
 * Usage:
 * ```php
 * $result = SyllabusArchiveHandler::storeBibliografia($curso, $file, $unidad, $programa);
 * ```
 */
class SyllabusArchiveHandler
{
    /**
     * Almacena un archivo físico de bibliografía con validación completa y trazabilidad.
     *
     * @param Curso $curso Curso al que pertenece el syllabus/bibliografía
     * @param UploadedFile $file Archivo físico subido
     * @param Unidad|null $unidad Unidad temática opcional
     * @param Programa|null $programa Programa/syllabus opcional (si ya existe o está en edición)
     * @param string|null $fileName Nombre explícito opcional (por defecto determinístico)
     * @return ArchiveStorageResult
     */
    public static function storeBibliografia(
        Curso $curso,
        UploadedFile $file,
        ?Unidad $unidad = null,
        ?Programa $programa = null,
        ?string $fileName = null
    ): ArchiveStorageResult {
        $relativeDirectory = self::buildPath($curso, $unidad, $programa);
        $finalFileName = $fileName ?: self::generateFileName();

        $request = new ArchiveHandlerRequest(
            file: $file,
            relativeDirectory: $relativeDirectory,
            fileName: $finalFileName
        );

        $archiveService = new SyllabusArchiveService();
        return $archiveService->performStorage($request);
    }

    /**
     * Construye la ruta relativa dentro del root 'syllabus'.
     *
     * Formato:
     * - {periodo}/{curso}/{programa}/bibliografias/{unidad}
     *
     * @param Curso $curso
     * @param Unidad|null $unidad
     * @param Programa|null $programa
     * @return string
     */
    public static function buildPath(
        Curso $curso,
        ?Unidad $unidad = null,
        ?Programa $programa = null
    ): string {
        $periodo = ($curso->agno_real && $curso->semestre_real)
            ? "{$curso->agno_real}-s{$curso->semestre_real}"
            : 'general';

        $cursoSegment = Str::slug($curso->nombre ?: "curso-{$curso->id_curso}");

        $programaSegment = $programa
            ? 'programa-' . Str::slug($programa->version_programa ?: (string) $programa->id_programa)
            : 'borrador';

        $unidadSegment = 'general';
        if ($unidad) {
            $num = $unidad->num_unidad ?: ($unidad->numero ?: 1);
            $nombre = $unidad->nombre ?: ($unidad->titulo ?: 'unidad');
            $unidadSegment = 'u' . $num . '-' . Str::slug($nombre);
        }

        return implode('/', [
            $periodo,
            $cursoSegment,
            $programaSegment,
            'bibliografias',
            $unidadSegment,
        ]);
    }

    /**
     * Genera un nombre de archivo base determinístico (sin extensión).
     *
     * La extensión segura la deduce AbstractArchiveService del contenido validado.
     *
     * @return string
     */
    private static function generateFileName(): string
    {
        $timestamp = now()->timestamp;
        $hash = Str::random(8);

        return "bib_{$timestamp}_{$hash}";
    }
}
