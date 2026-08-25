<?php

use App\Models\External\VwAlumno;
use App\Models\External\VwCarreraCurso;
use App\Models\External\VwInscripcion;
use Illuminate\Support\Facades\DB;
use Tests\Integration\External\IntranetTestHelper;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    IntranetTestHelper::ensureConnected($this);
});

describe('01. Conexión Básica y Modelos Oracle (Intranet)', function () {

    test('puede conectarse a Oracle y consultar DUAL', function () {
        $result = DB::connection('oracle')->select('SELECT 1 AS TEST, SYSDATE AS FECHA_ACTUAL FROM DUAL');
        expect($result)->not->toBeEmpty();
    });

    test('puede consultar el modelo VwAlumno con LIMIT', function () {
        $alumnos = VwAlumno::take(2)->get();
        expect($alumnos)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);

        if ($alumnos->isNotEmpty()) {
            $alumno = $alumnos->first();
            expect($alumno)->toBeInstanceOf(VwAlumno::class);
            expect($alumno->ALUM_RUT)->not->toBeNull();
        }
    });

    test('puede consultar el modelo VwCarreraCurso con LIMIT', function () {
        $cursos = VwCarreraCurso::take(2)->get();
        expect($cursos)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);

        if ($cursos->isNotEmpty()) {
            $curso = $cursos->first();
            expect($curso)->toBeInstanceOf(VwCarreraCurso::class);
            expect($curso->CUR_CODIGO)->not->toBeNull();
        }
    });

    test('puede consultar el modelo VwInscripcion y sus relaciones con LIMIT', function () {
        $inscripciones = VwInscripcion::with(['alumno', 'carreraCurso'])->take(2)->get();
        expect($inscripciones)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);

        if ($inscripciones->isNotEmpty()) {
            $inscripcion = $inscripciones->first();
            expect($inscripcion)->toBeInstanceOf(VwInscripcion::class);
            expect($inscripcion->INS_ID)->not->toBeNull();
        }
    });
});
