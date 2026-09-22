<?php

namespace Database\Seeders\Dm069;

use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Bibliografia;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Docente;
use App\Services\Agenda\GrupoIndividualService;
use App\Services\ProgramaService;
use App\Syllabus\SyllabusData;
use App\Syllabus\SyllabusSecciones;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga el CONTENIDO de DM069 (Taller de Multimedia II) en los DOS paralelos que
 * ya existen en producción, A y B: unidades, programa, bibliografía, actividades
 * y la rúbrica de la Tarea N°1.
 *
 * Alcance cerrado: sólo las filas de la hoja Cursos de programa_dm069.xlsx, que
 * son exactamente DM069 A (cod_curso 10000) y DM069 B (cod_curso 11100). No toca
 * ningún otro curso de la base.
 *
 * Pensado para producción, donde el wizard del admin e Intranet ya crearon los
 * cursos con sus componentes (Taller y Laboratorio, que en DM069 SÍ corresponden:
 * la malla declara c=0, t=2, l=2) y sus inscripciones —26 alumnos en A y 23 en B—.
 * Por eso NO crea cursos, componentes ni inscripciones: si el curso no está, lo
 * dice y sigue con el siguiente.
 *
 * Es idempotente: correrlo dos veces deja lo mismo. Las reglas de fusión con lo
 * que ya exista son las de la propia app:
 *  - unidades: se reutilizan por num_unidad y luego por nombre
 *    (Admin/ProgramaController::createUnidadesFromSyllabus);
 *  - programa: si el curso ya tiene uno actual, se conserva; si no, se clona el
 *    del paralelo hermano; si tampoco hay, se arma desde el Excel;
 *  - bibliografía: fila en curso.bibliografia (por título) Y entrada en
 *    VIII.bibliografias del JSON, porque el documento se dibuja desde el JSON y
 *    la tabla sólo sirve para abrir el archivo por uuid;
 *  - actividades: se buscan por nombre y por alias_nombre_prod; si existen se
 *    ACTUALIZAN con el Excel (incluido el componente), si no se crean;
 *  - rúbricas: las actividades que tienen matriz de evaluación reciben su fila en
 *    agenda.rubrica, salvo que la que ya tengan esté cerrada o con evaluaciones.
 *
 * Al terminar cada curso lista las actividades que ya estaban y que el Excel no
 * reclama. Importa: el paralelo A tenía 3 actividades el 2026-09-21 y no sabemos
 * cuáles son; si aparecen aquí, hay que decidir a mano si son alguna de las 7
 * (entonces va su nombre en la columna alias_nombre_prod) o si sobran.
 *
 * Opciones por entorno:
 *  - DM069_BIBLIO_URL_PLACEHOLDER: base de URL para los recursos que vienen sin
 *    url ni archivo. Sin ella, esas filas se OMITEN (la tabla exige url o archivo
 *    cuando es_bibliografia_uta = false) y quedan listadas al final. El EVEA no
 *    imprime ninguna URL, así que sin esta variable NO se siembra bibliografía.
 *  - DM069_PROGRAMA_ESTADO: estado del programa cuando hay que crearlo
 *    (default: el del hermano, o APROBADO).
 */
class Dm069ContenidoSeeder extends Seeder
{
    /** Escribir en la sección VIII aunque el programa esté APROBADO/PUBLICADO. */
    private const ACTUALIZAR_PROGRAMAS_APROBADOS = true;

    /** Al clonar el programa del hermano, poner I.codigo = cod_asignatura. */
    private const CORREGIR_CODIGO_ASIGNATURA = true;

    private Dm069Datos $datos;
    private GrupoIndividualService $grupos;

    /** @var list<string> */
    private array $omitidas = [];

    /** @var list<string> */
    private array $sobrantes = [];

