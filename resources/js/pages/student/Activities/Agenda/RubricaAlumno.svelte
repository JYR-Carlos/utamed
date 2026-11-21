<script lang="ts">
  /**
   * Pestaña «Rúbrica» del alumno (guía dm): la rúbrica entera en una tabla,
   * como la ventana «Rúbrica» de UTAmed anterior. Criterios y su peso en
   * filas; niveles y su puntaje en columnas. Evaluada, el nivel que asignó el
   * docente va en amarillo, cada criterio dice sus puntos y el pie, el total.
   * Antes de la evaluación es la misma tabla sin marcas: así se va a evaluar.
   *
   * Donde la tabla no cabe (teléfono, ventana angosta) cada criterio es una
   * fila de casillas con los puntos de cada nivel; la casilla tocada muestra
   * su descripción abajo. «Pantalla completa» abre la tabla en una ventana
   * ancha.
   */
  import { Check, Info, Maximize2 } from 'lucide-svelte';
  import type { Rubrica } from '@/types/rubrica';
  import ModalAlumno from '@/components/student/dm/ModalAlumno.svelte';
  import { btnSuave, etiqueta, pildora, tono } from '@/lib/dm';
  import { puntajeMinimoAprobacion } from '@/lib/notas';
  import { fmtPuntos, matrizDe, opacidadBarra, pts } from '@/lib/rubricaMatriz';
  import { formatNota } from '@/utils/formatters';

  interface Props {
    rubrica: Rubrica | null | undefined;
    /** criterio → nivel marcado. Sin él, la rúbrica se lee «antes» de evaluar. */
    resultado?: Record<string, string> | null;
    puntajeObtenido?: number | string | null;
    retroalimentacion?: string | null;
    /** «Paula Fuentes · lun 19 oct», junto al título de una rúbrica evaluada. */
    subtitulo?: string | null;
    /** Nota de esta evaluación; null cuando no se conoce (una evaluación anterior abierta desde Consultas). */
    nota?: number | string | null;
    /** % del puntaje que da el 4,0. */
    exigencia?: number;
    /** Nombre de la actividad y del ramo, para la ventana de pantalla completa. */
    actividad?: string;
    curso?: string;
    /** Variables --curso* (estiloCurso): la ventana va en un portal fuera de la página. */
    estiloCurso?: string;
  }

  let {
    rubrica,
    resultado = null,
    puntajeObtenido = null,
    retroalimentacion = null,
    subtitulo = null,
    nota = null,
    exigencia = 60,
    actividad = '',
    curso = '',
    estiloCurso = '',
  }: Props = $props();

  const m = $derived(matrizDe(rubrica, resultado));
  const evaluada = $derived(m.marcados > 0);
  // El puntaje que se guardó con la evaluación manda; si no vino, la suma de la tabla.
  const obtenido = $derived.by(() => {
    const n = Number(puntajeObtenido);
    return puntajeObtenido !== null && puntajeObtenido !== '' && Number.isFinite(n) ? n : m.obtenido;
  });
  const notaNum = $derived.by(() => {
    const n = Number(nota);
    return nota !== null && nota !== '' && Number.isFinite(n) ? n : null;
  });
  const paraCuatro = $derived(puntajeMinimoAprobacion(m.maximo, exigencia));

  // Hasta 5 niveles la tabla entra desde 768 px de ancho; con 6 o 7, desde 896.
  // Los dos juegos de clases se escriben enteros para que Tailwind los genere.
  const VISTAS = {
    normal: { tabla: 'hidden @3xl:block', filas: '@3xl:hidden' },
    ancha: { tabla: 'hidden @4xl:block', filas: '@4xl:hidden' },
  } as const;
  const vista = $derived(VISTAS[m.columnas.length <= 5 ? 'normal' : 'ancha']);

  const resumen = $derived(
    evaluada
      ? [
          ...(notaNum !== null
            ? [{ dato: 'Tu nota', valor: formatNota(notaNum), unidad: '', chip: notaNum >= 4 ? { texto: 'Aprobada', tono: tono.ok } : { texto: 'Reprobada', tono: tono.error } }]
            : []),
          { dato: 'Tu puntaje', valor: fmtPuntos(obtenido), unidad: `de ${fmtPuntos(m.maximo)} pts`, chip: null },
          { dato: 'Para el 4,0', valor: fmtPuntos(paraCuatro), unidad: `pts · ${exigencia} %`, chip: null },
          {
            dato: 'Criterios',
            valor: String(m.marcados),
            unidad: m.marcados === m.criterios.length ? 'evaluados' : `de ${m.criterios.length} evaluados`,
            chip: null,
          },
        ]
      : [
          { dato: 'Puntaje máximo', valor: fmtPuntos(m.maximo), unidad: 'pts', chip: null },
          { dato: 'Para el 4,0', valor: fmtPuntos(paraCuatro), unidad: `pts · ${exigencia} %`, chip: null },
          { dato: 'Criterios', valor: String(m.criterios.length), unidad: '', chip: null },
          { dato: 'Niveles', valor: String(m.columnas.length), unidad: 'por criterio', chip: null },
        ],
  );

  const pie = $derived(
    evaluada
      ? `Suma de los niveles asignados${notaNum !== null ? ` · nota ${formatNota(notaNum)}` : ''}`
      : `Puntaje máximo ${pts(m.maximo)} · el 4,0 se alcanza con ${pts(paraCuatro)} (${exigencia} %)`,
  );

  // ── Vista en filas: qué casilla se está leyendo ───────────────────────────
  let leida = $state<{ c: number; j: number } | null>(null);
  const porDefecto = $derived.by(() => {
    const j = m.criterios[0]?.celdas.findIndex((d) => d?.marcada) ?? -1;
    return { c: 0, j: Math.max(0, j) };
  });
  const actual = $derived(leida && m.criterios[leida.c]?.celdas[leida.j] ? leida : porDefecto);
  const criterioLeido = $derived(m.criterios[actual.c]);
  const celdaLeida = $derived(criterioLeido?.celdas[actual.j] ?? null);
  const asignadaEnLeido = $derived.by(() => {
    const j = criterioLeido?.celdas.findIndex((d) => d?.marcada) ?? -1;
    return j >= 0 ? { nombre: m.columnas[j].nombre, puntos: criterioLeido.celdas[j]!.puntos } : null;
  });

  let ampliada = $state(false);

  const barra = (intensidad: number) => (intensidad > 0 ? 'bg-(--curso)' : 'bg-dm-control');
