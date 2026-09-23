<?php

namespace Database\Seeders\Dm095;

use App\Enums\DB\EstadoRubrica;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Carrera;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\Programa;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\Agenda\GrupoIndividualService;
use App\Services\CursoService;
use App\Services\EstudianteService;
use App\Services\InscripcionCursoService;
use App\Services\ProgramaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * SÓLO LOCAL. Reproduce en la BD de desarrollo lo que hay en producción para
 * DM095 (foto del 2026-09-21 en estado_produccion_2026-09-21.xlsx), para poder
 * probar Dm095ContenidoSeeder y Dm095LimpiarLaboratorioSeeder contra un estado
 * realista antes de correrlos allá.
 *
 * Reproduce, con los mismos servicios que usa la app (así los contextos y roles
 * quedan como los dejaría el wizard):
 *  - el docente titular (csandovy) con su perfil usuario.docente;
 *  - los cursos A y B del período 2026-2 con los cod_curso reales (11000 y
 *    111100), **sólo con Taller**: DM095 es full Taller (malla c=0, t=4, l=0).
 *    Con DM095_REPRODUCIR_BUG_LABORATORIO=1 se agrega además el Laboratorio que
 *    producción tiene por error, para que la limpieza tenga qué limpiar;
 *  - los 38 alumnos de estudiantes.xlsx inscritos en su paralelo, en curso y en
 *    sus componentes, como hace IntranetService::inscribirAutomaticamente;
 *  - lo que la sección A ya tenía: 3 unidades, un programa COMPLETO/APROBADO
 *    creado por superadmin y la actividad «Tarea 1» (SUMATIVA 30 %) con su
 *    rúbrica y sus 19 grupos individuales.
 *
 * Se niega a correr fuera de local/testing. Es idempotente: si el curso ya
 * existe no lo vuelve a crear.
 */
class Dm095EscenarioProduccionSeeder extends Seeder
{
    /**
     * DM095 es full Taller: su malla declara (c=0, t=4, l=0). El Laboratorio que
     * producción tiene en A y en B es un error de Intranet/el wizard, el mismo que
     * limpia Dm095LimpiarLaboratorioSeeder.
     *
     * Por defecto NO se reproduce: el escenario local queda como el curso debe ser.
     * Con DM095_REPRODUCIR_BUG_LABORATORIO=1 se crea igual, que es lo que hace falta
     * para probar el seeder de limpieza contra un estado realista antes de correrlo
     * en producción.
     */
    private function reproducirBugLaboratorio(): bool
    {
        return filter_var(env('DM095_REPRODUCIR_BUG_LABORATORIO', false), FILTER_VALIDATE_BOOL);
    }

    /** RUT real de Cristian Sandoval Yañez, entregado por el equipo el 2026-09-22. */
    private const RUT_DOCENTE = '12024627-5';

    /** Clave del docente en local, entregada por el equipo. */
    private const PASSWORD_DOCENTE_LOCAL = 'csando1234';

