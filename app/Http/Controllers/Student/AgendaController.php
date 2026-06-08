<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;

use App\Services\Archive\Handlers\AgendaArchiveHandler;
use App\Http\Requests\Archive\AgendaFileRequest;
use App\Exceptions\Archive\FileValidationException;
use App\Exceptions\Archive\VirusDetectedException;
use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\StorageException;
use App\Exceptions\Archive\ArchiveException;
use InvalidArgumentException;


use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Usuario\Usuario;
use App\Enums\DB\TipoMensaje;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function index()
    {
        
    }

    // POST 'grupos-asignados/{actividadAsignadaGrupo}/agenda'
    public function store(Request $request, ActividadAsignadaGrupo $actividadAsignadaGrupo)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            abort(403, 'Usuario no es estudiante');
        }

        $estudiante = $user->estudiante;

        // Validar inputs
        $validated = $request->validate([
            'tipo' => 'required|string|in:Consulta,Entrega de Avance,Duda sobre Rúbrica,Otro',
            'id_actividad_asignada_grupo' => 'required|integer|exists:actividad_asignada_grupo,id_actividad_asignada_grupo',
            'mensaje' => 'required|string|max:5000',
        ]);

        // Verificar que el estudiante pertenece a este grupo
        $integrante = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_actividad_asignada_grupo', $actividadAsignadaGrupo->id_actividad_asignada_grupo)
            ->firstOrFail();

        // Crear la entrada en la agenda
        $agenda = Agenda::create([
            'id_actividad_asignada_grupo' => $actividadAsignadaGrupo->id_actividad_asignada_grupo,
            'id_usuario_emisor' => $user->id_usuario,
            'fecha_envio' => now(),
            'tipo_mensaje' => $this->mapearTipoMensaje($validated['tipo']),
            'mensaje' => $validated['mensaje'],
        ]);

        return response()->json([
            'id_agenda' => $agenda->id_agenda // Asegúrate de usar el nombre correcto de la llave primaria aquí
        ]);
    }

    public function saveArchivo(AgendaFileRequest $request)
    {

        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // 1. Obtener la actividad asignada al grupo desde el request
        $grupoId = $request->input('id_actividad_asignada_grupo');
        $grupo = ActividadAsignadaGrupo::findOrFail($grupoId);

        // 2. Guardar en disco físico
        try {
            $storedFile = AgendaArchiveHandler::store(
                grupo: $grupo,
                file: $request->getFile(),
                fileName: $request->getCustomFileName()
            );

            // 3. Conectar a registro de Agenda
            Agenda::create([
                'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
                'id_usuario_emisor' => $user->id_usuario,
                'fecha_envio' => now(),
                'tipo_mensaje' => TipoMensaje::ENTREGA_DE_ARCHIVO,
                'mensaje' => $request->input('descripcion', "Entrega de archivo para actividad {$grupo->actividad->nombre}"),
                'uuid_archivo_subido' => $storedFile->uuidArchivo,
                // Se vincula el UUID del archivo devuelto por el handler
            ]);

            return back()->with('success', 'Entrega subida correctamente');
        } catch (FileValidationException $e) {
            return back()->withErrors(['error' => 'El archivo no es válido: ' . $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Ocurrió un error al subir el archivo: ' . $e->getMessage()]);
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
