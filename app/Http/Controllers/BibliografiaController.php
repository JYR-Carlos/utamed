<?php

namespace App\Http\Controllers;

use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\FileValidationException;
use App\Exceptions\Archive\StorageException;
use App\Exceptions\Archive\VirusDetectedException;
use App\Http\Requests\Archive\BibliografiaFileRequest;
use App\Models\Curso\Bibliografia;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Services\Archive\Handlers\SyllabusArchiveHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BibliografiaController extends Controller
{
    /**
     * Muestra la información de la bibliografía en formato JSON.
     */
    public function show(string $id_bibliografia)
    {
        $bibliografia = Bibliografia::where('uuid_bibliografia', $id_bibliografia)
            ->orWhere('uuid_archivo', $id_bibliografia)
            ->firstOrFail();

        if ($bibliografia->programa?->curso) {
            $this->authorize('viewPrograma', $bibliografia->programa->curso);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'uuid_bibliografia' => $bibliografia->uuid_bibliografia,
                'id_programa' => $bibliografia->id_programa,
                'id_unidad' => $bibliografia->id_unidad,
                'titulo' => $bibliografia->titulo,
                'autor' => $bibliografia->autor,
                'cita' => $bibliografia->cita,
                'anio' => $bibliografia->agno,
                'es_bibliografia_uta' => $bibliografia->es_bibliografia_uta,
                'url' => $bibliografia->url,
                'uuid_archivo' => $bibliografia->uuid_archivo,
                'tiene_archivo' => $bibliografia->uuid_archivo !== null,
            ],
        ]);
    }

    /**
     * Sube un archivo físico asociado a un curso y syllabus mediante SyllabusArchiveHandler.
     * POST /api/bibliografias/archivo
     */
    public function uploadArchivo(BibliografiaFileRequest $request)
    {
        $curso = Curso::findOrFail($request->getCursoId());
        $this->authorize('viewPrograma', $curso);

        $unidad = null;
        if ($request->getUnidadId()) {
            $numUnidad = (int) $request->getUnidadId();

            $unidad = Unidad::where('id_curso', $curso->id_curso)
                ->where('num_unidad', $numUnidad)
                ->first();

            if (!$unidad) {
                $unidad = new Unidad([
                    'num_unidad' => $numUnidad,
                    'nombre' => 'unidad',
                ]);
            }
        }

        $programa = null;
        if ($request->getProgramaId()) {
            $programa = Programa::where('id_curso', $curso->id_curso)
                ->where('id_programa', $request->getProgramaId())
                ->first();
        }

        try {
            $result = SyllabusArchiveHandler::storeBibliografia(
                curso: $curso,
                file: $request->file('archivo'),
                unidad: $unidad,
                programa: $programa,
                fileName: $request->getFileName()
            );

            return response()->json([
                'success' => true,
                'uuid_archivo' => $result->uuidArchivo,
                'nombre_original' => $result->originalName,
                'file_name' => $result->fileName,
                'size_bytes' => $result->sizeBytes,
                'mime_type' => $result->mimeType,
            ]);
        } catch (FileValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo no es válido: ' . $e->getMessage(),
                'error_type' => $e->errorType->value,
            ], 422);
        } catch (VirusDetectedException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Alerta de seguridad: se detectó un virus en el archivo.',
            ], 422);
        } catch (CompressionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 422);
        } catch (StorageException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de almacenamiento: ' . $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            Log::error('[BibliografiaController::uploadArchivo] Error inesperado', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error inesperado al almacenar el archivo.',
            ], 500);
        }
    }

    /**
     * Visualiza el archivo físico en el navegador (ej: PDF).
     */
    public function showArchivo(string $id_bibliografia)
    {
        $bibliografia = Bibliografia::with(['archivo', 'programa.curso'])
            ->where('uuid_bibliografia', $id_bibliografia)
            ->orWhere('uuid_archivo', $id_bibliografia)
            ->firstOrFail();

        if ($bibliografia->es_bibliografia_uta || !$bibliografia->uuid_archivo) {
            abort(404, 'Esta bibliografía no tiene un archivo físico asociado.');
        }

        if ($bibliografia->programa?->curso) {
            $this->authorize('viewPrograma', $bibliografia->programa->curso);
        }

        $archivo = $bibliografia->archivo;
        if (!$archivo) {
            abort(404, 'Registro de archivo no encontrado.');
        }

        $disk = config('files.storage.disk', 'local_archives');
        if (!Storage::disk($disk)->exists($archivo->ruta_fisica)) {
            abort(404, 'El archivo físico no fue encontrado en el disco de almacenamiento.');
        }

        return Storage::disk($disk)->response($archivo->ruta_fisica, $archivo->nombre_original, [
            'Content-Type' => $archivo->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($archivo->nombre_original) . '"',
        ]);
    }

    /**
     * Descarga el archivo físico forzadamente.
     */
    public function downloadArchivo(string $id_bibliografia)
    {
        $bibliografia = Bibliografia::with(['archivo', 'programa.curso'])
            ->where('uuid_bibliografia', $id_bibliografia)
            ->orWhere('uuid_archivo', $id_bibliografia)
            ->firstOrFail();

        if ($bibliografia->es_bibliografia_uta || !$bibliografia->uuid_archivo) {
            abort(404, 'Esta bibliografía no tiene un archivo físico asociado para descargar.');
        }

        if ($bibliografia->programa?->curso) {
            $this->authorize('viewPrograma', $bibliografia->programa->curso);
        }

        $archivo = $bibliografia->archivo;
        if (!$archivo) {
            abort(404, 'Registro de archivo no encontrado.');
        }

        $disk = config('files.storage.disk', 'local_archives');
        if (!Storage::disk($disk)->exists($archivo->ruta_fisica)) {
            abort(404, 'El archivo físico no fue encontrado en el disco de almacenamiento.');
        }

        $extension = $archivo->extension ?: 'pdf';
        $downloadName = $archivo->nombre_original ?: (Str::slug($bibliografia->titulo) . '.' . $extension);

        return Storage::disk($disk)->download($archivo->ruta_fisica, $downloadName);
    }
}
