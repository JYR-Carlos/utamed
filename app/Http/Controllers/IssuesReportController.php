<?php

namespace App\Http\Controllers;

use App\Models\Usuario\Usuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class IssuesReportController extends Controller
{
    public function index() 
    {
        $reportIds = [];

        // Descubrir reportes a partir de PDFs existentes
        $files = Storage::disk('local')->files('reports');
        foreach ($files as $file) {
            $basename = basename($file);
            if (str_ends_with($basename, '_report.pdf')) {
                $reportIds[] = str_replace('_report.pdf', '', $basename);
            }
        }

        // Descubrir reportes a partir de metadatos existentes
        $metaFiles = Storage::disk('local')->files('reports/metadata');
        foreach ($metaFiles as $file) {
            $basename = basename($file);
            if (str_ends_with($basename, '_metadata.json')) {
                $reportIds[] = str_replace('_metadata.json', '', $basename);
            }
        }

        $reportIds = array_values(array_unique($reportIds));
        // Orden descendente por identificador (comienza con timestamp)
        rsort($reportIds);

        $reportsData = [];
        $ruts = [];

        foreach ($reportIds as $id) {
            $cleanId = basename($id);
            $metaPath = "reports/metadata/{$cleanId}_metadata.json";
            $pdfPath = "reports/{$cleanId}_report.pdf";

            $metadata = null;
            if (Storage::disk('local')->exists($metaPath)) {
                $metadata = json_decode(Storage::disk('local')->get($metaPath), true);
            }

            $rut = $metadata['rut_usuario'] ?? null;
            if (!$rut && str_contains($cleanId, '_')) {
                $parts = explode('_', $cleanId);
                $rut = end($parts);
            }

            if ($rut) {
                $ruts[] = $rut;
            }

            $hasPdf = Storage::disk('local')->exists($pdfPath);

            // Verificar si hay imagen de evidencia
            $hasEvidence = false;
            $evidenceFileName = null;
            if (!empty($metadata['image_file'])) {
                $candidate = "reports/evidences/{$cleanId}_{$metadata['image_file']}";
                if (Storage::disk('local')->exists($candidate)) {
                    $hasEvidence = true;
                    $evidenceFileName = basename($candidate);
                }
            }
            if (!$hasEvidence) {
                $evidences = Storage::disk('local')->files('reports/evidences');
                foreach ($evidences as $ev) {
                    if (str_starts_with(basename($ev), "{$cleanId}_")) {
                        $hasEvidence = true;
                        $evidenceFileName = basename($ev);
                        break;
                    }
                }
            }

            $reportsData[] = [
                'id' => $cleanId,
                'rut_usuario' => $rut,
                'metadata' => $metadata,
                'has_pdf' => $hasPdf,
                'has_evidence' => $hasEvidence,
                'evidence_filename' => $evidenceFileName,
                'created_at' => $metadata['created_at'] ?? (str_contains($cleanId, '_') ? explode('_', $cleanId)[0] : null),
            ];
        }

        // Cargar usuarios en una sola consulta para evitar N+1
        $usuarios = Usuario::whereIn('rut', array_unique(array_filter($ruts)))
            ->get(['rut', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'email'])
            ->keyBy('rut');

        $reports = array_map(function ($item) use ($usuarios) {
            $user = $item['rut_usuario'] ? ($usuarios[$item['rut_usuario']] ?? null) : null;
            return [
                'id' => $item['id'],
                'metadata' => $item['metadata'] ?? [
                    'title' => 'Reporte ' . $item['id'],
                    'description' => 'Sin metadatos registrados.',
                    'expected' => null,
                    'actual' => null,
                ],
                'usuario' => $user ? [
                    'rut' => $user->rut,
                    'nombre_completo' => $user->nombre_completo,
                    'email' => $user->email,
                ] : ($item['rut_usuario'] ? [
                    'rut' => $item['rut_usuario'],
                    'nombre_completo' => 'RUT: ' . $item['rut_usuario'],
                    'email' => null,
                ] : null),
                'has_pdf' => $item['has_pdf'],
                'has_evidence' => $item['has_evidence'],
                'created_at' => $item['created_at'],
            ];
        }, $reportsData);

        return Inertia::render('admin/ReportesBugs', [
            'reports' => $reports,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'description' => ['required', 'string'],
            'expected'    => ['nullable', 'string'],
            'actual'      => ['nullable', 'string'],
            'image'       => ['nullable', 'image', 'max:10240'],
        ]);

        $reportId = (string) Str::uuid();
        $baseFolder = "reports/";
        $timestampReporte = now();

        $imageFileName = null;
        $imageAbsolutePath = null;

        if ($request->hasFile('image')) {
            $imageExtension = $request->file('image')->getClientOriginalExtension();
            $imageFileName = "evidence.{$imageExtension}";
            $imageRelativePath = "{$baseFolder}evidences/{$timestampReporte}_{$user->rut}_{$imageFileName}";
            Storage::disk('local')->putFileAs($baseFolder . 'evidences/', $request->file('image'), "{$timestampReporte}_{$user->rut}_{$imageFileName}");
            $imageAbsolutePath = Storage::disk('local')->path($imageRelativePath);
        }

        $metadata = array_merge($validated, [
            'id'          => $reportId,
            'image_file'  => $imageFileName,
            'created_at'  => $timestampReporte->toIso8601String(),
            'rut_usuario' => $user->rut,
        ]);
        unset($metadata['image']);

        Storage::disk('local')->put(
            "{$baseFolder}metadata/{$timestampReporte}_{$user->rut}_metadata.json", 
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $pdf = Pdf::loadView('pdf.report', [
            'data'      => $metadata,
            'imagePath' => $imageAbsolutePath,
        ]);

        $pdfRelativePath = "{$baseFolder}{$timestampReporte}_{$user->rut}_report.pdf";
        Storage::disk('local')->put($pdfRelativePath, $pdf->output());

        return redirect()->back()->with('success', 'Reporte creado con éxito');
    }

    public function downloadPdf(string $id, ?string $filename = null)
    {
        $cleanId = basename($id);
        $path = str_ends_with($cleanId, '_report.pdf') 
            ? "reports/{$cleanId}" 
            : "reports/{$cleanId}_report.pdf";

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        return Storage::disk('local')->download($path, basename($path));
    }

    public function downloadEvidence(string $id)
    {
        $cleanId = basename($id);
        $files = Storage::disk('local')->files('reports/evidences');
        $evidencePath = null;

        foreach ($files as $file) {
            if (str_starts_with(basename($file), "{$cleanId}_")) {
                $evidencePath = $file;
                break;
            }
        }

        if (!$evidencePath || !Storage::disk('local')->exists($evidencePath)) {
            abort(404, 'Evidencia no encontrada');
        }

        return Storage::disk('local')->response($evidencePath);
    }

    public function destroy(string $id)
    {
        $cleanId = basename($id);

        if (empty($cleanId) || $cleanId === '.' || $cleanId === '..') {
            abort(404, 'Reporte no encontrado');
        }

        $baseId = preg_replace('/(_report\.pdf|_metadata\.json)$/', '', $cleanId);

        $filesToDelete = [];

        // 1. PDF del reporte: "reports/{$cleanId}_report.pdf" (y "reports/{$cleanId}" si viniera con sufijo)
        $pdfCandidates = array_unique([
            "reports/{$cleanId}_report.pdf",
            "reports/{$cleanId}",
            "reports/{$baseId}_report.pdf",
        ]);

        foreach ($pdfCandidates as $candidate) {
            if (Storage::disk('local')->exists($candidate) && !Storage::disk('local')->directoryExists($candidate)) {
                $filesToDelete[] = $candidate;
            }
        }

        // 2. Archivo de metadatos: "reports/metadata/{$cleanId}_metadata.json"
        $metaCandidates = array_unique([
            "reports/metadata/{$cleanId}_metadata.json",
            "reports/metadata/{$baseId}_metadata.json",
        ]);

        foreach ($metaCandidates as $candidate) {
            if (Storage::disk('local')->exists($candidate) && !Storage::disk('local')->directoryExists($candidate)) {
                $filesToDelete[] = $candidate;
            }
        }

        // 3. Imágenes de evidencia: cualquier archivo que comience con "{$cleanId}_" en "reports/evidences/"
        $evidenceFiles = Storage::disk('local')->files('reports/evidences');
        foreach ($evidenceFiles as $file) {
            $fileBasename = basename($file);
            if (str_starts_with($fileBasename, "{$cleanId}_") || str_starts_with($fileBasename, "{$baseId}_")) {
                $filesToDelete[] = $file;
            }
        }

        $filesToDelete = array_values(array_unique($filesToDelete));

        if (empty($filesToDelete)) {
            abort(404, 'Reporte no encontrado');
        }

        Storage::disk('local')->delete($filesToDelete);

        return redirect()->back()->with('success', 'Reporte eliminado con éxito');
    }
}