<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';
  import { Link, page } from '@inertiajs/svelte';
  import { BookOpen, CalendarClock, ChevronRight, FileText, MessageSquare } from 'lucide-svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import { useFormatName } from '@/hooks';
  import { estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import { enlace, etiqueta, pildora, tarjeta, tituloCond, tono } from '@/lib/dm';
  import { plazoDe, type ActividadPorVencer } from '@/lib/plazoAlumno';

  interface CursoAlumno {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    cod_asignatura?: string | null;
    asignatura_nombre: string;
    carrera_nombre: string;
    letra_grupo?: string | null;
    semestre: number;
    agno: number;
    tiene_programa: boolean;
    profesor: string;
  }

  interface Props {
    cursos: CursoAlumno[];
    periodoActual: { semestre: number | string; agno: number | string };
    proximasAVencer?: ActividadPorVencer[];
    mensajeria?: { no_leidos: number; cursos: Array<{ id_curso: number; no_leidos: number }> };
  }

  let { cursos, periodoActual, proximasAVencer = [], mensajeria }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis cursos', href: '/estudiante/cursos' },
  ];

  const { formatName } = useFormatName();

  const semestre = $derived(Number(periodoActual.semestre));
  const agno = $derived(Number(periodoActual.agno));
  const nombreSemestre = (s: number, a: number) => `${s === 1 ? '1.er' : '2.º'} semestre ${a}`;

  const actuales = $derived(cursos.filter((c) => c.semestre === semestre && c.agno === agno));

  /** Semestres anteriores, del más reciente al más antiguo. */
  const anteriores = $derived.by(() => {
    const grupos = new Map<string, { titulo: string; orden: number; cursos: CursoAlumno[] }>();
    for (const c of cursos) {
      if (c.semestre === semestre && c.agno === agno) continue;
      const clave = `${c.agno}-${c.semestre}`;
      if (!grupos.has(clave)) grupos.set(clave, { titulo: nombreSemestre(c.semestre, c.agno), orden: c.agno * 10 + c.semestre, cursos: [] });
      grupos.get(clave)!.cursos.push(c);
    }
    return [...grupos.values()].sort((a, b) => b.orden - a.orden);
  });

  const listaMenu = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? []);
  const colorDe = (idCurso: number) => estiloCurso(numeroColorCurso(idCurso, listaMenu));

  const noLeidos = $derived(new Map((mensajeria?.cursos ?? []).map((c) => [c.id_curso, c.no_leidos])));

  /** Primera entrega que todavía se puede hacer en el curso. */
  function proximaDe(idCurso: number): ActividadPorVencer | null {
    return (
      proximasAVencer
        .filter((a) => a.id_curso === idCurso && plazoDe(a).abierta)
        .sort((a, b) => (a.plazo_hasta ?? a.fecha_limite).localeCompare(b.plazo_hasta ?? b.fecha_limite))[0] ?? null
    );
  }

  const docente = (c: CursoAlumno) =>
    !c.profesor || c.profesor === '(sin docente asignado)' ? 'Sin docente asignado' : formatName(c.profesor);
  const codigo = (c: CursoAlumno) => c.cod_asignatura || c.cod_curso;
  const nombre = (c: CursoAlumno) => (c.asignatura_nombre && c.asignatura_nombre !== 'N/A' ? c.asignatura_nombre : c.nombre);
</script>

<StudentLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-280 flex-col gap-6 pb-10 lg:gap-8 lg:px-10 lg:pt-8 lg:pb-12">
    <section
      aria-labelledby="t-cursos"
      class="sobre-noche relative overflow-hidden bg-dm-noche lg:rounded-xl lg:border lg:border-dm-noche-borde"
    >
      <Reticula class="left-[45%]" />
      <div class="relative flex flex-col gap-2 px-4 py-6 lg:px-10 lg:py-8">
        <span class="{etiqueta} text-dm-amarillo">{nombreSemestre(semestre, agno)}</span>
        <h1 id="t-cursos" class="{tituloCond} text-[40px] leading-10 text-dm-noche-texto lg:text-[56px] lg:leading-[52px]">
          Mis cursos
        </h1>
        <p class="text-base leading-6 text-dm-noche-texto-2">
          {actuales.length}
          {actuales.length === 1 ? 'curso' : 'cursos'} este semestre{actuales[0]?.carrera_nombre && actuales[0].carrera_nombre !== 'N/A'
            ? ` · ${actuales[0].carrera_nombre}`
            : ''}
        </p>
      </div>
    </section>

    <div class="flex flex-col gap-8 px-4 lg:px-0">
      {#if cursos.length === 0}
        <EstadoVacio
          icono={BookOpen}
          titulo="Aún no tienes cursos matriculados"
          texto="Tu matrícula de este semestre está en trámite. En cuanto se confirme, tus cursos aparecerán aquí."
          class="min-h-60"
        />
      {:else}
        <section aria-labelledby="t-actual" class="flex flex-col gap-4">
          <h2 id="t-actual" class="text-[22px] leading-7 font-bold">Este semestre</h2>
          {#if actuales.length > 0}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-[repeat(auto-fill,minmax(300px,1fr))]">
              {#each actuales as c (c.id_curso)}
                {@const proxima = proximaDe(c.id_curso)}
                {@const plazo = proxima ? plazoDe(proxima) : null}
                {@const sinLeer = noLeidos.get(c.id_curso) ?? 0}
                <article
                  style={colorDe(c.id_curso)}
                  class="flex flex-col gap-2 rounded-xl border border-t-6 border-dm-linea border-t-(--curso) bg-dm-tarjeta p-4 lg:p-5"
                >
                  <span class="flex items-baseline justify-between gap-2">
                    <span class="font-dm-cond text-2xl leading-7 font-extrabold tracking-[0.02em] text-(--curso-t)">{codigo(c)}</span>
                    {#if c.letra_grupo}<span class="text-[13px] leading-[18px] text-dm-ink-3">Grupo {c.letra_grupo}</span>{/if}
                  </span>
                  <h3 class="text-lg leading-6 font-semibold">{nombre(c)}</h3>
                  <span class="text-[13px] leading-[18px] text-dm-ink-2">{docente(c)}</span>
                  <span class="mt-1 flex flex-wrap items-center gap-2">
                    {#if plazo}
                      <span class="{pildora} {plazo.urgente ? tono.warn : tono.neutro}">
                        <CalendarClock size={14} aria-hidden="true" />{plazo.texto}
                      </span>
                    {:else}
                      <span class="{pildora} {tono.neutro}">Nada por entregar esta semana</span>
                    {/if}
                    {#if sinLeer > 0}
                      <span class="{pildora} {tono.accion}"><MessageSquare size={14} aria-hidden="true" />{sinLeer} sin leer</span>
                    {/if}
                  </span>
                  <span class="mt-2 flex items-center justify-between gap-2 border-t border-dm-linea pt-3">
                    <Link href={`/estudiante/cursos/${c.id_curso}`} class={enlace}>
                      Entrar al curso <ChevronRight size={16} aria-hidden="true" />
                    </Link>
                    {#if c.tiene_programa}
                      <Link
                        href={`/estudiante/cursos/${c.id_curso}/programa`}
                        class="inline-flex min-h-11 items-center gap-1.5 px-1 text-sm font-medium text-dm-ink-2 no-underline hover:text-dm-ink hover:underline lg:min-h-9"
                      >
                        <FileText size={16} aria-hidden="true" />Programa
                      </Link>
                    {/if}
                  </span>
                </article>
              {/each}
            </div>
          {:else}
            <p class="text-sm text-dm-ink-2">No tienes cursos inscritos en {nombreSemestre(semestre, agno)}.</p>
          {/if}
        </section>

        {#if anteriores.length > 0}
          <section aria-labelledby="t-anteriores" class="flex flex-col gap-3">
            <div class="flex items-baseline gap-3">
              <h2 id="t-anteriores" class="text-[22px] leading-7 font-bold">Semestres anteriores</h2>
              <span class="text-[13px] leading-[18px] text-dm-ink-3">Sólo lectura</span>
            </div>
            <div class="overflow-hidden {tarjeta}">
              {#each anteriores as grupo (grupo.titulo)}
                <div class="{etiqueta} border-b border-dm-linea bg-dm-sutil px-4 py-3 text-dm-ink-3 lg:px-5">{grupo.titulo}</div>
                {#each grupo.cursos as c (c.id_curso)}
                  <Link
                    href={`/estudiante/cursos/${c.id_curso}`}
                    style={colorDe(c.id_curso)}
                    class="flex min-h-13 flex-wrap items-center gap-x-4 gap-y-1 border-b border-dm-linea px-4 py-2 text-dm-ink no-underline last:border-b-0 hover:bg-dm-sutil lg:px-5"
                  >
                    <span class="{pildora} {tono.curso} shrink-0 font-bold">
                      <span aria-hidden="true" class="size-2 rounded-full bg-(--curso)"></span>{codigo(c)}
                    </span>
                    <span class="min-w-0 flex-1 text-[15px] font-semibold">{nombre(c)}</span>
                    <span class="shrink-0 text-[13px] leading-[18px] text-dm-ink-2 max-sm:basis-full">{docente(c)}</span>
                    <ChevronRight size={16} aria-hidden="true" class="shrink-0 text-dm-ink-3 max-sm:hidden" />
                  </Link>
                {/each}
              {/each}
            </div>
          </section>
        {/if}
      {/if}
    </div>
  </div>
</StudentLayout>
