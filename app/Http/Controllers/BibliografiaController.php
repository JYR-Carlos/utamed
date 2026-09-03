<?php

namespace App\Http\Controllers;

use App\Models\Curso\Bibliografia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BibliografiaController extends Controller
{
    /**
     * Muestra la información de la bibliografía en formato JSON.
     */
    public function show(string $id_bibliografia)
    {
        $bibliografia = Bibliografia::findOrFail($id_bibliografia);

        // TODO: Validar que el usuario tenga acceso al programa/curso asociado
        // Ej: $this->authorize('view', $bibliografia);

        return response()->json([
            'success' => true,
            'data' => $bibliografia
        ]);
    }

    /**
     * Visualiza el archivo físico en el navegador (ej: PDF).
     */
    public function showArchivo(string $id_bibliografia)
    {
        $bibliografia = Bibliografia::findOrFail($id_bibliografia);

        // TODO: Validar acceso
        // $this->authorize('view', $bibliografia);

        if ($bibliografia->es_bibliografia_uta || !$bibliografia->uuid_archivo) {
            abort(404, 'Esta bibliografía no tiene un archivo físico asociado.');
        }

        // Asumiendo que se guardan en storage/app/bibliografias/
        $path = 'bibliografias/' . $bibliografia->uuid_archivo;

        if (!Storage::exists($path)) {
            abort(404, 'Archivo no encontrado en el servidor.');
        }

        return response()->file(Storage::path($path));
    }

    /**
     * Descarga el archivo físico forzadamente.
     */
    public function downloadArchivo(string $id_bibliografia)
    {
        $bibliografia = Bibliografia::findOrFail($id_bibliografia);

        // TODO: Validar acceso
        // $this->authorize('view', $bibliografia);

        if ($bibliografia->es_bibliografia_uta || !$bibliografia->uuid_archivo) {
            abort(404, 'Esta bibliografía no tiene un archivo físico asociado para descargar.');
        }

        $path = 'bibliografias/' . $bibliografia->uuid_archivo;

        if (!Storage::exists($path)) {
            abort(404, 'Archivo no encontrado en el servidor.');
        }

        // Descarga con el nombre original o título
        $filename = \Illuminate\Support\Str::slug($bibliografia->titulo) . '.pdf'; 

        return Storage::download($path, $filename);
    }
}
