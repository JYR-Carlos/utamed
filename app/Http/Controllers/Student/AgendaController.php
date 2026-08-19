<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Agenda\ActividadAsignadaGrupo;

use App\Services\Archive\Handlers\AgendaArchiveHandler;
use App\Http\Requests\Archive\AgendaFileRequest;
use App\Exceptions\Archive\FileValidationException;
use App\Exceptions\Archive\VirusDetectedException;
use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\StorageException;
use App\Exceptions\Archive\ArchiveException;
use InvalidArgumentException;

use Illuminate\Support\Facades\DB;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Usuario\Usuario;
use App\Enums\DB\TipoMensaje;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Agenda de un grupo de actividad (perspectiva del estudiante).
 *
 * Permite al estudiante enviar mensajes de texto al docente y subir entregas de
 * archivos sobre las actividades de los grupos a los que pertenece. La subida de
 * archivos delega en AgendaArchiveHandler (validación de tipo y tamaño, compresión
 * y almacenamiento) y respeta la fecha límite más la holgura
 * `nro_dias_adicionales_para_bloqueo`. **No hay antivirus**: el hook existe pero
 * ninguna subclase lo implementa, así que con `ARCHIVE_VIRUS_SCAN_ENABLED=true` el
 * pipeline rechaza las subidas en vez de aprobarlas sin escanear.
 */
class AgendaController extends Controller
{
    public function index(): void {}

    // POST 'grupos-asignados/{actividadAsignadaGrupo}/agenda'
    public function store(Request $request, ActividadAsignadaGrupo $actividadAsignadaGrupo): RedirectResponse
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            abort(403, 'Usuario no es estudiante');
        }

        $estudiante = $user->estudiante;

        // Validar inputs
        $validated = $request->validate([
            'tipo' => 'required|string|in:Consulta,Duda sobre Rúbrica,Otro',
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

        return back()
            ->with('success', 'Mensaje enviado exitosamente')
            ->with('flash_data', [
                'id_agenda' => $agenda->id_agenda,
            ]);
    }
    
    public function storeEntrega(
        AgendaFileRequest $request,
        ActividadAsignadaGrupo $actividadAsignadaGrupo
    ): RedirectResponse {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            abort(403, 'Usuario no es estudiante');
        }

        $estudiante = $user->estudiante;

        IntegranteGrupo::where(
            'id_estudiante',
            $estudiante->id_estudiante
        )
            ->where(
                'id_actividad_asignada_grupo',
                $actividadAsignadaGrupo->id_actividad_asignada_grupo
            )
            ->firstOrFail();

        $actividad = $actividadAsignadaGrupo->actividad;

        // Guard invertido: con `if ($actividad) { … }`, un grupo sin actividad
        // asociada saltaba entera la comprobación de plazo y la entrega se aceptaba.
        if (!$actividad) {
            abort(404, 'El grupo no tiene una actividad asociada.');
        }

        $limiteReal = $actividad->fecha_limite->copy()
            ->addDays($actividad->nro_dias_adicionales_para_bloqueo ?? 0)
            ->endOfDay();

        if (now()->isAfter($limiteReal)) {
            return back()->withErrors([
                'error_general' => 'La fecha límite de entrega ha vencido. No se pueden subir archivos.',
            ]);
        }

        DB::beginTransaction();

        try {

            $agenda = $this->crearAgenda(
                user: $user,
                actividadAsignadaGrupo: $actividadAsignadaGrupo,
                tipo: 'Entrega de Avance',
                mensaje: $request->input('mensaje')
            );

            $storedFile = AgendaArchiveHandler::store(
                grupo: $actividadAsignadaGrupo,
                file: $request->getFile(),
                fileName: $request->getFileName()
            );

            $agenda->uuid_archivo_subido = $storedFile->uuidArchivo;
            $agenda->save();

            DB::commit();

            return back()->with(
                'success',
                'Entrega enviada exitosamente'
            );

        } catch (FileValidationException $e) {

            DB::rollBack();

            return back()->withErrors([
                'archivo' => 'El archivo no es válido: ' .
                    $e->getMessage()
            ]);

        } catch (VirusDetectedException $e) {

            DB::rollBack();

            return back()->withErrors([
                'archivo' => 'Alerta de seguridad: Se detectó un virus en el archivo.'
            ]);

        } catch (CompressionException $e) {

            DB::rollBack();

            return back()->withErrors([
                'archivo' => 'Hubo un problema al comprimir el archivo.'
            ]);

        } catch (StorageException $e) {

            DB::rollBack();
            report($e);

            return back()->withErrors([
                'error_general' => 'No se pudo guardar el archivo. ' . $e->getMessage()
            ]);

        } catch (ArchiveException $e) {

            DB::rollBack();

            // Aquí caen también los errores de configuración del pipeline (por
            // ejemplo, el antivirus habilitado sin escáner implementado). El
            // estudiante ve un mensaje genérico, pero tiene que llegar al log:
            // es un fallo de operación, no del archivo que subió.
            report($e);

            return back()->withErrors([
                'error_general' => 'Error al procesar el archivo.'
            ]);

        } catch (InvalidArgumentException $e) {

            DB::rollBack();

            return back()->withErrors([
                'error_general' => 'Faltan datos para procesar la solicitud.'
            ]);

        } catch (\Throwable $e) {

            // Esta rama no revertía: cualquier excepción fuera de la jerarquía
            // Archive dejaba la transacción abierta con la agenda ya insertada.
            DB::rollBack();

            report($e);

            return back()->withErrors([
                'error_general' =>
                    'Ocurrió un error inesperado. Contacte soporte.'
            ]);
        }
    }

    
    private function crearAgenda(
        Usuario $user,
        ActividadAsignadaGrupo $actividadAsignadaGrupo,
        string $tipo,
        string $mensaje
    ): Agenda {
        return Agenda::create([
            'id_actividad_asignada_grupo'
                => $actividadAsignadaGrupo->id_actividad_asignada_grupo,

            'id_usuario_emisor'
                => $user->id_usuario,

            'fecha_envio'
                => now(),

            'tipo_mensaje'
                => $this->mapearTipoMensaje($tipo),

            'mensaje'
                => $mensaje,
        ]);
    }
    /**
     * Mapea el tipo de interacción del frontend al enum TipoMensaje
     */
    private function mapearTipoMensaje(string $tipo): TipoMensaje
    {
        return match ($tipo) {
            'Consulta' => TipoMensaje::MENSAJE_AL_PROFESOR,
            'Entrega de Avance' => TipoMensaje::ENTREGA_DE_ARCHIVO,
            'Duda sobre Rúbrica' => TipoMensaje::MENSAJE_AL_PROFESOR,
            'Otro' => TipoMensaje::MENSAJE_AL_PROFESOR,
            default => TipoMensaje::MENSAJE_AL_PROFESOR,
        };
    }
}
