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
   * Pestaña «Resumen de notas» de la ficha del curso (T30): tres cifras
   * (mi promedio, el del curso y cuántas sumativas van calificadas) y, por
   * componente, promedios, evaluadas, regla de aprobación y la asistencia
   * contra el mínimo.
   *
   * El promedio del curso es el promedio de los promedios de los alumnos de
   * cada componente; nunca se muestra información de otro alumno.
   */
  import { CircleAlert, CircleCheck, Info, LineChart } from 'lucide-svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import { btnSecundario, etiqueta, pildora, tarjeta, tono } from '@/lib/dm';

  interface Props {
    cargando?: boolean;
    error?: string | null;
    rendimiento?: Rendimiento | null;
    onReintentar?: () => void;
  }

  let { cargando = false, error = null, rendimiento = null, onReintentar }: Props = $props();

  const fmt = (n: number | null | undefined) => (n == null ? '—' : n.toFixed(1).replace('.', ','));
  // Nota roja bajo 4,0; sin nota, el guion en gris.
  const colorNota = (n: number | null | undefined, base = 'text-dm-ink') =>
    n == null ? 'text-dm-ink-3' : n < 4 ? 'text-dm-error' : base;

  // Sumativas con nota publicada, de todos los componentes del alumno.
  const totales = $derived({
    evaluadas: rendimiento?.componentes.reduce((n, c) => n + c.actividades_evaluadas, 0) ?? 0,
    sumativas: rendimiento?.componentes.reduce((n, c) => n + c.actividades_sumativas, 0) ?? 0,
  });

  const CIFRA = 'font-dm-cond text-[56px] leading-[56px] font-black tabular-nums';
  const NUMERO = 'm-0 font-dm-cond text-[32px] leading-9 font-black tabular-nums';
</script>

