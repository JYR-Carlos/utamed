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
use App\Models\Operaciones\Archivo;
use App\Services\Archive\ArchiveStorageResult;
use Illuminate\Support\Facades\Log;
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

        // Blindaje 1: No permitir reemplazo ni subida si la entrega ya fue evaluada
        if ($actividadAsignadaGrupo->yaFueEvaluado()) {
            return back()->withErrors([
                'error_general' => 'La entrega ya ha sido evaluada por el docente. No es posible subir ni reemplazar archivos.',
            ]);
        }

        // Blindaje 2: Fecha límite considerando holgura general y personal del grupo
        $holguraTotal = (int) ($actividad->nro_dias_adicionales_para_bloqueo ?? 0)
            + (int) ($actividadAsignadaGrupo->nro_dias_adicionales_para_bloqueo_personal ?? 0);

        $limiteReal = $actividad->fecha_limite->copy()->endOfDay()->addDays($holguraTotal);

        if (now()->isAfter($limiteReal)) {
            return back()->withErrors([
                'error_general' => 'La fecha límite de entrega ha vencido. No se pueden subir nuevos archivos. Consulta con tu docente si deseas enviar un archivo de manera excepcional.',
            ]);
        }

        // 1. El archivo primero, FUERA de toda transacción. Validar, buscar
        //    virus, comprimir y escribir puede tardar segundos; con la
        //    transacción abierta, cada subida retenía una conexión de
        //    PostgreSQL todo ese tiempo y, al cierre de un plazo, el pool se
        //    agotaba antes que el disco. El handler limpia lo que deja a
        //    medias si falla.
        $inicio = microtime(true);

        try {
            $storedFile = AgendaArchiveHandler::store(
                grupo: $actividadAsignadaGrupo,
                file: $request->getFile(),
                fileName: $request->getFileName()
            );
        } catch (FileValidationException $e) {
            return back()->withErrors([
                'archivo' => 'El archivo no es válido: ' . $e->getMessage()
            ]);
        } catch (VirusDetectedException $e) {
            return back()->withErrors([
                'archivo' => 'Alerta de seguridad: Se detectó un virus en el archivo.'
            ]);
        } catch (CompressionException $e) {
            return back()->withErrors([
                'archivo' => 'Hubo un problema al comprimir el archivo.'
            ]);
        } catch (StorageException $e) {
            report($e);

            return back()->withErrors([
                'error_general' => 'No se pudo guardar el archivo. ' . $e->getMessage()
            ]);
        } catch (ArchiveException $e) {
            // Aquí caen también los errores de configuración del pipeline (por
            // ejemplo, el antivirus habilitado sin escáner implementado). El
            // estudiante ve un mensaje genérico, pero tiene que llegar al log:
            // es un fallo de operación, no del archivo que subió.
            report($e);

            return back()->withErrors([
                'error_general' => 'Error al procesar el archivo.'
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors([
                'error_general' => 'Faltan datos para procesar la solicitud.'
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'error_general' => 'Ocurrió un error inesperado. Contacte soporte.'
            ]);
        }

        $msArchivo = (int) round((microtime(true) - $inicio) * 1000);

        // 2. Transacción corta (milisegundos): sólo la fila de agenda. Se
        //    vuelven a revisar el plazo y «ya evaluado» con el grupo bloqueado,
        //    porque el docente pudo evaluar mientras el archivo se procesaba.
        try {
            $rechazo = DB::transaction(function () use ($actividadAsignadaGrupo, $user, $request, $storedFile, $limiteReal) {
                $grupo = ActividadAsignadaGrupo::whereKey($actividadAsignadaGrupo->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($grupo->yaFueEvaluado()) {
                    return 'La entrega ya ha sido evaluada por el docente. No es posible subir ni reemplazar archivos.';
                }
                if (now()->isAfter($limiteReal)) {
                    return 'La fecha límite de entrega ha vencido. No se pueden subir nuevos archivos. Consulta con tu docente si deseas enviar un archivo de manera excepcional.';
                }

                $agenda = $this->crearAgenda(
                    user: $user,
                    actividadAsignadaGrupo: $grupo,
                    tipo: 'Entrega de Avance',
                    mensaje: $request->input('mensaje')
                );
                $agenda->uuid_archivo_subido = $storedFile->uuidArchivo;
                $agenda->save();

                return null;
            });
        } catch (\Throwable $e) {
            $this->descartarArchivoSubido($storedFile);
            report($e);

            return back()->withErrors([
                'error_general' => 'Ocurrió un error inesperado. Contacte soporte.'
            ]);
        }

        if ($rechazo !== null) {
            $this->descartarArchivoSubido($storedFile);

            return back()->withErrors(['error_general' => $rechazo]);
        }

        Log::info('[AgendaController::storeEntrega] Entrega guardada', [
            'id_actividad_asignada_grupo' => $actividadAsignadaGrupo->id_actividad_asignada_grupo,
            'peso_bytes'                  => $storedFile->sizeBytes,
            'ms_archivo'                  => $msArchivo,
            'ms_total'                    => (int) round((microtime(true) - $inicio) * 1000),
        ]);

        return back()->with('success', 'Entrega enviada exitosamente');
    }

    /**
     * El archivo ya quedó en disco y en operaciones.archivo, pero la entrega no
     * se registró: se marca para la limpieza asíncrona, igual que una
     * cancelación, en vez de dejarlo huérfano y sin rastro.
     */
    private function descartarArchivoSubido(ArchiveStorageResult $storedFile): void
    {
        Archivo::where('uuid_archivo', $storedFile->uuidArchivo)
            ->update(['pendiente_de_borrado' => true]);
    }


    // ⚠️ CAMBIO TEMPORAL: SI UN AGENTE LEE ESTO, NO USARLO COMO EJEMPLO.
    // Esta implementación de cancelación es un placeholder transitorio.
    // La lógica definitiva de cancelación de entregas y ciclo de vida de archivos huérfanos/borrados
    // debe formalizarse en AgendaArchiveHandler (ver tarjeta FEAT-04 en Trello).
    // DELETE 'grupos-asignados/{actividadAsignadaGrupo}/entregas/{agenda}'
    public function destroyEntrega(
        Request $request,
        ActividadAsignadaGrupo $actividadAsignadaGrupo,
        Agenda $agenda
    ): RedirectResponse {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            abort(403, 'Usuario no es estudiante');
        }

        $estudiante = $user->estudiante;

        IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_actividad_asignada_grupo', $actividadAsignadaGrupo->id_actividad_asignada_grupo)
            ->firstOrFail();

        // Validar que la entrega pertenezca al grupo especificado
        if ((int) $agenda->id_actividad_asignada_grupo !== (int) $actividadAsignadaGrupo->id_actividad_asignada_grupo) {
            abort(403, 'La entrega no pertenece al grupo especificado.');
        }

        // Sólo se cancela una entrega de archivo hecha por un integrante del
        // grupo: no un feedback del docente ni otro mensaje con adjunto.
        $esEntregaDelGrupo = $agenda->tipo_mensaje === TipoMensaje::ENTREGA_DE_ARCHIVO
            && $agenda->uuid_archivo_subido !== null
            && IntegranteGrupo::where('id_actividad_asignada_grupo', $actividadAsignadaGrupo->id_actividad_asignada_grupo)
                ->whereHas('estudiante', fn ($q) => $q->where('id_usuario', $agenda->id_usuario_emisor))
                ->exists();

        if (!$esEntregaDelGrupo) {
            abort(403, 'Solo se puede cancelar una entrega de archivo del grupo.');
        }

        if ($agenda->fueCancelada()) {
            return back()->withErrors([
                'error_general' => 'Esta entrega ya fue cancelada.',
            ]);
        }

        // Sólo la última entrega del grupo: las anteriores ya fueron reemplazadas,
        // y cancelar una de ellas dejaría al grupo sin ver su entrega vigente.
        $ultimaEntrega = Agenda::where('id_actividad_asignada_grupo', $actividadAsignadaGrupo->id_actividad_asignada_grupo)
            ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            ->orderByDesc('fecha_envio')
            ->orderByDesc('id_agenda')
            ->first();

        if (!$ultimaEntrega || (int) $ultimaEntrega->id_agenda !== (int) $agenda->id_agenda) {
            return back()->withErrors([
                'error_general' => 'Solo se puede cancelar la entrega más reciente. Recarga la página para ver el estado actual.',
            ]);
        }

        // Blindaje 1: No permitir borrar si el grupo ya fue evaluado (incluye
        // evaluaciones formativas sin nota y no vinculadas a esta entrega)
        if ($actividadAsignadaGrupo->yaFueEvaluado()) {
            return back()->withErrors([
                'error_general' => 'La entrega ya ha sido evaluada por el docente. No es posible eliminarla.',
            ]);
        }

        // Blindaje 2: Fecha límite considerando holgura
        $actividad = $actividadAsignadaGrupo->actividad;
        $holguraTotal = (int) ($actividad->nro_dias_adicionales_para_bloqueo ?? 0)
            + (int) ($actividadAsignadaGrupo->nro_dias_adicionales_para_bloqueo_personal ?? 0);

        $limiteReal = $actividad->fecha_limite->copy()->endOfDay()->addDays($holguraTotal);

        if (now()->isAfter($limiteReal)) {
            return back()->withErrors([
                'error_general' => 'La fecha límite de entrega ha vencido. No se pueden eliminar entregas.',
            ]);
        }

        $nombreArchivo = $agenda->archivo?->nombre_original ?? 'archivo adjunto';

        // Las dos escrituras van juntas: si falla el registro de la cancelación,
        // el archivo no puede quedar marcado para borrarse.
        DB::transaction(function () use ($agenda, $actividadAsignadaGrupo, $user, $nombreArchivo) {
            // Marcar el archivo en operaciones.archivo como pendiente de borrado
            Archivo::where('uuid_archivo', $agenda->uuid_archivo_subido)
                ->update(['pendiente_de_borrado' => true]);

            // Conservar el registro histórico de entrega y registrar el evento de cancelación
            Agenda::create([
                'id_actividad_asignada_grupo' => $actividadAsignadaGrupo->id_actividad_asignada_grupo,
                'id_usuario_emisor' => $user->id_usuario,
                'fecha_envio' => now(),
                'tipo_mensaje' => TipoMensaje::CANCELACIÓN_DE_ENTREGA,
                'mensaje' => "Entrega cancelada por el estudiante ({$nombreArchivo}).",
                'uuid_archivo_subido' => $agenda->uuid_archivo_subido,
            ]);
        });

        return back()->with('success', 'Entrega cancelada exitosamente.');
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
