<script lang="ts" module>
  export type { DocenteSyllabus, ProgramaPublicado, SeccionPrograma } from '@/lib/programaAlumno';
</script>

<script lang="ts">
  /**
   * Pestaña «Programa» de la ficha del curso: la ficha de identificación
   * (docentes, categoría, créditos, horas y cómo se evalúa) y el documento
   * íntegro, una tarjeta por sección, con las Unidades primero (T24).
   *
   * En escritorio un índice lateral fijo marca la sección que se está
   * leyendo; en móvil el índice es una fila de chips que se desliza. La
   * versión para imprimir es otra página (Courses/Syllabus.svelte).
   */
  import { BookOpen, Printer } from 'lucide-svelte';
  import { Link } from '@inertiajs/svelte';
  import CuerpoSeccion from '@/components/student/programa/CuerpoSeccion.svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import { useFormatName } from '@/hooks';
  import { btnSecundario, etiqueta, tarjeta } from '@/lib/dm';
  import {
    mesYAgno,
    porcentaje,
    seccionesParaLeer,
    type DocenteSyllabus,
    type ProgramaPublicado,
    type SeccionPrograma,
  } from '@/lib/programaAlumno';
  import type { Curso } from '@/types';

  interface Props {
    curso?: Curso | null;
    programa?: ProgramaPublicado | null;
    docentes?: DocenteSyllabus[];
    secciones?: SeccionPrograma[];
    categoria?: string;
    /** Versión para imprimir; en móvil el botón va al pie (en escritorio lo pone la página). */
    hrefImprimir?: string;
  }

  let { curso, programa = null, docentes = [], secciones = [], categoria = '', hrefImprimir }: Props = $props();

  const { formatName } = useFormatName();

  const asignatura = $derived(curso?.asignatura);

  // Sólo las horas que la asignatura tiene: una asignatura sin cátedra no muestra «0».
  const horas = $derived(
    [
      { largo: 'Horas cátedra', corto: 'Cátedra', valor: asignatura?.horas_catedra },
      { largo: 'Horas taller', corto: 'Taller', valor: asignatura?.horas_taller },
      { largo: 'Horas laboratorio', corto: 'Lab.', valor: asignatura?.horas_laboratorio },
    ].filter((h) => Number(h.valor) > 0),
  );

  const componentes = $derived(secciones.find((s) => s.numeral_romano === 'IX')?.componentes ?? []);

  const ordenadas = $derived(seccionesParaLeer(secciones));
  const idDe = (s: SeccionPrograma) => `seccion-${s.numeral_romano ?? s.nombre_seccion}`;

  // ─── Sección que se está leyendo ────────────────────────────────────────────
  let activa = $state('');

  $effect(() => {
    const ids = ordenadas.map(idDe);
    activa = ids[0] ?? '';
    const visibles = new Set<string>();
    const io = new IntersectionObserver(
      (entradas) => {
        for (const e of entradas) {
          if (e.isIntersecting) visibles.add(e.target.id);
          else visibles.delete(e.target.id);
        }
        activa = ids.find((id) => visibles.has(id)) ?? activa;
      },
      // Cuenta como «leyendo» la que ocupa la franja superior de la pantalla.
      { rootMargin: '-10% 0px -60% 0px' },
    );
    for (const id of ids) {
      const el = document.getElementById(id);
      if (el) io.observe(el);
    }
    return () => io.disconnect();
  });

  const CELDA = 'flex flex-col gap-1 border-dm-linea px-4 py-3.5 lg:px-6 lg:py-4.5';
  const NUMERO = 'm-0 font-dm-cond text-[28px] leading-8 font-black lg:text-[32px] lg:leading-9';
</script>