    public function run(): void
    {
        // db:seed corre con Model::unguarded(); ProgramaService pasa a create()/update()
        // claves que no son columnas (fecha_creacion, fecha_modificacion) y que en la
        // web el $fillable descarta. Con la guarda puesta el seeder se comporta igual.
        Model::reguard();

        $this->datos = new Dm069Datos();
        $this->grupos = app(GrupoIndividualService::class);

        $this->command->info('== Dm069ContenidoSeeder: unidades, programa, bibliografía, actividades y rúbricas ==');

        foreach ($this->datos->cursos() as $fila) {
            $curso = $this->localizarCurso($fila);
            if (!$curso) {
                $this->command->warn(sprintf(
                    '  ! No existe el curso %s %s %d-%d (indice_grupo %d%s). Se omite: los cursos los crea el wizard admin + Intranet.',
                    $fila['cod_asignatura'], $fila['letra_grupo'], $fila['agno_real'], $fila['semestre_real'], $fila['indice_grupo'],
                    $fila['cod_curso_prod'] ? ", cod_curso {$fila['cod_curso_prod']}" : ''
                ));
                continue;
            }

            $this->command->info(sprintf("\n-- Curso [%d] cod_curso=%s %s %s", $curso->id_curso, $curso->cod_curso, $fila['cod_asignatura'], $fila['letra_grupo']));
            DB::transaction(fn () => $this->sembrarCurso($curso, $fila));
        }

        if ($this->omitidas) {
            $this->command->warn("\nBibliografías OMITIDAS por no tener url ni archivo (define DM069_BIBLIO_URL_PLACEHOLDER o completa la columna url del Excel):");
            foreach (array_unique($this->omitidas) as $t) {
                $this->command->warn("  - {$t}");
            }
        }

        if ($this->sobrantes) {
            $this->command->warn("\nActividades que ya estaban en el curso y que el Excel NO reclama. Revisar a mano: si alguna es una de las 7, poner su nombre en la columna alias_nombre_prod de actividades_dm069.xlsx y volver a correr. El seeder no las toca ni las borra:");
            foreach ($this->sobrantes as $t) {
                $this->command->warn("  - {$t}");
            }
        }
    }

    // ------------------------------------------------------------------ curso

    private function localizarCurso(array $fila): ?Curso
    {
        $q = Curso::query()->where('es_plantilla', false)->whereNull('fecha_eliminacion');

        if ($fila['cod_curso_prod']) {
            $porCodigo = (clone $q)->where('cod_curso', $fila['cod_curso_prod'])->first();
            if ($porCodigo) {
                return $porCodigo;
            }
        }

        return $q->whereHas('asignacionPlan.asignatura', fn ($a) => $a->where('cod_asignatura', $fila['cod_asignatura']))
            ->where('agno_real', $fila['agno_real'])
            ->where('semestre_real', $fila['semestre_real'])
            ->where('indice_grupo', $fila['indice_grupo'])
            ->first();
    }

    private function sembrarCurso(Curso $curso, array $fila): void
    {
        $titular = Docente::with('usuario')->find($curso->id_docente_titular);
        if (!$titular?->usuario) {
            throw new \RuntimeException("El curso {$curso->id_curso} no tiene docente titular con usuario; no hay a quién atribuir programa y bibliografía.");
        }
        $idUsuario = (int) $titular->usuario->id_usuario;

        $actividades = $this->datos->actividades($fila['letra_grupo']);

        $unidades = $this->sembrarUnidades($curso);
        $programa = $this->asegurarPrograma($curso, $idUsuario);
        $this->sembrarBibliografia($programa, $unidades, $idUsuario);
        $this->sembrarActividades($curso, $unidades, $actividades, $titular);
        $this->revisarSobrantes($curso, $fila['letra_grupo'], $actividades);
    }

