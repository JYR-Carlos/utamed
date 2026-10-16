<script lang="ts">
  /**
   * Calendario del curso (vista del alumno): las fechas límite de las
   * actividades del curso, en un mes o como agenda.
   *
   * Es de solo lectura y sólo dibuja lo que el alumno puede actuar: la fecha
   * límite de cada actividad visible, con su estado (vencida sin entrega, cierra
   * pronto, entregada, pendiente). El estado nunca va sólo en el color: cada
   * marca lleva ícono y texto, y el detalle del día repite el plazo en palabras.
   *
   * - Mes: grilla de semanas que parten en lunes. Cada día es un botón; en
   *   pantallas angostas muestra puntos de estado y en escritorio, las marcas.
   *   Las flechas mueven el foco entre días.
   * - Agenda: los días del mes que tienen fechas, en orden. Es la vista por
   *   defecto en móvil, donde la grilla sólo sirve para ubicarse.
   *
   * Pulsar un día abre su detalle bajo la grilla, con las mismas tarjetas de la
   * pestaña Actividades; desde ahí se entra a la actividad.
   */
  import { Link } from '@inertiajs/svelte';
  import { AlertCircle, CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock } from 'lucide-svelte';
  import { onMount, tick } from 'svelte';
  import ActividadCard from '@/modules/resources/actividad/components/actividadCard.svelte';
  import {
    DIAS_SEMANA,
    MESES,
    buildMonthMatrix,
    formatLargo,
    formatMedio,
    sumarDias,
    todayKey,
  } from '@/modules/resources/calendario';
  import { diasHasta } from '@/utils/formatters';

  interface Actividad {
    id_actividad: number;
    nombre: string;
    es_sumativa: boolean;
    con_entrega: boolean;
    es_grupal: boolean;
    max_integrantes: number;
    fecha_limite: string;
    visible: boolean;
    ya_entregada?: boolean;
    fecha_entrega?: string | null;
    ya_evaluada?: boolean;
  }

  interface Props {
    idCurso: number;
    actividades?: Actividad[];
  }

  let { idCurso, actividades = [] }: Props = $props();

  type Estado = 'vencida' | 'urgente' | 'entregada' | 'pendiente' | 'informativa';

  /** Estado de una fecha límite para el alumno. */
  function estadoDe(a: Actividad): Estado {
    if (a.ya_entregada || a.ya_evaluada) return 'entregada';
    if (!a.con_entrega) return 'informativa';
    const dias = diasHasta(a.fecha_limite);
    if (dias < 0) return 'vencida';
    return dias <= 3 ? 'urgente' : 'pendiente';
  }

  /** Marca de un día: relleno suave, borde izquierdo y texto con contraste ≥ 4,5:1. */
  function estiloMarca(a: Actividad): { clases: string; punto: string; etiqueta: string } {
    switch (estadoDe(a)) {
      case 'vencida':
        return { clases: 'bg-red-50 text-red-800 border-red-600', punto: 'bg-red-600', etiqueta: 'Vencida' };
      case 'urgente':
        return { clases: 'bg-amber-50 text-amber-800 border-amber-500', punto: 'bg-amber-500', etiqueta: 'Cierra pronto' };
      case 'entregada':
        return { clases: 'bg-emerald-50 text-emerald-800 border-emerald-600', punto: 'bg-emerald-600', etiqueta: 'Entregada' };
      case 'informativa':
        return { clases: 'bg-gray-100 text-slate-700 border-slate-400', punto: 'bg-slate-400', etiqueta: 'Sin entrega' };
      default:
        return a.es_sumativa
          ? { clases: 'bg-indigo-50 text-uta-blue border-uta-blue', punto: 'bg-uta-blue', etiqueta: 'Sumativa' }
          : { clases: 'bg-sky-50 text-sky-900 border-sky-600', punto: 'bg-sky-600', etiqueta: 'Formativa' };
    }
  }

  // ─── Datos por día ──────────────────────────────────────────────────────────
  const porDia = $derived.by(() => {
    const mapa: Record<string, Actividad[]> = {};
    for (const a of actividades) {
      if (!a.visible || !a.fecha_limite) continue;
      (mapa[a.fecha_limite.slice(0, 10)] ??= []).push(a);
    }
    return mapa;
  });

  const totalFechas = $derived(Object.values(porDia).reduce((n, l) => n + l.length, 0));

  // ─── Navegación ─────────────────────────────────────────────────────────────
  const hoy = todayKey();
  const [anioHoy, mesHoy] = hoy.split('-').map(Number);
  let year = $state(anioHoy);
  let month = $state(mesHoy - 1);
  let vista = $state<'mes' | 'agenda'>('mes');
  let seleccion = $state<string | null>(null);
  let grilla = $state<HTMLElement | null>(null);

  // En móvil la agenda es la vista útil; sólo se aplica al montar.
  onMount(() => {
    if (window.matchMedia('(max-width: 767px)').matches) vista = 'agenda';
  });

  const prefijoMes = $derived(`${year}-${String(month + 1).padStart(2, '0')}`);
  const semanas = $derived(buildMonthMatrix(year, month));
  const esMesActual = $derived(year === anioHoy && month === mesHoy - 1);

  const diasConFechas = $derived(
    Object.keys(porDia)
      .filter((iso) => iso.startsWith(prefijoMes))
      .sort(),
  );
  const fechasDelMes = $derived(diasConFechas.reduce((n, iso) => n + porDia[iso].length, 0));

  // Sin elegir, el detalle muestra el próximo día con fechas (o el primero del mes).
  const diaPorDefecto = $derived(
    diasConFechas.find((iso) => iso >= hoy) ?? diasConFechas[0] ?? null,
  );
  const diaActivo = $derived(seleccion ?? diaPorDefecto);
  const iso1 = $derived(`${prefijoMes}-01`);
  const diaConTab = $derived(diaActivo && diaActivo.startsWith(prefijoMes) ? diaActivo : iso1);

  function irAMes(delta: number) {
    const fecha = new Date(year, month + delta, 1);
    year = fecha.getFullYear();
    month = fecha.getMonth();
    seleccion = null;
  }

  function irAHoy() {
    year = anioHoy;
    month = mesHoy - 1;
    seleccion = hoy;
  }

  /** Flechas: ±1 día, ±1 semana; cruzar de mes cambia el mes mostrado. */
  async function alPulsarTecla(e: KeyboardEvent, iso: string) {
    const delta = ({ ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 } as Record<string, number>)[e.key];
    if (delta === undefined) return;
    e.preventDefault();
    const destino = sumarDias(iso, delta);
    const [y, m] = destino.split('-').map(Number);
    year = y;
    month = m - 1;
    seleccion = destino;
    await tick();
    grilla?.querySelector<HTMLElement>(`[data-dia="${destino}"]`)?.focus();
  }

  /** Sólo la primera letra en mayúscula: «Miércoles 2 de diciembre de 2026». */
  const mayuscula = (texto: string) => texto.charAt(0).toUpperCase() + texto.slice(1);

  function etiquetaDia(iso: string, n: number): string {
    const base = formatLargo(iso);
    return n === 0 ? base : `${base}: ${n} ${n === 1 ? 'fecha límite' : 'fechas límite'}`;
  }

  const LEYENDA = [
    { punto: 'bg-red-600', texto: 'Vencida sin entrega' },
    { punto: 'bg-amber-500', texto: 'Cierra en 3 días o menos' },
    { punto: 'bg-emerald-600', texto: 'Entregada' },
    { punto: 'bg-uta-blue', texto: 'Sumativa' },
    { punto: 'bg-sky-600', texto: 'Formativa' },
  ];

  const BOTON =
    'inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-uta-blue transition-colors hover:bg-gray-50';
  const BOTON_ICONO =
    'inline-flex size-11 items-center justify-center rounded-lg border border-slate-300 bg-white text-uta-blue transition-colors hover:bg-gray-50';
