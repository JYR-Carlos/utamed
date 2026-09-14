<?php

namespace App\Console\Commands;

use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class DevGetCoursePairCommand extends Command
{
    /**
     * Nombre y firma del comando en consola.
     */
    protected $signature = 'dev:pair
        {--curso= : ID específico del curso a consultar}
        {--actividades : Filtrar solo cursos que tengan actividades creadas}
        {--unlock : Actualiza fecha_cambio_passhash a hoy y resetea contraseña a "password" para login directo}
        {--more-students=3 : Cantidad de estudiantes del curso a listar}
        {--todos : Permitir cursos históricos o fuera del período actual (por defecto filtra cursos de hoy)}
        {--json : Mostrar la salida estructurada en JSON}';

    /**
     * Descripción del comando.
     */
    protected $description = 'Obtiene un docente y un estudiante del mismo curso (por defecto del período actual activo) para pruebas rápidas de desarrollo';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(): int
    {
        $cursoId = $this->option('curso');
        $onlyWithActivities = $this->option('actividades');
        $shouldUnlock = $this->option('unlock');
        $moreStudentsCount = max(1, (int) $this->option('more-students'));
        $allowAnyPeriod = $this->option('todos');
        $asJson = $this->option('json');

        $agnoActual = now()->year;
        $semestreActual = now()->month >= 8 ? 2 : 1;

        // Construir consulta base de cursos con docentes y estudiantes
        $query = Curso::query()
            ->with([
                'docenteTitular.usuario',
                'componentes.tipoComponente',
                'componentes.docenteComponentes.docente.usuario',
                'componentes.actividades',
                'inscripcionCursos.estudiante.usuario',
            ])
            ->where('es_plantilla', false)
            ->where(function ($q) {
                $q->whereNotNull('id_docente_titular')
                  ->orWhereHas('componentes.docenteComponentes');
            });

        // Por defecto, filtrar cursos del período actual activo ("hoy")
        if (! $cursoId && ! $allowAnyPeriod) {
            $query->where('estado_interno', 'activo')
                  ->where('agno_real', $agnoActual)
                  ->where('semestre_real', $semestreActual)
                  ->whereHas('inscripcionCursos', function ($q) {
                      $q->where('estado_inscripcion', 'INSCRITO');
                  });
        } else {
            $query->whereHas('inscripcionCursos', function ($q) {
                $q->where('estado_inscripcion', '!=', 'RETIRADO')
                  ->orWhereNull('estado_inscripcion');
            });
        }

        if ($cursoId) {
            $query->where('id_curso', $cursoId);
        }

        if ($onlyWithActivities) {
            $query->whereHas('componentes.actividades');
        }

        /** @var Curso|null $curso */
        $curso = $cursoId ? $query->first() : $query->inRandomOrder()->first();

        if (! $curso) {
            if ($asJson) {
                $this->line(json_encode(['error' => 'No se encontró ningún curso con docentes y estudiantes inscritos.']));
            } else {
                $this->error('❌ No se encontró ningún curso que cumpla con los criterios especificados.');
            }

            return self::FAILURE;
        }

        // Resolver Docente (priorizar Titular, sino tomar el primero asignado a un componente)
        $docenteUser = null;
        $rolDocente = 'Docente Titular';
        if ($curso->docenteTitular && $curso->docenteTitular->usuario) {
            $docenteUser = $curso->docenteTitular->usuario;
        } else {
            foreach ($curso->componentes as $comp) {
                foreach ($comp->docenteComponentes as $dc) {
                    if ($dc->docente && $dc->docente->usuario) {
                        $docenteUser = $dc->docente->usuario;
                        $tipoCompNombre = $comp->tipoComponente->nombre ?? 'Componente';
                        $rolDocente = "Docente ({$tipoCompNombre})";
                        break 2;
                    }
                }
            }
        }

        if (! $docenteUser) {
            $this->error("❌ El curso ID {$curso->id_curso} no tiene ningún docente con usuario asociado.");
            return self::FAILURE;
        }

        // Resolver Estudiantes inscritos activos
        $inscripcionesValidas = $curso->inscripcionCursos
            ->filter(function ($insc) use ($cursoId, $allowAnyPeriod) {
                if (! $insc->estudiante || ! $insc->estudiante->usuario) {
                    return false;
                }
                if (! $cursoId && ! $allowAnyPeriod) {
                    return $insc->estado_inscripcion === 'INSCRITO';
                }
                return $insc->estado_inscripcion !== 'RETIRADO';
            })
            ->shuffle();

        if ($inscripcionesValidas->isEmpty()) {
            $this->error("❌ El curso ID {$curso->id_curso} no tiene estudiantes con usuario asociado.");
            return self::FAILURE;
        }

        $estudiantePrincipal = $inscripcionesValidas->first()->estudiante->usuario;
        $estudiantesListado = $inscripcionesValidas->take($moreStudentsCount)->map(fn ($i) => $i->estudiante->usuario);

        $hasFechaCol = \Illuminate\Support\Facades\Schema::hasColumn('usuario.usuario', 'fecha_cambio_passhash');

        // Desbloquear cuentas si se solicita --unlock
        if ($shouldUnlock) {
            $usuariosAdesbloquear = collect([$docenteUser])->merge($estudiantesListado)->unique('id_usuario');
            foreach ($usuariosAdesbloquear as $user) {
                /** @var Usuario $user */
                $user->passhash = Hash::make('password');
                if ($hasFechaCol) {
                    $user->fecha_cambio_passhash = now();
                }
                $user->save();
            }
        }

        // Total actividades del curso
        $totalActividades = $curso->componentes->flatMap->actividades->count();
        $actividadesMuestra = $curso->componentes->flatMap->actividades->take(3);

        // Salida JSON
        if ($asJson) {
            $payload = [
                'curso' => [
                    'id_curso' => $curso->id_curso,
                    'codigo' => $curso->cod_curso,
                    'nombre' => $curso->nombre,
                    'semestre' => $curso->semestre_real,
                    'agno' => $curso->agno_real,
                    'total_estudiantes' => $curso->inscripcionCursos->count(),
                    'total_actividades' => $totalActividades,
                ],
                'docente' => [
                    'id_usuario' => $docenteUser->id_usuario,
                    'rut' => $docenteUser->rut,
                    'username' => $docenteUser->username,
                    'email' => $docenteUser->email,
                    'nombre' => trim("{$docenteUser->nombre1} {$docenteUser->apellido1}"),
                    'rol' => $rolDocente,
                    'debe_cambiar_password' => $hasFechaCol ? $docenteUser->debeCambiarPassword() : false,
                ],
                'estudiante' => [
                    'id_usuario' => $estudiantePrincipal->id_usuario,
                    'rut' => $estudiantePrincipal->rut,
                    'username' => $estudiantePrincipal->username,
                    'email' => $estudiantePrincipal->email,
                    'nombre' => trim("{$estudiantePrincipal->nombre1} {$estudiantePrincipal->apellido1}"),
                    'debe_cambiar_password' => $hasFechaCol ? $estudiantePrincipal->debeCambiarPassword() : false,
                ],
                'otros_estudiantes' => $estudiantesListado->skip(1)->values()->map(fn ($e) => [
                    'username' => $e->username,
                    'rut' => $e->rut,
                    'nombre' => trim("{$e->nombre1} {$e->apellido1}"),
                ]),
                'desbloqueado' => $shouldUnlock,
            ];

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        // Salida Formateada CLI
        $this->newLine();
        $this->info("===============================================================================");
        $this->info(" 🎯 PAREJA DOCENTE & ESTUDIANTE DE PRUEBA (MISMO CURSO)");
        $this->info("===============================================================================");

        $esHoy = ($curso->agno_real === $agnoActual && $curso->semestre_real === $semestreActual && $curso->estado_interno === 'activo');
        $tagPeriodo = $esHoy ? ' <fg=green;options=bold>[Vigente / Hoy]</>' : ' <fg=yellow>[Histórico / Cerrado]</>';

        $this->line(" 📚 <options=bold>Curso:</> [ID: {$curso->id_curso}] {$curso->nombre} (Cód: {$curso->cod_curso})");
        $this->line(" 🗓️  <options=bold>Periodo:</> Semestre {$curso->semestre_real} · Año {$curso->agno_real}{$tagPeriodo}");
        $this->line(" 👥 <options=bold>Alumnos inscritos:</> {$curso->inscripcionCursos->count()} | <options=bold>Actividades:</> {$totalActividades}");
        $this->newLine();

        if ($shouldUnlock) {
            $this->warn(" 🔓 [ACCESO INMEDIATO HABILITADO]:");
            $this->line("    Contraseña restablecida a: <fg=yellow;options=bold>password</>");
            if ($hasFechaCol) {
                $this->line("    Vigencia de contraseña: <fg=green;options=bold>Al día (no pedirá cambio obligatorio)</>");
            }
            $this->newLine();
        }

        $estadoDocente = $hasFechaCol
            ? ($docenteUser->debeCambiarPassword() ? '<fg=red>⚠️ Requiere cambio</>' : '<fg=green>🟢 Vigente</>')
            : '<fg=green>🟢 Listo para login</>';

        $estadoEstudiante = $hasFechaCol
            ? ($estudiantePrincipal->debeCambiarPassword() ? '<fg=red>⚠️ Requiere cambio</>' : '<fg=green>🟢 Vigente</>')
            : '<fg=green>🟢 Listo para login</>';

        $headers = ['Rol', 'Nombre Completo', 'RUT', 'Username / Email', 'Password por Defecto', 'Estado Password'];
        $rows = [
            [
                "<fg=cyan;options=bold>{$rolDocente}</>",
                trim("{$docenteUser->nombre1} {$docenteUser->apellido1}"),
                $docenteUser->rut,
                "{$docenteUser->username}\n{$docenteUser->email}",
                'password',
                $estadoDocente,
            ],
            [
                '<fg=green;options=bold>Estudiante (Principal)</>',
                trim("{$estudiantePrincipal->nombre1} {$estudiantePrincipal->apellido1}"),
                $estudiantePrincipal->rut,
                "{$estudiantePrincipal->username}\n{$estudiantePrincipal->email}",
                'password',
                $estadoEstudiante,
            ],
        ];

        $this->table($headers, $rows);

        if ($estudiantesListado->count() > 1) {
            $this->newLine();
            $this->line(" 👥 <options=bold>Otros estudiantes de este mismo curso:</>");
            $otherRows = [];
            foreach ($estudiantesListado->skip(1) as $index => $other) {
                $otherRows[] = [
                    $index + 1,
                    trim("{$other->nombre1} {$other->apellido1}"),
                    $other->rut,
                    $other->username,
                    $other->email,
                ];
            }
            $this->table(['#', 'Nombre', 'RUT', 'Username', 'Email'], $otherRows);
        }

        if ($actividadesMuestra->isNotEmpty()) {
            $this->newLine();
            $this->line(" 📋 <options=bold>Muestra de Actividades del Curso:</>");
            foreach ($actividadesMuestra as $act) {
                $isSumativa = ($act->tipo_actividad instanceof \App\Enums\DB\TipoActividad)
                    ? $act->tipo_actividad === \App\Enums\DB\TipoActividad::SUMATIVA
                    : (strtoupper((string) ($act->tipo_actividad->value ?? $act->tipo_actividad ?? '')) === 'SUMATIVA');
                $tipoBadge = $isSumativa ? '<fg=red;options=bold>[Sumativa]</>' : '<fg=blue;options=bold>[Formativa]</>';
                $fechaStr = $act->fecha_limite ? $act->fecha_limite->format('Y-m-d') : 'Sin fecha';
                $this->line("    • [ID: {$act->id_actividad}] {$act->nombre} {$tipoBadge} - Límite: {$fechaStr}");
            }
        }

        $this->newLine();
        $this->info(" 🔗 <options=bold>ENLACES DIRECTOS PARA DEV:</>");
        $this->line("    • Login:                 <fg=blue>http://localhost:8000/login</>");
        $this->line("    • Vista Docente Curso:   <fg=blue>http://localhost:8000/docente/cursos/{$curso->id_curso}/actividades</>");
        $this->line("    • Vista Estudiante:      <fg=blue>http://localhost:8000/estudiante/dashboard</>");
        $this->line("    • Detalle Alumno Curso:  <fg=blue>http://localhost:8000/estudiante/cursos/{$curso->id_curso}</>");

        if (! $shouldUnlock && ($docenteUser->debeCambiarPassword() || $estudiantePrincipal->debeCambiarPassword())) {
            $this->newLine();
            $this->comment(" 💡 Tip: Usa el flag --unlock para saltarte la pantalla de cambio de clave:");
            $this->line("    <fg=yellow>php artisan dev:pair --curso={$curso->id_curso} --unlock</>");
        }

        $this->newLine();
        return self::SUCCESS;
    }
}
