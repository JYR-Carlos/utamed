<?php

namespace App\Api\V1\Controllers\Student\Activity;

use App\Models\Agenda\Actividad;
use App\Models\Agenda\Agenda;
use App\Models\Curso\Curso;
use App\Services\Student\ActividadesCursoEstudiante;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga segura del archivo entregado por el grupo y calificado por el docente.
 */
class DownloadActivitySubmissionController
{
    public function __construct(
        protected ActividadesCursoEstudiante $actividadesService
    ) {}

    public function __invoke(Request $request, Curso $curso, Actividad $actividad, Agenda $agenda): BinaryFileResponse
    {
        $estudiante = $request->user()->estudiante;
        $enLinea = $request->boolean('ver', false);

        return $this->actividadesService->descargarEntrega(
            $curso,
            $actividad,
            $agenda,
            $estudiante,
            $enLinea
        );
    }
}