    /**
     * El componente al que va cada actividad. DM069 tiene Taller y Laboratorio, y
     * la columna componente_tipo del Excel decide cuál; se resuelve por actividad
     * porque el equipo puede repartirlas.
     */
    private function componentePara(Curso $curso, string $tipo, Docente $titular): Componente
    {
        $componente = Componente::where('id_curso', $curso->id_curso)
            ->whereHas('tipoComponente', fn ($q) => $q->where('tipo', $tipo))
            ->first();

        if (!$componente) {
            throw new \RuntimeException("El curso {$curso->id_curso} no tiene componente «{$tipo}»; los componentes los crea Intranet, no este seeder.");
        }

        $this->asegurarDocenteComponente($componente, $titular);

        return $componente;
    }

    /** Un componente sin docente_componente no le aparece al docente en su listado. */
    private function asegurarDocenteComponente(Componente $componente, Docente $titular): void
    {
        if (DocenteComponente::where('id_componente', $componente->id_componente)->exists()) {
            return;
        }

        DocenteComponente::create([
            'id_componente' => $componente->id_componente,
            'id_docente'    => $titular->id_docente,
            'es_titular'    => true,
        ]);
        $this->command->line("   componente #{$componente->id_componente}: docente_componente asignado al titular ({$titular->usuario->username}).");
    }

    // ------------------------------------------------------------------ unidades

    /** @return array<int, Unidad> num_unidad => unidad */
    private function sembrarUnidades(Curso $curso): array
    {
        $contenidos = $this->datos->contenidos();
        $out = [];

        foreach ($this->datos->unidades() as $u) {
            $nombre = Dm069SyllabusBuilder::tituloUnidad($u['numero'], $u['titulo']);
            $descripcion = Dm069SyllabusBuilder::descripcionUnidad($contenidos[$u['numero']] ?? []);

            // Misma regla que createUnidadesFromSyllabus: primero por número, luego
            // por nombre (con y sin el prefijo «Unidad N : »).
            $unidad = Unidad::where('id_curso', $curso->id_curso)->where('num_unidad', $u['numero'])->first()
                ?? Unidad::where('id_curso', $curso->id_curso)->whereIn('nombre', [$nombre, $u['titulo']])->first();

            if ($unidad) {
                $cambios = array_filter([
                    'num_unidad'  => $unidad->num_unidad === $u['numero'] ? null : $u['numero'],
                    'descripcion' => ($unidad->descripcion ?? '') === ($descripcion ?? '') ? null : $descripcion,
                ], fn ($v) => $v !== null);
                // El nombre que ya está en producción se respeta: no se renombra.
                if ($cambios) {
                    $unidad->update($cambios);
                    $this->command->line("   unidad {$u['numero']} «{$unidad->nombre}»: actualizada (" . implode(', ', array_keys($cambios)) . ').');
                } else {
                    $this->command->line("   unidad {$u['numero']} «{$unidad->nombre}»: ya existía (id {$unidad->id_unidad}).");
                }
            } else {
                $unidad = Unidad::create([
                    'num_unidad'  => $u['numero'],
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion,
                    'id_curso'    => $curso->id_curso,
                ]);
                $this->command->line("   unidad {$u['numero']} «{$nombre}»: creada (id {$unidad->id_unidad}).");
            }
            $out[$u['numero']] = $unidad;
        }

        return $out;
    }

    // ------------------------------------------------------------------ programa

