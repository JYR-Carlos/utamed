<?php

namespace App\Services\Student;

use App\Enums\DB\TipoMensaje;
use App\Exceptions\Archive\ArchiveException;
use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\FileValidationException;
use App\Exceptions\Archive\StorageException;
use App\Exceptions\Archive\VirusDetectedException;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Curso;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\Agenda\GrupoIndividualService;
use App\Services\Agenda\LecturaAgendaService;
use App\Services\Archive\ArchiveStorageResult;
use App\Services\Archive\Handlers\AgendaArchiveHandler;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Servicio de Dominio para la gestión de la Agenda de Actividades,
 * envío de mensajes al docente, control de entregas e interacciones para estudiantes.
 */
class AgendaCursoEstudiante
{
    private const VENTANA_ENVIO_REPETIDO = 10;
    private const ENVIO_REPETIDO = '__envio_repetido__';

    public function __construct(
        protected ActividadesCursoEstudiante $actividadesService,
        protected GrupoIndividualService $grupoIndividualService,
        protected LecturaAgendaService $lecturaAgendaService
    ) {}

    /**
     * Valida la pertenencia de la actividad al curso, visibilidad y resuelve el grupo del alumno.
     */
    public function validarPertenenciaYGrupo(Curso $curso, Actividad $actividad, Estudiante $estudiante): ActividadAsignadaGrupo
    {
        $this->actividadesService->validarAccesoActividad($curso, $actividad);

        if (! $actividad->es_grupal) {
            return $this->grupoIndividualService->asegurarGrupo($actividad, $estudiante->id_estudiante);
        }

        $integrante = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('actividadAsignadaGrupo', fn ($q) => $q->where('id_actividad', $actividad->id_actividad))
            ->with(['actividadAsignadaGrupo.miembros.estudiante.usuario'])
            ->first();

        if (! $integrante || ! $integrante->actividadAsignadaGrupo) {
            abort(404, 'No tienes un grupo asignado para esta actividad.');
        }

        return $integrante->actividadAsignadaGrupo;
    }

