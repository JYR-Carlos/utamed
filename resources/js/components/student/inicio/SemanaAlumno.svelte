<script lang="ts">
  /**
   * «Tu semana»: las entregas pendientes de lunes a domingo, por día. En
   * escritorio son siete columnas; en móvil, una fila por entrega. Lo que
   * vence después del domingo (dentro de los 7 días que manda el servidor)
   * se nombra abajo.
   */
  import { Link } from '@inertiajs/svelte';
  import { ChevronRight } from 'lucide-svelte';
  import { claveDia, formatDiaCorto, parseFechaSoloDia } from '@/utils/formatters';
  import { plazoDe, type ActividadPorVencer } from '@/lib/plazoAlumno';
  import { enlace } from '@/lib/dm';

  interface Props {
    items: ActividadPorVencer[];
    /** Estilo de color (estiloCurso) por id de curso. */
    colorDe: (idCurso: number) => string;
    /** Calendario del semestre; sin destino no se muestra el enlace. */
    hrefCalendario?: string | null;
  }

  let { items, colorDe, hrefCalendario = null }: Props = $props();

  const hoy = claveDia();

  const dias = $derived.by(() => {
    const base = parseFechaSoloDia(hoy);
    const lunes = new Date(base);
    lunes.setDate(base.getDate() - ((base.getDay() + 6) % 7));
    return Array.from({ length: 7 }, (_, i) => {
      const d = new Date(lunes);
      d.setDate(lunes.getDate() + i);
      const clave = claveDia(d);
      return {
        clave,
        nombre: d.toLocaleDateString('es-CL', { weekday: 'short' }).replace('.', ''),
        numero: d.getDate(),
        esHoy: clave === hoy,
        items: items.filter((a) => a.fecha_limite.slice(0, 10) === clave),
      };
    });
  });

  const domingo = $derived(dias[6].clave);
  const rango = $derived(
    `${dias[0].numero} al ${parseFechaSoloDia(domingo).toLocaleDateString('es-CL', { day: 'numeric', month: 'long' })}`,
  );
  const enLaSemana = $derived(dias.flatMap((d) => d.items.map((a) => ({ dia: d, a }))));
  const despues = $derived(items.filter((a) => a.fecha_limite.slice(0, 10) > domingo));

  /** Nota bajo la entrega: sólo cuando el plazo no es el del día en que aparece. */
  function nota(a: ActividadPorVencer): string {
    const plazo = plazoDe(a, hoy);
    return a.es_vencida || a.fecha_limite.slice(0, 10) < hoy ? plazo.texto : '';
  }

  function hrefActividad(a: ActividadPorVencer): string {
    return `/estudiante/cursos/${a.id_curso}/actividad/${a.id_actividad}`;
  }
</script>

{#snippet entrega(a: ActividadPorVencer)}
  {@const aviso = nota(a)}
  <Link
    href={hrefActividad(a)}
    style={colorDe(a.id_curso)}
    class="flex min-w-0 flex-col gap-1 rounded-lg bg-(--curso-s) p-2 text-dm-ink no-underline hover:outline-2 hover:outline-(--curso) max-lg:flex-1 max-lg:bg-transparent max-lg:p-0 max-lg:hover:outline-0"
  >
    <span class="flex items-center gap-1.5 text-[13px] leading-[18px] font-bold text-(--curso-t)">
      <span aria-hidden="true" class="size-2 shrink-0 rounded-full bg-(--curso)"></span>{a.curso} · 23:59
    </span>
    <span class="text-[13px] leading-[18px] font-semibold max-lg:text-[15px] max-lg:leading-5">{a.nombre}</span>
    {#if aviso}<span class="text-[13px] leading-[18px] font-semibold text-dm-warn">{aviso}</span>{/if}
  </Link>
{/snippet}

<section aria-labelledby="t-semana" class="flex flex-col gap-3 lg:gap-4">
  <div class="flex flex-wrap items-baseline justify-between gap-x-4">
    <div class="flex items-baseline gap-3">
      <h2 id="t-semana" class="text-[22px] leading-7 font-bold">Tu semana</h2>
      <span class="hidden text-[13px] leading-[18px] text-dm-ink-3 sm:inline">{rango}</span>
    </div>
    {#if hrefCalendario}
      <Link href={hrefCalendario} class={enlace}>Ver calendario <ChevronRight size={16} aria-hidden="true" /></Link>
    {/if}
  </div>

  <!-- Escritorio: los siete días -->
  <ol class="hidden grid-cols-7 overflow-hidden rounded-xl border border-dm-linea bg-dm-tarjeta lg:grid">
    {#each dias as d (d.clave)}
      <li
        aria-current={d.esHoy ? 'date' : undefined}
        class="relative flex min-h-42 flex-col gap-2 border-r border-dm-linea px-3 py-4 last:border-r-0 {d.esHoy
          ? 'bg-dm-activo-suave'
          : ''}"
      >
        {#if d.esHoy}<span aria-hidden="true" class="absolute inset-x-0 top-0 h-[3px] bg-dm-amarillo"></span>{/if}
        <span class="flex items-baseline gap-1.5 {d.esHoy ? 'text-dm-activo-texto' : 'text-dm-ink'}">
          <span class="text-xs leading-4 font-bold uppercase">{d.nombre}</span>
          <span class="text-[22px] leading-7 font-bold tabular-nums">{d.numero}</span>
          {#if d.esHoy}<span class="text-xs leading-4 font-bold uppercase">· Hoy</span>{/if}
        </span>
        {#each d.items as a (a.id_actividad)}
          {@render entrega(a)}
        {:else}
          <span class="text-[13px] leading-[18px] text-dm-ink-3">Sin entregas</span>
        {/each}
      </li>
    {/each}
  </ol>

  <!-- Móvil: una fila por entrega -->
  {#if enLaSemana.length > 0}
    <ol class="flex flex-col overflow-hidden rounded-xl border border-dm-linea bg-dm-tarjeta lg:hidden">
      {#each enLaSemana as { dia, a } (a.id_actividad)}
        <li
          class="relative flex gap-3 border-b border-dm-linea px-4 py-3 last:border-b-0 {dia.esHoy
            ? 'bg-dm-activo-suave'
            : ''}"
        >
          {#if dia.esHoy}<span aria-hidden="true" class="absolute inset-y-0 left-0 w-[3px] bg-dm-amarillo"></span>{/if}
          <span class="flex w-13 shrink-0 flex-col {dia.esHoy ? 'text-dm-activo-texto' : 'text-dm-ink'}">
            <span class="text-xs leading-4 font-bold uppercase">{dia.esHoy ? 'Hoy' : dia.nombre}</span>
            <span class="text-[22px] leading-7 font-bold tabular-nums">{dia.numero}</span>
          </span>
          {@render entrega(a)}
        </li>
      {/each}
    </ol>
  {:else}
    <p class="rounded-xl border border-dm-linea bg-dm-tarjeta px-4 py-3 text-sm text-dm-ink-2 lg:hidden">
      No tienes entregas pendientes esta semana.
    </p>
  {/if}

  {#if despues.length > 0}
    <p class="text-[13px] leading-[18px] text-dm-ink-2">
      La próxima semana:
      {#each despues as a, i (a.id_actividad)}{i > 0 ? (i === despues.length - 1 ? ' y ' : ', ') : ' '}<Link
          href={hrefActividad(a)}
          class="font-semibold text-dm-enlace no-underline hover:underline">{a.nombre}</Link
        > ({formatDiaCorto(a.fecha_limite)}){/each}.
    </p>
  {/if}
</section>
