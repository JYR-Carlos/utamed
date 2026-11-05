<?php

namespace App\Api\V1\Controllers\Student\Messaging;

use App\Api\V1\Resources\Student\Messaging\CourseMessagingResource;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Student\MensajeriaCursoEstudiante;
use Illuminate\Http\Request;

/**
 * Endpoint único GET que retorna la estructura consolidada de mensajería del curso.
 * Consolida los componentes inscritos, contadores de no leídos, difusiones y mensajes.
 */
class GetCourseMessagingController
{
    public function __construct(
        private readonly MensajeriaCursoEstudiante $service,
    ) {}

    public function __invoke(Request $request, Curso $curso): CourseMessagingResource
    {
        /** @var Usuario $user */
        $user = $request->user();

        $marcarLeido = $request->boolean('marcar_leido', true);

        $datos = $this->service->consolidarMensajeria(
            curso: $curso,
            idUsuario: (int) $user->id_usuario,
            marcarLeidos: $marcarLeido,
        );

        return new CourseMessagingResource($datos);
    }
}