    private function asegurarPrograma(Curso $curso, int $idUsuario): Programa
    {
        $actual = Programa::where('id_curso', $curso->id_curso)->where('es_actual', true)->orderByDesc('version_programa')->first();
        if ($actual) {
            $tipo = $actual->data_syllabus['metadata']['tipo_syllabus'] ?? '?';
            $this->command->line("   programa: ya existe id {$actual->id_programa} v{$actual->version_programa} {$actual->estado} ({$tipo}); se conserva.");
            return $actual;
        }

        $hermano = $this->programaHermano($curso);
        $creador = \App\Models\Usuario\Usuario::find($idUsuario);
        $estado = env('DM069_PROGRAMA_ESTADO') ?: ($hermano?->estado ?? 'APROBADO');

        if ($hermano) {
            $secciones = $hermano->data_syllabus['secciones'] ?? [];
            $tipo = $hermano->data_syllabus['metadata']['tipo_syllabus'] ?? 'COMPLETO';
            // La bibliografía del hermano apunta a SUS filas de curso.bibliografia;
            // este curso recibe las suyas más abajo.
            if (isset($secciones['VIII']['contenido']['bibliografias'])) {
                $secciones['VIII']['contenido']['bibliografias'] = [];
            }
            if (self::CORREGIR_CODIGO_ASIGNATURA && isset($secciones['I']['contenido']['codigo'])) {
                $secciones['I']['contenido']['codigo'] = (string) $curso->asignacionPlan?->asignatura?->cod_asignatura;
            }
            $origen = "clonado del programa {$hermano->id_programa} (curso {$hermano->id_curso})";
        } else {
            $secciones = (new Dm069SyllabusBuilder($this->datos))->secciones();
            $tipo = 'COMPLETO';
            $origen = 'armado desde los Excel';
        }

        $programa = ProgramaService::generateProgramaWithSyllabus($curso, $creador, [
            'tipo_syllabus' => $tipo,
            'estado'        => $estado,
            'secciones'     => $secciones,
        ]);
        if (in_array($estado, ['APROBADO', 'PUBLICADO'], true)) {
            $programa->update(['revisado_por' => $idUsuario]);
        }

        $this->command->line("   programa: creado id {$programa->id_programa} {$tipo} {$estado}, {$origen}.");
        return $programa;
    }

    /** Programa actual de otro paralelo de la misma asignatura y período. */
    private function programaHermano(Curso $curso): ?Programa
    {
        return Programa::where('es_actual', true)
            ->whereHas('curso', fn ($q) => $q
                ->where('id_asignacion_plan', $curso->id_asignacion_plan)
                ->where('agno_real', $curso->agno_real)
                ->where('semestre_real', $curso->semestre_real)
                ->where('id_curso', '!=', $curso->id_curso)
                ->where('es_plantilla', false))
            ->orderByDesc('version_programa')
            ->first();
    }

    // ------------------------------------------------------------------ bibliografía