{#if cargando && !rendimiento}
  <EstadoVacio tipo="cargando" titulo="Calculando tu rendimiento…" />
{:else if error}
  <EstadoVacio tipo="error" titulo="No se pudo cargar el resumen" texto={error}>
    {#snippet accion()}
      {#if onReintentar}<button type="button" class={btnSecundario} onclick={onReintentar}>Intentar de nuevo</button>{/if}
    {/snippet}
  </EstadoVacio>
{:else if rendimiento}
  <div class="flex flex-col gap-6">
    <div class="grid gap-4 md:grid-cols-3">
      <div class="{tarjeta} flex flex-col gap-1 border-t-6 border-t-(--curso) px-5 py-4 lg:px-6 lg:py-5">
        <span class="{etiqueta} text-dm-ink-3">Mi promedio</span>
        <span class="{CIFRA} {colorNota(rendimiento.mi_promedio)}">{fmt(rendimiento.mi_promedio)}</span>
        <span class="text-[13px] leading-[18px] text-dm-ink-2">
          {#if totales.evaluadas > 0}
            Con {totales.evaluadas}
            {totales.evaluadas === 1 ? 'actividad sumativa calificada' : 'actividades sumativas calificadas'}
          {:else}
            Aún no hay actividades calificadas
          {/if}
        </span>
      </div>
      <div class="{tarjeta} flex flex-col gap-1 border-t-6 border-t-dm-linea px-5 py-4 lg:px-6 lg:py-5">
        <span class="{etiqueta} text-dm-ink-3">Promedio del curso</span>
        <span class="{CIFRA} {rendimiento.promedio_curso == null ? 'text-dm-ink-3' : 'text-dm-ink-2'}"
          >{fmt(rendimiento.promedio_curso)}</span
        >
        <span class="text-[13px] leading-[18px] text-dm-ink-2">Promedio de todo el grupo</span>
      </div>
      <div class="{tarjeta} flex flex-col gap-1 border-t-6 border-t-dm-linea px-5 py-4 lg:px-6 lg:py-5">
        <span class="{etiqueta} text-dm-ink-3">Actividades calificadas</span>
        <span class="{CIFRA} text-dm-ink-2"
          >{totales.evaluadas}<span class="text-[28px] font-extrabold text-dm-ink-3"> de {totales.sumativas}</span></span
        >
        <span class="text-[13px] leading-[18px] text-dm-ink-2">Sumativas del curso</span>
      </div>
    </div>

    <section aria-labelledby="t-por-componente" class="flex flex-col gap-4">
      <h3 id="t-por-componente" class="text-lg leading-6 font-bold">Por componente</h3>
      {#if rendimiento.componentes.length === 0}
        <EstadoVacio
          icono={LineChart}
          titulo="Sin componentes"
          texto="No estás inscrito en componentes de este curso."
        />
      {:else}
        <div class="grid gap-4 lg:grid-cols-2">
          {#each rendimiento.componentes as c (c.id_componente)}
            {@const asis = c.asistencia.porcentaje}
            {@const minimo = rendimiento.asistencia_minima}
            {@const bajo = asis != null && asis < minimo}
            <article class="{tarjeta} flex min-w-0 flex-col overflow-hidden">
              <div class="flex items-center justify-between gap-3 border-b border-dm-linea px-5 py-4 lg:px-6">
                <h4 class="font-dm-cond text-2xl leading-7 font-extrabold uppercase">{c.tipo}</h4>
                {#if c.syllabus.porcentaje != null}
                  <span class="{pildora} {tono.accion} font-bold">{c.syllabus.porcentaje} % del curso</span>
                {/if}
              </div>
              <dl class="m-0 grid grid-cols-2 gap-x-6 gap-y-4 px-5 py-5 lg:px-6">
                <div class="flex flex-col gap-0.5">
                  <dt class="text-[13px] leading-[18px] text-dm-ink-2">Mi promedio</dt>
                  <dd class="{NUMERO} {colorNota(c.mi_promedio)}">{fmt(c.mi_promedio)}</dd>
                </div>
                <div class="flex flex-col gap-0.5">
                  <dt class="text-[13px] leading-[18px] text-dm-ink-2">Promedio del componente</dt>
                  <dd class="{NUMERO} {c.promedio_curso == null ? 'text-dm-ink-3' : 'text-dm-ink-2'}">{fmt(c.promedio_curso)}</dd>
                </div>
                <div class="flex flex-col gap-0.5">
                  <dt class="text-[13px] leading-[18px] text-dm-ink-2">Evaluadas</dt>
                  <dd class="m-0 text-[15px] leading-[22px] font-semibold">
                    {c.actividades_evaluadas} de {c.actividades_sumativas} sumativas{c.ponderacion_evaluada != null
                      ? ` · ${c.ponderacion_evaluada} % del peso`
                      : ''}
                  </dd>
                </div>
                <div class="flex flex-col gap-0.5">
                  <dt class="text-[13px] leading-[18px] text-dm-ink-2">Aprobación</dt>
                  <dd class="m-0 text-[15px] leading-[22px] font-semibold">
                    {c.syllabus.aprobacion_obligatoria ? 'Obligatoria por separado' : 'No obligatoria por separado'}{c.exigencia !=
                    null
                      ? ` · exigencia ${c.exigencia} %`
                      : ''}
                  </dd>
                </div>
              </dl>

              <!-- Asistencia contra el mínimo: barra con el mínimo marcado y su rótulo. -->
              <div class="mt-auto flex flex-col gap-2.5 border-t border-dm-linea bg-dm-sutil px-5 pt-4 pb-5 lg:px-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="text-sm font-bold">
                    Asistencia <span class="tabular-nums">{asis != null ? `${asis} %` : '· sin registro'}</span>
                  </span>
                  {#if asis != null}
                    <span class="{pildora} {bajo ? tono.warn : tono.ok}">
                      {#if bajo}<CircleAlert size={14} aria-hidden="true" />Bajo el mínimo ({minimo} %){:else}<CircleCheck
                          size={14}
                          aria-hidden="true"
                        />Sobre el mínimo ({minimo} %){/if}
                    </span>
                  {/if}
                </div>
                <div
                  role="img"
                  aria-label={asis != null ? `Asistencia ${asis} %, mínimo ${minimo} %` : `Sin registro de asistencia, mínimo ${minimo} %`}
                  class="relative h-2.5 rounded-full border border-dm-linea bg-dm-tarjeta"
                >
                  <span
                    class="absolute inset-y-0 left-0 rounded-full {bajo ? 'bg-dm-warn' : 'bg-(--curso)'}"
                    style="width: {Math.min(asis ?? 0, 100)}%"
                  ></span>
                  <span class="absolute -top-1.5 -bottom-1.5 w-0.5 bg-dm-ink" style="left: {minimo}%"></span>
                </div>
                <div aria-hidden="true" class="relative h-4.5 text-xs leading-[18px] text-dm-ink-3 tabular-nums">
                  <span class="absolute left-0">0 %</span>
                  <span class="absolute -translate-x-1/2 font-bold text-dm-ink-2" style="left: {minimo}%">mínimo</span>
                  <span class="absolute right-0">100 %</span>
                </div>
                <span class="text-[13px] leading-[18px] text-dm-ink-2">
                  {c.asistencia.total > 0
                    ? `${c.asistencia.presentes} de ${c.asistencia.total} sesiones`
                    : 'Aún no hay registro de asistencia.'}
                </span>
              </div>
            </article>
          {/each}
        </div>
      {/if}
    </section>

    <div class="flex gap-3 rounded-xl bg-dm-info-s px-5 py-3.5 text-dm-info">
      <Info size={20} aria-hidden="true" class="mt-px shrink-0" />
      <p class="text-sm leading-5 text-dm-ink">
        El promedio del curso es el promedio de los promedios de cada estudiante. Nunca ves notas de otras personas.
      </p>
    </div>
  </div>
{/if}
