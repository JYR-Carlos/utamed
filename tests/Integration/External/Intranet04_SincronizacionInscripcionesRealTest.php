<?php

use App\DTOs\External\ResultadoSincronizacionInscripciones;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\External\VwInscripcion;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\IntranetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Integration\External\IntranetTestHelper;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    IntranetTestHelper::ensureConnected($this);
});

describe('04. Flujo de Sincronización Bidireccional con Oracle Real (Manejo de Ramos Botados)', function () {

    test('sincroniza con Oracle real y detecta a alumno local ausente en el acta marcandolo como RETIRADO', function () {
        // 1. Obtener una inscripción y curso real desde Oracle usando LIMIT estricto (first())
        $inscripcionReal = VwInscripcion::with('carreraCurso')->first();
        if (!$inscripcionReal || !$inscripcionReal->carreraCurso) {
            $this->markTestSkipped('No se encontraron inscripciones con curso asociado en Oracle.');
        }

        $cursoOracle = $inscripcionReal->carreraCurso;
        $semestreReal = (int)$cursoOracle->CURSO_SEMESTRE_ASIG;
        $agnoReal = (int)$cursoOracle->CURSO_ANO;
        $codCarrera = (int)$cursoOracle->CARRERA_COD;
        $agnoPlan = (int)$cursoOracle->PLAN_ANO;
        $codAsignatura = trim($cursoOracle->ASIG_CODIGO);
        $letraGrupo = $cursoOracle->CURSO_GRUPO_ASIG;
        $tipoAsigOracle = $cursoOracle->CURSO_TIPO_ASIG; // C, T, L

        // 2. Crear jerarquía académica en PostgreSQL que corresponda a los datos reales de Oracle
        $facultad = Facultad::firstOrCreate(
            ['nombre' => 'Facultad Test Oracle Real Sincronizacion'],
            ['id_contexto' => 1]
        );

        $departamento = Departamento::firstOrCreate(
            ['nombre' => 'Departamento Test Oracle Real Sincronizacion'],
            ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]
        );

        $carrera = Carrera::find($codCarrera);
        if (!$carrera) {
            $contextoCarrera = Contexto::create(['contexto_display' => "Contexto Carrera {$codCarrera} Test Oracle Sync"]);
            DB::statement("
                INSERT INTO carrera (id_carrera, nombre, id_departamento, id_contexto, fecha_creacion, fecha_modificacion)
                OVERRIDING SYSTEM VALUE
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ", [$codCarrera, "Carrera {$codCarrera} Test Oracle Sync", $departamento->id_departamento, $contextoCarrera->id_contexto]);
            $carrera = Carrera::find($codCarrera);
        }

        $plan = Plan::firstOrCreate(
            ['id_carrera' => $carrera->id_carrera, 'agno_plan' => $agnoPlan],
            ['version_plan' => 1, 'id_contexto' => 1]
        );

        $asignatura = Asignatura::firstOrCreate(
            ['cod_asignatura' => $codAsignatura],
            [
                'nombre'            => "Asignatura {$codAsignatura} Sync Test",
                'creditos_sct'      => 6,
                'horas_catedra'     => 4,
                'horas_taller'      => 0,
                'horas_laboratorio' => 0,
                'horas_dirigidas'   => 2,
                'horas_autonomas'   => 4,
            ]
        );

        $asignacionPlan = AsignacionPlan::firstOrCreate(
            ['id_plan' => $plan->id_plan, 'id_asignatura' => $asignatura->id_asignatura],
            [
                'agno_planificado'     => 1,
                'semestre_planificado' => $semestreReal,
                'id_contexto'          => 1,
            ]
        );

        // 3. Crear docente y contexto de curso
        $usuarioDocente = Usuario::firstOrCreate(
            ['rut' => '11111111-1'],
            [
                'username'    => 'docente_oracle_sync_test',
                'passhash'    => bcrypt('password'),
                'nombre1'     => 'Docente',
                'apellido1'   => 'Test',
                'esta_activo' => true,
            ]
        );
        $usuarioDocente->fecha_cambio_passhash = now();
        $usuarioDocente->save();

        $docente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario]);

        $indiceGrupo = max(1, ord(strtoupper($letraGrupo ?: 'A')) - 64);

        $contextoCurso = Contexto::create(['contexto_display' => "Curso {$codAsignatura} Oracle Sync Test " . uniqid()]);
        $curso = Curso::forceCreate([
            'cod_curso'          => rand(90000, 99999),
            'nombre'             => "Curso {$codAsignatura} {$letraGrupo} Sync Test",
            'indice_grupo'       => $indiceGrupo,
            'fecha_inicio'       => now()->toDateString(),
            'fecha_fin'          => now()->addMonths(5)->toDateString(),
            'semestre_real'      => $semestreReal,
            'agno_real'          => $agnoReal,
            'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
            'id_contexto'        => $contextoCurso->id_contexto,
            'id_docente_titular' => $docente->id_docente,
        ]);

        $curso->refresh();

        // 4. Crear en PostgreSQL TODOS los componentes que Oracle tiene para este curso (ej. C, T, L)
        // para que la lectura de Intranet sea completa y no queden componentes sin mapear.
        /** @var IntranetService $intranetService */
        $intranetService = app(IntranetService::class);
        $componentesIntranet = $intranetService->resolverComponentesIntranet($curso);

        $tipoNombreMap = ['C' => 'CATEDRA', 'T' => 'TALLER', 'L' => 'LABORATORIO'];
        foreach ($componentesIntranet as $compInt) {
            $tipoNombre = $tipoNombreMap[$compInt->curso_tipo_asig->value] ?? 'CATEDRA';
            $tipoComp = TipoComponente::firstOrCreate(['tipo' => $tipoNombre]);
            $contextoComp = Contexto::create(['contexto_display' => "Componente {$tipoNombre} Oracle Sync Test " . uniqid()]);
            Componente::forceCreate([
                'genera_acta'                       => true,
                'porcentaje_aprobacion'             => 60,
                'aprobacion_obligatoria'            => true,
                'porcentaje_asistencia_obligatoria' => 75,
                'id_tipo_componente'                => $tipoComp->id_tipo_componente,
                'id_curso'                          => $curso->id_curso,
                'id_contexto'                       => $contextoComp->id_contexto,
            ]);
        }

        $curso->refresh();
        $curso->load('componentes.tipoComponente');

        // 5. Primera corrida de sincronización con Oracle Real: inscribe a los alumnos del acta
        $resultado1 = $intranetService->sincronizarInscripciones($curso);

        expect($resultado1)->toBeInstanceOf(ResultadoSincronizacionInscripciones::class);
        expect($resultado1->retiro_aplicado)->toBeTrue();
        expect($resultado1->inscripcion->total_procesados)->toBeGreaterThanOrEqual(1);

        // Como todos los inscritos vienen de Oracle, ninguno debe ser retirado
        expect($resultado1->retirados)->toBeEmpty();

        // 5. Simular alumno que botó el ramo: creamos un alumno dummy local en PostgreSQL con estado INSCRITO
        $rutDummy = '29999999-9';
        $usuarioDummy = Usuario::forceCreate([
            'rut'                   => $rutDummy,
            'username'              => 'dummy_botado_' . uniqid(),
            'passhash'              => bcrypt('password123'),
            'nombre1'               => 'ALUMNO',
            'apellido1'             => 'BOTADO',
            'esta_activo'           => true,
            'fecha_cambio_passhash' => now(),
        ]);
        $estudianteDummy = Estudiante::create([
            'id_usuario' => $usuarioDummy->id_usuario,
            'id_carrera' => $carrera->id_carrera,
        ]);
        $inscripcionDummy = InscripcionCurso::create([
            'id_curso'            => $curso->id_curso,
            'id_estudiante'       => $estudianteDummy->id_estudiante,
            'cod_inscripcion_uta' => '999999',
            'fecha_inscripcion'   => now()->toDateString(),
            'estado_inscripcion'  => 'INSCRITO',
            'num_intento'         => 1,
        ]);

        $rolEstudiante = Rol::where('nombre', 'Estudiante')->first();
        UsuarioRolAsignacion::create([
            'id_usuario'               => $usuarioDummy->id_usuario,
            'id_rol'                   => $rolEstudiante->id_rol,
            'id_contexto'              => $curso->id_contexto,
            'asignado_por'             => $usuarioDocente->id_usuario,
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada'    => now()->addYears(1),
            'esta_activo'              => true,
            'fue_eliminado'            => false,
            'creado_por'               => $usuarioDocente->id_usuario,
        ]);

        // 6. Segunda corrida de sincronización con Oracle Real:
        // Debe detectar que el alumno dummy NO figura en el acta de Oracle y retirarlo automáticamente
        $resultado2 = $intranetService->sincronizarInscripciones($curso);

        expect($resultado2->retiro_aplicado)->toBeTrue();
        expect($resultado2->retirados)->not->toBeEmpty();

        $rutsRetirados = array_column($resultado2->retirados, 'rut');
        expect($rutsRetirados)->toContain($rutDummy);

        // Validar persistencia en PostgreSQL:
        // El alumno dummy pasa a estado RETIRADO
        $inscripcionDummy->refresh();
        expect($inscripcionDummy->estado_inscripcion)->toBe('RETIRADO');

        // Su rol en el contexto del curso debe estar inactivo/revocado
        $rolActivoDummy = UsuarioRolAsignacion::where('id_usuario', $usuarioDummy->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolEstudiante->id_rol)
            ->where('esta_activo', true)
            ->exists();
        expect($rolActivoDummy)->toBeFalse();

        // Los alumnos reales provenientes de Oracle deben seguir INSCRITOS y con rol activo
        $insRealPostgres = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('id_estudiante', '!=', $estudianteDummy->id_estudiante)
            ->first();

        expect($insRealPostgres)->not->toBeNull();
        expect($insRealPostgres->estado_inscripcion)->toBe('INSCRITO');

        $rolActivoReal = UsuarioRolAsignacion::where('id_usuario', $insRealPostgres->estudiante->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolEstudiante->id_rol)
            ->where('esta_activo', true)
            ->exists();
        expect($rolActivoReal)->toBeTrue();
    });
});
