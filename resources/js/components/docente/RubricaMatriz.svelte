<script lang="ts">
  /**
   * La rúbrica en una tabla, en la dirección institucional UTA: criterios y su
   * peso en filas, niveles y su puntaje en columnas, como la ventana «Rúbrica»
   * de UTAmed anterior. El alumno ve la misma tabla en la guía dm
   * (RubricaAlumno.svelte).
   *
   * - Sólo lectura: «Ver rúbrica», la vista previa del editor y el detalle de
   *   una evaluación ya grabada (con `resultado`, el nivel asignado marcado).
   * - Evaluando (`onElegir`): cada celda es un botón; la columna «Puntos» y el
   *   total se llenan al elegir.
   */
  import type { Snippet } from 'svelte';
  import { Check } from 'lucide-svelte';
  import type { Rubrica } from '@/types/rubrica';
  import { etiqueta, tarjeta, titulo as claseTitulo } from '@/lib/inst';
  import { puntajeMinimoAprobacion } from '@/lib/notas';
  import { fmtPuntos, matrizDe, opacidadBarra, pts } from '@/lib/rubricaMatriz';

  interface Props {
    rubrica: Rubrica | null | undefined;
    /** criterio → escala marcada: la evaluación grabada o la que se está eligiendo. */
    resultado?: Record<string, string> | null;
    /** Con él, cada celda es un botón para elegir el nivel de ese criterio. */
    onElegir?: (criterioId: string, escalaId: string) => void;
    /** Columna «Puntos» y total al pie. Por omisión, cuando se evalúa o hay niveles marcados. */
    conPuntos?: boolean;
    /** Sumativa: el pie explica el corte del 4,0. Formativa: la escala cualitativa, si la trae. */
    esSumativa?: boolean;
    exigencia?: number;
    /** Lo que va en el pie junto al total; si no, una línea que explica cómo se suma. */
    pie?: Snippet;
  }

  let { rubrica, resultado = null, onElegir, conPuntos, esSumativa = true, exigencia = 60, pie }: Props = $props();

  const m = $derived(matrizDe(rubrica, resultado));
  const evaluando = $derived(!!onElegir);
  const puntos = $derived(conPuntos ?? (evaluando || m.marcados > 0));
  const n = $derived(m.columnas.length);
  const escala = $derived(rubrica?.detalles_evaluacion?.escala_evaluacion ?? []);

  const ANCHO_CRITERIO = 210;
  const ANCHO_PUNTOS = 84;

  const textoPie = $derived.by(() => {
    if (!esSumativa) {
      const cortes = escala.filter((e) => e.evaluacion).map((e) => `${e.evaluacion} desde ${pts(Number(e.puntaje_minimo))}`);
      return `Un nivel por criterio; los puntos se suman${cortes.length ? `: ${cortes.join(' · ')}` : '.'}`;
    }
    const corte = `nota 4,0 con ${pts(puntajeMinimoAprobacion(m.maximo, exigencia))}`;
    if (puntos && m.marcados > 0) return `Suma de los niveles ${evaluando ? 'elegidos' : 'asignados'} · ${corte}`;
    return `Un nivel por criterio. Los puntos de los niveles elegidos se suman: ${pts(m.maximo)} como máximo, ${corte}.`;
  });

  const barra = (intensidad: number) => (intensidad > 0 ? 'bg-inst-celeste' : 'bg-inst-control');
</script>

