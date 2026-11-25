<?php

namespace App\Http\Controllers;

use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\ProgramaPdfService;
use App\Support\Permissions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Entrega el PDF institucional unificado del Programa de Asignatura por rol.
 */
class ProgramaPdfController extends Controller
{
    public function __construct(private readonly ProgramaPdfService $pdfService) {}

    /**
     * GET admin/cursos/{curso}/programa/pdf
     */
    public function admin(Curso $curso): Response
    {
        $programa = $this->programaActualOrFail($curso);
        $this->authorize('view', $programa);

        return $this->streamPdf($curso, $programa, 'admin');
    }

    /**
     * GET docente/cursos/{curso}/programa/pdf
     */
    public function docente(Curso $curso): Response
    {
        $this->authorize('viewPrograma', $curso);
        $programa = $this->programaActualOrFail($curso);
        $this->authorize('view', $programa);

        return $this->streamPdf($curso, $programa, 'docente');
    }

    /**
     * GET ayudante/cursos/{curso}/programa/pdf
     */
    public function ayudante(Curso $curso): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$curso->id_contexto) {
            abort(403, 'El curso no tiene contexto configurado');
        }

        $rolAyudante = Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();
        if (!$rolAyudante) {
            abort(403, 'Rol de ayudante no configurado en el sistema');
        }

        $asignacion = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolAyudante->id_rol)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        if (!$asignacion) {
            abort(403, 'No estás asignado a este curso como ayudante');
        }

        if (!$user->hasPermission(Permissions::CURSOS_PROGRAMAS_VER_TODOS, $curso->id_contexto)) {
            abort(403, 'No tienes permiso para acceder al programa de este curso');
        }

        $programa = $this->programaActualOrFail($curso);

        return $this->streamPdf($curso, $programa, 'ayudante');
    }

    /**
     * GET estudiante/cursos/{curso}/programa/pdf
     */
    public function estudiante(Curso $curso): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            abort(403, 'No tienes un perfil de estudiante');
        }

        $inscrito = InscripcionCurso::where('id_estudiante', $user->estudiante->id_estudiante)
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->exists();

        if (!$inscrito) {
            abort(403, 'No estás inscrito en este curso');
        }

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
            ->where('es_actual', true)
            ->orderByRaw("CASE estado WHEN 'APROBADO' THEN 0 WHEN 'BASICO_COMPLETO' THEN 1 ELSE 2 END")
            ->first();

        if (!$programa) {
            abort(404, 'No hay un programa disponible para este curso');
        }

        return $this->streamPdf($curso, $programa, 'estudiante', $user->estudiante);
    }

    private function programaActualOrFail(Curso $curso): Programa
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            abort(404, 'No existe un programa para este curso');
        }

        return $programa;
    }

    private function streamPdf(
        Curso $curso,
        Programa $programa,
        string $audiencia,
        ?Estudiante $estudiante = null,
    ): Response {
        $resultado = $this->pdfService->generar($curso, $programa, $audiencia, $estudiante);

        return $resultado['pdf']->stream($resultado['filename']);
    }
}
