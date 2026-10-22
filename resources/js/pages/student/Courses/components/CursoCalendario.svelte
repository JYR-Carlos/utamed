<script lang="ts" module>
  /** Qué fechas muestra el calendario: las del curso o las de todo el semestre. */
  export type AlcanceCalendario = 'curso' | 'semestre';
</script>

<script lang="ts">
  /**
   * Calendario del curso (vista del alumno): las fechas límite de las
   * actividades, en un mes o como agenda.
   *
   * Dos alcances: «Este curso» (las actividades del curso, que ya trae la
   * ficha) y «Todo el semestre» (las de todos los cursos inscritos del mismo
   * período, que se piden al elegirlo). En esa vista cada fecha lleva el
   * punto del color de su curso y su código o nombre.
   *
   * Es de solo lectura y sólo dibuja lo que el alumno puede actuar: la fecha
   * límite de cada actividad visible, con su estado (vencida, cierra pronto,
   * entregada, sumativa, formativa, sin entrega). El estado nunca va sólo en
   * el color: cada marca lleva ícono y el detalle lo dice en palabras.
   *
   * - Escritorio: grilla del mes (o agenda) y, al lado, el detalle del día
   *   elegido y la leyenda. Las flechas mueven el foco entre días.
   * - Móvil: la grilla compacta con puntos, el detalle del día y, debajo, la
   *   agenda del mes.
   */
  import { Link, page, router } from '@inertiajs/svelte';
  import { CalendarDays, ChevronLeft, ChevronRight, CircleAlert, CircleCheck, Clock } from 'lucide-svelte';
  import { tick } from 'svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import { fechaLimiteTexto } from '@/lib/actividadAlumno';
  import { numeroColorCurso } from '@/lib/colorCurso';
  import { btnSecundario, etiqueta, pildora, tarjeta } from '@/lib/dm';
  import { DIAS_SEMANA, MESES, buildMonthMatrix, formatLargo, sumarDias, todayKey } from '@/modules/resources/calendario';
  import type { SidebarCourse } from '@/types';
  import { diasHasta, formatDiaCorto } from '@/utils/formatters';

  interface Actividad {
    id_actividad: number;
    nombre: string;
    es_sumativa: boolean;
    con_entrega: boolean;
    es_grupal: boolean;
    fecha_limite: string;
    visible: boolean;
    estado?: string;
    ya_entregada?: boolean;
    ya_evaluada?: boolean;
    /** Sólo en la vista del semestre: el curso al que pertenece. */
    id_curso?: number;
    /** Código del curso (DM301). */
    curso?: string;
    asignatura?: string | null;
  }

  interface Props {
    idCurso: number;
    actividades?: Actividad[];
    /** Prop diferida: llega al elegir «Todo el semestre». */
    semestre?: Actividad[] | null;
    alcance?: AlcanceCalendario;
  }

  let { idCurso, actividades = [], semestre = null, alcance = $bindable('curso') }: Props = $props();

  // ─── Alcance ────────────────────────────────────────────────────────────────
  let cargandoSemestre = $state(false);
  let errorSemestre = $state(false);

  function cargarSemestre() {
    errorSemestre = false;
    cargandoSemestre = true;
    router.reload({
      only: ['calendario_semestre'],
      onError: () => (errorSemestre = true),
      onFinish: () => (cargandoSemestre = false),
    });
  }

  // Las fechas del semestre se piden una vez, la primera vez que se eligen.
  $effect(() => {
    if (alcance === 'semestre' && semestre === null && !cargandoSemestre && !errorSemestre) cargarSemestre();
  });

  const esSemestre = $derived(alcance === 'semestre');
  const fuente = $derived(esSemestre ? (semestre ?? []) : actividades);

  // ─── Estados ────────────────────────────────────────────────────────────────
  type Estado = 'vencida' | 'urgente' | 'entregada' | 'sumativa' | 'formativa' | 'informativa';

  function estadoDe(a: Actividad): Estado {
    if (a.ya_entregada || a.ya_evaluada) return 'entregada';
    if (!a.con_entrega) return 'informativa';
    const dias = diasHasta(a.fecha_limite);
    if (dias < 0) return 'vencida';
    if (dias <= 3) return 'urgente';
    return a.es_sumativa ? 'sumativa' : 'formativa';
  }

  /** `marca`: relleno, texto y borde izquierdo de la marca del día. */
  const ESTILOS: Record<Estado, { etiqueta: string; marca: string; punto: string; icono: typeof Clock }> = {
    vencida: { etiqueta: 'Vencida', marca: 'bg-dm-warn text-dm-tarjeta border-dm-warn', punto: 'bg-dm-warn', icono: CircleAlert },
    urgente: { etiqueta: 'Cierra pronto', marca: 'bg-dm-warn-s text-dm-warn border-dm-warn', punto: 'bg-dm-warn', icono: Clock },
    entregada: { etiqueta: 'Entregada', marca: 'bg-dm-ok-s text-dm-ok border-dm-ok', punto: 'bg-dm-ok', icono: CircleCheck },
    sumativa: {
      etiqueta: 'Sumativa',
      marca: 'bg-dm-accion-suave text-dm-accion-texto border-dm-accion',
      punto: 'bg-dm-accion',
      icono: Clock,
    },
    formativa: { etiqueta: 'Formativa', marca: 'bg-dm-info-s text-dm-info border-dm-info', punto: 'bg-dm-info', icono: Clock },
    informativa: { etiqueta: 'Sin entrega', marca: 'bg-dm-off-s text-dm-off border-dm-off', punto: 'bg-dm-off', icono: Clock },
  };
  const LEYENDA: Estado[] = ['vencida', 'urgente', 'entregada', 'sumativa', 'formativa', 'informativa'];

  /** «Vencida» si ya no se recibe; «Atrasada» si la holgura todavía la recibe. */
  function etiquetaDe(a: Actividad): string {
    const e = estadoDe(a);
    return e === 'vencida' && a.estado && a.estado !== 'CERRADA' ? 'Atrasada' : ESTILOS[e].etiqueta;
  }

  // ─── Datos por día ──────────────────────────────────────────────────────────
  const porDia = $derived.by(() => {
    const mapa: Record<string, Actividad[]> = {};
    for (const a of fuente) {
      if (!a.visible || !a.fecha_limite) continue;
      (mapa[a.fecha_limite.slice(0, 10)] ??= []).push(a);
    }
    return mapa;
  });
  const diasConFechasTodos = $derived(Object.keys(porDia).sort());
  const totalFechas = $derived(Object.values(porDia).reduce((n, l) => n + l.length, 0));

  // Color de cada curso del semestre, el mismo del menú.
  const listaMenu = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? []);
  const colorDe = (a: Actividad) => `var(--color-dm-c${numeroColorCurso(a.id_curso ?? idCurso, listaMenu)})`;
  const cursoDe = (a: Actividad) => a.asignatura || a.curso || '';

  // ─── Navegación ─────────────────────────────────────────────────────────────
  const hoy = todayKey();
  const [anioHoy, mesHoy] = hoy.split('-').map(Number);
  let year = $state(anioHoy);
  let month = $state(mesHoy - 1);
  let vista = $state<'mes' | 'agenda'>('mes');
  let seleccion = $state<string | null>(null);
  let grilla = $state<HTMLElement | null>(null);

  const prefijoMes = $derived(`${year}-${String(month + 1).padStart(2, '0')}`);
  const semanas = $derived(buildMonthMatrix(year, month));
  const esMesActual = $derived(year === anioHoy && month === mesHoy - 1);
  const diasConFechas = $derived(diasConFechasTodos.filter((iso) => iso.startsWith(prefijoMes)));

  // Sin elegir, el detalle muestra hoy si es este mes; si no, el primer día con fechas.
  const diaActivo = $derived(
    seleccion ?? (esMesActual ? hoy : (diasConFechas.find((iso) => iso >= hoy) ?? diasConFechas[0] ?? `${prefijoMes}-01`)),
  );
  const diaConTab = $derived(diaActivo.startsWith(prefijoMes) ? diaActivo : `${prefijoMes}-01`);
  const siguienteFecha = $derived(diasConFechasTodos.find((iso) => iso > diaActivo) ?? null);

  function irAMes(delta: number) {
    const fecha = new Date(year, month + delta, 1);
    year = fecha.getFullYear();
    month = fecha.getMonth();
    seleccion = null;
  }

  function irADia(iso: string) {
    const [y, m] = iso.split('-').map(Number);
    year = y;
    month = m - 1;
    seleccion = iso;
  }

  /** Flechas: ±1 día, ±1 semana; cruzar de mes cambia el mes mostrado. */
  async function alPulsarTecla(e: KeyboardEvent, iso: string) {
    const delta = ({ ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 } as Record<string, number>)[e.key];
    if (delta === undefined) return;
    e.preventDefault();
    const destino = sumarDias(iso, delta);
    irADia(destino);
    await tick();
    grilla?.querySelector<HTMLElement>(`[data-dia="${destino}"]`)?.focus();
  }

  const sinAgno = (iso: string) => formatLargo(iso).replace(/ de \d{4}$/, '');
  const mayuscula = (t: string) => t.charAt(0).toUpperCase() + t.slice(1);
  const minuscula = (t: string) => t.charAt(0).toLowerCase() + t.slice(1);
  const cuenta = (n: number) => (n === 0 ? 'Sin fechas límite' : `${n} ${n === 1 ? 'fecha límite' : 'fechas límite'}`);

  function etiquetaDia(iso: string, n: number): string {
    return `${sinAgno(iso)}${iso === hoy ? ', hoy' : ''}${n > 0 ? `, ${cuenta(n).toLowerCase()}` : ''}`;
  }

  const SEGMENTO = 'flex gap-1 rounded-xl border border-dm-linea bg-dm-sutil p-1';
  const opcion = (activa: boolean) =>
    `inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-lg px-3.5 py-1.5 text-sm transition-colors md:min-h-9 md:flex-none ${
      activa ? 'bg-dm-tarjeta font-semibold text-dm-ink shadow-sm' : 'font-medium text-dm-ink-2 hover:text-dm-ink'
    }`;
  const BOTON_ICONO =
    'inline-flex size-11 cursor-pointer items-center justify-center rounded-[10px] text-dm-ink hover:bg-dm-sutil md:size-9 md:border md:border-dm-control md:bg-dm-tarjeta';
