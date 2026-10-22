<script lang="ts">
  /**
   * Cuerpo del panel «Rúbrica» del alumno (guía dm): el resumen de puntajes,
   * la retroalimentación y, por criterio, sus niveles con el asignado
   * destacado. Antes de la evaluación es la misma rúbrica sin marcas: así se
   * va a evaluar. La del docente (y el editor) sigue en Rubrica.svelte.
   */
  import { Check, Info } from 'lucide-svelte';
  import type { Rubrica } from '@/types/rubrica';
  import { etiqueta, tarjeta } from '@/lib/dm';
  import { puntajeMinimoAprobacion } from '@/lib/notas';
  import { puntajeTotal } from '@/lib/rubricaAlumno';

  interface Props {
    rubrica: Rubrica | null | undefined;
    /** criterio → nivel marcado. Sin él, la rúbrica se lee «antes» de evaluar. */
    resultado?: Record<string, string> | null;
    puntajeObtenido?: number | null;
    retroalimentacion?: string | null;
    evaluador?: string | null;
    /** % del puntaje que da el 4,0. */
    exigencia?: number;
  }

  let { rubrica, resultado = null, puntajeObtenido = null, retroalimentacion = null, evaluador = null, exigencia = 60 }: Props = $props();

  const num = (n: number) => n.toLocaleString('es-CL', { maximumFractionDigits: 2 });

  const criterios = $derived(
    (rubrica?.niveles ?? []).map((c) => {
      const elegida = resultado?.[String(c.id)];
      const niveles = c.escalas.map((e, i) => ({
        id: String(e.id),
        nombre: rubrica?.columnas?.[i]?.nombre?.trim() || `Nivel ${i + 1}`,
        puntos: Number(e.puntos),
        texto: e.criterio,
        marcado: elegida != null && String(e.id) === String(elegida),
      }));
      const ponderacion = Number(c.ponderacion);
      return {
        id: String(c.id),
        nombre: c.nombre,
        descripcion: c.descripcion,
        peso: ponderacion > 0 ? `${num(ponderacion)} %` : null,
        maximo: Number(c.puntaje_total) || Math.max(0, ...niveles.map((n) => n.puntos)),
        niveles,
        obtenido: niveles.find((n) => n.marcado)?.puntos ?? null,
      };
    }),
  );

  const evaluada = $derived(criterios.some((c) => c.obtenido !== null));
  const total = $derived(puntajeTotal(rubrica));
  const paraCuatro = $derived(puntajeMinimoAprobacion(total, exigencia));
  const evaluados = $derived(criterios.filter((c) => c.obtenido !== null).length);

  const resumen = $derived(
    evaluada
      ? [
          { dato: 'Tu puntaje', valor: num(puntajeObtenido ?? criterios.reduce((s, c) => s + (c.obtenido ?? 0), 0)), unidad: `de ${num(total)} pts` },
          { dato: 'Para el 4,0', valor: num(paraCuatro), unidad: `pts · ${exigencia} %` },
          {
            dato: 'Criterios',
            valor: String(evaluados),
            unidad: evaluados === criterios.length ? 'evaluados' : `de ${criterios.length} evaluados`,
          },
        ]
      : [
          { dato: 'Puntaje máximo', valor: num(total), unidad: 'pts' },
          { dato: 'Para el 4,0', valor: num(paraCuatro), unidad: `pts · ${exigencia} %` },
          { dato: 'Criterios', valor: String(criterios.length), unidad: '' },
        ],
  );

  // Con muchas columnas (hay rúbricas de 7 niveles) los niveles bajan de línea.
  const columnasNiveles = (n: number) =>
    n <= 5
      ? 'sm:grid-cols-2 lg:grid-cols-[repeat(var(--niveles),minmax(0,1fr))]'
      : 'sm:grid-cols-2 lg:grid-cols-[repeat(auto-fill,minmax(150px,1fr))]';
</script>