{#if m.criterios.length === 0}
  <p class="m-0 rounded-lg border border-dashed border-inst-linea p-6 text-center text-inst-ink-2">Esta rúbrica no tiene criterios.</p>
{:else}
  <!-- shrink-0: con overflow, en una columna flex de alto fijo la tabla se
       encogería y quedaría con scroll propio, cortando filas y el total. -->
  <div class="{tarjeta} shrink-0 overflow-x-auto">
    <table
      class="w-full table-fixed border-separate border-spacing-0 text-[13px] leading-[19px]"
      style="min-width: {ANCHO_CRITERIO + n * (evaluando ? 124 : 112) + (puntos ? ANCHO_PUNTOS : 0)}px"
    >
      <caption class="sr-only">
        Rúbrica: criterios en filas y niveles en columnas.{evaluando ? ' Elige un nivel en cada fila.' : ''}
      </caption>
      <thead>
        <tr>
          <th scope="col" class="{etiqueta} border-b border-inst-linea bg-inst-cabecera px-3.5 py-2.5 text-left align-bottom text-inst-ink-2" style="width: {ANCHO_CRITERIO}px">
            Criterio y peso
          </th>
          {#each m.columnas as col, i (i)}
            <th scope="col" class="border-b border-l border-inst-linea bg-inst-cabecera p-0 align-top">
              <span aria-hidden="true" class="block h-1 {barra(col.intensidad)}" style="opacity: {opacidadBarra(col.intensidad)}"></span>
              <span class="flex flex-col items-center gap-1.5 px-2 pt-2.5 pb-3 text-center">
                <span class="text-sm leading-[18px] font-extrabold break-words text-inst-ink">{col.nombre}</span>
                {#if col.puntos !== null}
                  <span class="rounded border border-inst-linea bg-inst-tarjeta px-2.5 text-[13px] leading-[18px] font-extrabold text-inst-ink tabular-nums">
                    {pts(col.puntos)}
                  </span>
                {/if}
              </span>
            </th>
          {/each}
          {#if puntos}
            <th scope="col" class="{etiqueta} border-b border-l border-inst-linea bg-inst-cabecera px-2 py-2.5 text-center align-bottom text-inst-ink-2" style="width: {ANCHO_PUNTOS}px">
              Puntos
            </th>
          {/if}
        </tr>
      </thead>
      <tbody>
        {#each m.criterios as c (c.id)}
          <tr>
            <th scope="row" class="border-b border-inst-linea bg-inst-sutil px-3.5 py-3 text-left align-top font-normal">
              <span class="block text-sm leading-5 font-extrabold break-words text-inst-ink">{c.nombre}</span>
              {#if c.descripcion}
                <span class="mt-1 block text-[13px] leading-[18px] break-words whitespace-pre-line text-inst-ink-2">{c.descripcion}</span>
              {/if}
              <span class="mt-2 inline-flex rounded bg-inst-accion-s px-2 py-0.5 text-[13px] leading-[18px] font-extrabold text-inst-accion-t">
                {c.peso ?? `máx. ${pts(c.maximo)}`}
              </span>
            </th>
            {#each c.celdas as d, j (j)}
              {#if !d}
                <td class="border-b border-l border-inst-linea bg-inst-sutil"></td>
              {:else if evaluando}
                <td class="h-px border-b border-l border-inst-linea p-1.5 align-top">
                  <button
                    type="button"
                    aria-pressed={d.marcada}
                    onclick={() => onElegir?.(c.id, d.id)}
                    class="flex h-full min-h-22 w-full cursor-pointer flex-col items-start gap-1.5 rounded-md border px-3 py-2.5 text-left text-[13px] leading-[19px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-inst-foco {d.marcada
                      ? 'border-2 border-inst-accion bg-inst-accion-s px-[11px] py-[9px] font-semibold text-inst-ink'
                      : 'border-inst-linea bg-inst-tarjeta text-inst-ink-2 hover:border-inst-control hover:bg-inst-sutil'}"
                  >
                    <span class="sr-only">{m.columnas[j].nombre}, {pts(d.puntos)}:</span>
                    {#if d.marcada}
                      <span class="inline-flex items-center gap-1 rounded bg-inst-accion px-2 text-[13px] leading-[18px] font-extrabold text-inst-sobre-accion">
                        <Check size={13} strokeWidth={3} aria-hidden="true" />Elegido
                      </span>
                    {/if}
                    {#if !m.puntajePorColumna}
                      <span aria-hidden="true" class="text-[13px] font-bold text-inst-ink tabular-nums">{pts(d.puntos)}</span>
                    {/if}
                    <span class="break-words whitespace-pre-line">{d.texto}</span>
                  </button>
                </td>
              {:else}
                <td
                  class="border-b border-l border-inst-linea px-3.5 py-3 align-top {d.marcada
                    ? 'bg-inst-accion-s font-semibold text-inst-ink shadow-[inset_0_0_0_2px_var(--color-inst-accion)]'
                    : 'text-inst-ink-2'}"
                >
                  {#if d.marcada}
                    <span class="mb-1.5 inline-flex items-center gap-1 rounded bg-inst-accion px-2 text-[13px] leading-[18px] font-extrabold text-inst-sobre-accion">
                      <Check size={13} strokeWidth={3} aria-hidden="true" />Asignado
                    </span>
                  {/if}
                  {#if !m.puntajePorColumna}
                    <span class="mb-1 block text-[13px] font-bold text-inst-ink tabular-nums">{pts(d.puntos)}</span>
                  {/if}
                  <span class="block break-words whitespace-pre-line">{d.texto}</span>
                </td>
              {/if}
            {/each}
            {#if puntos}
              <td class="border-b border-l border-inst-linea px-2 py-3 text-center align-middle">
                <span class="{claseTitulo} block text-2xl leading-7 text-inst-ink">{c.obtenido !== null ? fmtPuntos(c.obtenido) : '—'}</span>
                <span class="block text-[13px] leading-[18px] text-inst-ink-2">de {fmtPuntos(c.maximo)}</span>
              </td>
            {/if}
          </tr>
        {/each}
      </tbody>
      <tfoot>
        <tr>
          <th scope="row" class="bg-inst-cabecera px-3.5 py-3 text-left text-sm leading-5 font-extrabold text-inst-ink">Total</th>
          <td colspan={n} class="border-l border-inst-linea bg-inst-cabecera px-3.5 py-3 align-middle text-inst-ink-2">
            {#if pie}{@render pie()}{:else}{textoPie}{/if}
          </td>
          {#if puntos}
            <td class="border-l border-inst-linea bg-inst-cabecera px-2 py-3 text-center align-middle">
              <span class="{claseTitulo} block text-2xl leading-7 text-inst-ink">{fmtPuntos(m.obtenido)}</span>
              <span class="block text-[13px] leading-[18px] text-inst-ink-2">de {fmtPuntos(m.maximo)}</span>
            </td>
          {/if}
        </tr>
      </tfoot>
    </table>
  </div>
{/if}