</script>

{#snippet tabla(grande: boolean)}
  {@const anchoCriterio = grande ? 220 : 176}
  {@const anchoPuntos = grande ? 96 : 84}
  <!-- shrink-0: dentro de la pantalla completa (columna flex con scroll) la
       tabla se encogería y cortaría las últimas filas. -->
  <div class="shrink-0 overflow-x-auto rounded-xl border border-dm-linea bg-dm-tarjeta">
    <table
      class="w-full table-fixed border-separate border-spacing-0 {grande ? 'text-sm leading-[21px]' : 'text-[13px] leading-[19px]'}"
      style="min-width: {anchoCriterio + m.columnas.length * (grande ? 120 : 100) + (evaluada ? anchoPuntos : 0)}px"
    >
      <caption class="sr-only">
        Rúbrica: criterios en filas y niveles en columnas{evaluada ? '. «Asignado» marca el nivel que asignó tu docente.' : '.'}
      </caption>
      <thead>
        <tr>
          <th
            scope="col"
            class="{etiqueta} border-b border-dm-linea bg-dm-sutil px-4 py-3 text-left align-bottom text-dm-ink-3"
            style="width: {anchoCriterio}px"
          >
            Criterio y peso
          </th>
          {#each m.columnas as col, i (i)}
            <th scope="col" class="border-b border-l border-dm-linea bg-dm-sutil p-0 align-top">
              <span aria-hidden="true" class="block h-1 {barra(col.intensidad)}" style="opacity: {opacidadBarra(col.intensidad)}"></span>
              <span class="flex flex-col items-center gap-1.5 px-2 pt-2.5 pb-3 text-center">
                <span class="text-sm leading-[18px] font-extrabold break-words text-dm-ink">{col.nombre}</span>
                {#if col.puntos !== null}
                  <span class="rounded-full border border-dm-linea bg-dm-tarjeta px-2.5 text-[13px] leading-[18px] font-extrabold text-dm-ink tabular-nums">
                    {pts(col.puntos)}
                  </span>
                {/if}
              </span>
            </th>
          {/each}
          {#if evaluada}
            <th
              scope="col"
              class="{etiqueta} border-b border-l border-dm-linea bg-dm-sutil px-2 py-3 text-center align-bottom text-dm-ink-3"
              style="width: {anchoPuntos}px"
            >
              Puntos
            </th>
          {/if}
        </tr>
      </thead>
      <tbody>
        {#each m.criterios as c (c.id)}
          <tr>
            <th scope="row" class="border-b border-dm-linea bg-dm-sutil px-4 py-3.5 text-left align-top font-normal">
              <span class="block text-[15px] leading-5 font-bold break-words text-dm-ink">{c.nombre}</span>
              {#if c.descripcion}
                <span class="mt-1 block text-[13px] leading-[18px] break-words text-dm-ink-2">{c.descripcion}</span>
              {/if}
              <span class="{pildora} {tono.accion} mt-2 font-extrabold">{c.peso ?? `máx. ${pts(c.maximo)}`}</span>
            </th>
            {#each c.celdas as d, j (j)}
              <td
                class="border-b border-l border-dm-linea px-3.5 py-3 align-top {d?.marcada
                  ? 'bg-dm-activo-suave font-semibold text-dm-ink shadow-[inset_0_0_0_2px_var(--color-dm-amarillo)]'
                  : 'text-dm-ink-2'}"
              >
                {#if d}
                  {#if d.marcada}
                    <span class="mb-1.5 inline-flex items-center gap-1 rounded-full bg-dm-amarillo px-2 text-[13px] leading-[18px] font-extrabold text-dm-sobre-amarillo">
                      <Check size={13} strokeWidth={3} aria-hidden="true" />Asignado
                    </span>
                  {/if}
                  {#if !m.puntajePorColumna}
                    <span class="mb-1 block text-[13px] leading-[18px] font-bold text-dm-ink tabular-nums">{pts(d.puntos)}</span>
                  {/if}
                  <span class="block break-words whitespace-pre-line">{d.texto}</span>
                {/if}
              </td>
            {/each}
            {#if evaluada}
              <td class="border-b border-l border-dm-linea px-2 py-3 text-center align-middle">
                <span class="block font-dm-cond text-[28px] leading-7 font-black text-dm-ink tabular-nums">
                  {c.obtenido !== null ? fmtPuntos(c.obtenido) : '—'}
                </span>
                <span class="block text-[13px] leading-[18px] text-dm-ink-2">de {fmtPuntos(c.maximo)}</span>
              </td>
            {/if}
          </tr>
        {/each}
      </tbody>
      <tfoot>
        <tr>
          <th scope="row" class="bg-dm-sutil px-4 py-3 text-left text-[15px] leading-5 font-bold text-dm-ink">Total</th>
          <td colspan={m.columnas.length} class="border-l border-dm-linea bg-dm-sutil px-4 py-3 align-middle text-sm leading-5 text-dm-ink-2">
            {pie}
          </td>
          {#if evaluada}
            <td class="border-l border-dm-linea bg-dm-sutil px-2 py-3 text-center align-middle">
              <span class="block font-dm-cond text-[28px] leading-7 font-black text-dm-ink tabular-nums">{fmtPuntos(obtenido)}</span>
              <span class="block text-[13px] leading-[18px] text-dm-ink-2">de {fmtPuntos(m.maximo)}</span>
            </td>
          {/if}
        </tr>
      </tfoot>
    </table>
  </div>
{/snippet}

<section aria-labelledby="t-rubrica" class="@container flex flex-col gap-4">
  <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
    <div class="flex min-w-0 flex-col gap-1">
      <h2 id="t-rubrica" class="text-xl leading-7 font-bold lg:text-[22px]">
        {evaluada ? 'Cómo te evaluaron, criterio por criterio' : 'Cómo se evalúa esta actividad'}
      </h2>
      {#if evaluada && subtitulo}<span class="text-sm leading-5 text-dm-ink-2">{subtitulo}</span>{/if}
    </div>
    {#if m.criterios.length > 0}
      <span class={vista.tabla}>
        <button type="button" class={btnSuave} aria-haspopup="dialog" onclick={() => (ampliada = true)}>
          <Maximize2 size={18} aria-hidden="true" />Pantalla completa
        </button>
      </span>
    {/if}
  </div>

  {#if m.criterios.length === 0}
    <p class="py-6 text-sm leading-5 text-dm-ink-2">Esta actividad todavía no tiene una rúbrica publicada.</p>
  {:else}
    <dl class="grid grid-cols-2 gap-2 @3xl:grid-cols-[repeat(var(--n),minmax(0,1fr))]" style="--n: {resumen.length}">
      {#each resumen as t (t.dato)}
        <div class="flex flex-col gap-1 rounded-xl border border-dm-linea bg-dm-sutil px-3.5 py-2.5">
          <dt class="{etiqueta} text-dm-ink-3">{t.dato}</dt>
          <dd class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
            <span class="font-dm-cond text-[28px] leading-none font-black tabular-nums lg:text-[30px]">{t.valor}</span>
            {#if t.unidad}<span class="text-[13px] leading-[18px] text-dm-ink-2">{t.unidad}</span>{/if}
            {#if t.chip}<span class="{pildora} {t.chip.tono} self-center font-bold">{t.chip.texto}</span>{/if}
          </dd>
        </div>
      {/each}
    </dl>

    {#if evaluada && retroalimentacion}
      <blockquote class="m-0 rounded-r-[10px] border-l-4 border-dm-amarillo bg-dm-sutil px-4 py-3.5">
        <span class="{etiqueta} mb-1 block text-dm-ink-3">Retroalimentación de tu docente</span>
        <p class="max-w-[72ch] text-[15px] leading-6 break-words whitespace-pre-line">{retroalimentacion}</p>
      </blockquote>
    {/if}

    <!-- Pantalla ancha: la tabla. -->
    <div class={vista.tabla}>
      {@render tabla(false)}
    </div>

    <!-- Pantalla angosta: una fila de casillas por criterio. -->
    <section aria-labelledby="t-rubrica-filas" class="flex flex-col gap-3 rounded-xl border border-dm-linea bg-dm-tarjeta px-3.5 py-4 {vista.filas}">
      <div class="flex flex-col gap-0.5">
        <h3 id="t-rubrica-filas" class="text-[17px] leading-[22px] font-bold">La rúbrica en una vista</h3>
        <p class="text-sm leading-5 text-dm-ink-2">
          Cada fila es un criterio y cada casilla un nivel, con sus puntos.{evaluada ? ' En amarillo, el que te asignó tu docente.' : ''} Toca
          una casilla para leer qué pide.
        </p>
      </div>
      <div aria-hidden="true" class="flex gap-1.5">
        {#each m.columnas as col, i (i)}
          <span class="flex min-w-0 flex-1 flex-col gap-1">
            <span class="h-1 rounded-sm {barra(col.intensidad)}" style="opacity: {opacidadBarra(col.intensidad)}"></span>
            <span class="truncate text-center text-[13px] leading-4 font-bold text-dm-ink-3">{col.nombre}</span>
          </span>
        {/each}
      </div>
      <ul class="m-0 flex list-none flex-col gap-3.5 p-0">
        {#each m.criterios as c, i (c.id)}
          <li class="flex flex-col gap-1.5">
            <span id="rub-fila-{i}" class="flex items-baseline justify-between gap-2">
              <span class="text-sm leading-[19px] font-bold">{c.nombre}</span>
              <span class="shrink-0 text-[13px] leading-[18px] font-extrabold text-dm-accion-texto">{c.peso ?? `máx. ${pts(c.maximo)}`}</span>
            </span>
            <span role="group" aria-labelledby="rub-fila-{i}" class="flex gap-1.5">
              {#each c.celdas as d, j (j)}
                {#if d}
                  {@const leyendo = actual.c === i && actual.j === j}
                  <button
                    type="button"
                    aria-pressed={leyendo}
                    aria-label="{m.columnas[j].nombre}, {pts(d.puntos)}{d.marcada ? ', asignado por tu docente' : ''}"
                    onclick={() => (leida = { c: i, j })}
                    class="flex h-11 min-w-0 flex-1 cursor-pointer items-center justify-center gap-1 rounded-lg text-base leading-5 tabular-nums focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-dm-enlace {d.marcada
                      ? 'border-2 border-dm-amarillo bg-dm-amarillo font-extrabold text-dm-sobre-amarillo'
                      : leyendo
                        ? 'border-2 border-dm-accion-texto bg-dm-accion-suave font-extrabold text-dm-ink'
                        : 'border border-dm-linea bg-dm-tarjeta font-semibold text-dm-ink-2'} {d.marcada && leyendo
                      ? 'outline-2 outline-offset-2 outline-dm-accion-texto'
                      : ''}"
                  >
                    {#if d.marcada}<Check size={14} strokeWidth={3} aria-hidden="true" />{/if}{fmtPuntos(d.puntos)}
                  </button>
                {:else}
                  <span aria-hidden="true" class="h-11 min-w-0 flex-1 rounded-lg border border-dashed border-dm-linea"></span>
                {/if}
              {/each}
            </span>
          </li>
        {/each}
      </ul>
      {#if criterioLeido && celdaLeida}
        <div aria-live="polite" class="flex flex-col gap-2 rounded-[10px] border border-dm-linea bg-dm-sutil p-3.5">
          <span class="text-[13px] leading-[18px] font-bold text-dm-ink-3">{criterioLeido.nombre}</span>
          <span class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
            <span class="text-[17px] leading-[22px] font-extrabold">{m.columnas[actual.j].nombre}</span>
            <span class="text-sm leading-5 text-dm-ink-2">{pts(celdaLeida.puntos)}</span>
            {#if celdaLeida.marcada}
              <span class="inline-flex items-center gap-1 rounded-full bg-dm-amarillo px-2 text-[13px] leading-[18px] font-extrabold text-dm-sobre-amarillo">
                <Check size={13} strokeWidth={3} aria-hidden="true" />Asignado por tu docente
              </span>
            {/if}
          </span>
          <p class="text-[15px] leading-[23px] break-words whitespace-pre-line">{celdaLeida.texto || 'Este nivel no tiene descripción.'}</p>
          {#if !celdaLeida.marcada && asignadaEnLeido}
            <span class="text-sm leading-5 text-dm-ink-2">
              Tu docente te asignó <strong class="font-bold text-dm-ink">{asignadaEnLeido.nombre} ({pts(asignadaEnLeido.puntos)})</strong> en este criterio.
            </span>
          {/if}
        </div>
      {/if}
      <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 border-t border-dm-linea pt-2.5">
        <span class="text-[15px] leading-5 font-bold">Total</span>
        <span class="text-sm leading-5 text-dm-ink-2">
          {#if evaluada}
            <strong class="text-[17px] font-extrabold text-dm-ink">{fmtPuntos(obtenido)} de {pts(m.maximo)}</strong>
          {:else}
            Máximo <strong class="font-extrabold text-dm-ink">{pts(m.maximo)}</strong>
          {/if}
          · el 4,0 con {fmtPuntos(paraCuatro)}
        </span>
      </div>
    </section>

    <p class="flex items-start gap-2.5 rounded-[10px] bg-dm-info-s px-3.5 py-3 text-sm leading-5">
      <Info size={18} aria-hidden="true" class="mt-px shrink-0 text-dm-info" />
      <span>
        {evaluada
          ? `En amarillo, el nivel que asignó tu docente en cada criterio. Los puntos de esos niveles se suman y dan tu nota: el 4,0 se alcanza con ${fmtPuntos(paraCuatro)} de ${pts(m.maximo)}.`
          : `Tu docente marcará un nivel por criterio. Los puntos de los niveles marcados se suman y dan tu nota: el 4,0 se alcanza con ${fmtPuntos(paraCuatro)} de ${pts(m.maximo)}.`}
      </span>
    </p>
  {/if}
</section>

<ModalAlumno
  bind:open={ampliada}
  variante="completa"
  etiqueta={curso ? `Rúbrica · ${curso}` : 'Rúbrica'}
  titulo={actividad || 'Rúbrica'}
  subtitulo={evaluada && subtitulo ? subtitulo : ''}
  estilo={estiloCurso}
  rotuloCerrar="Cerrar la pantalla completa"
>
  <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4 lg:p-6">
    {#if evaluada}
      <p class="flex flex-wrap items-center gap-2">
        <span class="rounded-full border border-dm-linea bg-dm-sutil px-3 py-1 text-sm leading-5">
          Tu puntaje <strong class="font-extrabold">{fmtPuntos(obtenido)} de {pts(m.maximo)}</strong>
        </span>
        {#if notaNum !== null}
          <span class="rounded-full px-3 py-1 text-sm leading-5 font-bold {notaNum >= 4 ? tono.ok : tono.error}">Nota {formatNota(notaNum)}</span>
        {/if}
      </p>
    {/if}
    {@render tabla(true)}
  </div>
</ModalAlumno>
