<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use App\Models\External\VwCarreraCurso;
use App\Models\External\VwInscripcion;
use App\Models\External\VwAlumno;
use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\InscripcionData;
use App\DTOs\External\AlumnoIntranetData;
use App\Enums\External\TipoAsignatura;

/**
 * LO QUE HACE ESTE PROVIDER
 * 
 * TRAER VISTAS DE INTRANET Y TIPARLAS
 * ACTUALMENTE LAS VISTAS SON:
 * 
 *      INSCRIPCION (información de la inscripción de alumnos a las componentes de cada curso)
 *      CARRERA_CURSO (información de las componentes como tal (no representa al curso, representa a las componentes) )
 *      ALUMNO (informacion de los alumnos inscritos a la UTA)
 * 
 * CREAR CLASES PARA TIPAR EL RETORNO DE LAS VISTAS (NO MODELOS PORQUE NO SON DE ESTE SISTEMA)
 * 
 * metodos:
 *  traer_cur_codigos(semestre real, año real, cod plan, cod asignatura)
 *      esto trae todos los cur-codigos de los cursos 
 *  traer_ins_id(cur_codigo) {}
 * 
 * 
 */
class IntranetViewConnectionProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('OracleDataService', function ($app) {
            return new class {

                /**
                 * Obtiene los CUR_CODIGO de las componentes (cursos) asociadas a una asignatura.
                 * Permite filtrar opcionalmente por tipo de asignatura (C, T, L) y grupo (A, B, C...).
                 *
                 * No filtra por carrera: Asignatura + Plan + Año + Semestre + Grupo ya
                 * identifican la oferta sin ambigüedad, y el código de carrera de Oracle
                 * no tiene equivalente confiable en UTAMED (ver migración 05, revertida).
                 *
                 * @param int $semestre Semestre de la asignatura (number(1))
                 * @param int $agno Año académico (number(4))
                 * @param int $planCod Código de plan de estudios (number(4))
                 * @param string $asigCodigo Código de asignatura (varchar(10))
                 * @param TipoAsignatura|string|null $tipoAsig Opcional: Tipo de asignatura (Cátedra, Taller, Laboratorio) (varchar(1))
                 * @param string|null $grupoAsig Opcional: Grupo de la asignatura (A, B, C...) (varchar(2))
                 * @return Collection<int, ComponenteCursoData>
                 * @throws \InvalidArgumentException Si alguno de los parámetros excede los límites permitidos.
                 */
                public function traer_cur_codigos(
                    int $semestre,
                    int $agno,
                    int $planCod,
                    string $asigCodigo,
                    TipoAsignatura|string|null $tipoAsig = null,
                    ?string $grupoAsig = null
                ): Collection {
                    // Validar límites para evitar consultas mal formadas a Oracle
                    if (abs($semestre) > 9) {
                        throw new \InvalidArgumentException("El semestre excede el límite permitido de 1 dígito: {$semestre}");
                    }
                    if (abs($agno) > 9999) {
                        throw new \InvalidArgumentException("El año excede el límite permitido de 4 dígitos: {$agno}");
                    }
                    if (abs($planCod) > 9999) {
                        throw new \InvalidArgumentException("El código de plan excede el límite permitido de 4 dígitos: {$planCod}");
                    }
                    if (mb_strlen($asigCodigo) > 10) {
                        throw new \InvalidArgumentException("El código de asignatura excede el límite permitido de 10 caracteres: '{$asigCodigo}'");
                    }

                    $tipoValue = $tipoAsig instanceof TipoAsignatura ? $tipoAsig->value : $tipoAsig;
                    if ($tipoValue !== null && mb_strlen($tipoValue) > 1) {
                        throw new \InvalidArgumentException("El tipo de asignatura excede el límite permitido de 1 carácter: '{$tipoValue}'");
                    }

                    if ($grupoAsig !== null && mb_strlen($grupoAsig) > 2) {
                        throw new \InvalidArgumentException("El grupo de asignatura excede el límite permitido de 2 caracteres: '{$grupoAsig}'");
                    }

                    $query = VwCarreraCurso::select(['CUR_CODIGO', 'CURSO_TIPO_ASIG', 'CURSO_GRUPO_ASIG'])
                        ->where('CURSO_SEMESTRE_ASIG', $semestre)
                        ->where('CURSO_ANO', $agno)
                        ->where('PLAN_ANO', $planCod)
                        ->where('ASIG_CODIGO', $asigCodigo);

                    if ($tipoValue !== null) {
                        $query->where('CURSO_TIPO_ASIG', $tipoValue);
                    }

                    if ($grupoAsig !== null) {
                        $query->where('CURSO_GRUPO_ASIG', $grupoAsig);
                    }

                    return $query->get()->map(
                        fn(VwCarreraCurso $curso) => new ComponenteCursoData(
                            cur_codigo: $curso->CUR_CODIGO,
                            curso_tipo_asig: TipoAsignatura::from($curso->CURSO_TIPO_ASIG),
                            curso_grupo_asig: $curso->CURSO_GRUPO_ASIG
                        )
                    );
                }

                /**
                 * @param iterable<int> $curCodigos
                 * @return Collection<int, InscripcionData>
                 * @throws \InvalidArgumentException
                 */
                public function traer_ins_id(iterable $curCodigos): Collection
                {
                    foreach ($curCodigos as $code) {
                        if (abs((int)$code) > 999999999999) { // number(12)
                            throw new \InvalidArgumentException("El CUR_CODIGO excede el límite permitido de 12 dígitos: {$code}");
                        }
                    }

                    return VwInscripcion::select(['INS_ID', 'ALUM_RUT'])
                        ->whereIn('CUR_CODIGO', $curCodigos)
                        ->get()
                        ->map(fn(VwInscripcion $inscripcion) => new InscripcionData(
                            ins_id: $inscripcion->INS_ID,
                            alum_rut: $inscripcion->ALUM_RUT
                        ));
                }

                /**
                 * Obtiene los datos personales de un alumno desde la intranet por RUT.
                 *
                 * @param int $alumRut RUT del alumno (sin puntos ni DV)
                 * @return AlumnoIntranetData|null
                 */
                public function traer_alumno(int $alumRut): ?AlumnoIntranetData
                {
                    if (abs($alumRut) > 999999999) { // number(9)
                        throw new \InvalidArgumentException("El ALUM_RUT excede el límite permitido: {$alumRut}");
                    }

                    $alumno = VwAlumno::where('ALUM_RUT', $alumRut)->first();

                    if (!$alumno) {
                        return null;
                    }

                    return new AlumnoIntranetData(
                        alum_rut: $alumno->ALUM_RUT,
                        alum_digito: $alumno->ALUM_DIGITO,
                        alum_nombre: trim($alumno->ALUM_NOMBRE ?? ''),
                        alum_apellido_pat: trim($alumno->ALUM_APELLIDO_PAT ?? ''),
                        alum_apellido_mat: $alumno->ALUM_APELLIDO_MAT ? trim($alumno->ALUM_APELLIDO_MAT) : null
                    );
                }
            };
        });
    }


    public function boot(): void
    {
        //
    }
}
