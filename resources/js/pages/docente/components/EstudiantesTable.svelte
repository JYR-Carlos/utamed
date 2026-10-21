<script lang="ts">
  /**
   * Tabla de estudiantes inscritos en un componente.
   * Hallazgo D-06: extraída de las ramas TITULAR y COLEGIADO de CursoDetalle.svelte,
   * donde estaba duplicada casi idéntica.
   *
   * Reglas del lenguaje visual (mockup «Detalle del curso · Mi Grupo», auditoría 2026-10-19):
   * - La nota es la del COMPONENTE activo, no la del curso: por eso la cabecera
   *   de la columna lleva el nombre del componente.
   * - Escala 1,0–7,0 con coma; el 0 no existe. Sin nota se dibuja «—» — vacío no es cero.
   * - La asistencia bajo el mínimo del componente se marca con color, texto y la
   *   pastilla «Bajo mínimo» (hallazgo #12): no basta el color.
   * - Bajo 768 px la tabla pasa a tarjetas con «Ver ficha» de 44 px (hallazgo #18).
   * - La columna/botón "Ficha" sólo se renderiza si `mostrarDetalle` es true.
   *
   * El pie de la tabla (totales, promedio) lo aporta quien la usa mediante el
   * snippet `pie`, para que quede dentro del mismo marco redondeado.
   */
  import type { Snippet } from 'svelte';
  import { TriangleAlert } from 'lucide-svelte';
  import { formatNota } from '@/utils/formatters';

  interface EstudianteItem {
    id_inscripcion_componente: number;
    nota_componente: number | null;
    estudiante: {
      nombre: string;
      username: string;
    };
    /** Sesiones presentes / registradas en el componente. */
    asistencia?: { presentes: number; total: number };
  }

  interface Props {
    estudiantes: EstudianteItem[];
    /** Tipo de componente: encabeza la columna de nota y el <caption>. */
    tipoComponente?: string;
    /** Mínimo de asistencia del componente (0–100); sin él no se marca el riesgo. */
    minAsistencia?: number | null;
    /** Renderiza la columna "Ficha" con botón por fila. */
    mostrarDetalle?: boolean;
    onDetalle?: (item: EstudianteItem) => void;
    /** Pie de tabla opcional (totales, promedio); recibe cuántas filas se muestran. */
    pie?: Snippet<[number]>;
    /** Sin marco propio: la tabla va a sangre dentro de otra tarjeta. */
    plano?: boolean;
    /** Filas visibles antes de «Ver los N»; null = todas. */
    inicial?: number | null;
  }

  let {
    estudiantes,
    tipoComponente = 'Componente',
    minAsistencia = null,
    mostrarDetalle = false,
    onDetalle,
    pie,
    plano = false,
    inicial = null,
  }: Props = $props();

  let verTodos = $state(false);

  const estudiantesOrdenados = $derived(
    [...estudiantes].sort((a, b) =>
      (a.estudiante?.nombre ?? '').localeCompare(
        b.estudiante?.nombre ?? '',
        'es',
        { sensitivity: 'base' }
      )
    ),
  );

  const recortada = $derived(!verTodos && inicial !== null && estudiantesOrdenados.length > inicial);
  const visibles = $derived(recortada ? estudiantesOrdenados.slice(0, inicial!) : estudiantesOrdenados);

  /** Porcentaje entero de asistencia, o null si aún no hay sesiones. */
  function porcentaje(item: EstudianteItem): number | null {
    const a = item.asistencia;
    if (!a || a.total === 0) return null;
    return Math.round((a.presentes / a.total) * 100);
  }

  function bajoMinimo(item: EstudianteItem): boolean {
    const p = porcentaje(item);
    return p !== null && minAsistencia !== null && minAsistencia > 0 && p < minAsistencia;
  }

  const etiquetaMinimo = $derived(
    minAsistencia !== null && minAsistencia > 0 ? `${Math.round(minAsistencia)} %` : null,
  );

  const TH =
    'px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600';
  const BTN_FICHA =
    'inline-flex items-center justify-center rounded-lg border border-line-control bg-white px-4 text-sm font-semibold text-uta-blue transition-colors hover:bg-uta-blue-light focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uta-blue focus-visible:ring-offset-2';
</script>

