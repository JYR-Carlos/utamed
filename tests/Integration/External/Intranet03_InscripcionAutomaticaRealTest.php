<?php

use App\DTOs\External\ResultadoInscripcionAutomatica;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\External\VwCarreraCurso;
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

describe('03. Flujo de Inscripción Automática con Oracle Real (Sin Mocks)', function () {

    test('inscribe automáticamente alumnos reales desde Oracle a un curso y componente en PostgreSQL', function () {
        // 1. Obtener una inscripción y curso real desde Oracle para tener códigos válidos
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
            ['nombre' => 'Facultad Test Oracle Real'],
            ['id_contexto' => 1]
        );

        $departamento = Departamento::firstOrCreate(
            ['nombre' => 'Departamento Test Oracle Real'],
            ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]
        );

        $carrera = Carrera::find($codCarrera);
        if (!$carrera) {
            $contextoCarrera = Contexto::create(['contexto_display' => "Contexto Carrera {$codCarrera} Test Oracle"]);
            DB::statement("
                INSERT INTO carrera (id_carrera, nombre, id_departamento, id_contexto, fecha_creacion, fecha_modificacion)
                OVERRIDING SYSTEM VALUE
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ", [$codCarrera, "Carrera {$codCarrera} Test Oracle", $departamento->id_departamento, $contextoCarrera->id_contexto]);
            $carrera = Carrera::find($codCarrera);
        }

        $plan = Plan::firstOrCreate(
            ['id_carrera' => $carrera->id_carrera, 'agno_plan' => $agnoPlan],
            ['version_plan' => 1, 'id_contexto' => 1]
        );

        $asignatura = Asignatura::firstOrCreate(
            ['cod_asignatura' => $codAsignatura],
            [
                'nombre'            => "Asignatura {$codAsignatura} Test",
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
                'username'    => 'docente_oracle_test',
                'passhash'    => bcrypt('password'),
                'nombre1'     => 'Docente',
                'apellido1'   => 'Test',
                'esta_activo' => true,
            ]
        );
        $docente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario]);

        // Calcular indice_grupo numérico a partir de la letra (A=1, B=2, C=3, etc.)
        $indiceGrupo = max(1, ord(strtoupper($letraGrupo ?: 'A')) - 64);

        $contextoCurso = Contexto::create(['contexto_display' => "Curso {$codAsignatura} Oracle Test " . uniqid()]);
        $curso = Curso::forceCreate([
            'cod_curso'          => rand(90000, 99999),
            'nombre'             => "Curso {$codAsignatura} {$letraGrupo} Test",
            'indice_grupo'       => $indiceGrupo,
            'fecha_inicio'       => now()->toDateString(),
            'fecha_fin'          => now()->addMonths(5)->toDateString(),
            'semestre_real'      => $semestreReal,
            'agno_real'          => $agnoReal,
            'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
            'id_contexto'        => $contextoCurso->id_contexto,
            'id_docente_titular' => $docente->id_docente,
        ]);

        // Determinar tipo de componente correspondiente (C -> CATEDRA, T -> TALLER, L -> LABORATORIO)
        $tipoNombreMap = ['C' => 'CATEDRA', 'T' => 'TALLER', 'L' => 'LABORATORIO'];
        $tipoNombre = $tipoNombreMap[$tipoAsigOracle] ?? 'CATEDRA';

        $tipoComp = TipoComponente::firstOrCreate(['tipo' => $tipoNombre]);
        $contextoComp = Contexto::create(['contexto_display' => "Componente {$tipoNombre} Oracle Test"]);
        $componente = Componente::forceCreate([
            'genera_acta'                       => true,
            'porcentaje_aprobacion'             => 60,
            'aprobacion_obligatoria'            => true,
            'porcentaje_asistencia_obligatoria' => 75,
            'id_tipo_componente'                => $tipoComp->id_tipo_componente,
            'id_curso'                          => $curso->id_curso,
            'id_contexto'                       => $contextoComp->id_contexto,
        ]);

        // 4. Ejecutar el servicio de Inscripción Automática CON LA INTRANET REAL
        /** @var IntranetService $intranetService */
        $intranetService = app(IntranetService::class);
        $resultado = $intranetService->inscribirAutomaticamente($curso);

        // 5. Validar el resultado del servicio
        expect($resultado)->toBeInstanceOf(ResultadoInscripcionAutomatica::class);
        expect($resultado->total_procesados)->toBeGreaterThanOrEqual(1);
        expect($resultado->inscritos_exitosamente + $resultado->ya_inscritos)->toBeGreaterThanOrEqual(1);

        // 6. Verificar que el alumno de la muestra se creó y vinculó en PostgreSQL
        $alumRutReal = $inscripcionReal->ALUM_RUT;
        $usuarioCreado = Usuario::where('username', (string)$alumRutReal)->first();

        expect($usuarioCreado)->not->toBeNull();
        expect($usuarioCreado->nombre1)->not->toBeEmpty();
        expect($usuarioCreado->apellido1)->not->toBeEmpty();

        $estudiante = Estudiante::where('id_usuario', $usuarioCreado->id_usuario)->first();
        expect($estudiante)->not->toBeNull();
        expect($estudiante->id_carrera)->toBe($carrera->id_carrera);

        // Verificar InscripcionCurso e InscripcionComponente
        $insCurso = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->first();
        expect($insCurso)->not->toBeNull();
        expect($insCurso->cod_inscripcion_uta)->not->toBeNull();

        $insComp = InscripcionComponente::where('id_componente', $componente->id_componente)
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->first();
        expect($insComp)->not->toBeNull();
        expect($insComp->cod_inscripcion_curso_uta)->not->toBeNull();

        // Verificar roles en los contextos
        $rolEstudiante = Rol::where('nombre', 'Estudiante')->first();
        expect($rolEstudiante)->not->toBeNull();

        $rolEnCurso = UsuarioRolAsignacion::where('id_usuario', $usuarioCreado->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolEstudiante->id_rol)
            ->where('esta_activo', true)
            ->exists();
        expect($rolEnCurso)->toBeTrue();
    });
});
