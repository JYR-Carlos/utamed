<?php

namespace App\Api\V1\Controllers\Student\Activity;

use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Services\Student\ActividadesCursoEstudiante;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Endpoint para descargar de manera segura el archivo de enunciado oficial de una actividad.
 */
class DownloadActivityStatementController
{
    public function __construct(
        private readonly ActividadesCursoEstudiante $service,
    ) {}

    public function __invoke(Request $request, Curso $curso, Actividad $actividad): BinaryFileResponse
    {
        $enLinea = $request->boolean('ver');

        return $this->service->descargarEnunciado($curso, $actividad, $enLinea);
    }
}