    /** @param  array<int, Unidad>  $unidades */
    private function sembrarBibliografia(Programa $programa, array $unidades, int $idUsuario): void
    {
        $placeholder = env('DM069_BIBLIO_URL_PLACEHOLDER');
        $entradasJson = [];
        $creadas = $actualizadas = 0;

        foreach ($this->datos->bibliografia() as $b) {
            $fila = Bibliografia::where('id_programa', $programa->id_programa)->where('titulo', $b['titulo'])->first();

            $url = $b['url'];
            $uuidArchivo = $b['uuid_archivo'];
            if (!$url && !$uuidArchivo && !$b['es_bibliografia_uta']) {
                if ($fila && ($fila->url || $fila->uuid_archivo)) {
                    // Ya está en la tabla con url/archivo (puesto a mano o en una
                    // corrida anterior): se conserva lo que hay.
                    $url = $fila->url;
                    $uuidArchivo = $fila->uuid_archivo;
                } elseif ($placeholder) {
                    $url = rtrim($placeholder, '/') . '/' . Str::slug($b['titulo']);
                } else {
                    $this->omitidas[] = $b['titulo'];
                    continue;
                }
            }

            $idUnidad = $b['num_unidad'] !== null ? ($unidades[$b['num_unidad']]->id_unidad ?? null) : null;

            $atributos = [
                'id_unidad'           => $idUnidad,
                'autor'               => $b['autor'],
                'cita'                => $b['cita'],
                'editorial'           => $b['editorial'],
                'agno'                => $b['agno'],
                'url'                 => $url,
                'uuid_archivo'        => $uuidArchivo,
                'es_bibliografia_uta' => $b['es_bibliografia_uta'],
            ];
            if ($fila) {
                $fila->update($atributos);
                $actualizadas++;
            } else {
                $fila = Bibliografia::create($atributos + [
                    'id_programa'  => $programa->id_programa,
                    'titulo'       => $b['titulo'],
                    'agregado_por' => $idUsuario,
                ]);
                $creadas++;
            }

            // Forma de BibliografiaSyllabus. `id_unidad` lleva el NÚMERO de unidad:
            // así lo escribe el wizard y así lo resuelve createBibliografiasFromSyllabus.
            $entradasJson[] = [
                'id_bibliografia'     => $fila->uuid_bibliografia,
                'titulo'              => $b['titulo'],
                'autor'               => $b['autor'],
                'cita'                => $b['cita'],
                'editorial'           => $b['editorial'],
                'anio'                => $b['agno'],
                'es_bibliografia_uta' => $b['es_bibliografia_uta'],
                'url'                 => $url,
                'uuid_archivo'        => $uuidArchivo,
                'id_unidad'           => $b['num_unidad'],
            ];
        }

        $this->command->line("   bibliografía: {$creadas} creadas, {$actualizadas} actualizadas en curso.bibliografia" . ($placeholder ? ' (url PLACEHOLDER para las que no traían)' : '') . '.');

        if (!$entradasJson) {
            return;
        }

        if (in_array($programa->estado, ['APROBADO', 'PUBLICADO'], true) && !self::ACTUALIZAR_PROGRAMAS_APROBADOS) {
            $this->command->warn("   programa {$programa->id_programa} está {$programa->estado}: no se toca la sección VIII (ACTUALIZAR_PROGRAMAS_APROBADOS = false).");
            return;
        }

        $this->escribirSeccionVIII($programa, $entradasJson);
        $this->command->line('   programa: sección VIII.bibliografias escrita con ' . count($entradasJson) . ' entradas' . ($programa->estado === 'APROBADO' ? ' (programa APROBADO, decisión del equipo)' : '') . '.');
    }

    /** @param  list<array<string, mixed>>  $entradas */
    private function escribirSeccionVIII(Programa $programa, array $entradas): void
    {
        $data = $programa->data_syllabus ?? [];
        $recursos = $data['secciones']['VIII']['contenido']['recursos'] ?? [];
        $contenido = ['bibliografias' => $entradas, 'recursos' => $recursos];

        $syllabus = SyllabusData::fromArray($data);
        if ($syllabus->secciones->has('VIII')) {
            ProgramaService::updateSeccion($programa, 'VIII', $contenido);
            return;
        }

        // Programa sin sección VIII (no debería pasar en COMPLETO ni BASICO): se agrega.
        $dto = SyllabusSecciones::contenidoFromArray('VIII', $contenido, $syllabus->metadata?->tipoSyllabus);
        $secciones = $syllabus->secciones->with('VIII', $dto, now()->toIso8601String());
        $programa->update(['data_syllabus' => $syllabus->withSecciones($secciones)->withTimestamp(now()->toIso8601String())->toArray()]);
    }

    // ------------------------------------------------------------------ actividades