    /**
     * Consulta el hilo cronológico de la agenda, metadatos, estado y resumen de entregas.
     *
     * @return array<string, mixed>
     */
    public function obtenerAgenda(Curso $curso, Actividad $actividad, Estudiante $estudiante, Usuario $usuario, bool $marcarLeido = true): array
    {
        $grupo = $this->validarPertenenciaYGrupo($curso, $actividad, $estudiante);
        $grupo->loadMissing(['miembros.estudiante.usuario']);

        $diasHolguraBase = (int) ($actividad->nro_dias_adicionales_para_bloqueo ?? 0);
        $diasHolguraPersonal = (int) ($grupo->nro_dias_adicionales_para_bloqueo_personal ?? 0);
        $holguraTotal = $diasHolguraBase + $diasHolguraPersonal;

        $plazoFinal = $actividad->fecha_limite
            ? $actividad->fecha_limite->copy()->endOfDay()->addDays($holguraTotal)
            : null;

        $plazoExpirado = $plazoFinal ? now()->isAfter($plazoFinal) : false;
        $evaluado = $grupo->yaFueEvaluado();

        $tipoEntregaStr = (string) $actividad->tipo_entrega;
        $admiteEntrega = strtolower($tipoEntregaStr) !== 'sin entrega' && $tipoEntregaStr !== 'SIN_ENTREGA';
        $permiteEntrega = $admiteEntrega && ! $evaluado && ! $plazoExpirado;

        // Cargar interacciones cronológicas ascendentes (chat)
        $interaccionesRaw = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
            ->with(['usuario.docente', 'usuario.estudiante', 'archivo'])
            ->orderBy('fecha_envio', 'asc')
            ->orderBy('id_agenda', 'asc')
            ->get();

        $idsAgenda = $interaccionesRaw->pluck('id_agenda')->all();

        // Marcado de lectura automático si se solicita
        if ($marcarLeido && ! empty($idsAgenda)) {
            $this->lecturaAgendaService->registrar($usuario->id_usuario, $idsAgenda);
        }

        // Obtener vistos por mensaje
        $vistos = $this->lecturaAgendaService->vistoPor($idsAgenda, $usuario->id_usuario);

        // Identificar última entrega activa para cancelabilidad
        $ultimaEntregaVigente = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
            ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            ->orderByDesc('fecha_envio')
            ->orderByDesc('id_agenda')
            ->first();

        $puedeCancelarGeneral = false;
        if ($ultimaEntregaVigente && ! $ultimaEntregaVigente->fueCancelada() && ! $evaluado && ! $plazoExpirado) {
            $puedeCancelarGeneral = true;
        }

        $interacciones = $interaccionesRaw->map(function (Agenda $item) use ($usuario, $vistos, $ultimaEntregaVigente, $puedeCancelarGeneral, $curso, $actividad) {
            $emisor = $item->usuario;
            $esUsuarioActual = (int) $item->id_usuario_emisor === (int) $usuario->id_usuario;

            $rol = 'estudiante';
            if ($emisor) {
                if ($emisor->docente !== null) {
                    $rol = 'docente';
                } elseif ($emisor->hasAnyRole(['Docente', 'Docente Titular', 'Docente Componente', 'Docente Componente Colegiado'])) {
                    $rol = 'docente';
                }
            }

            $archivoData = null;
            if ($item->uuid_archivo_subido && $item->archivo) {
                $archivo = $item->archivo;
                $archivoData = [
                    'uuid_archivo' => $archivo->uuid_archivo,
                    'nombre_original' => $archivo->nombre_original,
                    'peso_bytes' => (int) $archivo->peso_bytes,
                    'extension' => $archivo->extension,
                    'url_descarga' => route('api.v1.estudiante.cursos.actividades.entregas.descargar', [
                        'curso' => $curso->id_curso,
                        'actividad' => $actividad->id_actividad,
                        'agenda' => $item->id_agenda,
                    ]),
                ];
            }

            $fechaEnvio = $item->fecha_envio instanceof Carbon
                ? $item->fecha_envio->toIso8601String()
                : ($item->fecha_envio ? Carbon::parse($item->fecha_envio)->toIso8601String() : null);

            $datos = [
                'id_agenda' => (int) $item->id_agenda,
                'tipo_mensaje' => $item->tipo_mensaje instanceof \BackedEnum ? $item->tipo_mensaje->value : (string) $item->tipo_mensaje,
                'mensaje' => $item->mensaje,
                'fecha_envio' => $fechaEnvio,
                'emisor' => [
                    'id_usuario' => (int) $item->id_usuario_emisor,
                    'nombre' => $emisor ? $emisor->nombre_completo : '',
                    'es_usuario_actual' => $esUsuarioActual,
                    'rol' => $rol,
                ],
                'archivo' => $archivoData,
                'visto_por' => $vistos[$item->id_agenda] ?? [],
            ];

            if ($item->tipo_mensaje === TipoMensaje::ENTREGA_DE_ARCHIVO || (is_string($item->tipo_mensaje) && $item->tipo_mensaje === TipoMensaje::ENTREGA_DE_ARCHIVO->value)) {
                $esUltima = $ultimaEntregaVigente && (int) $ultimaEntregaVigente->id_agenda === (int) $item->id_agenda;
                $datos['es_cancelable'] = $esUltima && $puedeCancelarGeneral;
            }

            return $datos;
        })->values()->all();

        // Integrantes del grupo
        $integrantes = $grupo->miembros->map(function (IntegranteGrupo $miembro) use ($estudiante) {
            $u = $miembro->estudiante?->usuario;
            return [
                'id_estudiante' => (int) $miembro->id_estudiante,
                'nombre_completo' => $u ? $u->nombre_completo : '',
                'es_usuario_actual' => (int) $miembro->id_estudiante === (int) $estudiante->id_estudiante,
            ];
        })
        ->sortBy(fn ($i) => [! $i['es_usuario_actual'], $i['nombre_completo']])
        ->values()
        ->all();

        // Resumen de entregas
        $entregasTotales = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
            ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            ->count();

        $ultimaEntregaData = null;
        if ($ultimaEntregaVigente && ! $ultimaEntregaVigente->fueCancelada()) {
            $archivo = $ultimaEntregaVigente->archivo;
            $fechaEnvioUltima = $ultimaEntregaVigente->fecha_envio instanceof Carbon
                ? $ultimaEntregaVigente->fecha_envio->toIso8601String()
                : ($ultimaEntregaVigente->fecha_envio ? Carbon::parse($ultimaEntregaVigente->fecha_envio)->toIso8601String() : null);

            $ultimaEntregaData = [
                'id_agenda' => (int) $ultimaEntregaVigente->id_agenda,
                'fecha_envio' => $fechaEnvioUltima,
                'archivo' => $archivo ? [
                    'uuid_archivo' => $archivo->uuid_archivo,
                    'nombre_original' => $archivo->nombre_original,
                    'peso_bytes' => (int) $archivo->peso_bytes,
                    'extension' => $archivo->extension,
                    'url_descarga' => route('api.v1.estudiante.cursos.actividades.entregas.descargar', [
                        'curso' => $curso->id_curso,
                        'actividad' => $actividad->id_actividad,
                        'agenda' => $ultimaEntregaVigente->id_agenda,
                    ]),
                ] : null,
                'puede_cancelar' => $puedeCancelarGeneral,
            ];
        }

        return [
            'curso' => [
                'id_curso' => (int) $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
            ],
            'actividad' => [
                'id_actividad' => (int) $actividad->id_actividad,
                'nombre' => $actividad->nombre,
                'tipo_actividad' => $actividad->tipo_actividad instanceof \BackedEnum ? $actividad->tipo_actividad->value : (string) $actividad->tipo_actividad,
                'tipo_entrega' => $tipoEntregaStr,
                'es_grupal' => (bool) $actividad->es_grupal,
                'fecha_limite' => $actividad->fecha_limite?->toIso8601String(),
                'plazo_final_con_holgura' => $plazoFinal?->toIso8601String(),
                'permite_entrega' => $permiteEntrega,
                'puede_cancelar_entrega' => $puedeCancelarGeneral,
            ],
            'grupo' => [
                'id_actividad_asignada_grupo' => (int) $grupo->id_actividad_asignada_grupo,
                'nombre_grupo' => $grupo->nombre_grupo,
                'estado' => $grupo->calcularEstadoGrupo($actividad),
                'evaluado' => $evaluado,
                'integrantes' => $integrantes,
            ],
            'resumen_entregas' => [
                'total_entregas_realizadas' => $entregasTotales,
                'ultima_entrega_vigente' => $ultimaEntregaData,
            ],
            'interacciones' => $interacciones,
        ];
    }

