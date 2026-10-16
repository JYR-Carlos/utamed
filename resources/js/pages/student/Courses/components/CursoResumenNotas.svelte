<script lang="ts" module>
  export interface ComponenteRendimiento {
    id_componente: number;
    tipo: string;
    mi_promedio: number | null;
    promedio_curso: number | null;
    actividades_sumativas: number;
    actividades_evaluadas: number;
    ponderacion_evaluada: number | null;
    asistencia: { presentes: number; total: number; porcentaje: number | null };
    syllabus: { porcentaje: number | null; aprobacion_obligatoria: boolean };
    exigencia: number | null;
  }

  export interface Rendimiento {
    asistencia_minima: number;
    mi_promedio: number | null;
    promedio_curso: number | null;
    componentes: ComponenteRendimiento[];
  }
</script>

<script lang="ts">
  /**
   * Pestaña «Resumen de notas» de la ficha del curso. Antes era un panel
   * lateral que se abría desde el botón «Rendimiento» (T30); ahora es una
   * pestaña, como en la UTAMED anterior a la que los alumnos están habituados.
   *
   * El promedio del curso es el promedio de los promedios de los alumnos de
   * cada componente; nunca se muestra información de otro alumno.
   */
  import { Loader2, CalendarCheck2 } from 'lucide-svelte';

  interface Props {
    cargando?: boolean;
    error?: string | null;
    rendimiento?: Rendimiento | null;
  }

  let { cargando = false, error = null, rendimiento = null }: Props = $props();

  const fmt = (n: number | null | undefined) => (n == null ? '—' : n.toFixed(1).replace('.', ','));
  const colorNota = (n: number | null | undefined) =>
    n == null ? 'text-slate-600' : n >= 4 ? 'text-emerald-700' : 'text-uta-red';

  // Sumativas con nota publicada, de todos los componentes del alumno.
  const totales = $derived({
    evaluadas: rendimiento?.componentes.reduce((n, c) => n + c.actividades_evaluadas, 0) ?? 0,
    sumativas: rendimiento?.componentes.reduce((n, c) => n + c.actividades_sumativas, 0) ?? 0,
  });
</script>

{#if cargando && !rendimiento}
  <div class="flex items-center justify-center gap-2 py-16 text-sm text-slate-600">
    <Loader2 class="h-4 w-4 animate-spin" />
    Calculando tu rendimiento…
  </div>
{:else if error}
  <p class="rounded-xl border border-uta-red/20 bg-uta-red-light p-4 text-sm text-uta-red">{error}</p>
{:else if rendimiento}
  <!-- Resumen: tres tarjetas con la cifra grande y, debajo, qué significa -->
  <section class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
    <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-5">
      <p class="m-0 text-sm font-semibold text-slate-600">Mi promedio</p>
      <p class="m-0 text-[32px] font-extrabold leading-tight tabular-nums {colorNota(rendimiento.mi_promedio)}">
        {fmt(rendimiento.mi_promedio)}
      </p>
      {#if rendimiento.mi_promedio == null}
        <p class="m-0 text-sm text-slate-600">Aún no hay actividades calificadas.</p>
      {/if}
    </div>

    <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-5">
      <p class="m-0 text-sm font-semibold text-slate-600">Promedio del curso</p>
      <p class="m-0 text-[32px] font-extrabold leading-tight tabular-nums text-slate-900">
        {fmt(rendimiento.promedio_curso)}
      </p>
    </div>

    <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-5">
      <p class="m-0 text-sm font-semibold text-slate-600">Actividades calificadas</p>
      <p class="m-0 text-[32px] font-extrabold leading-tight tabular-nums text-slate-900">
        {totales.evaluadas} de {totales.sumativas}
      </p>
      <p class="m-0 text-sm text-slate-600">Las notas aparecen cuando se publica la calificación.</p>
    </div>
  </section>

  <!-- Desglose por componente -->
  <h3 class="mb-4 text-base font-semibold text-slate-900">Por componente</h3>
  <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    {#each rendimiento.componentes as c (c.id_componente)}
      {@const asis = c.asistencia.porcentaje}
      {@const minimo = rendimiento.asistencia_minima}
      {@const bajoMinimo = asis != null && asis < minimo}
      <article class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex items-baseline justify-between gap-3">
          <span class="text-base font-bold text-slate-900">{c.tipo}</span>
          {#if c.syllabus.porcentaje != null}
            <span class="text-sm font-bold text-uta-blue">{c.syllabus.porcentaje}% del curso</span>
          {/if}
        </div>

        <dl class="m-0 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
          <div>
            <dt class="text-xs font-medium text-slate-600">Mi promedio</dt>
            <dd class="m-0 font-semibold tabular-nums {colorNota(c.mi_promedio)}">{fmt(c.mi_promedio)}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-600">Promedio del componente</dt>
            <dd class="m-0 font-semibold tabular-nums text-slate-900">{fmt(c.promedio_curso)}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-600">Evaluadas</dt>
            <dd class="m-0 text-slate-900">
              {c.actividades_evaluadas} de {c.actividades_sumativas} sumativas{#if c.ponderacion_evaluada != null}
                <span class="text-slate-600">{` · ${c.ponderacion_evaluada}% del peso`}</span>{/if}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-600">Aprobación</dt>
            <dd class="m-0 text-slate-900">
              {c.syllabus.aprobacion_obligatoria ? 'Obligatoria por separado' : 'No obligatoria por separado'}{#if c.exigencia != null}
                <span class="text-slate-600">{` · exigencia ${c.exigencia}%`}</span>{/if}
            </dd>
          </div>
        </dl>

        <!-- Asistencia vs mínimo: riel visible, marcador de 2 px con su rótulo y escala 0–100 -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="inline-flex items-center gap-1.5 font-semibold text-slate-600">
              <CalendarCheck2 class="h-4 w-4" aria-hidden="true" />
              Asistencia
            </span>
            <span class="font-bold {bajoMinimo ? 'text-uta-red' : 'text-slate-900'}">
              {asis != null ? `${asis} %` : 'Sin registro'}
            </span>
          </div>

          <div class="relative pt-5">
            <span
              class="absolute top-0 -translate-x-1/2 whitespace-nowrap text-xs font-bold text-uta-blue"
              style="left: {minimo}%"
            >
              Mínimo {minimo} %
            </span>
            <div
              class="relative h-3 rounded-full border border-slate-300 bg-slate-200"
              role="progressbar"
              aria-label="Asistencia"
              aria-valuemin={0}
              aria-valuemax={100}
              aria-valuenow={asis ?? 0}
            >
              <div
                class="h-full rounded-full {bajoMinimo ? 'bg-uta-red' : 'bg-uta-blue'}"
                style="width: {Math.min(asis ?? 0, 100)}%"
              ></div>
              <span
                class="absolute -inset-y-1.5 w-0.5 bg-uta-blue"
                style="left: {minimo}%"
                aria-hidden="true"
              ></span>
            </div>
          </div>
          <div class="flex justify-between text-xs font-medium text-slate-600">
            <span>0 %</span>
            <span>100 %</span>
          </div>
          <p class="m-0 text-[13px] text-slate-600">
            {#if c.asistencia.total > 0}
              {c.asistencia.presentes} de {c.asistencia.total} sesiones
            {:else}
              Aún no hay registro de asistencia.
            {/if}
          </p>
        </div>
      </article>
    {:else}
      <p class="text-sm text-slate-600 lg:col-span-2">No estás inscrito en componentes de este curso.</p>
    {/each}
  </section>
{/if}
