<?php
// Consulta de SÓLO LECTURA: detalle de lo que YA tiene cada curso de una asignatura
// (unidades, componentes con docentes, actividades, programas y bibliografía), para
// decidir cómo debe fusionar el seeder. Pensada para DM095 A y B en producción.
//
// Uso (desde la raíz del proyecto Laravel):
//   php artisan tinker --execute="include 'database/seeders/data/consulta_dm095_detalle.tinker.php';"
//   php artisan tinker --execute="include 'consulta_dm095_detalle.tinker.php';"   (si lo copiaste a la raíz)
//
$soloAsignatura = 'DM095';   // null = todas las asignaturas (cuidado con el volumen)
$soloIdCurso    = null;      // p. ej. 2 para ver sólo ese curso
$verSyllabus    = true;      // false para omitir el resumen del JSON data_syllabus

$q = DB::table('curso.curso as c')
    ->join('administrativo.asignacion_plan as ap', 'ap.id_asignacion_plan', '=', 'c.id_asignacion_plan')
    ->join('administrativo.asignatura as a', 'a.id_asignatura', '=', 'ap.id_asignatura')
    ->leftJoin('usuario.docente as d', 'd.id_docente', '=', 'c.id_docente_titular')
    ->leftJoin('usuario.usuario as u', 'u.id_usuario', '=', 'd.id_usuario')
    ->where('c.es_plantilla', false)
    ->orderBy('c.id_curso')
    ->select('c.*', 'a.cod_asignatura', 'a.nombre as asignatura', 'u.id_usuario as titular_id_usuario', 'u.username as titular_username');
if ($soloAsignatura) { $q->where('a.cod_asignatura', $soloAsignatura); }
if ($soloIdCurso)    { $q->where('c.id_curso', $soloIdCurso); }
$cursos = $q->get();

$letra = fn ($c) => $c->letra_grupo ?: \App\Support\LetraGrupo::fromIndice($c->indice_grupo);

echo "\n=== DETALLE DE " . $cursos->count() . " CURSO(S) — BD: " . DB::connection()->getDatabaseName() . " ===\n";

