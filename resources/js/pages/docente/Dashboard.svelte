<script lang="ts">
  /**
   * Inicio del docente — /docente/dashboard (dirección institucional UTA).
   *
   * Responde «¿qué me pide atención hoy?»: alertas de syllabus arriba, los
   * cursos que dicto y donde imparto un componente, lo que está por evaluar
   * y, a la derecha, los próximos 7 días y los dos canales de mensajes
   * (curso.mensaje y la agenda de actividades, ver [[mensajeria-dos-niveles]]).
   *
   * «Ver agenda» abre la agenda del estudiante sobre el inicio (revisión
   * «Mi UTAmed»): al cerrarla se sigue aquí.
   */
  import { Link, page, router } from '@inertiajs/svelte';
  import {
    AlertTriangle,
    ArrowRight,
    BookOpen,
    CalendarOff,
    ChevronRight,
    ClipboardCheck,
    GraduationCap,
    MessageSquare,
  } from 'lucide-svelte';
  import AgendaGrupoModal from './Activities/Agenda/AgendaGrupoModal.svelte';
  import { nombreSemestre } from '@/lib/cursoDocente';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { titleCase } from '@/lib/utils';
  import {
    btnPrimario,
    btnSecundario,
    chico,
    chip,
    codigo,
    cuerpo,
    etiqueta,
    seccionTitulo,
    tarjeta,
    tarjetaCabecera,
    titulo,
    tono,
  } from '@/lib/inst';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';

  type EstadoSyllabus = 'NO_INICIADO' | 'BORRADOR' | 'RECHAZADO' | 'EN_REVISION' | 'APROBADO';

  interface CursoTitular {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    estado_syllabus: EstadoSyllabus;
    rechazo?: { razon: string; por: string | null; fecha: string | null } | null;
    proxima_entrega?: string | null;
  }

  interface AlertaSyllabus {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    estado_syllabus: 'BORRADOR' | 'RECHAZADO' | 'EN_REVISION';
    rechazo?: { razon: string; por: string | null; fecha: string | null } | null;
  }

  interface ComponenteCurso {
    id_curso: number;
    id_componente: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    tipo_componente: string;
    titular_nombre?: string | null;
  }

  interface ActividadDelDia {
    id_actividad: number;
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo: string | null;
    es_sumativa: boolean;
    fecha_limite: string;
  }

  interface PorEvaluar {
    id_actividad: number;
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo: string | null;
    es_sumativa: boolean;
    es_grupal: boolean;
    pendientes: number;
  }

  interface AgendaItem {
    quien: string;
    actividad_nombre: string;
    mensaje: string;
    id_curso: number;
    id_actividad: number;
    id_grupo: number;
    cod_curso: string;
    letra_grupo: string | null;
    fecha_envio: string | null;
  }

  interface Props {
    stats: { nombre_completo: string; total_cursos: number; total_titular?: number; total_componente?: number };
    periodo?: { ano: number; sem: number; fecha_inicio: string | null } | null;
    cursosTitular: CursoTitular[];
    componentes: ComponenteCurso[];
    alertasSyllabus: AlertaSyllabus[];
    mensajeria: { no_leidos: number; cursos: Array<{ id_curso: number; nombre: string; cod_curso: string; no_leidos: number }> };
    agendaPendiente: { items: AgendaItem[]; total: number };
    proximosSieteDias: { desde: string; actividades: ActividadDelDia[] };
    porEvaluar: PorEvaluar[];
    jefatura: { has_access: boolean; carrera?: { id_carrera: number; nombre: string } | null; pendientes_revision?: number };
    entreSemestres?: { ultimo_semestre: number | null; ultimo_agno: number | null; ultima_fecha_fin: string | null } | null;
  }

  let {
    stats,
    periodo,
    cursosTitular,
    componentes,
    alertasSyllabus,
    mensajeria,
    agendaPendiente,
    proximosSieteDias,
    porEvaluar,
    jefatura,
    entreSemestres = null,
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/docente/dashboard' }];

  // El nombre llega como está en la base (a veces todo en mayúsculas).
  const primerNombre = $derived(titleCase(stats.nombre_completo.split(' ')[0] || stats.nombre_completo));

  // El código visible es el de la asignatura (DM301); cod_curso es interno y
  // queda de respaldo. El menú ya lo trae para todos los cursos del docente.
  const codigoPorCurso = $derived(
    new Map(
      (($page.props.auth?.docente_courses as SidebarCourse[]) ?? []).map((c) => [c.id_curso, c.cod_asignatura ?? null]),
    ),
  );

  function codigoCurso(idCurso: number, codCurso: string, letra?: string | null): string {
    const cod = codigoPorCurso.get(idCurso) || codCurso;
    return letra ? `${cod} · Grupo ${letra}` : cod;
  }

  const ESTADO_SYLLABUS = {
    RECHAZADO: { texto: 'Rechazado', chip: tono.error, tarjeta: 'Syllabus rechazado' },
    BORRADOR: { texto: 'Borrador', chip: tono.warn, tarjeta: 'Syllabus en borrador' },
    EN_REVISION: { texto: 'En revisión', chip: tono.info, tarjeta: 'Syllabus en revisión' },
    NO_INICIADO: { texto: 'Sin syllabus', chip: tono.off, tarjeta: 'Sin syllabus' },
  } as const;

  const criticas = $derived(alertasSyllabus.filter((a) => a.estado_syllabus === 'RECHAZADO').length);
  

  // Los siete días desde hoy, también los que no tienen fechas límite.
  const DIA = 86_400_000;
  const aUtc = (iso: string) => {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    return Date.UTC(y, m - 1, d);
  };
  const fmtDiaSemana = new Intl.DateTimeFormat('es-CL', { weekday: 'short', timeZone: 'UTC' });
  const fmtDiaMes = new Intl.DateTimeFormat('es-CL', { day: 'numeric', month: 'long', timeZone: 'UTC' });

  const sieteDias = $derived.by(() => {
    const desde = aUtc(proximosSieteDias.desde);
    return Array.from({ length: 7 }, (_, k) => {
      const t = desde + k * DIA;
      const iso = new Date(t).toISOString().slice(0, 10);
      return {
        iso,
        dia: new Date(t).getUTCDate(),
        nombre: fmtDiaSemana.format(t).replace('.', ''),
        hoy: k === 0,
        actividades: proximosSieteDias.actividades.filter((a) => a.fecha_limite === iso),
      };
    });
  });

  const rangoSieteDias = $derived.by(() => {
    const desde = aUtc(proximosSieteDias.desde);
    const [d1, d2] = [fmtDiaMes.format(desde), fmtDiaMes.format(desde + 6 * DIA)];
    const mismoMes = d1.split(' de ')[1] === d2.split(' de ')[1];
    return mismoMes ? `${d1.split(' de ')[0]} al ${d2}` : `${d1} al ${d2}`;
  });

  const totalPorEvaluar = $derived(porEvaluar.reduce((n, a) => n + a.pendientes, 0));

  // ── Agenda del estudiante sobre el inicio ─────────────────────────────
  let agendaDe = $state<AgendaItem | null>(null);

  function cerrarAgenda() {
    agendaDe = null;
    router.reload({ only: ['agendaPendiente'] });
  }

  function fechaLarga(fechaSoloDia: string): string {
    const [y, m, d] = fechaSoloDia.slice(0, 10).split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d))
      .toLocaleDateString('es-CL', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' })
      .replaceAll('.', '');
  }

  function hace(fecha: string | null): string {
    if (!fecha) return '';
    const horas = Math.floor((Date.now() - new Date(fecha).getTime()) / 3_600_000);
    if (horas < 1) return 'recién';
    if (horas < 24) return `hace ${horas} h`;
    const dias = Math.floor(horas / 24);
    return dias === 1 ? 'ayer' : `hace ${dias} días`;
  }

  function fechaCierre(fecha: string | null): string | null {
    if (!fecha) return null;
    const d = new Date(fecha);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  const ceja = $derived(
    entreSemestres ? 'Receso académico' : periodo ? nombreSemestre(periodo.sem, periodo.ano) : undefined,
  );

  const tituloTarjeta = `${titulo} text-[17px] leading-[22px]`;
  const tituloAside = `${titulo} m-0 text-base leading-[22px]`;
</script>

<DocenteLayout {breadcrumbs} {ceja} titulo="Hola, {primerNombre}">
  {#snippet acciones()}
    {#if !entreSemestres}
      <Link href="/docente/cursos" class={btnSecundario}>
        Ver todos mis cursos
        <ArrowRight size={16} aria-hidden="true" />
      </Link>
    {/if}
  {/snippet}

  <div class={cuerpo}>
    {#if entreSemestres}
      <section class="{tarjeta} flex flex-wrap items-start gap-5 p-6 lg:p-8">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-inst-cabecera">
          <CalendarOff size={22} aria-hidden="true" class="text-inst-ink-2" />
        </span>
        <div class="flex max-w-[620px] min-w-0 flex-1 flex-col gap-2.5">
          <h2 class="{titulo} m-0 text-lg leading-6">No hay cursos activos en este momento</h2>
          {#if entreSemestres.ultimo_semestre && entreSemestres.ultima_fecha_fin}
            <p class="m-0 text-inst-ink-2">
              El semestre {entreSemestres.ultimo_semestre} de {entreSemestres.ultimo_agno} cerró el
              {fechaCierre(entreSemestres.ultima_fecha_fin)}.
            </p>
          {/if}
          <p class="m-0 text-inst-ink-2">
            No tienes cursos como titular ni como componente en el período vigente. Cuando la jefatura de carrera te
            asigne los del próximo período, aparecerán aquí junto con sus syllabus por redactar.
          </p>
          <Link href="/docente/cursos" class="{btnSecundario} mt-1 self-start">Ver mis cursos anteriores</Link>
        </div>
      </section>
    {:else}
      {#if jefatura.has_access && jefatura.carrera}
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-inst-linea bg-inst-accion-s px-4 py-3">
          <GraduationCap size={20} aria-hidden="true" class="shrink-0 text-inst-accion-t" />
          <span class="flex min-w-0 flex-col">
            <span class="{etiqueta} text-inst-accion-t">Otro rol</span>
            <span class="font-bold">Jefatura de Carrera — {jefatura.carrera.nombre}</span>
            {#if (jefatura.pendientes_revision ?? 0) > 0}
              <span class="text-[13px] text-inst-ink-2">
                {jefatura.pendientes_revision}
                {jefatura.pendientes_revision === 1 ? 'syllabus espera' : 'syllabus esperan'} tu revisión.
              </span>
            {/if}
          </span>
          <Link
            href="/docente/jefe-carrera/dashboard"
            class="ml-auto inline-flex min-h-11 items-center gap-1.5 font-bold text-inst-enlace no-underline hover:underline lg:min-h-9"
          >
            Ir al dashboard de jefatura
            <ChevronRight size={16} aria-hidden="true" />
          </Link>
        </div>
      {/if}

      {#if alertasSyllabus.length > 0}
        <div class={seccionTitulo}>
                <BookOpen size={20} aria-hidden="true" />
                <h2 id="t-titular" class="{titulo} m-0 text-[19px] leading-6">Avisos de Syllabus</h2>
              </div>
        <section aria-labelledby="t-alertas" class="{tarjeta} overflow-hidden">
            <ul class="m-0 list-none px-5 py-0">
              {#each alertasSyllabus as alerta (alerta.id_curso)}
                <li class="flex min-h-13 flex-wrap items-center gap-x-4 gap-y-2 border-b border-inst-linea py-2 last:border-b-0">
                  <span class="{chip} {ESTADO_SYLLABUS[alerta.estado_syllabus].chip} w-26 justify-center uppercase">
                    {ESTADO_SYLLABUS[alerta.estado_syllabus].texto}
                  </span>
                  <span class="text-[13px] font-extrabold text-inst-enlace">
                    {codigoCurso(alerta.id_curso, alerta.cod_curso, alerta.letra_grupo)}
                  </span>
                  <span class="font-semibold">{alerta.nombre}</span>
                  <span class="text-inst-ink-2">
                    {#if alerta.estado_syllabus === 'RECHAZADO'}
                      Rechazado por la jefatura de carrera.
                    {:else if alerta.estado_syllabus === 'BORRADOR'}
                      Aún no se ha enviado a revisión.
                    {:else}
                      En revisión.
                    {/if}
                  </span>
                  {#if alerta.estado_syllabus !== 'EN_REVISION'}
                    <Link
                      href="/docente/cursos/{alerta.id_curso}/programa/editar"
                      class="ml-auto inline-flex min-h-11 items-center font-bold text-inst-enlace no-underline hover:underline lg:min-h-9"
                    >
                      {alerta.estado_syllabus === 'RECHAZADO' ? 'Corregir syllabus' : 'Continuar redacción'}
                    </Link>
                  {/if}
                </li>
              {/each}
            </ul>
        </section>
      {/if}

      <div class="flex flex-wrap items-start gap-6">
        <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-7">
          {#if cursosTitular.length > 0}
            <section aria-labelledby="t-titular" class="flex flex-col gap-3.5">
              <div class={seccionTitulo}>
                <BookOpen size={20} aria-hidden="true" />
                <h2 id="t-titular" class="{titulo} m-0 text-[19px] leading-6">Cursos que dicto</h2>
              </div>
              <div class="grid grid-cols-[repeat(auto-fill,minmax(min(280px,100%),1fr))] gap-4">
                {#each cursosTitular as curso (curso.id_curso)}
                  {@const syllabus = curso.estado_syllabus === 'APROBADO' ? null : ESTADO_SYLLABUS[curso.estado_syllabus]}
                  <Link
                    href="/docente/cursos/{curso.id_curso}"
                    class="{tarjeta} flex flex-col gap-3 border-t-4 border-t-inst-celeste px-5 py-4.5 text-inst-ink no-underline transition-shadow hover:text-inst-ink hover:shadow-md"
                  >
                    <span class="flex flex-col gap-1">
                      <span class={codigo}>{codigoCurso(curso.id_curso, curso.cod_curso, curso.letra_grupo)}</span>
                      <span class={tituloTarjeta}>{curso.nombre}</span>
                    </span>
                    <span class="flex flex-wrap items-center justify-between gap-2 border-t border-inst-linea pt-3">
                      <span class="flex flex-col">
                        <span class={etiqueta}>Próxima entrega</span>
                        <span class="font-bold">
                          {curso.proxima_entrega ? fechaLarga(curso.proxima_entrega) : 'Sin entregas próximas'}
                        </span>
                      </span>
                      {#if syllabus}
                        <span class="{chip} {syllabus.chip}">{syllabus.tarjeta}</span>
                      {/if}
                    </span>
                  </Link>
                {/each}
              </div>
            </section>
          {/if}

          {#if componentes.length > 0}
            <section aria-labelledby="t-componente" class="flex flex-col gap-3.5">
              <div class={seccionTitulo}>
                <BookOpen size={20} aria-hidden="true" />
                <h2 id="t-componente" class="{titulo} m-0 text-[19px] leading-6">Cursos donde imparto un componente</h2>
              </div>
              <div class="grid grid-cols-[repeat(auto-fill,minmax(min(280px,100%),1fr))] gap-4">
                {#each componentes as comp (comp.id_componente)}
                  <Link
                    href="/docente/cursos/{comp.id_curso}"
                    class="{tarjeta} flex flex-col gap-3 border-t-4 border-t-inst-oro px-5 py-4.5 text-inst-ink no-underline transition-shadow hover:text-inst-ink hover:shadow-md"
                  >
                    <span class="flex flex-col gap-1">
                      <span class={codigo}>{codigoCurso(comp.id_curso, comp.cod_curso, comp.letra_grupo)}</span>
                      <span class={tituloTarjeta}>{comp.nombre}</span>
                    </span>
                    <span class="flex flex-wrap items-center justify-between gap-2 border-t border-inst-linea pt-3">
                      <span class="{chip} {tono.oro} uppercase">{comp.tipo_componente}</span>
                      <span class="text-[13px] text-inst-ink-2">Titular: {comp.titular_nombre ?? '—'}</span>
                    </span>
                  </Link>
                {/each}
              </div>
            </section>
          {/if}

          <section aria-labelledby="t-evaluar" class="flex flex-col gap-3.5">
            <div class={seccionTitulo}>
              <ClipboardCheck size={20} aria-hidden="true" />
              <h2 id="t-evaluar" class="{titulo} m-0 text-[19px] leading-6">Por evaluar</h2>
              {#if totalPorEvaluar > 0}
                <span class="{chip} {tono.warn}">{totalPorEvaluar} {totalPorEvaluar === 1 ? 'entrega' : 'entregas'}</span>
              {/if}
            </div>
            {#if porEvaluar.length > 0}
              <div class="{tarjeta} overflow-x-auto">
                <table class="w-full border-collapse text-left">
                  <thead>
                    <tr class="bg-inst-cabecera text-xs font-extrabold tracking-[0.04em] text-inst-ink-2 uppercase">
                      <th scope="col" class="px-5 py-3">Actividad</th>
                      <th scope="col" class="px-3 py-3">Curso</th>
                      <th scope="col" class="px-3 py-3 text-right">Por evaluar</th>
                      <th scope="col" class="px-5 py-3"><span class="sr-only">Acción</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    {#each porEvaluar as a (a.id_actividad)}
                      <tr class="border-t border-inst-linea">
                        <td class="px-5 py-3">
                          <span class="flex flex-col">
                            <span class="font-bold">{a.nombre}</span>
                            <span class="text-[13px] text-inst-ink-2">
                              {a.es_sumativa ? 'Sumativa' : 'Formativa'} · {a.es_grupal ? 'grupal' : 'individual'}
                            </span>
                          </span>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap"><span class={codigo}>{codigoCurso(a.id_curso, a.cod_curso, a.letra_grupo)}</span></td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">
                          <span class="font-bold">{a.pendientes}</span>
                          <span class="text-[13px] text-inst-ink-2">
                            {a.es_grupal ? (a.pendientes === 1 ? 'grupo' : 'grupos') : a.pendientes === 1 ? 'estudiante' : 'estudiantes'}
                          </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                          <Link href="/docente/cursos/{a.id_curso}/actividades/{a.id_actividad}/evaluacion" class="{btnSecundario} {chico}">
                            Evaluar
                          </Link>
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            {:else}
              <p class="{tarjeta} m-0 px-5 py-4 text-inst-ink-2">
                No hay entregas por evaluar. Aparecerán aquí cuando un estudiante entregue una actividad de tus cursos.
              </p>
            {/if}
          </section>
        </div>

        <aside aria-label="Pendientes" class="flex min-w-0 flex-[1_1_340px] flex-col gap-5">
          <section aria-labelledby="t-fechas" class="{tarjeta} overflow-hidden">
            <div class="{tarjetaCabecera} items-baseline justify-between">
              <h2 id="t-fechas" class={tituloAside}>Próximos 7 días</h2>
              <span class="text-[13px] text-inst-ink-3">{rangoSieteDias}</span>
            </div>
            <ul class="m-0 list-none px-4.5 py-0">
              {#each sieteDias as d (d.iso)}
                <li class="flex gap-3 border-b border-inst-linea py-2.5 last:border-b-0">
                  <span
                    class="flex w-11 shrink-0 flex-col items-center self-start rounded-md py-1 {d.hoy
                      ? 'bg-inst-accion text-inst-sobre-accion'
                      : 'bg-inst-cabecera text-inst-ink'}"
                    aria-label={d.hoy ? 'Hoy' : undefined}
                  >
                    <span class="{titulo} text-lg leading-5 {d.hoy ? 'text-inst-sobre-accion' : ''}">{d.dia}</span>
                    <span class="text-xs leading-3.5 font-extrabold uppercase">{d.nombre}</span>
                  </span>
                  <span class="flex min-w-0 flex-1 flex-col justify-center gap-1.5">
                    {#each d.actividades as a (a.id_actividad)}
                      <Link
                        href="/docente/cursos/{a.id_curso}/actividades/{a.id_actividad}/evaluacion"
                        class="flex min-h-11 items-center gap-2 text-inst-ink no-underline hover:text-inst-ink lg:min-h-9"
                      >
                        <span class="flex min-w-0 flex-1 flex-col">
                          <span class="truncate font-bold">{a.nombre}</span>
                          <span class="text-[13px] leading-[18px] text-inst-ink-2">
                            {codigoCurso(a.id_curso, a.cod_curso, a.letra_grupo)} · {a.es_sumativa ? 'Sumativa' : 'Formativa'}
                          </span>
                        </span>
                        <span class="shrink-0 text-[13px] font-bold text-inst-enlace">Calificar</span>
                        <ChevronRight size={16} aria-hidden="true" class="shrink-0 text-inst-enlace" />
                      </Link>
                    {:else}
                      <span class="text-[13px] text-inst-ink-3">Sin fechas límite</span>
                    {/each}
                  </span>
                </li>
              {/each}
            </ul>
          </section>

          <section aria-labelledby="t-mensajeria" class="{tarjeta} overflow-hidden">
            <div class="{tarjetaCabecera} flex-nowrap">
              <span class="{titulo} text-[30px] leading-8 text-inst-accion-t">{mensajeria.no_leidos}</span>
              <span class="flex flex-col">
                <h2 id="t-mensajeria" class={tituloAside}>Mensajería de curso</h2>
                <span class="text-[13px] text-inst-ink-2">
                  {mensajeria.no_leidos === 1 ? 'mensaje sin leer' : 'mensajes sin leer'}
                </span>
              </span>
            </div>
            {#if mensajeria.cursos.length > 0}
              <ul class="m-0 list-none px-4.5 py-0">
                {#each mensajeria.cursos as curso (curso.id_curso)}
                  <li class="border-b border-inst-linea last:border-b-0">
                    <Link
                      href="/docente/cursos/{curso.id_curso}/mensajeria"
                      class="flex min-h-12 items-center gap-2.5 text-inst-ink no-underline hover:text-inst-ink"
                    >
                      <span class="text-[13px] font-extrabold text-inst-enlace">{codigoCurso(curso.id_curso, curso.cod_curso)}</span>
                      <span class="min-w-0 flex-1 truncate">{curso.nombre}</span>
                      <span class="{chip} {tono.solido}">{curso.no_leidos}</span>
                    </Link>
                  </li>
                {/each}
              </ul>
            {:else}
              <p class="m-0 px-4.5 py-4 text-inst-ink-2">No hay mensajes nuevos en tus cursos.</p>
            {/if}
            <Link
              href="/docente/mensajes"
              class="flex min-h-11 items-center justify-center border-t border-inst-linea bg-inst-sutil font-bold text-inst-enlace no-underline hover:underline"
            >
              Abrir bandeja
            </Link>
          </section>

          <section aria-labelledby="t-agenda" class="{tarjeta} overflow-hidden">
            <div class={tarjetaCabecera}>
              <h2 id="t-agenda" class={tituloAside}>Agenda pendiente</h2>
            </div>
            {#if agendaPendiente.items.length > 0}
              <ul class="m-0 list-none px-4.5 py-0">
                {#each agendaPendiente.items as item (item.id_grupo)}
                  <li class="flex items-center gap-3 border-b border-inst-linea py-3 last:border-b-0">
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                      <span class="flex justify-between gap-2">
                        <span class="min-w-0 font-bold">{item.quien}</span>
                        {#if item.fecha_envio}
                          <span class="shrink-0 text-[13px] text-inst-ink-3">{hace(item.fecha_envio)}</span>
                        {/if}
                      </span>
                      <span class="text-[13px] leading-[18px] text-inst-ink-2">
                        {codigoCurso(item.id_curso, item.cod_curso, item.letra_grupo)} · {item.actividad_nombre}
                      </span>
                    </span>
                    <button type="button" class="{btnSecundario} {chico} shrink-0" onclick={() => (agendaDe = item)}>
                      <MessageSquare size={16} aria-hidden="true" />Ver agenda
                    </button>
                  </li>
                {/each}
              </ul>
            {:else}
              <p class="m-0 px-4.5 py-4 text-inst-ink-2">
                Sin consultas pendientes. Aparecerán cuando un estudiante escriba en una de tus actividades.
              </p>
            {/if}
          </section>
        </aside>
      </div>
    {/if}
  </div>
</DocenteLayout>

{#if agendaDe}
  <AgendaGrupoModal
    idCurso={agendaDe.id_curso}
    idActividad={agendaDe.id_actividad}
    idGrupo={agendaDe.id_grupo}
    nombre={agendaDe.quien}
    pieCerrar="Al cerrar sigues en el inicio."
    recargar={['agendaPendiente']}
    onCerrar={cerrarAgenda}
  />
{/if}