    /**
     * @param  array<int, Unidad>  $unidades
     * @param  list<array<string, mixed>>  $actividades  ya resueltas para este paralelo
     */
    private function sembrarActividades(Curso $curso, array $unidades, array $actividades, Docente $titular): void
    {
        foreach ($actividades as $a) {
            $unidad = $unidades[$a['num_unidad']] ?? null;
            if (!$unidad) {
                throw new \RuntimeException("La actividad «{$a['nombre']}» apunta a la unidad {$a['num_unidad']}, que no existe en el curso {$curso->id_curso}.");
            }

            $componente = $this->componentePara($curso, $a['componente_tipo'], $titular);

            $atributos = [
                'nombre'                            => $a['nombre'],
                'fecha_limite'                      => $a['fecha_limite'],
                'tipo_actividad'                    => $a['tipo_actividad'],
                'tipo_entrega'                      => $a['tipo_entrega'],
                'ponderacion'                       => $a['ponderacion'],
                'exigencia'                         => $a['exigencia'],
                'nro_dias_adicionales_para_bloqueo' => $a['nro_dias_adicionales_para_bloqueo'],
                'visible'                           => $a['visible'],
                'es_grupal'                         => $a['es_grupal'],
                'max_integrantes'                   => $a['max_integrantes'],
                'es_plantilla'                      => false,
                'id_componente'                     => $componente->id_componente,
                'id_unidad'                         => $unidad->id_unidad,
            ];

            $existente = $this->buscarActividad($curso, $a['nombre'])
                ?? ($a['alias_nombre_prod'] ? $this->buscarActividad($curso, $a['alias_nombre_prod']) : null);

            if ($existente) {
                $cambios = $this->diferencias($existente, $atributos);
                if ($cambios) {
                    $existente->update($atributos);
                    $this->command->line("   actividad «{$existente->getOriginal('nombre')}» (id {$existente->id_actividad}): actualizada → " . $this->describir($cambios));
                } else {
                    $this->command->line("   actividad «{$a['nombre']}» (id {$existente->id_actividad}): sin cambios.");
                }
                $actividad = $existente->fresh();
            } else {
                $actividad = Actividad::create($atributos);
                $this->command->line("   actividad «{$a['nombre']}»: creada (id {$actividad->id_actividad}, vence {$a['fecha_limite']}).");
            }

            // Igual que DocenteActivityController::store(): un grupo de 1 por inscrito.
            $this->grupos->asegurarGruposDelCurso($curso, $actividad);

            $this->sembrarRubrica($actividad, $a['orden']);
        }
    }

    /**
     * Lista las actividades del curso que el Excel no reclama. No borra nada: sólo
     * avisa, porque el paralelo A ya traía 3 actividades de origen desconocido.
     *
     * @param  list<array<string, mixed>>  $actividades
     */
    private function revisarSobrantes(Curso $curso, string $letra, array $actividades): void
    {
        $esperados = [];
        foreach ($actividades as $a) {
            $esperados[] = $a['nombre'];
            if ($a['alias_nombre_prod']) {
                $esperados[] = $a['alias_nombre_prod'];
            }
        }

        $sobrantes = Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
            ->where('es_plantilla', false)
            ->whereNotIn('nombre', $esperados)
            ->orderBy('id_actividad')
            ->get();

        foreach ($sobrantes as $s) {
            $this->sobrantes[] = sprintf(
                '%s %s: «%s» (id %d, %s, vence %s)',
                $curso->cod_curso, $letra, $s->nombre, $s->id_actividad,
                $s->tipo_actividad instanceof \BackedEnum ? $s->tipo_actividad->value : $s->tipo_actividad,
                $s->fecha_limite?->format('Y-m-d') ?? 'sin fecha'
            );
        }

        if ($sobrantes->isNotEmpty()) {
            $this->command->warn('   ! ' . $sobrantes->count() . ' actividad(es) del curso no están en el Excel; ver el resumen del final.');
        }
    }

    // ------------------------------------------------------------------ rúbricas

