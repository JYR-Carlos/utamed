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
   * Panel lateral «Rendimiento» del curso (T30). Va a la derecha sin tapar el
   * resto de la página: no hay fondo oscuro y la ficha sigue usable detrás.
   *
   * El promedio del curso es el promedio de los promedios de los alumnos de
   * cada componente; nunca se muestra información de otro alumno.
   */
  import { BarChart3, Loader2, X, CalendarCheck2 } from 'lucide-svelte';

  interface Props {
    abierto: boolean;
    cargando?: boolean;
    error?: string | null;
    rendimiento?: Rendimiento | null;
    onCerrar: () => void;
  }

  let { abierto, cargando = false, error = null, rendimiento = null, onCerrar }: Props = $props();

  const fmt = (n: number | null | undefined) => (n == null ? '—' : n.toFixed(1).replace('.', ','));
  const colorNota = (n: number | null | undefined) =>
    n == null ? 'text-gray-400' : n >= 4 ? 'text-emerald-700' : 'text-uta-red';

  $effect(() => {
    if (!abierto) return;
    const alTeclear = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onCerrar();
    };
    window.addEventListener('keydown', alTeclear);
    return () => window.removeEventListener('keydown', alTeclear);
  });
</script>

{#if abierto}
  <aside
    class="fixed inset-y-0 right-0 z-40 flex w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-2xl"
    aria-label="Rendimiento en el curso"
  >
    <header class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
      <div class="flex items-center gap-2">
        <BarChart3 class="h-5 w-5 text-uta-blue" />
        <h2 class="m-0 text-base font-semibold text-gray-900">Rendimiento</h2>
      </div>
      <button
        type="button"
        onclick={onCerrar}
        class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
        aria-label="Cerrar panel de rendimiento"
      >
        <X class="h-5 w-5" />
      </button>
    </header>

    <div class="flex-1 overflow-y-auto px-5 py-5">
      {#if cargando && !rendimiento}
        <div class="flex items-center justify-center gap-2 py-16 text-sm text-gray-500">
          <Loader2 class="h-4 w-4 animate-spin" />
          Calculando tu rendimiento…
        </div>
      {:else if error}
        <p class="rounded-xl border border-uta-red/20 bg-uta-red-light p-4 text-sm text-uta-red">{error}</p>
      {:else if rendimiento}
        <!-- Resumen -->
        <section class="mb-6 grid grid-cols-2 gap-3">
          <div class="rounded-2xl border border-gray-200 p-4">
            <p class="m-0 text-xs font-medium text-gray-500">Mi promedio</p>
            <p class="m-0 text-3xl font-bold tabular-nums {colorNota(rendimiento.mi_promedio)}">
              {fmt(rendimiento.mi_promedio)}
            </p>
          </div>
          <div class="rounded-2xl border border-gray-200 p-4">
            <p class="m-0 text-xs font-medium text-gray-500">Promedio del curso</p>
            <p class="m-0 text-3xl font-bold tabular-nums text-gray-700">
              {fmt(rendimiento.promedio_curso)}
            </p>
          </div>
          {#if rendimiento.mi_promedio == null}
            <p class="col-span-2 m-0 text-xs text-gray-500">
              Aún no tienes notas registradas en actividades sumativas.
            </p>
          {/if}
        </section>

        <!-- Desglose por componente -->
        <section class="flex flex-col gap-4">
          <h3 class="m-0 text-sm font-semibold text-gray-700">Por componente</h3>
          {#each rendimiento.componentes as c (c.id_componente)}
            {@const asis = c.asistencia.porcentaje}
            {@const bajoMinimo = asis != null && asis < rendimiento.asistencia_minima}
            <article class="flex flex-col gap-3 rounded-2xl border border-gray-200 p-4">
              <div class="flex items-baseline justify-between gap-3">
                <span class="font-semibold text-gray-900">{c.tipo}</span>
                {#if c.syllabus.porcentaje != null}
                  <span class="text-sm font-bold text-uta-blue">{c.syllabus.porcentaje}% del curso</span>
                {/if}
              </div>

              <dl class="m-0 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                  <dt class="text-xs text-gray-500">Mi promedio</dt>
                  <dd class="m-0 font-semibold tabular-nums {colorNota(c.mi_promedio)}">{fmt(c.mi_promedio)}</dd>
                </div>
                <div>
                  <dt class="text-xs text-gray-500">Promedio del componente</dt>
                  <dd class="m-0 font-semibold tabular-nums text-gray-700">{fmt(c.promedio_curso)}</dd>
                </div>
                <div>
                  <dt class="text-xs text-gray-500">Evaluadas</dt>
                  <dd class="m-0 text-gray-700">
                    {c.actividades_evaluadas} de {c.actividades_sumativas} sumativas{#if c.ponderacion_evaluada != null}
                      <span class="text-gray-500">{` · ${c.ponderacion_evaluada}% del peso`}</span>{/if}
                  </dd>
                </div>
                <div>
                  <dt class="text-xs text-gray-500">Aprobación</dt>
                  <dd class="m-0 text-gray-700">
                    {c.syllabus.aprobacion_obligatoria ? 'Obligatoria por separado' : 'No obligatoria por separado'}{#if c.exigencia != null}
                      <span class="text-gray-500">{` · exigencia ${c.exigencia}%`}</span>{/if}
                  </dd>
                </div>
              </dl>

              <!-- Asistencia vs mínimo -->
              <div class="flex flex-col gap-1.5">
                <div class="flex items-center justify-between text-xs">
                  <span class="inline-flex items-center gap-1.5 text-gray-600">
                    <CalendarCheck2 class="h-3.5 w-3.5" />
                    Asistencia
                  </span>
                  <span class="font-semibold {bajoMinimo ? 'text-uta-red' : 'text-gray-700'}">
                    {#if asis != null}
                      {asis}% · mínimo {rendimiento.asistencia_minima}%
                    {:else}
                      Sin registro · mínimo {rendimiento.asistencia_minima}%
                    {/if}
                  </span>
                </div>
                <div class="relative h-2 overflow-hidden rounded-full bg-gray-100">
                  <div
                    class="h-full rounded-full {bajoMinimo ? 'bg-uta-red' : 'bg-uta-blue'}"
                    style="width: {Math.min(asis ?? 0, 100)}%"
                  ></div>
                  <span
                    class="absolute inset-y-0 w-0.5 bg-gray-500"
                    style="left: {rendimiento.asistencia_minima}%"
                    aria-hidden="true"
                  ></span>
                </div>
                {#if c.asistencia.total > 0}
                  <p class="m-0 text-[11px] text-gray-500">
                    {c.asistencia.presentes} de {c.asistencia.total} sesiones
                  </p>
                {/if}
              </div>
            </article>
          {:else}
            <p class="text-sm text-gray-500">No estás inscrito en componentes de este curso.</p>
          {/each}
        </section>
      {/if}
    </div>
  </aside>
{/if}
