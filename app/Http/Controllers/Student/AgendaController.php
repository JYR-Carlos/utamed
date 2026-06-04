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

    public function saveEntrada(Request $request)
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

        // Encontrar la actividad asignada al grupo
        $actividadAsignadaGrupo = ActividadAsignadaGrupo::findOrFail(
            $validated['id_actividad_asignada_grupo']
        );

        // Verificar que el estudiante pertenece a este grupo
        $integrante = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_actividad_asignada_grupo', $actividadAsignadaGrupo->id_actividad_asignada_grupo)
            ->firstOrFail();

        // Crear la entrada en la agenda
        Agenda::create([
            'id_actividad_asignada_grupo' => $actividadAsignadaGrupo->id_actividad_asignada_grupo,
            'id_usuario_emisor' => $user->id_usuario,
            'fecha_envio' => now(),
            'tipo_mensaje' => $this->mapearTipoMensaje($validated['tipo']),
            'mensaje' => $validated['mensaje'],
        ]);

        return back()->with('success', 'Entrada guardada correctamente');
    }

    /**
     * algo asi es el url: POST /agenda/{agenda}/archivo
     * 
     * @param AgendaFileRequest $request
     * @param Agenda $agenda
     * @return void
     */
    public function saveEntradaFile(AgendaFileRequest $request, ActividadAsignadaGrupo $grupo)
    {
        // validar el archivo con el request

        // guardar en disco
        try {
            $storedFile = AgendaArchiveHandler::store(
                grupo: $grupo,
                file: $request->getFile(),
                fileName: $request->getCustomFileName()
            );

            // FIX: CONECTAR A REGISTRO DE AGENDA
            // el endpoint debe crear el mensaje tipo subida de archivo, y luego conectar el archivo subido a ese mensaje
            // $agendaEntry->archivo_id = $storedFile->id;
            // $agendaEntry->save();


            
        } catch (FileValidationException) {
            // Maneja validación de archivo (peso, tipo, etc)            
        } catch (VirusDetectedException) {
            // Maneja virus detectado
        } catch (CompressionException) {
            // Maneja error de compresión
        } catch (StorageException) {
            // Maneja error de almacenamiento
        } catch (ArchiveException) {
            // Maneja error genérico de archivo
        } catch (InvalidArgumentException) {
            // Maneja error de relaciones faltantes
        } catch (\Throwable $e) {
            // Maneja cualquier otro error inesperado
            // TODO: $storedFile->deleteFromDisk(); // Opcional: eliminar archivo si ya se subió pero hubo error en DB
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