    /**
     * Envía un mensaje o consulta al equipo docente.
     */
    public function enviarMensaje(ActividadAsignadaGrupo $grupo, Usuario $usuario, string $mensaje): Agenda
    {
        return Agenda::create([
            'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
            'id_usuario_emisor' => $usuario->id_usuario,
            'fecha_envio' => now(),
            'tipo_mensaje' => TipoMensaje::MENSAJE_AL_PROFESOR,
            'mensaje' => $mensaje,
        ]);
    }

    /**
     * Sube un archivo de entrega con validación de plazos, archivo fuera de Tx y serialización concurrente.
     */
    public function subirEntrega(
        Actividad $actividad,
        ActividadAsignadaGrupo $grupo,
        Usuario $usuario,
        UploadedFile $archivo,
        ?string $mensaje = null,
        ?string $nombreArchivo = null
    ): Agenda {
        // Blindaje 1: Actividad admite entrega
        $tipoEntrega = (string) $actividad->tipo_entrega;
        if (strtolower($tipoEntrega) === 'sin entrega' || $tipoEntrega === 'SIN_ENTREGA') {
            throw ValidationException::withMessages([
                'archivo' => 'Esta actividad no requiere entrega de archivos.',
            ]);
        }

        // Blindaje 2: Fecha límite considerando holguras
        $holguraTotal = (int) ($actividad->nro_dias_adicionales_para_bloqueo ?? 0)
            + (int) ($grupo->nro_dias_adicionales_para_bloqueo_personal ?? 0);

        $limiteReal = $actividad->fecha_limite
            ? $actividad->fecha_limite->copy()->endOfDay()->addDays($holguraTotal)
            : null;

        if ($limiteReal && now()->isAfter($limiteReal)) {
            throw ValidationException::withMessages([
                'archivo' => 'La fecha límite de entrega ha vencido. No se pueden subir nuevos archivos.',
            ]);
        }

        // Blindaje 3: Almacenar archivo FUERA de transacción relacional
        try {
            $storedFile = AgendaArchiveHandler::store(
                grupo: $grupo,
                file: $archivo,
                fileName: $nombreArchivo
            );
        } catch (FileValidationException $e) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no es válido: ' . $e->getMessage(),
            ]);
        } catch (VirusDetectedException $e) {
            throw ValidationException::withMessages([
                'archivo' => 'Alerta de seguridad: Se detectó un virus en el archivo.',
            ]);
        } catch (CompressionException $e) {
            throw ValidationException::withMessages([
                'archivo' => 'Hubo un problema al comprimir el archivo.',
            ]);
        } catch (StorageException $e) {
            report($e);
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo guardar el archivo: ' . $e->getMessage(),
            ]);
        } catch (ArchiveException $e) {
            report($e);
            throw ValidationException::withMessages([
                'archivo' => 'Error al procesar el archivo.',
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'archivo' => 'Faltan datos para procesar la solicitud.',
            ]);
        }

        // Blindaje 4: Transacción corta en DB con lockForUpdate
        try {
            $resultado = DB::transaction(function () use ($grupo, $usuario, $storedFile, $mensaje, $limiteReal) {
                $grupoBloqueado = ActividadAsignadaGrupo::whereKey($grupo->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($grupoBloqueado->yaFueEvaluado()) {
                    return ['error' => 'La entrega ya ha sido evaluada por el docente. No es posible subir ni reemplazar archivos.'];
                }

                if ($limiteReal && now()->isAfter($limiteReal)) {
                    return ['error' => 'La fecha límite de entrega ha vencido. No se pueden subir nuevos archivos.'];
                }

                if ($this->esEnvioRepetido($grupoBloqueado, $usuario, $storedFile)) {
                    return ['repetido' => true];
                }

                $agenda = Agenda::create([
                    'id_actividad_asignada_grupo' => $grupoBloqueado->id_actividad_asignada_grupo,
                    'id_usuario_emisor' => $usuario->id_usuario,
                    'fecha_envio' => now(),
                    'tipo_mensaje' => TipoMensaje::ENTREGA_DE_ARCHIVO,
                    'mensaje' => $mensaje ?? '',
                    'uuid_archivo_subido' => $storedFile->uuidArchivo,
                ]);

                return ['agenda' => $agenda];
            });
        } catch (\Throwable $e) {
            $this->descartarArchivoSubido($storedFile);
            report($e);
            throw ValidationException::withMessages([
                'archivo' => 'Ocurrió un error inesperado al registrar la entrega. Contacte soporte.',
            ]);
        }

        if (isset($resultado['repetido']) && $resultado['repetido'] === true) {
            $this->descartarArchivoSubido($storedFile);

            $previa = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
                ->where('id_usuario_emisor', $usuario->id_usuario)
                ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
                ->whereNotNull('uuid_archivo_subido')
                ->orderByDesc('id_agenda')
                ->first();

            return $previa;
        }

        if (isset($resultado['error'])) {
            $this->descartarArchivoSubido($storedFile);
            throw ValidationException::withMessages([
                'archivo' => $resultado['error'],
            ]);
        }

        return $resultado['agenda'];
    }

    /**
     * Cancela la última entrega activa del grupo no evaluada y con plazo vigente.
     */
    public function cancelarEntrega(Actividad $actividad, ActividadAsignadaGrupo $grupo, Agenda $agenda, Usuario $usuario): void
    {
        // Validar que la entrega pertenezca al grupo especificado
        if ((int) $agenda->id_actividad_asignada_grupo !== (int) $grupo->id_actividad_asignada_grupo) {
            abort(403, 'Solo se puede cancelar una entrega de archivo perteneciente a tu grupo.');
        }

        // Validar que sea una Entrega de archivo de un miembro del grupo
        $esEntregaDelGrupo = ($agenda->tipo_mensaje === TipoMensaje::ENTREGA_DE_ARCHIVO || $agenda->tipo_mensaje === TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            && $agenda->uuid_archivo_subido !== null
            && IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
                ->whereHas('estudiante', fn ($q) => $q->where('id_usuario', $agenda->id_usuario_emisor))
                ->exists();

        if (! $esEntregaDelGrupo) {
            abort(403, 'Solo se puede cancelar una entrega de archivo perteneciente a tu grupo.');
        }

        if ($agenda->fueCancelada()) {
            throw ValidationException::withMessages([
                'agenda' => 'Esta entrega ya fue cancelada previamente.',
            ]);
        }

        // Restringir a la entrega más reciente del grupo
        $ultimaEntrega = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
            ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            ->orderByDesc('fecha_envio')
            ->orderByDesc('id_agenda')
            ->first();

        if (! $ultimaEntrega || (int) $ultimaEntrega->id_agenda !== (int) $agenda->id_agenda) {
            throw ValidationException::withMessages([
                'agenda' => 'Solo se puede cancelar la entrega más reciente del grupo.',
            ]);
        }

        // Blindaje 1: No evaluado
        if ($grupo->yaFueEvaluado()) {
            throw ValidationException::withMessages([
                'agenda' => 'La entrega ya ha sido evaluada por el docente. No es posible cancelarla.',
            ]);
        }

        // Blindaje 2: Fecha límite con holgura
        $holguraTotal = (int) ($actividad->nro_dias_adicionales_para_bloqueo ?? 0)
            + (int) ($grupo->nro_dias_adicionales_para_bloqueo_personal ?? 0);

        $limiteReal = $actividad->fecha_limite
            ? $actividad->fecha_limite->copy()->endOfDay()->addDays($holguraTotal)
            : null;

        if ($limiteReal && now()->isAfter($limiteReal)) {
            throw ValidationException::withMessages([
                'agenda' => 'La fecha límite de entrega ha vencido. No se pueden cancelar entregas.',
            ]);
        }

        $nombreArchivo = $agenda->archivo?->nombre_original ?? 'archivo adjunto';

        DB::transaction(function () use ($agenda, $grupo, $usuario, $nombreArchivo) {
            // Marcar el archivo en operaciones.archivo como pendiente_de_borrado
            Archivo::where('uuid_archivo', $agenda->uuid_archivo_subido)
                ->update(['pendiente_de_borrado' => true]);

            // Registrar evento de cancelación en agenda
            Agenda::create([
                'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
                'id_usuario_emisor' => $usuario->id_usuario,
                'fecha_envio' => now(),
                'tipo_mensaje' => TipoMensaje::CANCELACIÓN_DE_ENTREGA,
                'mensaje' => "Entrega cancelada por el estudiante ({$nombreArchivo}).",
                'uuid_archivo_subido' => $agenda->uuid_archivo_subido,
            ]);
        });
    }

    /**
     * Marca manualmente como leídos los mensajes pendientes del grupo para el usuario.
     */
    public function marcarLectura(ActividadAsignadaGrupo $grupo, int $idUsuario): void
    {
        $ids = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
            ->pluck('id_agenda');

        $this->lecturaAgendaService->registrar($idUsuario, $ids);
    }

    /**
     * Cuenta mensajes no leídos del grupo para el estudiante.
     */
    public function contarNoLeidos(ActividadAsignadaGrupo $grupo, int $idUsuario): int
    {
        return DB::table('agenda.agenda as a')
            ->where('a.id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
            ->where('a.id_usuario_emisor', '<>', $idUsuario)
            ->whereNotExists(fn ($q) => $q->from('agenda.lectura_agenda as l')
                ->whereColumn('l.id_agenda', 'a.id_agenda')
                ->where('l.id_usuario_lector', $idUsuario))
            ->count();
    }

    private function esEnvioRepetido(ActividadAsignadaGrupo $grupo, Usuario $user, ArchiveStorageResult $nuevo): bool
    {
        $previa = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
            ->where('id_usuario_emisor', $user->id_usuario)
            ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            ->whereNotNull('uuid_archivo_subido')
            ->where('fecha_envio', '>=', now()->subSeconds(self::VENTANA_ENVIO_REPETIDO))
            ->orderByDesc('id_agenda')
            ->first();

        if (! $previa) {
            return false;
        }

        $archivoPrevio = Archivo::find($previa->uuid_archivo_subido);
        $archivoNuevo = Archivo::find($nuevo->uuidArchivo);

        return $archivoPrevio && $archivoNuevo
            && $archivoPrevio->nombre_original === $archivoNuevo->nombre_original
            && (int) $archivoPrevio->peso_bytes === (int) $archivoNuevo->peso_bytes;
    }

    private function descartarArchivoSubido(ArchiveStorageResult $storedFile): void
    {
        Archivo::where('uuid_archivo', $storedFile->uuidArchivo)
            ->update(['pendiente_de_borrado' => true]);
    }
}
