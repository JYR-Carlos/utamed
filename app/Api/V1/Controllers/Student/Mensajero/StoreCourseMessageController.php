<?php

namespace App\Api\V1\Controllers\Student\Mensajero;

use App\Api\V1\Requests\Student\Mensajero\StoreCourseMessageRequest;
use App\Api\V1\Resources\Student\Mensajero\CourseMessageResource;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Student\MensajeriaCursoEstudiante;
use Illuminate\Http\JsonResponse;

/**
 * Envía un mensaje privado del estudiante al equipo docente de un componente del curso.
 */
class StoreCourseMessageController
{
    public function __construct(
        private readonly MensajeriaCursoEstudiante $service,
    ) {}

    public function __invoke(StoreCourseMessageRequest $request, Curso $curso, int $componente): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();

        $mensaje = $this->service->enviarMensajeEstudiante(
            curso: $curso,
            idComponente: $componente,
            idUsuario: (int) $user->id_usuario,
            mensaje: (string) $request->validated('mensaje'),
            tema: $request->validated('tema'),
            idReceptor: $request->filled('id_usuario_receptor') ? (int) $request->validated('id_usuario_receptor') : null,
        );

        return response()->json([
            'message' => 'Mensaje enviado correctamente.',
            'data'    => new CourseMessageResource($mensaje),
        ], 201);
    }
}