    private Dm095Datos $datos;

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command->error('Dm095EscenarioProduccionSeeder es SÓLO para local/testing: reproduce producción, no la carga. Abortado.');
            return;
        }

        // db:seed corre con Model::unguarded(); los servicios de la app (CursoService,
        // CarreraService…) pasan a firstOrCreate atributos que no existen en la tabla
        // y que en la web el $fillable descarta. Con la guarda puesta se comportan igual
        // que desde el wizard.
        Model::reguard();

        $this->datos = new Dm095Datos();
        $this->command->info('== Dm095EscenarioProduccionSeeder: reproduciendo el estado de producción (2026-09-21) en local ==');

        $docente = $this->asegurarDocente();
        $cursos = [];
        foreach ($this->datos->cursos() as $fila) {
            $cursos[$fila['letra_grupo']] = $this->asegurarCurso($fila, $docente);
        }

        $this->inscribirEstudiantes($cursos);
        $this->estadoPrevioDeA($cursos['A'] ?? null, $docente);

        $this->command->info("\nEscenario listo. Ahora: php artisan db:seed --class=Database\\\\Seeders\\\\Dm095\\\\Dm095ContenidoSeeder");
    }

    // ------------------------------------------------------------------ docente

    private function asegurarDocente(): Docente
    {
        $t = $this->datos->tutor();
        $username = $t['username'] ?? 'csandovy';

        $usuario = Usuario::where('username', $username)->first()
            ?? Usuario::where('rut', self::RUT_DOCENTE)->first();

        if (!$usuario) {
            // Nombre del PDF: «Cristian Sandoval Yañez». El modelo guarda en mayúsculas.
            $usuario = Usuario::create([
                'username'                 => $username,
                'email'                    => $t['email'] ?? 'csandovy@academicos.uta.cl',
                'rut'                      => self::RUT_DOCENTE,
                'nombre1'                  => 'Cristian',
                'apellido1'                => 'Sandoval',
                'apellido2'                => 'Yañez',
                'passhash'                 => Hash::make(self::PASSWORD_DOCENTE_LOCAL),
                'fecha_verificacion_email' => now(),
                'esta_activo'              => true,
                // Con fecha para que el login no quede interceptado por el cambio
                // obligatorio de contraseña: en local se entra directo.
                'fecha_cambio_passhash'    => now(),
            ]);
            $this->command->line('   usuario ' . $username . ' creado (RUT ' . self::RUT_DOCENTE . ', clave local: ' . self::PASSWORD_DOCENTE_LOCAL . ').');
        } else {
            $this->command->line("   usuario {$usuario->username} ya existía (RUT {$usuario->rut}).");
        }

        $docente = Docente::firstOrCreate(
            ['id_usuario' => $usuario->id_usuario],
            ['grado' => $t['grado'] ?? null, 'titulo' => $t['titulo'] ?? null, 'cargo' => $t['cargo'] ?? null]
        );

        return $docente->load('usuario');
    }

    // ------------------------------------------------------------------ cursos

    private function asegurarCurso(array $fila, Docente $docente): Curso
    {
        $existente = Curso::where('cod_curso', $fila['cod_curso_prod'])->first();
        if ($existente) {
            $this->command->line("   curso {$fila['letra_grupo']} (cod_curso {$fila['cod_curso_prod']}) ya existía: id {$existente->id_curso}.");
            return $existente;
        }

        $asignacion = AsignacionPlan::whereHas('asignatura', fn ($q) => $q->where('cod_asignatura', $fila['cod_asignatura']))
            ->whereNull('fecha_eliminacion')
            ->firstOrFail();

        $taller = TipoComponente::where('tipo', 'Taller')->firstOrFail();
        $laboratorio = TipoComponente::where('tipo', 'Laboratorio')->firstOrFail();

        $conLaboratorio = $this->reproducirBugLaboratorio();

        // Producción: A tiene docente en Laboratorio y Taller; B sólo en Laboratorio
        // (Taller#12 quedó sin docente_componente). Sin el Laboratorio, el Taller es
        // el único componente y lleva al titular igual.
        $docentesPorComponente = [];
        if ($conLaboratorio) {
            $docentesPorComponente[$laboratorio->id_tipo_componente] = $docente->id_docente;
        }
        if ($fila['docente_taller_email'] || !$conLaboratorio) {
            $docentesPorComponente[$taller->id_tipo_componente] = $docente->id_docente;
        }

        $curso = app(CursoService::class)->create([
            'id_asignatura'                => $asignacion->id_asignatura,
            'id_plan'                      => $asignacion->id_plan,
            'cod_curso'                    => $fila['cod_curso_prod'],
            'nombre'                       => $asignacion->asignatura?->nombre ?? 'Taller Profesional IV',
            'fecha_inicio'                 => $fila['fecha_inicio'],
            'agno_real'                    => $fila['agno_real'],
            'semestre_real'                => $fila['semestre_real'],
            'indice_grupo'                 => $fila['indice_grupo'],
            'id_docente_sugerido'          => $docente->id_docente,
            'id_tipo_componente_principal' => $taller->id_tipo_componente,
            'tipos_componente_ids'         => $conLaboratorio ? [$taller->id_tipo_componente, $laboratorio->id_tipo_componente] : [$taller->id_tipo_componente],
            'docentes_por_componente'      => $docentesPorComponente,
        ]);

        // id_contexto no es fillable: lo pone el trigger tr_curso_pre_insert, así que
        // el modelo que devuelve el servicio no lo trae hasta releerlo.
        $curso->refresh();

        $comps = Componente::with('tipoComponente')->where('id_curso', $curso->id_curso)->get()
            ->map(fn ($c) => $c->tipoComponente->tipo . '#' . $c->id_componente)->implode(', ');
        $this->command->line("   curso {$fila['letra_grupo']} creado: id {$curso->id_curso}, cod_curso {$curso->cod_curso}, {$curso->fecha_inicio->format('Y-m-d')} → {$curso->fecha_fin->format('Y-m-d')}, componentes {$comps}.");

        return $curso;
    }

    // ------------------------------------------------------------------ estudiantes

    /** @param array<string, Curso> $cursos letra => curso */
    private function inscribirEstudiantes(array $cursos): void
    {
        $carrera = Carrera::where('nombre', 'Diseño Multimedia')->firstOrFail();
        $estudianteService = app(EstudianteService::class);
        $inscripcionService = app(InscripcionCursoService::class);
        $creados = $inscritos = 0;

        foreach ($this->datos->estudiantes() as $e) {
            $curso = $cursos[$e['grupo']] ?? null;
            if (!$curso) {
                $this->command->warn("   estudiante {$e['rut']}: grupo «{$e['grupo']}» sin curso; omitido.");
                continue;
            }

            $estudiante = $estudianteService->buscarPorRut($e['rut']);
            if (!$estudiante) {
                // Mismo camino que Intranet: username = cuerpo del RUT, clave = RUT, nombres en mayúsculas.
                $estudiante = Estudiante::createFromIntranet([
                    'rut'       => $e['rut'],
                    'dv'        => self::digitoVerificador($e['rut']),
                    'nombre'    => trim($e['nombre1'] . ' ' . ($e['nombre2'] ?? '')),
                    'apellido1' => $e['apellido1'],
                    'apellido2' => $e['apellido2'],
                ], $carrera);
                $creados++;
            }

            // Intranet no trae email; estudiantes.xlsx sí. Se completa si está libre.
            if ($e['email'] && $estudiante->usuario && !$estudiante->usuario->email
                && !Usuario::where('email', $e['email'])->exists()) {
                $estudiante->usuario->update(['email' => $e['email']]);
            }

            $yaInscrito = InscripcionCurso::where('id_curso', $curso->id_curso)->where('id_estudiante', $estudiante->id_estudiante)->exists();
            if (!$yaInscrito) {
                $inscripcionService->create(['id_curso' => $curso->id_curso, 'id_estudiante' => $estudiante->id_estudiante]);
                $inscritos++;
            }
            $estudianteService->asignarRolEnContexto($estudiante, $curso->id_contexto);

            foreach (Componente::where('id_curso', $curso->id_curso)->get() as $componente) {
                InscripcionComponente::firstOrCreate([
                    'id_estudiante' => $estudiante->id_estudiante,
                    'id_componente' => $componente->id_componente,
                ]);
                $estudianteService->asignarRolEnContexto($estudiante, $componente->id_contexto);
            }
        }

        $this->command->line("   estudiantes: {$creados} creados, {$inscritos} inscripciones nuevas (curso + componentes + roles).");
    }

    /** Módulo 11 del RUT chileno. */
    public static function digitoVerificador(int $cuerpo): string
    {
        $suma = 0;
        $factor = 2;
        foreach (array_reverse(str_split((string) $cuerpo)) as $d) {
            $suma += (int) $d * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $resto = 11 - ($suma % 11);
        return match ($resto) { 11 => '0', 10 => 'K', default => (string) $resto };
    }

    // ------------------------------------------------------------------ estado previo de A

    /**
     * Lo que producción ya tenía en A antes de cualquier seeder: unidades,
     * programa aprobado y «Tarea 1» en el Laboratorio.
     */
    private function estadoPrevioDeA(?Curso $curso, Docente $docente): void
    {
        if (!$curso) {
            return;
        }

        DB::transaction(function () use ($curso) {
            // Unidades con el nombre y la descripción que dejaron a mano en producción.
            $contenidos = $this->datos->contenidos();
            $unidades = [];
            foreach ($this->datos->unidades() as $u) {
                $unidades[$u['numero']] = Unidad::firstOrCreate(
                    ['id_curso' => $curso->id_curso, 'num_unidad' => $u['numero']],
                    [
                        'nombre'      => Dm095SyllabusBuilder::tituloUnidad($u['numero'], $u['titulo']),
                        'descripcion' => Dm095SyllabusBuilder::descripcionUnidad($contenidos[$u['numero']] ?? []),
                    ]
                );
            }
            $this->command->line('   A: ' . count($unidades) . ' unidades.');

            // Programa COMPLETO / APROBADO creado por superadmin, con I.codigo = cod_curso
            // (tal como quedó en producción; el clon para B lo corrige).
            if (!Programa::where('id_curso', $curso->id_curso)->where('es_actual', true)->exists()) {
                $superadmin = Usuario::where('username', 'superadmin')->first() ?? $curso->docenteTitular->usuario;
                $secciones = (new Dm095SyllabusBuilder($this->datos))->secciones();
                $secciones['I']['contenido']['codigo'] = (string) $curso->cod_curso;

                $programa = ProgramaService::generateProgramaWithSyllabus($curso, $superadmin, [
                    'tipo_syllabus' => 'COMPLETO',
                    'estado'        => 'APROBADO',
                    'secciones'     => $secciones,
                ]);
                $programa->update(['revisado_por' => $superadmin->id_usuario]);
                $this->command->line("   A: programa id {$programa->id_programa} COMPLETO/APROBADO creado por {$superadmin->username}.");
            }

            // «Tarea 1»: la actividad real del docente, en el Laboratorio (bug) y con rúbrica.
            $laboratorio = Componente::where('id_curso', $curso->id_curso)
                ->whereHas('tipoComponente', fn ($q) => $q->where('tipo', 'Laboratorio'))->first()
                ?? Componente::where('id_curso', $curso->id_curso)->whereHas('tipoComponente', fn ($q) => $q->where('tipo', 'Taller'))->firstOrFail();

            $tarea = Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))->where('nombre', 'Tarea 1')->first();
            if (!$tarea) {
                $tarea = Actividad::create([
                    'nombre'                            => 'Tarea 1',
                    'fecha_limite'                      => '2026-09-15',
                    'tipo_actividad'                    => 'SUMATIVA',
                    'tipo_entrega'                      => 'online',
                    'ponderacion'                       => 30,
                    'exigencia'                         => 60,
                    'nro_dias_adicionales_para_bloqueo' => 0,
                    'visible'                           => true,
                    'es_grupal'                         => false,
                    'max_integrantes'                   => 1,
                    'es_plantilla'                      => false,
                    'id_componente'                     => $laboratorio->id_componente,
                    'id_unidad'                         => $unidades[1]->id_unidad,
                ]);
                app(GrupoIndividualService::class)->asegurarGruposDelCurso($curso, $tarea);

                // Rúbrica mínima con la forma de resources/js/types/rubrica.ts.
                Rubrica::create([
                    'id_actividad'   => $tarea->id_actividad,
                    'estado_rubrica' => EstadoRubrica::POSTULADA,
                    'rubrica'        => [
                        'columnas' => [
                            ['id' => 'c1', 'nombre' => 'Insuficiente', 'puntos' => 1],
                            ['id' => 'c2', 'nombre' => 'Suficiente', 'puntos' => 3],
                            ['id' => 'c3', 'nombre' => 'Destacado', 'puntos' => 5],
                        ],
                        'niveles' => [[
                            'id'             => 'n1',
                            'nombre'         => 'Prototipo funcional',
                            'descripcion'    => 'El laberinto se recorre y tiene elementos de juego (escenario local).',
                            'ponderacion'    => 100,
                            'nro_escalas'    => 3,
                            'puntaje_total'  => 5,
                            'puntaje_minimo' => 1,
                            'escalas'        => [
                                ['id' => 'n1c1', 'puntos' => 1, 'criterio' => 'No compila o no se recorre.'],
                                ['id' => 'n1c2', 'puntos' => 3, 'criterio' => 'Se recorre; faltan elementos de juego.'],
                                ['id' => 'n1c3', 'puntos' => 5, 'criterio' => 'Se recorre e incorpora elementos de juego.'],
                            ],
                        ]],
                        'detalles_evaluacion' => ['puntaje_total' => 5, 'escala_evaluacion' => []],
                    ],
                ]);
                $tipo = $laboratorio->tipoComponente?->tipo ?? 'componente';
                $this->command->line("   A: «Tarea 1» (id {$tarea->id_actividad}) creada en {$tipo}#{$laboratorio->id_componente} con rúbrica y grupos individuales.");
            }
        });
    }
}
