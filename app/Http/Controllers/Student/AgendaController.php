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

        $integrante = IntegranteGrupo::where(
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
        $yaEvaluada = $actividadAsignadaGrupo->nota !== null
            || $integrante->nota_individual !== null
            || $actividadAsignadaGrupo->entregas()->whereHas('evaluacion')->exists();

        if ($yaEvaluada) {
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

        $integrante = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_actividad_asignada_grupo', $actividadAsignadaGrupo->id_actividad_asignada_grupo)
            ->firstOrFail();

        // Validar que la entrega pertenezca al grupo especificado
        if ((int) $agenda->id_actividad_asignada_grupo !== (int) $actividadAsignadaGrupo->id_actividad_asignada_grupo) {
            abort(403, 'La entrega no pertenece al grupo especificado.');
        }

        // Blindaje 1: No permitir borrar si la entrega ya fue evaluada
        $yaEvaluada = $actividadAsignadaGrupo->nota !== null
            || $integrante->nota_individual !== null
            || $agenda->tieneEvaluacion();

        if ($yaEvaluada) {
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

        // Marcar el archivo en operaciones.archivo como pendiente de borrado
        if ($agenda->uuid_archivo_subido) {
            Archivo::where('uuid_archivo', $agenda->uuid_archivo_subido)
                ->update(['pendiente_de_borrado' => true]);
        }

        $nombreArchivo = $agenda->archivo?->nombre_original ?? 'archivo adjunto';

        // Conservar el registro histórico de entrega y registrar el evento de cancelación
        Agenda::create([
            'id_actividad_asignada_grupo' => $actividadAsignadaGrupo->id_actividad_asignada_grupo,
            'id_usuario_emisor' => $user->id_usuario,
            'fecha_envio' => now(),
            'tipo_mensaje' => TipoMensaje::CANCELACIÓN_DE_ENTREGA,
            'mensaje' => "Entrega cancelada por el estudiante ({$nombreArchivo}).",
            'uuid_archivo_subido' => $agenda->uuid_archivo_subido,
        ]);

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