<div class="flex flex-col gap-4 lg:gap-6">
  <section aria-label="Ficha del curso" class="overflow-hidden {tarjeta}">
    <dl class="m-0 flex flex-wrap">
      <div class="{CELDA} order-2 basis-full border-b lg:order-1 lg:flex-[1_1_160px] lg:basis-auto lg:border-r lg:border-b-0">
        <dt class="{etiqueta} text-dm-ink-3">Categoría</dt>
        <dd class="m-0 text-[15px] leading-[22px] font-semibold">{categoria || '—'}</dd>
      </div>
      <div class="{CELDA} order-1 basis-full border-b lg:order-2 lg:flex-[2_1_280px] lg:basis-auto lg:border-r lg:border-b-0">
        <dt class="{etiqueta} text-dm-ink-3">{docentes.length === 1 ? 'Docente' : 'Docentes'}</dt>
        <dd class="m-0 flex flex-col gap-1.5">
          {#each docentes as d (d.nombre + (d.componente ?? ''))}
            <span class="text-[15px] leading-5 font-semibold">
              {formatName(d.nombre)}
              <span class="font-normal text-dm-ink-2"
                >· {d.es_titular ? 'Titular' : 'Docente'}{d.componente ? ` · ${d.componente}` : ''}</span
              >
            </span>
          {:else}
            <span class="text-[15px] text-dm-ink-2">Sin docente asignado</span>
          {/each}
        </dd>
      </div>
      <div class="{CELDA} order-3 flex-1 border-r lg:flex-[1_1_120px]">
        <dt class="{etiqueta} text-dm-ink-3"><span class="lg:hidden">SCT</span><span class="max-lg:hidden">Créditos SCT</span></dt>
        <dd class={NUMERO}>{asignatura?.creditos_sct ?? '—'}</dd>
      </div>
      {#each horas as h (h.largo)}
        <div class="{CELDA} order-3 flex-1 border-r last:border-r-0 lg:flex-[1_1_120px]">
          <dt class="{etiqueta} text-dm-ink-3"><span class="lg:hidden">{h.corto}</span><span class="max-lg:hidden">{h.largo}</span></dt>
          <dd class={NUMERO}>{h.valor}<span class="lg:hidden">{' '}h</span></dd>
        </div>
      {/each}
    </dl>
    {#if componentes.length > 0}
      <div
        class="flex flex-col gap-1.5 border-t border-dm-linea bg-dm-sutil px-4 py-3 lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-4 lg:gap-y-2 lg:px-6"
      >
        <span class="{etiqueta} text-dm-ink-3">Cómo se evalúa</span>
        {#each componentes as c, i (c.componente + i)}
          <span class="text-sm leading-5">
            <strong class="font-bold">{c.componente} {porcentaje(c.porcentaje)}</strong>
            <span class="text-dm-ink-2"
              >{c.aprobacion_obligatoria ? ' · aprobación obligatoria' : ''}{c.asistencia_obligatoria
                ? ` · asistencia mínima ${porcentaje(c.asistencia_obligatoria)}`
                : ''}</span
            >
          </span>
        {/each}
      </div>
    {/if}
  </section>

  {#if !programa}
    <EstadoVacio
      icono={BookOpen}
      titulo="Programa no disponible"
      texto="El programa de este curso todavía no ha sido aprobado. Cuando lo esté, lo podrás leer aquí."
    />
  {:else}
    <!-- Índice en móvil: chips que se deslizan. -->
    <nav
      aria-label="Secciones del programa"
      class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] lg:hidden [&::-webkit-scrollbar]:hidden"
    >
      {#each ordenadas as s (idDe(s))}
        {@const id = idDe(s)}
        <a
          href="#{id}"
          aria-current={activa === id ? 'location' : undefined}
          class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-semibold whitespace-nowrap no-underline {activa ===
          id
            ? 'border-dm-amarillo bg-dm-activo-suave text-dm-activo-texto'
            : 'border-dm-control bg-dm-tarjeta text-dm-ink'}"
        >
          <span class="font-dm-cond font-extrabold text-dm-ink-3">{s.numeral_romano}</span>{s.nombre_seccion}
        </a>
      {/each}
    </nav>

    <div class="flex items-start gap-6">
      <!-- Índice en escritorio: fijo mientras se lee. -->
      <nav
        aria-label="Secciones del programa"
        class="{tarjeta} sticky top-4 hidden w-60 shrink-0 flex-col gap-0.5 p-3 lg:flex"
      >
        <span class="{etiqueta} px-2.5 pt-1 pb-2 text-dm-ink-3">En este programa</span>
        {#each ordenadas as s (idDe(s))}
          {@const id = idDe(s)}
          <a
            href="#{id}"
            aria-current={activa === id ? 'location' : undefined}
            class="relative flex min-h-10 items-baseline gap-2.5 rounded-lg px-2.5 py-2 text-sm no-underline {activa === id
              ? 'bg-dm-activo-suave font-semibold text-dm-ink'
              : 'font-medium text-dm-ink-2 hover:bg-dm-sutil hover:text-dm-ink'}"
          >
            {#if activa === id}
              <span aria-hidden="true" class="absolute top-2 left-0 h-6 w-[3px] rounded-sm bg-dm-amarillo"></span>
            {/if}
            <span class="w-7.5 shrink-0 font-dm-cond text-[15px] font-extrabold text-dm-ink-3">{s.numeral_romano}</span>
            {s.nombre_seccion}
          </a>
        {/each}
      </nav>

      <div class="flex min-w-0 flex-1 flex-col gap-4">
        {#each ordenadas as s (idDe(s))}
          {@const id = idDe(s)}
          <article {id} aria-labelledby="titulo-{id}" class="scroll-mt-4 overflow-hidden {tarjeta}">
            <header class="flex items-baseline gap-2.5 border-b border-dm-linea px-4 py-3.5 lg:gap-3 lg:px-6 lg:py-4">
              <span class="font-dm-cond text-xl font-black text-(--curso-t) lg:text-[22px]">{s.numeral_romano}</span>
              <h3 id="titulo-{id}" class="font-dm-cond text-[22px] leading-[26px] font-extrabold uppercase lg:text-2xl lg:leading-7">
                {s.nombre_seccion}
              </h3>
            </header>
            <div class="p-4 lg:px-6 lg:py-5">
              <CuerpoSeccion seccion={s} />
            </div>
          </article>
        {/each}

        <footer class="flex flex-col gap-3 py-2 text-[13px] leading-[18px] text-dm-ink-2">
          <span class="flex flex-col gap-1">
            <span>
              Programa aprobado por el Consejo de Escuela · Versión {programa.version_programa}{programa.fecha_creacion
                ? ` · ${mesYAgno(programa.fecha_creacion)}`
                : ''}
            </span>
            {#if programa.creado_por}<span>Elaborado por {programa.creado_por}</span>{/if}
          </span>
          {#if hrefImprimir}
            <Link href={hrefImprimir} class="{btnSecundario} min-h-12 text-[15px] lg:hidden">
              <Printer size={18} aria-hidden="true" />Versión para imprimir
            </Link>
          {/if}
        </footer>
      </div>
    </div>
  {/if}
</div>
