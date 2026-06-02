<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Usuario\Usuario;
use App\Enums\DB\TipoMensaje;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgendaController extends Controller
{
    public function index()
    {
        
    }

    public function saveEntrada(Request $request)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return response()->json(['error' => 'Usuario no es estudiante'], 403);
        }

        $estudiante = $user->estudiante;

        // Validar inputs
        try {
            $validated = $request->validate([
                'tipo' => 'required|string|in:Consulta,Entrega de Avance,Duda sobre Rúbrica,Otro',
                'id_actividad_asignada_grupo' => 'required|integer|exists:actividad_asignada_grupo,id_actividad_asignada_grupo',
                'mensaje' => 'required|string|max:5000',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        // Encontrar la actividad asignada al grupo
        $actividadAsignadaGrupo = ActividadAsignadaGrupo::findOrFail(
            $validated['id_actividad_asignada_grupo']
        );

        // Verificar que el estudiante pertenece a este grupo
        $integrante = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_actividad_asignada_grupo', $actividadAsignadaGrupo->id_actividad_asignada_grupo)
            ->first();

        if (!$integrante) {
            return response()->json([
                'error' => 'No tienes acceso a esta actividad'
            ], 403);
        }

        try {
            // Crear la entrada en la agenda
            $entrada = Agenda::create([
                'id_actividad_asignada_grupo' => $actividadAsignadaGrupo->id_actividad_asignada_grupo,
                'id_usuario_emisor' => $user->id_usuario,
                'fecha_envio' => now(),
                'tipo_mensaje' => $this->mapearTipoMensaje($validated['tipo']),
                'mensaje' => $validated['mensaje'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entrada guardada correctamente',
                'data' => [
                    'id_agenda' => $entrada->id_agenda,
                    'fecha_envio' => $entrada->fecha_envio,
                    'tipo_mensaje' => $entrada->tipo_mensaje,
                    'mensaje' => $entrada->mensaje,
                    'usuario_emisor' => $user->nombre . ' ' . $user->apellido,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al guardar la entrada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mapea el tipo de interacción del frontend al enum TipoMensaje
     */
    private function mapearTipoMensaje(string $tipo): TipoMensaje
    {
        return match($tipo) {
            'Consulta' => TipoMensaje::MENSAJE_AL_PROFESOR,
            'Entrega de Avance' => TipoMensaje::ENTREGA_DE_ARCHIVO,
            'Duda sobre Rúbrica' => TipoMensaje::MENSAJE_AL_PROFESOR,
            'Otro' => TipoMensaje::MENSAJE_AL_PROFESOR,
            default => TipoMensaje::MENSAJE_AL_PROFESOR,
        };
    }
}