{#snippet asistencia(item: EstudianteItem)}
  {@const p = porcentaje(item)}
  {@const riesgo = bajoMinimo(item)}
  {#if p === null}
    <span class="text-[13px] text-muted-foreground">Sin sesiones</span>
  {:else}
    <span class="flex flex-wrap items-center gap-2.5">
      <span class="text-[13px] font-medium tabular-nums {riesgo ? 'text-red-700' : 'text-ink'}">
        {item.asistencia?.presentes}/{item.asistencia?.total} sesiones · {p} %
      </span>
      {#if riesgo}
        <span
          class="inline-flex items-center gap-1 rounded-full bg-red-100 py-0.5 pl-2 pr-2.5 text-xs font-semibold text-red-700"
        >
          <TriangleAlert size={12} strokeWidth={2.5} aria-hidden="true" />
          Bajo mínimo{etiquetaMinimo ? ` ${etiquetaMinimo}` : ''}
        </span>
      {/if}
    </span>
  {/if}
{/snippet}

{#snippet nota(item: EstudianteItem)}
  {@const n = item.nota_componente}
  {#if n === null}
    <span class="text-base font-bold text-slate-500" aria-hidden="true">—</span>
    <span class="sr-only">Sin nota</span>
  {:else}
    {@const reprobada = n < 4}
    <span class="text-base font-bold tabular-nums {reprobada ? 'text-red-700' : 'text-ink'}">
      {formatNota(n)}
    </span>
    <span class="sr-only">{reprobada ? '— Reprobado' : '— Aprobado'}</span>
  {/if}
{/snippet}

<div class="overflow-hidden {plano ? 'md:bg-white' : 'rounded-xl border border-slate-200 bg-white'}">
  <!-- Desktop y tablet: tabla -->
  <div class="hidden md:block">
    <table class="w-full border-collapse text-sm">
      <caption class="sr-only">Estudiantes inscritos — {tipoComponente}</caption>
      <thead class="bg-slate-50">
        <tr>
          <th class="{TH} w-14 !pr-2 {plano ? 'sm:!pl-6' : ''}">#</th>
          <th class={TH}>Estudiante</th>
          <th class={TH}>Asistencia{etiquetaMinimo ? ` (mín. ${etiquetaMinimo})` : ''}</th>
          <th class="{TH} w-36">Nota {tipoComponente}</th>
          {#if mostrarDetalle}
            <th class="w-40 px-4 py-2.5"><span class="sr-only">Acciones</span></th>
          {/if}
        </tr>
      </thead>
      <tbody>
        {#each visibles as item, i (item.id_inscripcion_componente)}
          <tr class="border-t border-slate-200 transition-colors duration-150 hover:bg-slate-50">
            <td class="py-3 pl-4 pr-2 font-mono text-xs tabular-nums text-muted-foreground {plano ? 'sm:pl-6' : ''}">
              {String(i + 1).padStart(2, '0')}
            </td>
            <td class="px-4 py-3">
              <!-- El RUT/matrícula queda en la ficha y en el title: el mockup deja la fila al nombre. -->
              <span class="block font-semibold text-ink" title={item.estudiante.username}>{item.estudiante.nombre}</span>
            </td>
            <td class="px-4 py-3">{@render asistencia(item)}</td>
            <td class="px-4 py-3">{@render nota(item)}</td>
            {#if mostrarDetalle}
              <td class="py-3 pl-4 text-right {plano ? 'pr-4 sm:pr-6' : 'pr-4'}">
                <button
                  onclick={() => onDetalle?.(item)}
                  title="Ver evaluaciones, mensajes y asistencia"
                  class="{BTN_FICHA} h-10 whitespace-nowrap"
                >
                  Ver ficha
                </button>
              </td>
            {/if}
          </tr>
        {/each}
      </tbody>
    </table>
  </div>

  <!-- Móvil: tarjetas -->
  <ul
    class="flex flex-col gap-3 md:hidden {plano ? 'bg-transparent' : 'bg-slate-50 p-3'}"
    aria-label="Estudiantes inscritos — {tipoComponente}"
  >
    {#each visibles as item (item.id_inscripcion_componente)}
      {@const riesgo = bajoMinimo(item)}
      <li
        class="flex flex-col gap-2.5 rounded-xl border bg-white px-4 py-3.5 {riesgo
          ? 'border-red-700'
          : 'border-slate-200'}"
      >
        <div class="flex items-start gap-2">
          <span class="flex-1 text-[15px] font-semibold leading-5 text-ink">{item.estudiante.nombre}</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs font-semibold text-muted-foreground">Asistencia</span>
          {@render asistencia(item)}
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs font-semibold text-muted-foreground">Nota {tipoComponente}</span>
          {@render nota(item)}
        </div>
        {#if mostrarDetalle}
          <button onclick={() => onDetalle?.(item)} class="{BTN_FICHA} h-11 w-full">Ver ficha</button>
        {/if}
      </li>
    {/each}
  </ul>

  {#if pie || recortada}
    <div
      class="flex flex-wrap items-center gap-3 {plano
        ? 'pt-3 md:border-t md:border-slate-200 md:bg-slate-50 md:px-6 md:py-3'
        : 'border-t border-slate-200 bg-slate-50 px-4 py-3'}"
    >
      <div class="min-w-0 flex-1 max-md:basis-full">{@render pie?.(visibles.length)}</div>
      {#if recortada}
        <button type="button" class="{BTN_FICHA} h-11 max-md:w-full md:h-10" onclick={() => (verTodos = true)}>
          Ver los {estudiantesOrdenados.length}<span class="md:hidden">&nbsp;estudiantes</span>
        </button>
      {/if}
    </div>
  {/if}
</div>