foreach ($cursos as $c) {
    printf("\n################ [%d] %s %s — paralelo %s — cod_curso=%s — titular %s (id_usuario %s) — id_contexto=%s\n",
        $c->id_curso, $c->cod_asignatura, $c->asignatura, $letra($c), $c->cod_curso, $c->titular_username, $c->titular_id_usuario, $c->id_contexto);

    // ---- Unidades
    $unidades = DB::table('curso.unidad')->where('id_curso', $c->id_curso)->orderBy('num_unidad')->orderBy('id_unidad')->get();
    echo "\n  UNIDADES (" . $unidades->count() . ")\n";
    foreach ($unidades as $u) {
        $nAct = DB::table('agenda.actividad')->where('id_unidad', $u->id_unidad)->count();
        $nBib = DB::table('curso.bibliografia')->where('id_unidad', $u->id_unidad)->count();
        printf("    id_unidad=%-4s num=%-4s %-45s actividades=%d bibliografias=%d\n      descripcion: %s\n",
            $u->id_unidad, $u->num_unidad ?? '-', $u->nombre, $nAct, $nBib, $u->descripcion ? mb_substr($u->descripcion, 0, 120) : '(null)');
    }

    // ---- Componentes
    $componentes = DB::table('curso.componente as k')
        ->join('curso.tipo_componente as tc', 'tc.id_tipo_componente', '=', 'k.id_tipo_componente')
        ->where('k.id_curso', $c->id_curso)->orderBy('k.id_componente')
        ->get(['k.*', 'tc.tipo']);
    echo "\n  COMPONENTES (" . $componentes->count() . ")\n";
    foreach ($componentes as $k) {
        $docs = DB::table('curso.docente_componente as dc')
            ->join('usuario.docente as d', 'd.id_docente', '=', 'dc.id_docente')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'd.id_usuario')
            ->where('dc.id_componente', $k->id_componente)
            ->get(['u.username', 'dc.es_titular'])
            ->map(fn ($x) => $x->username . ($x->es_titular ? '*' : ''))->implode(', ');
        $nInsc = DB::table('curso.inscripcion_componente')->where('id_componente', $k->id_componente)->count();
        printf("    id_componente=%-4s %-12s id_contexto=%-6s genera_acta=%s aprob=%s%% asist=%s%%  docentes=[%s]  inscritos_componente=%d\n",
            $k->id_componente, $k->tipo, $k->id_contexto, $k->genera_acta ? 'sí' : 'no', $k->porcentaje_aprobacion, $k->porcentaje_asistencia_obligatoria, $docs ?: 'NINGUNO', $nInsc);
    }

    // ---- Actividades
    $actividades = DB::table('agenda.actividad as a')
        ->join('curso.componente as k', 'k.id_componente', '=', 'a.id_componente')
        ->join('curso.tipo_componente as tc', 'tc.id_tipo_componente', '=', 'k.id_tipo_componente')
        ->leftJoin('curso.unidad as un', 'un.id_unidad', '=', 'a.id_unidad')
        ->where('k.id_curso', $c->id_curso)->orderBy('a.fecha_limite')->orderBy('a.id_actividad')
        ->get(['a.*', 'tc.tipo as componente', 'un.nombre as unidad', 'un.num_unidad']);
    echo "\n  ACTIVIDADES (" . $actividades->count() . ")\n";
    foreach ($actividades as $a) {
        $grupos = DB::table('agenda.actividad_asignada_grupo')->where('id_actividad', $a->id_actividad)->count();
        $rubricas = DB::table('agenda.rubrica')->where('id_actividad', $a->id_actividad)->count();
        printf("    id_actividad=%-4s %-45s %-9s entrega=%-10s limite=%s pond=%s exig=%s grupal=%s max=%s visible=%s plantilla=%s\n      componente=%s#%s  unidad=%s (num %s, id %s)  grupos=%d rubricas=%d enunciado=%s\n",
            $a->id_actividad, mb_substr($a->nombre, 0, 45), $a->tipo_actividad, $a->tipo_entrega, $a->fecha_limite ?? 'null', $a->ponderacion, $a->exigencia,
            $a->es_grupal ? 'sí' : 'no', $a->max_integrantes, $a->visible ? 'sí' : 'no', $a->es_plantilla ? 'sí' : 'no',
            $a->componente, $a->id_componente, $a->unidad ?? 'null', $a->num_unidad ?? '-', $a->id_unidad ?? 'null', $grupos, $rubricas, $a->uuid_archivo_enunciado ? 'sí' : 'no');
    }

    // ---- Programas
    $programas = DB::table('curso.programa as p')
        ->leftJoin('usuario.usuario as uc', 'uc.id_usuario', '=', 'p.creado_por')
        ->where('p.id_curso', $c->id_curso)->orderBy('p.version_programa')
        ->get(['p.*', 'uc.username as creado_por_username']);
    echo "\n  PROGRAMAS (" . $programas->count() . ")\n";
    foreach ($programas as $p) {
        $nBib = DB::table('curso.bibliografia')->where('id_programa', $p->id_programa)->count();
        printf("    id_programa=%-4s version=%s estado=%-16s es_actual=%s creado_por=%s (%s) revisado_por=%s bibliografias_tabla=%d\n",
            $p->id_programa, $p->version_programa, $p->estado, $p->es_actual ? 'sí' : 'no', $p->creado_por, $p->creado_por_username, $p->revisado_por ?? 'null', $nBib);

        if ($verSyllabus && $p->data_syllabus) {
            $js = json_decode($p->data_syllabus, true) ?: [];
            $meta = $js['metadata'] ?? [];
            printf("      tipo_syllabus=%s  timestamp=%s\n", $meta['tipo_syllabus'] ?? 'null', $js['timestamp'] ?? 'null');
            foreach (($js['secciones'] ?? []) as $sec => $env) {
                $cont = $env['contenido'] ?? [];
                $resumen = match ($sec) {
                    'I'    => sprintf('%s %s, %s SCT, horas C/T/L=%s/%s/%s, categoria=%s', $cont['codigo'] ?? '-', $cont['nombre_asignatura'] ?? '-', $cont['creditos_sct'] ?? '-',
                                      $cont['horas']['catedra'] ?? '-', $cont['horas']['taller'] ?? '-', $cont['horas']['laboratorio'] ?? '-', $cont['categoria'] ?? '-'),
                    'II', 'III' => 'texto de ' . mb_strlen($cont['texto'] ?? '') . ' caracteres',
                    'IV'   => sprintf('especificas=%d genericas=%d subcompetencias=%d', count($cont['competencias_especificas'] ?? []), count($cont['competencias_genericas'] ?? []), count($cont['subcompetencias'] ?? [])),
                    'V'    => 'items=' . count($cont['items'] ?? []),
                    'VI'   => 'unidades=' . count($cont['unidades'] ?? []) . ' → ' . collect($cont['unidades'] ?? [])->map(fn ($u) => ($u['numero'] ?? '?') . ':' . mb_substr($u['titulo'] ?? '', 0, 30))->implode(' | '),
                    'VII'  => isset($cont['actividades']) ? 'actividades=' . count($cont['actividades']) : 'planificacion (COMPLETO): ' . implode(',', array_keys($cont)),
                    'VIII' => sprintf('bibliografias=%d recursos=%d', count($cont['bibliografias'] ?? []), count($cont['recursos'] ?? [])),
                    'IX'   => 'tabla_componentes=' . count($cont['tabla_componentes'] ?? []),
                    default => implode(',', array_keys($cont)),
                };
                printf("      [%-4s] %s  (ultima_modificacion=%s)\n", $sec, $resumen, $env['ultima_modificacion'] ?? 'null');
            }
        }
    }

    // ---- Bibliografía (tabla)
    $bibs = DB::table('curso.bibliografia as b')->join('curso.programa as p', 'p.id_programa', '=', 'b.id_programa')
        ->leftJoin('curso.unidad as un', 'un.id_unidad', '=', 'b.id_unidad')
        ->where('p.id_curso', $c->id_curso)->orderBy('un.num_unidad')->orderBy('b.fecha_creacion')
        ->get(['b.*', 'un.num_unidad', 'un.nombre as unidad']);
    echo "\n  BIBLIOGRAFIA (" . $bibs->count() . ")\n";
    foreach ($bibs as $b) {
        printf("    %-40s %-22s %s  unidad=%s  uta=%s url=%s archivo=%s\n",
            mb_substr($b->titulo, 0, 40), mb_substr($b->autor, 0, 22), $b->agno, $b->num_unidad ?? 'null', $b->es_bibliografia_uta ? 'sí' : 'no', $b->url ? 'sí' : 'no', $b->uuid_archivo ? 'sí' : 'no');
    }
}
echo "\n";
