<?php

use App\DTOs\External\AlumnoIntranetData;
use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\InscripcionData;
use App\Models\External\VwAlumno;
use App\Models\External\VwCarreraCurso;
use App\Models\External\VwInscripcion;
use Tests\Integration\External\IntranetTestHelper;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    IntranetTestHelper::ensureConnected($this);
});

describe('02. Servicio OracleDataService Real (Sin Mocks)', function () {

    test('traer_alumno() retorna datos reales de un alumno desde Oracle', function () {
        $oracleService = app('OracleDataService');

        // Obtener un alumno real para la prueba
        $alumnoDb = VwAlumno::first();
        expect($alumnoDb)->not->toBeNull();

        $rut = $alumnoDb->ALUM_RUT;
        $alumnoData = $oracleService->traer_alumno($rut);

        expect($alumnoData)->toBeInstanceOf(AlumnoIntranetData::class);
        expect($alumnoData->alum_rut)->toBe((int)$rut);
        expect($alumnoData->alum_nombre)->not->toBeEmpty();
        expect($alumnoData->alum_apellido_pat)->not->toBeEmpty();
    });

    test('traer_alumno() retorna null para un RUT inexistente', function () {
        $oracleService = app('OracleDataService');
        $resultado = $oracleService->traer_alumno(99999999);

        expect($resultado)->toBeNull();
    });

    test('traer_ins_id() retorna inscripciones reales asociadas a un CUR_CODIGO', function () {
        $oracleService = app('OracleDataService');

        // Obtener una inscripción real existente en Oracle
        $inscripcionDb = VwInscripcion::first();
        expect($inscripcionDb)->not->toBeNull();

        $curCodigo = $inscripcionDb->CUR_CODIGO;
        $inscripciones = $oracleService->traer_ins_id([$curCodigo]);

        expect($inscripciones)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($inscripciones)->not->toBeEmpty();

        $primera = $inscripciones->first();
        expect($primera)->toBeInstanceOf(InscripcionData::class);
        expect($primera->ins_id)->not->toBeNull();
        expect($primera->alum_rut)->not->toBeNull();
    });

    test('traer_cur_codigos() retorna componentes reales asociadas a una asignatura', function () {
        $oracleService = app('OracleDataService');

        // Obtener un curso real existente en Oracle
        $cursoDb = VwCarreraCurso::first();
        expect($cursoDb)->not->toBeNull();

        $curCodigos = $oracleService->traer_cur_codigos(
            semestre: (int)$cursoDb->CURSO_SEMESTRE_ASIG,
            agno: (int)$cursoDb->CURSO_ANO,
            carreraCod: (int)$cursoDb->CARRERA_COD,
            planCod: (int)$cursoDb->PLAN_ANO,
            asigCodigo: trim($cursoDb->ASIG_CODIGO),
            grupoAsig: $cursoDb->CURSO_GRUPO_ASIG
        );

        expect($curCodigos)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($curCodigos)->not->toBeEmpty();

        $primerComponente = $curCodigos->first();
        expect($primerComponente)->toBeInstanceOf(ComponenteCursoData::class);
        expect($primerComponente->cur_codigo)->not->toBeNull();
    });
});