</script>

{#snippet detalleDelDia(iso: string)}
  {@const lista = porDia[iso] ?? []}
  <ul class="m-0 flex list-none flex-col gap-3 p-0">
    {#each lista as act (act.id_actividad)}
      <li>
        <Link href={`/estudiante/cursos/${idCurso}/actividad/${act.id_actividad}`} class="block rounded-xl no-underline">
          <ActividadCard actividad={act} />
        </Link>
      </li>
    {/each}
  </ul>
{/snippet}

{#if totalFechas === 0}
  <div class="flex flex-col items-center gap-3 rounded-xl border border-slate-200 bg-white px-6 py-8 text-center">
    <div class="flex size-14 items-center justify-center rounded-full bg-gray-100">
      <CalendarDays class="h-6 w-6 text-slate-600" aria-hidden="true" />
    </div>
    <h3 class="text-lg font-bold text-slate-900">Todavía no hay fechas en el calendario</h3>
    <p class="text-sm text-slate-600">
      Las fechas límite aparecen aquí cuando tu docente publica actividades con plazo.
    </p>
  </div>
{:else}
  <!-- Barra: mes, hoy y vista -->
  <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-3">
    <div class="flex items-center gap-1">
      <button type="button" class={BOTON_ICONO} onclick={() => irAMes(-1)} aria-label="Mes anterior">
        <ChevronLeft class="h-5 w-5" aria-hidden="true" />
      </button>
      <h3
        class="m-0 min-w-[10.5rem] text-center text-lg font-bold text-slate-900"
        aria-live="polite"
      >
        {MESES[month]} {year}
      </h3>
      <button type="button" class={BOTON_ICONO} onclick={() => irAMes(1)} aria-label="Mes siguiente">
        <ChevronRight class="h-5 w-5" aria-hidden="true" />
      </button>
    </div>

    <button type="button" class={BOTON} onclick={irAHoy} disabled={esMesActual && seleccion === hoy}>
      Hoy
    </button>

    <div class="flex gap-1 rounded-xl bg-gray-100 p-1 sm:ml-auto" role="group" aria-label="Vista del calendario">
      {#each [{ id: 'mes', etiqueta: 'Mes' }, { id: 'agenda', etiqueta: 'Agenda' }] as v (v.id)}
        <button
          type="button"
          aria-pressed={vista === v.id}
          onclick={() => (vista = v.id as 'mes' | 'agenda')}
          class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm transition-colors {vista === v.id
            ? 'bg-uta-blue font-semibold text-white'
            : 'font-medium text-slate-700 hover:bg-white hover:text-uta-blue'}"
        >
          {v.etiqueta}
        </button>
      {/each}
    </div>
  </div>

  <!-- Leyenda: color + texto -->
  <ul class="m-0 mb-4 flex list-none flex-wrap gap-x-4 gap-y-1.5 p-0">
    {#each LEYENDA as l (l.texto)}
      <li class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
        <span class="size-2.5 rounded-full {l.punto}" aria-hidden="true"></span>
        {l.texto}
      </li>
    {/each}
  </ul>

  {#if vista === 'mes'}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div role="grid" aria-label="Fechas límite de {MESES[month]} {year}" bind:this={grilla}>
        <div role="row" class="grid grid-cols-7 border-b border-slate-200 bg-gray-50">
          {#each DIAS_SEMANA as dia (dia)}
            <div role="columnheader" class="px-1 py-2 text-center text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600">
              {dia}
            </div>
          {/each}
        </div>

        {#each semanas as semana, i (i)}
          <div role="row" class="grid grid-cols-7 {i < semanas.length - 1 ? 'border-b border-slate-200' : ''}">
            {#each semana as dia (dia.iso)}
              {@const lista = porDia[dia.iso] ?? []}
              {@const elegido = diaActivo === dia.iso}
              <div role="gridcell" class="min-w-0 border-r border-slate-200 last:border-r-0 {dia.inMonth ? '' : 'bg-gray-50'}">
                <button
                  type="button"
                  data-dia={dia.iso}
                  tabindex={dia.iso === diaConTab ? 0 : -1}
                  aria-pressed={elegido}
                  aria-label={etiquetaDia(dia.iso, lista.length)}
                  aria-current={dia.isToday ? 'date' : undefined}
                  onclick={() => (seleccion = dia.iso)}
                  onkeydown={(e) => alPulsarTecla(e, dia.iso)}
                  class="flex min-h-[52px] w-full flex-col items-center gap-1 p-1 text-left transition-colors md:min-h-[108px] md:items-stretch md:p-2 {elegido
                    ? 'bg-indigo-50'
                    : 'hover:bg-gray-50'}"
                >
                  <span
                    class="inline-flex size-7 items-center justify-center rounded-full text-sm md:self-start {dia.isToday
                      ? 'bg-uta-blue font-bold text-white'
                      : dia.inMonth
                        ? 'font-semibold text-slate-900'
                        : 'font-medium text-slate-500'}"
                  >
                    {dia.dayOfMonth}
                  </span>

                  <!-- Móvil: puntos de estado (el texto está en el detalle del día) -->
                  {#if lista.length > 0}
                    <span class="flex items-center gap-0.5 md:hidden" aria-hidden="true">
                      {#each lista.slice(0, 3) as a (a.id_actividad)}
                        <span class="size-2 rounded-full {estiloMarca(a).punto}"></span>
                      {/each}
                    </span>
                  {/if}

                  <!-- Escritorio: una marca por actividad, con ícono y título -->
                  <span class="hidden min-w-0 flex-col gap-1 md:flex" aria-hidden="true">
                    {#each lista.slice(0, 2) as a (a.id_actividad)}
                      {@const m = estiloMarca(a)}
                      {@const estado = estadoDe(a)}
                      <span
                        class="flex min-w-0 items-start gap-1 rounded-[4px] border-l-[3px] px-1.5 py-1 text-[11px] font-semibold leading-tight {m.clases}"
                        title="{a.nombre} · {m.etiqueta}"
                      >
                        {#if estado === 'vencida'}
                          <AlertCircle class="mt-px h-3 w-3 shrink-0" />
                        {:else if estado === 'entregada'}
                          <CheckCircle2 class="mt-px h-3 w-3 shrink-0" />
                        {:else if estado === 'urgente'}
                          <Clock class="mt-px h-3 w-3 shrink-0" />
                        {/if}
                        <span class="line-clamp-2 break-words">{a.nombre}</span>
                      </span>
                    {/each}
                    {#if lista.length > 2}
                      <span class="px-1 text-[11px] font-bold text-uta-blue">+{lista.length - 2} más</span>
                    {/if}
                  </span>
                </button>
              </div>
            {/each}
          </div>
        {/each}
      </div>
    </div>

    <!-- Detalle del día -->
    <section class="mt-4" aria-labelledby="detalle-dia" aria-live="polite">
      {#if diaActivo}
        <h4 id="detalle-dia" class="mb-3 text-base font-semibold text-slate-900">
          {mayuscula(formatLargo(diaActivo))}
        </h4>
        {#if (porDia[diaActivo] ?? []).length > 0}
          {@render detalleDelDia(diaActivo)}
        {:else}
          <p class="m-0 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
            No hay fechas límite este día.
          </p>
        {/if}
      {:else}
        <h4 id="detalle-dia" class="sr-only">Detalle del día</h4>
        <p class="m-0 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
          No hay fechas límite en {MESES[month].toLowerCase()}. Usa las flechas para ver otro mes.
        </p>
      {/if}
    </section>
  {:else}
    <!-- Agenda: sólo los días del mes con fechas -->
    {#if diasConFechas.length === 0}
      <p class="m-0 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
        No hay fechas límite en {MESES[month].toLowerCase()}. Usa las flechas para ver otro mes.
      </p>
    {:else}
      <div class="flex flex-col gap-6">
        {#each diasConFechas as iso (iso)}
          <section aria-labelledby="agenda-{iso}">
            <h4 id="agenda-{iso}" class="mb-3 flex items-center gap-2 text-base font-semibold text-slate-900">
              {mayuscula(formatMedio(iso))}
              {#if iso === hoy}
                <span class="rounded-full bg-uta-blue px-2.5 py-0.5 text-xs font-bold normal-case text-white">Hoy</span>
              {/if}
            </h4>
            {@render detalleDelDia(iso)}
          </section>
        {/each}
      </div>
    {/if}
  {/if}

  <p class="sr-only" aria-live="polite">
    {fechasDelMes} {fechasDelMes === 1 ? 'fecha límite' : 'fechas límite'} en {MESES[month]} {year}.
  </p>
{/if}