    /**
     * Engancha la matriz de evaluación del Excel (si la actividad tiene una) a
     * agenda.rubrica, con las mismas reglas que DocenteActivityController::storeRubrica():
     * se reutiliza la rúbrica más reciente de la actividad y NO se toca si ya está
     * cerrada o si alguien empezó a evaluar con ella.
     */
    private function sembrarRubrica(Actividad $actividad, int $orden): void
    {
        $def = $this->datos->rubricas()[$orden] ?? null;

        if (!$def) {
            // Sin matriz no hay nada que sembrar, pero si la actividad arrastra una
            // rúbrica de antes conviene decirlo: puede ser una rúbrica que quedó
            // pegada a la actividad equivocada al conciliar nombres con producción.
            $heredada = Rubrica::where('id_actividad', $actividad->id_actividad)->orderByDesc('id_rubrica')->first();
            if ($heredada) {
                $this->command->warn("   ! «{$actividad->nombre}» no tiene matriz de evaluación en el Excel, pero ya arrastra la rúbrica id {$heredada->id_rubrica}. El seeder no la borra: revisar a qué actividad corresponde.");
            }

            return;
        }

        $tipo = $actividad->tipo_actividad instanceof \BackedEnum
            ? $actividad->tipo_actividad->value
            : (string) $actividad->tipo_actividad;

        // Una formativa sin escala cualitativa no le muestra ningún resultado al
        // alumno: la nota es lo único que la rúbrica produce y las formativas no
        // llevan nota. Se siembra igual, pero avisando.
        if ($tipo !== 'SUMATIVA' && empty($def['rubrica']['detalles_evaluacion']['escala_evaluacion'])) {
            $this->command->warn("   ! «{$actividad->nombre}» es {$tipo} y su matriz ({$def['archivo']}) no trae escala cualitativa: el alumno verá la rúbrica sin resultado.");
        }

        $existente = Rubrica::where('id_actividad', $actividad->id_actividad)
            ->orderByDesc('id_rubrica')
            ->first();

        $resumen = sprintf(
            '%d criterios × %d niveles, %s pts',
            count($def['rubrica']['niveles']),
            count($def['rubrica']['columnas']),
            $def['rubrica']['detalles_evaluacion']['puntaje_total']
        );

        if (!$existente) {
            $rubrica = Rubrica::create([
                'rubrica'        => $def['rubrica'],
                'estado_rubrica' => $def['estado_rubrica'],
                'id_actividad'   => $actividad->id_actividad,
            ]);
            $this->command->line("   rúbrica de «{$actividad->nombre}»: creada (id {$rubrica->id_rubrica}, {$resumen}).");

            return;
        }

        if ($existente->estaBloqueadaParaEdicion()) {
            $this->command->warn("   ! rúbrica id {$existente->id_rubrica} de «{$actividad->nombre}»: CERRADA o con evaluaciones hechas. Se deja como está (la app tampoco dejaría editarla).");

            return;
        }

        if (json_encode($existente->rubrica) === json_encode($def['rubrica'])) {
            $this->command->line("   rúbrica de «{$actividad->nombre}» (id {$existente->id_rubrica}): sin cambios.");

            return;
        }

        $existente->update(['rubrica' => $def['rubrica']]);
        $this->command->line("   rúbrica de «{$actividad->nombre}» (id {$existente->id_rubrica}): reemplazada por la matriz de {$def['archivo']} ({$resumen}).");
    }

    private function buscarActividad(Curso $curso, string $nombre): ?Actividad
    {
        return Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
            ->where('nombre', $nombre)
            ->where('es_plantilla', false)
            ->first();
    }

    /** @return array<string, array{0: mixed, 1: mixed}> campo => [antes, después] */
    private function diferencias(Actividad $actividad, array $atributos): array
    {
        $cambios = [];
        foreach ($atributos as $campo => $nuevo) {
            $actual = $actividad->getAttribute($campo);
            if ($actual instanceof \BackedEnum) {
                $actual = $actual->value;
            } elseif ($actual instanceof \DateTimeInterface) {
                $actual = $actual->format('Y-m-d');
            }
            $iguales = is_bool($nuevo) ? ((bool) $actual === $nuevo) : ((string) $actual === (string) $nuevo);
            if (!$iguales) {
                $cambios[$campo] = [$actual, $nuevo];
            }
        }
        return $cambios;
    }

    private function describir(array $cambios): string
    {
        return implode(', ', array_map(
            fn ($campo, $par) => sprintf('%s: %s → %s', $campo, var_export($par[0], true), var_export($par[1], true)),
            array_keys($cambios), $cambios
        ));
    }
}