<div class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto bg-dm-pagina px-4 pt-5 pb-8 lg:px-7">
  {#if criterios.length === 0}
    <p class="py-10 text-center text-sm leading-5 text-dm-ink-2">Esta actividad todavía no tiene una rúbrica publicada.</p>
  {:else}
    <dl class="grid grid-cols-3 gap-2 lg:gap-3">
      {#each resumen as t (t.dato)}
        <div class="{tarjeta} flex flex-col gap-1 px-3 py-2.5 lg:px-4 lg:py-3.5">
          <dt class="{etiqueta} text-dm-ink-3">{t.dato}</dt>
          <dd class="flex flex-wrap items-baseline gap-1">
            <span class="font-dm-cond text-[26px] leading-none font-black tabular-nums lg:text-[32px]">{t.valor}</span>
            {#if t.unidad}<span class="text-[13px] leading-[18px] text-dm-ink-2">{t.unidad}</span>{/if}
          </dd>
        </div>
      {/each}
    </dl>

    {#if evaluada && retroalimentacion}
      <section aria-labelledby="rubrica-retro" class="flex flex-col gap-2">
        <h3 id="rubrica-retro" class="{etiqueta} text-dm-ink-3">Retroalimentación{evaluador ? ` de ${evaluador}` : ''}</h3>
        <p class="rounded-r-[10px] border-l-4 border-dm-amarillo bg-dm-tarjeta px-4 py-3 text-[15px] leading-6 break-words whitespace-pre-line">
          {retroalimentacion}
        </p>
      </section>
    {/if}

    {#if !evaluada}
      <p class="flex items-start gap-2.5 rounded-[10px] bg-dm-info-s px-3.5 py-3 text-sm leading-5">
        <Info size={18} aria-hidden="true" class="mt-px shrink-0 text-dm-info" />
        <span>Tu docente marcará un nivel por criterio. Los puntajes de los niveles se suman y dan tu nota.</span>
      </p>
    {/if}

    {#each criterios as c (c.id)}
      <section aria-labelledby="criterio-{c.id}" class="{tarjeta} flex flex-col gap-3 px-4 py-4 lg:px-4.5">
        <div class="flex items-start gap-3">
          <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <h3 id="criterio-{c.id}" class="text-[17px] leading-[22px] font-bold">{c.nombre}</h3>
            {#if c.descripcion}<span class="text-sm leading-5 text-dm-ink-2">{c.descripcion}</span>{/if}
            <span class="text-[13px] leading-[18px] text-dm-ink-3">
              {c.peso ? `${c.peso} de la nota · ` : ''}máx. {num(c.maximo)} pts
            </span>
          </div>
          {#if c.obtenido !== null}
            <span class="flex shrink-0 flex-col items-end">
              <strong class="font-dm-cond text-[28px] leading-7 font-black tabular-nums">{num(c.obtenido)}</strong>
              <span class="text-[13px] leading-[18px] text-dm-ink-2">de {num(c.maximo)} pts</span>
            </span>
          {/if}
        </div>
        <ol
          aria-label="Niveles de {c.nombre}"
          style="--niveles: {c.niveles.length}"
          class="grid grid-cols-1 gap-2 {columnasNiveles(c.niveles.length)}"
        >
          {#each c.niveles as n (n.id)}
            <li
              aria-current={n.marcado ? 'true' : undefined}
              class="flex flex-col gap-1 rounded-[10px] px-3 py-2.5 {n.marcado
                ? 'border-2 border-dm-amarillo bg-dm-activo-suave'
                : 'border border-dm-linea bg-dm-tarjeta'}"
            >
              <span class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 lg:flex-col lg:gap-0.5">
                <span class="text-sm leading-5 font-bold {n.marcado ? 'text-dm-activo-texto' : ''}">{n.nombre}</span>
                <span class="text-[13px] leading-5 font-semibold text-dm-ink-2 tabular-nums">{num(n.puntos)} pts</span>
              </span>
              {#if n.texto}<span class="text-[13px] leading-[18px] text-dm-ink-2">{n.texto}</span>{/if}
              {#if n.marcado}
                <span class="mt-0.5 inline-flex items-center gap-1 self-start rounded-full bg-dm-amarillo px-2 text-xs leading-5 font-extrabold text-[#161949]">
                  <Check size={13} strokeWidth={3} aria-hidden="true" />Nivel asignado
                </span>
              {/if}
            </li>
          {/each}
        </ol>
      </section>
    {/each}
  {/if}
</div>
