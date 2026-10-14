<?php

namespace App\Http\Controllers;


use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class IssuesReportController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'description' => ['required', 'string'],
            'expected'    => [],
            'actual'      => [],
            'image'       => ['required', 'image', 'max:10240'],
        ]);

        $reportId = (string) Str::uuid();
        $baseFolder = "reports/";
        $timestampReporte = now();

        $imageExtension = $request->file('image')->getClientOriginalExtension();
        $imageFileName = "evidence.{$imageExtension}";
        $imageRelativePath = "{$baseFolder}/evidences/{$timestampReporte}_{$user->rut}_{$imageFileName}";

        Storage::disk('local')->putFileAs($baseFolder.'/evidences/', $request->file('image'), "{$timestampReporte}_{$user->rut}_{$imageFileName}");

        $metadata = array_merge($validated, [
            'id'         => $reportId,
            'image_file' => $imageFileName,
            'created_at' => $timestampReporte->toIso8601String(),
            'rut_usuario' => $user->rut,
        ]);
        unset($metadata['image']);

        Storage::disk('local')->put(
            "{$baseFolder}/metadata/{$timestampReporte}_{$user->rut}_metadata.json", 
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // 4. Compilar el PDF pasando la ruta absoluta de la imagen
        $imageAbsolutePath = Storage::disk('local')->path($imageRelativePath);

        $pdf = Pdf::loadView('pdf.report', [
            'data'      => $metadata,
            'imagePath' => $imageAbsolutePath,
        ]);


        $pdfRelativePath = "{$baseFolder}/{$timestampReporte}_{$user->rut}_report.pdf";
        Storage::disk('local')->put($pdfRelativePath, $pdf->output());

        return redirect()->route('dashboard')->with('success', 'Reporte creado con éxito');
    }

    public function downloadPdf(string $id, string $filename)
    {
        $cleanId = basename($id);
        $cleanFilename = basename($filename);
        $path = "reports/{$cleanId}/{$cleanFilename}";

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        return Storage::download($path, $cleanFilename);
    }
}