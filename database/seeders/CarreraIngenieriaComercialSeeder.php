<?php

namespace Database\Seeders;

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeder para la carrera de Ingeniería Comercial (Área Economía / Administración y Negocios).
 * 
 * Crea o encuentra:
 * - Facultad de Administración y Economía
 * - Escuela de Administración y Negocios
 * - Carrera Ingeniería Comercial
 * - Plan de estudio 2026 v1
 * - Catálogo completo de 54 asignaturas y sus asignaciones al plan.
 */
class CarreraIngenieriaComercialSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seedeando Carrera: Ingeniería Comercial (Economía / Administración)...');

        // 1. Facultad (firstOrCreate para reutilizar la misma si ya fue creada)
        $facultad = Facultad::firstOrCreate(
            ['nombre' => 'Facultad de Administración y Economía']
        );

        // 2. Departamento
        $departamento = Departamento::firstOrCreate(
            [
                'nombre' => 'Escuela de Administración y Negocios',
                'id_facultad' => $facultad->id_facultad,
            ]
        );

        // 3. Carrera
        $carrera = Carrera::firstOrCreate(
            [
                'nombre' => 'Ingeniería Comercial',
                'id_departamento' => $departamento->id_departamento,
            ]
        );

        // 4. Plan de Estudios (2026 v1)
        $plan = Plan::firstOrCreate(
            [
                'id_carrera' => $carrera->id_carrera,
                'agno_plan' => 2026,
                'version_plan' => 1,
            ],
            [
                'creditos_sct_totales' => 270,
            ]
        );

        // 5. Catálogo de asignaturas (54 asignaturas del plan de estudio)
        $asignaturas = [
            // Año 1, Semestre I
            ['cod' => 'IC001', 'nombre' => 'Aplicaciones Computacionales para los Negocios',             'creditos' => 5, 'agno' => 1, 'sem' => 1],
            ['cod' => 'IC002', 'nombre' => 'Administración de Empresas I',                             'creditos' => 5, 'agno' => 1, 'sem' => 1],
            ['cod' => 'IC003', 'nombre' => 'Algebra',                                                  'creditos' => 5, 'agno' => 1, 'sem' => 1],
            ['cod' => 'IC004', 'nombre' => 'Expresión Oral y Argumentación',                           'creditos' => 5, 'agno' => 1, 'sem' => 1],
            ['cod' => 'IC005', 'nombre' => 'Elementos del Derecho Civil y Comercial',                  'creditos' => 5, 'agno' => 1, 'sem' => 1],

            // Año 1, Semestre II
            ['cod' => 'IC006', 'nombre' => 'Contabilidad I',                                           'creditos' => 5, 'agno' => 1, 'sem' => 2],
            ['cod' => 'IC007', 'nombre' => 'Administración de Empresas II',                            'creditos' => 5, 'agno' => 1, 'sem' => 2],
            ['cod' => 'IC008', 'nombre' => 'Cálculo I',                                                'creditos' => 5, 'agno' => 1, 'sem' => 2],
            ['cod' => 'IC009', 'nombre' => 'Expresión Escrita',                                        'creditos' => 5, 'agno' => 1, 'sem' => 2],
            ['cod' => 'IC010', 'nombre' => 'Introducción a la Economía',                               'creditos' => 5, 'agno' => 1, 'sem' => 2],

            // Año 2, Semestre III
            ['cod' => 'IC011', 'nombre' => 'Contabilidad II',                                          'creditos' => 5, 'agno' => 2, 'sem' => 1],
            ['cod' => 'IC012', 'nombre' => 'Habilidades Directivas',                                   'creditos' => 5, 'agno' => 2, 'sem' => 1],
            ['cod' => 'IC013', 'nombre' => 'Cálculo II',                                               'creditos' => 5, 'agno' => 2, 'sem' => 1],
            ['cod' => 'IC014', 'nombre' => 'Probabilidad y Estadística',                               'creditos' => 5, 'agno' => 2, 'sem' => 1],
            ['cod' => 'IC015', 'nombre' => 'Microeconomía I',                                          'creditos' => 5, 'agno' => 2, 'sem' => 1],

            // Año 2, Semestre IV
            ['cod' => 'IC016', 'nombre' => 'Costos para la Toma de Decisiones',                         'creditos' => 5, 'agno' => 2, 'sem' => 2],
            ['cod' => 'IC017', 'nombre' => 'Legislación Tributaria',                                   'creditos' => 5, 'agno' => 2, 'sem' => 2],
            ['cod' => 'IC018', 'nombre' => 'Algebra Lineal',                                           'creditos' => 5, 'agno' => 2, 'sem' => 2],
            ['cod' => 'IC019', 'nombre' => 'Inferencia Estadística',                                   'creditos' => 5, 'agno' => 2, 'sem' => 2],
            ['cod' => 'IC020', 'nombre' => 'Macroeconomía I',                                          'creditos' => 5, 'agno' => 2, 'sem' => 2],
            ['cod' => 'IC021', 'nombre' => 'Inglés para Propósitos Especiales: Comprensión Lectora I',  'creditos' => 5, 'agno' => 2, 'sem' => 2],

            // Año 3, Semestre V
            ['cod' => 'IC022', 'nombre' => 'Comportamiento Organizacional',                            'creditos' => 5, 'agno' => 3, 'sem' => 1],
            ['cod' => 'IC023', 'nombre' => 'Principio del Derecho del Trabajo',                         'creditos' => 5, 'agno' => 3, 'sem' => 1],
            ['cod' => 'IC024', 'nombre' => 'Investigación de Operaciones',                              'creditos' => 5, 'agno' => 3, 'sem' => 1],
            ['cod' => 'IC025', 'nombre' => 'Econometría',                                              'creditos' => 5, 'agno' => 3, 'sem' => 1],
            ['cod' => 'IC026', 'nombre' => 'Microeconomía II',                                         'creditos' => 5, 'agno' => 3, 'sem' => 1],
            ['cod' => 'IC027', 'nombre' => 'Inglés para Propósitos Especiales: Comprensión Lectora II', 'creditos' => 5, 'agno' => 3, 'sem' => 1],

            // Año 3, Semestre VI
            ['cod' => 'IC028', 'nombre' => 'Finanzas I',                                               'creditos' => 5, 'agno' => 3, 'sem' => 2],
            ['cod' => 'IC029', 'nombre' => 'Gestión de Personas',                                      'creditos' => 5, 'agno' => 3, 'sem' => 2],
            ['cod' => 'IC030', 'nombre' => 'Administración de la Producción y de Operaciones',          'creditos' => 5, 'agno' => 3, 'sem' => 2],
            ['cod' => 'IC031', 'nombre' => 'Marketing I',                                              'creditos' => 5, 'agno' => 3, 'sem' => 2],
            ['cod' => 'IC032', 'nombre' => 'Macroeconomía II',                                         'creditos' => 5, 'agno' => 3, 'sem' => 2],
            ['cod' => 'IC033', 'nombre' => 'Inglés Comunicacional para los Negocios',                  'creditos' => 5, 'agno' => 3, 'sem' => 2],

            // Año 4, Semestre VII
            ['cod' => 'IC034', 'nombre' => 'Finanzas II',                                              'creditos' => 5, 'agno' => 4, 'sem' => 1],
            ['cod' => 'IC035', 'nombre' => 'Desarrollo Organizacional',                                'creditos' => 5, 'agno' => 4, 'sem' => 1],
            ['cod' => 'IC036', 'nombre' => 'Teoría de Decisiones',                                     'creditos' => 5, 'agno' => 4, 'sem' => 1],
            ['cod' => 'IC037', 'nombre' => 'Marketing II',                                             'creditos' => 5, 'agno' => 4, 'sem' => 1],
            ['cod' => 'IC038', 'nombre' => 'Sistema de Información Gerencial',                         'creditos' => 5, 'agno' => 4, 'sem' => 1],
            ['cod' => 'IC039', 'nombre' => 'Gestión Ambiental',                                        'creditos' => 5, 'agno' => 4, 'sem' => 1],

            // Año 4, Semestre VIII
            ['cod' => 'IC040', 'nombre' => 'Preparación y Evaluación de Proyectos',                     'creditos' => 5, 'agno' => 4, 'sem' => 2],
            ['cod' => 'IC041', 'nombre' => 'Administración Estratégica I',                             'creditos' => 5, 'agno' => 4, 'sem' => 2],
            ['cod' => 'IC042', 'nombre' => 'Responsabilidad Social Empresarial y Ética',               'creditos' => 5, 'agno' => 4, 'sem' => 2],
            ['cod' => 'IC043', 'nombre' => 'Investigación de Mercado',                                 'creditos' => 5, 'agno' => 4, 'sem' => 2],
            ['cod' => 'IC044', 'nombre' => 'Relaciones Económicas Internacionales',                     'creditos' => 5, 'agno' => 4, 'sem' => 2],

            // Año 5, Semestre IX
            ['cod' => 'IC045', 'nombre' => 'Electivo de Formación Profesional I',                      'creditos' => 5, 'agno' => 5, 'sem' => 1],
            ['cod' => 'IC046', 'nombre' => 'Administración Estratégica II',                            'creditos' => 5, 'agno' => 5, 'sem' => 1],
            ['cod' => 'IC047', 'nombre' => 'Electivo de Formación Profesional II',                     'creditos' => 5, 'agno' => 5, 'sem' => 1],
            ['cod' => 'IC048', 'nombre' => 'Control de Gestión',                                       'creditos' => 5, 'agno' => 5, 'sem' => 1],
            ['cod' => 'IC049', 'nombre' => 'Metodología de la Investigación',                          'creditos' => 5, 'agno' => 5, 'sem' => 1],
            ['cod' => 'IC050', 'nombre' => 'Práctica Profesional',                                     'creditos' => 5, 'agno' => 5, 'sem' => 1],

            // Año 5, Semestre X
            ['cod' => 'IC051', 'nombre' => 'Electivo de Formación Profesional III',                    'creditos' => 5, 'agno' => 5, 'sem' => 2],
            ['cod' => 'IC052', 'nombre' => 'Electivo de Formación Profesional IV',                     'creditos' => 5, 'agno' => 5, 'sem' => 2],
            ['cod' => 'IC053', 'nombre' => 'Electivo de Formación Profesional V',                      'creditos' => 5, 'agno' => 5, 'sem' => 2],
            ['cod' => 'IC054', 'nombre' => 'Actividad de Titulación',                                  'creditos' => 5, 'agno' => 5, 'sem' => 2],
        ];

        $asignaturasCreadas = 0;
        $asignacionesCreadas = 0;

        foreach ($asignaturas as $data) {
            $asignatura = Asignatura::firstOrCreate(
                ['cod_asignatura' => $data['cod']],
                [
                    'nombre'            => $data['nombre'],
                    'descripcion'       => 'Asignatura del plan de Ingeniería Comercial',
                    'creditos_sct'      => $data['creditos'] ?? 5,
                    'horas_catedra'     => 0,
                    'horas_taller'      => 0,
                    'horas_laboratorio' => 0,
                    'horas_dirigidas'   => 0,
                    'horas_autonomas'   => 0,
                ]
            );

            if ($asignatura->wasRecentlyCreated) {
                $asignaturasCreadas++;
            }

            $asignacion = AsignacionPlan::firstOrCreate([
                'id_asignatura'        => $asignatura->id_asignatura,
                'id_plan'              => $plan->id_plan,
                'agno_planificado'     => $data['agno'],
                'semestre_planificado' => $data['sem'],
            ]);

            if ($asignacion->wasRecentlyCreated) {
                $asignacionesCreadas++;
            }
        }

        $this->command?->info("✓ Ingeniería Comercial: {$asignaturasCreadas} asignaturas nuevas, {$asignacionesCreadas} asignaciones al plan.");
    }
}
