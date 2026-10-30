<?php

namespace App\Api\V1\Controllers\Student;

use App\Api\V1\Resources\Student\PerfilResource;
use App\Http\Controllers\Controller;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Usuario;
use App\Services\FotoIntranetService;
use Illuminate\Http\Request;

/**
 * Consulta de Perfil Institucional y Credencial Móvil (Solo Lectura).
 */
class GetProfileController extends Controller
{
    public function __construct(
        private readonly FotoIntranetService $fotoIntranet,
    ) {}

    public function __invoke(Request $request): PerfilResource
    {
        /** @var Usuario $user */
        $user = $request->user();

        // Carga ansiosa para evitar consultas N+1
        $user->loadMissing(['estudiante.carrera']);
        $estudiante = $user->estudiante;

        $totalCursos = InscripcionCurso::query()
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado_inscripcion', 'INSCRITO')
            ->count();

        $urlFotoPerfil = $user->rut
            ? $this->fotoIntranet->getImagenPerfilURL($user->rut)
            : null;

        return new PerfilResource(
            usuario: $user,
            estudiante: $estudiante,
            totalCursosInscritos: $totalCursos,
            urlFotoPerfil: $urlFotoPerfil,
        );
    }
}