</script>

<!-- Una fecha límite: curso (en el semestre), nombre, tipo · modalidad · plazo y su estado. -->
{#snippet fila(a: Actividad)}
  {@const e = ESTILOS[estadoDe(a)]}
  {@const Icono = e.icono}
  <Link
    href={`/estudiante/cursos/${a.id_curso ?? idCurso}/actividad/${a.id_actividad}`}
    class="flex flex-col gap-1.5 border-b border-dm-linea px-4 py-3.5 text-dm-ink no-underline last:border-b-0 hover:bg-dm-sutil xl:px-5"
  >
    {#if esSemestre && cursoDe(a)}
      <span class="flex items-center gap-1.5 text-[13px] leading-[18px] text-dm-ink-2">
        <span class="size-2 shrink-0 rounded-full" style="background: {colorDe(a)}"></span>{cursoDe(a)}
      </span>
    {/if}
    <span class="text-[15px] leading-5 font-semibold">{a.nombre}</span>
    <span class="text-[13px] leading-[18px] text-dm-ink-2">
      {a.es_sumativa ? 'Sumativa' : 'Formativa'} · {a.es_grupal ? 'Grupal' : 'Individual'} · {minuscula(fechaLimiteTexto(a.fecha_limite))}
    </span>
    <span class="{pildora} {e.marca} self-start"><Icono size={14} aria-hidden="true" />{etiquetaDe(a)}</span>
  </Link>
{/snippet}

<div class="flex flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-3 max-md:w-full">
      <div role="group" aria-label="Qué fechas mostrar" class="{SEGMENTO} max-md:w-full">
        {#each [{ id: 'curso', etiqueta: 'Este curso' }, { id: 'semestre', etiqueta: 'Todo el semestre' }] as a (a.id)}
          <button
            type="button"
            aria-pressed={alcance === a.id}
            onclick={() => (alcance = a.id as AlcanceCalendario)}
            class={opcion(alcance === a.id)}>{a.etiqueta}</button
          >
        {/each}
      </div>
      <div role="group" aria-label="Vista del calendario" class="{SEGMENTO} max-md:hidden">
        {#each [{ id: 'mes', etiqueta: 'Mes' }, { id: 'agenda', etiqueta: 'Agenda' }] as v (v.id)}
          <button
            type="button"
            aria-pressed={vista === v.id}
            onclick={() => (vista = v.id as 'mes' | 'agenda')}
            class={opcion(vista === v.id)}>{v.etiqueta}</button
          >
        {/each}
      </div>
    </div>

    <div class="flex items-center gap-2 max-md:hidden">
      <button type="button" class={BOTON_ICONO} onclick={() => irAMes(-1)} aria-label="Mes anterior">
        <ChevronLeft size={18} aria-hidden="true" />
      </button>
      <h3 class="min-w-35 text-center font-dm-cond text-[22px] leading-7 font-extrabold uppercase" aria-live="polite">
        {MESES[month]} {year}
      </h3>
      <button type="button" class={BOTON_ICONO} onclick={() => irAMes(1)} aria-label="Mes siguiente">
        <ChevronRight size={18} aria-hidden="true" />
      </button>
      <button
        type="button"
        class="{btnSecundario} min-h-9 px-3.5"
        onclick={() => irADia(hoy)}
        disabled={esMesActual && diaActivo === hoy}>Hoy</button
      >
    </div>
  </div>

  {#if esSemestre && semestre === null}
    {#if errorSemestre}
      <EstadoVacio tipo="error" titulo="No se pudieron cargar las fechas del semestre">
        {#snippet accion()}
          <button type="button" class={btnSecundario} onclick={cargarSemestre}>Intentar de nuevo</button>
        {/snippet}
      </EstadoVacio>
    {:else}
      <EstadoVacio tipo="cargando" titulo="Cargando las fechas de todos tus cursos del semestre…" />
    {/if}
  {:else if totalFechas === 0}
    <EstadoVacio
      icono={CalendarDays}
      titulo="Todavía no hay fechas en el calendario"
      texto={esSemestre
        ? 'Las fechas límite aparecen aquí cuando los docentes de tus cursos del semestre publican actividades con plazo.'
        : 'Las fechas límite aparecen aquí cuando tu docente publica actividades con plazo.'}
    />
  {:else}
    <div class="flex flex-col gap-4 xl:grid xl:grid-cols-[minmax(0,1fr)_280px] xl:items-start xl:gap-6">
      <!-- Bajo xl las dos columnas se disuelven (contents) y el orden lo ponen los order-*:
           grilla, detalle del día, agenda del mes y leyenda. -->
      <div class="flex min-w-0 flex-col gap-4 max-xl:contents">
        {#if vista === 'mes'}
          <section aria-label="{MESES[month]} {year}" class="order-1 overflow-hidden {tarjeta}">
            <!-- En móvil el mes se cambia aquí; en escritorio, en la barra de arriba. -->
            <div class="flex items-center justify-between gap-2 border-b border-dm-linea p-1.5 md:hidden">
              <button type="button" class={BOTON_ICONO} onclick={() => irAMes(-1)} aria-label="Mes anterior">
                <ChevronLeft size={20} aria-hidden="true" />
              </button>
              <span class="font-dm-cond text-xl leading-6 font-extrabold uppercase" aria-live="polite">{MESES[month]} {year}</span>
              <button type="button" class={BOTON_ICONO} onclick={() => irAMes(1)} aria-label="Mes siguiente">
                <ChevronRight size={20} aria-hidden="true" />
              </button>
            </div>

            <div role="grid" aria-label="Fechas límite de {MESES[month]} {year}" bind:this={grilla}>
              <div role="row" class="grid grid-cols-7 px-1.5 pt-1.5 md:border-b md:border-dm-linea md:bg-dm-sutil md:p-0">
                {#each DIAS_SEMANA as dia (dia)}
                  <span role="columnheader" class="{etiqueta} py-1 text-center text-dm-ink-3 md:px-2 md:py-2.5">{dia}</span>
                {/each}
              </div>

              {#each semanas as semana, i (i)}
                <div
                  role="row"
                  class="grid grid-cols-7 gap-0.5 px-1.5 last:pb-2 md:gap-0 md:p-0 md:last:pb-0 md:[&:last-child>div]:border-b-0"
                >
                  {#each semana as dia (dia.iso)}
                    {@const lista = porDia[dia.iso] ?? []}
                    {@const elegido = diaActivo === dia.iso}
                    <div
                      role="gridcell"
                      aria-selected={elegido}
                      class="min-w-0 md:border-r md:border-b md:border-dm-linea md:last:border-r-0 {dia.inMonth
                        ? ''
                        : 'md:bg-dm-sutil'}"
                    >
                      <button
                        type="button"
                        data-dia={dia.iso}
                        tabindex={dia.iso === diaConTab ? 0 : -1}
                        aria-label={etiquetaDia(dia.iso, lista.length)}
                        aria-current={dia.isToday ? 'date' : undefined}
                        onclick={() => (seleccion = dia.iso)}
                        onkeydown={(e) => alPulsarTecla(e, dia.iso)}
                        class="flex min-h-12 w-full cursor-pointer flex-col items-center gap-1 rounded-[10px] px-0 py-1 md:min-h-27 md:items-stretch md:rounded-none md:p-1.5 {elegido
                          ? 'outline-2 -outline-offset-2 outline-dm-accion'
                          : 'hover:bg-dm-sutil'}"
                      >
                        <span
                          class="inline-flex size-7 shrink-0 items-center justify-center rounded-full text-sm tabular-nums md:self-start {dia.isToday
                            ? 'bg-dm-amarillo font-bold text-dm-noche'
                            : dia.inMonth
                              ? 'font-semibold text-dm-ink'
                              : 'font-medium text-dm-ink-3'}"
                        >
                          {dia.dayOfMonth}
                        </span>

                        <!-- Móvil: puntos de estado (el texto está en el detalle del día) -->
                        <span class="flex h-1.5 items-center gap-0.5 md:hidden" aria-hidden="true">
                          {#each lista.slice(0, 3) as a (a.id_actividad)}
                            <span class="size-1.5 rounded-full {ESTILOS[estadoDe(a)].punto}"></span>
                          {/each}
                        </span>

                        <!-- Escritorio: una marca por actividad, con ícono y nombre -->
                        <span class="hidden min-w-0 flex-col gap-1 md:flex" aria-hidden="true">
                          {#each lista.slice(0, 2) as a (a.id_actividad)}
                            {@const e = ESTILOS[estadoDe(a)]}
                            {@const Icono = e.icono}
                            <span
                              class="flex min-w-0 items-center gap-1 rounded-md border-l-3 px-1.5 py-0.5 text-xs leading-4 font-semibold {e.marca}"
                              title="{cursoDe(a) ? `${cursoDe(a)} · ` : ''}{a.nombre} · {etiquetaDe(a)}"
                            >
                              <Icono size={12} class="shrink-0" />
                              <span class="truncate">{esSemestre && a.curso ? `${a.curso} · ${a.nombre}` : a.nombre}</span>
                            </span>
                          {/each}
                          {#if lista.length > 2}
                            <span class="px-1 text-xs font-bold text-dm-enlace">+{lista.length - 2} más</span>
                          {/if}
                        </span>
                      </button>
                    </div>
                  {/each}
                </div>
              {/each}
            </div>
          </section>
        {/if}

        <!-- Agenda: la vista elegida en escritorio; en móvil va siempre, bajo el detalle del día. -->
        <section
          aria-labelledby="t-agenda"
          class="flex flex-col gap-3 {vista === 'agenda' ? 'order-1' : 'order-3 md:hidden'}"
        >
          <h3 id="t-agenda" class="text-lg leading-6 font-bold {vista === 'agenda' ? 'md:sr-only' : ''}">
            Este mes
          </h3>
          {#if diasConFechas.length === 0}
            <p class="{tarjeta} px-4 py-3.5 text-sm text-dm-ink-2">
              No hay fechas límite en {MESES[month].toLowerCase()}. Usa las flechas para ver otro mes.
            </p>
          {:else}
            {#each diasConFechas as iso (iso)}
              {@const d = Number(iso.slice(8, 10))}
              <section aria-label={mayuscula(sinAgno(iso))} class="{tarjeta} flex gap-3 px-3.5 py-3 md:gap-4 md:px-5 md:py-4">
                <div class="flex w-11 shrink-0 flex-col items-center md:w-14">
                  <span class="{etiqueta} text-dm-ink-3">{formatDiaCorto(iso).split(' ')[0]}</span>
                  <span
                    class="font-dm-cond text-[28px] leading-8 font-black md:text-[32px] md:leading-9 {iso === hoy
                      ? 'text-dm-accion-texto'
                      : 'text-dm-ink'}">{d}</span
                  >
                </div>
                <ul class="flex min-w-0 flex-1 flex-col gap-2.5">
                  {#each porDia[iso] as a (a.id_actividad)}
                    {@const e = ESTILOS[estadoDe(a)]}
                    <li>
                      <Link
                        href={`/estudiante/cursos/${a.id_curso ?? idCurso}/actividad/${a.id_actividad}`}
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-dm-ink no-underline hover:underline"
                      >
                        <span class="flex min-w-0 flex-col gap-0.5">
                          {#if esSemestre && cursoDe(a)}
                            <span class="flex items-center gap-1.5 text-[13px] leading-[18px] text-dm-ink-2">
                              <span class="size-2 shrink-0 rounded-full" style="background: {colorDe(a)}"></span>
                              <span class="md:hidden">{a.curso || cursoDe(a)}</span><span class="max-md:hidden">{cursoDe(a)}</span>
                            </span>
                          {/if}
                          <span class="text-[15px] leading-5 font-semibold">{a.nombre}</span>
                          <span class="text-[13px] leading-[18px] text-dm-ink-2 max-md:hidden">
                            {a.es_sumativa ? 'Sumativa' : 'Formativa'} · {a.es_grupal ? 'Grupal' : 'Individual'} · {minuscula(
                              fechaLimiteTexto(a.fecha_limite),
                            )}
                          </span>
                        </span>
                        <span class="{pildora} {e.marca}">{etiquetaDe(a)}</span>
                      </Link>
                    </li>
                  {/each}
                </ul>
              </section>
            {/each}
          {/if}
        </section>
      </div>

      <aside class="flex flex-col gap-4 max-xl:contents">
        <section
          aria-labelledby="t-dia"
          aria-live="polite"
          class="order-2 overflow-hidden border-t-6 border-t-dm-amarillo {tarjeta}"
        >
          <div class="flex flex-col gap-0.5 border-b border-dm-linea px-4 py-3.5 xl:px-5">
            <h3 id="t-dia" class="text-lg leading-6 font-bold">
              {mayuscula(sinAgno(diaActivo))}{diaActivo === hoy ? ' · hoy' : ''}
            </h3>
            <span class="text-[13px] leading-[18px] text-dm-ink-3">{cuenta((porDia[diaActivo] ?? []).length)}</span>
          </div>
          {#each porDia[diaActivo] ?? [] as a (a.id_actividad)}
            {@render fila(a)}
          {:else}
            <p class="border-b border-dm-linea px-4 py-3.5 text-sm text-dm-ink-2 xl:px-5">No hay fechas límite este día.</p>
          {/each}
          {#if siguienteFecha}
            <button
              type="button"
              onclick={() => irADia(siguienteFecha)}
              class="flex min-h-11 w-full cursor-pointer items-center gap-1 px-4 text-sm font-semibold text-dm-enlace hover:underline xl:px-5"
            >
              Siguiente fecha: {formatDiaCorto(siguienteFecha)}<ChevronRight size={16} aria-hidden="true" />
            </button>
          {/if}
        </section>

        <section aria-labelledby="t-leyenda" class="{tarjeta} order-4 flex flex-col gap-2.5 px-4 py-4 xl:px-5">
          <h3 id="t-leyenda" class="{etiqueta} text-dm-ink-3">Leyenda</h3>
          <ul class="flex flex-wrap gap-2">
            {#each LEYENDA as e (e)}
              <li class="{pildora} {ESTILOS[e].marca}">{ESTILOS[e].etiqueta}</li>
            {/each}
          </ul>
        </section>
      </aside>
    </div>
  {/if}
</div>
