<script lang="ts" module>
  interface Entrada {
    id_agenda: number;
    fecha: string;
    tipo: string;
    mensaje: string;
    emisor: string;
    es_propio: boolean;
    es_de_docente: boolean;
    archivo: string | null;
    resultado: string | null;
    actividad: { id: number; nombre: string };
    componente: { id: number | null; tipo: string | null };
  }

  /** Prop diferida `bitacora` de la ficha (BitacoraCursoEstudiante::compilar). */
  export interface Bitacora {
    entradas: Entrada[];
    actividades: Array<{ id: number; nombre: string }>;
    componentes: Array<{ id: number; tipo: string }>;
  }
</script>

<script lang="ts">
  /**
   * Pestaña «Bitácora» de la ficha del curso (T41, guía dm): todas las agendas
   * de las actividades del alumno —entregas, mensajes, retroalimentaciones y
   * evaluaciones— en un solo flujo por día, de lo más reciente a lo más
   * antiguo. Se filtra por actividad y por componente; cada entrada lleva a la
   * agenda de su actividad.
   */
  import { Link } from '@inertiajs/svelte';
  import { Check, ChevronRight, FileText, History } from 'lucide-svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import { btnPrimario, btnSecundario, campo, etiqueta, tarjeta } from '@/lib/dm';
  import { titleCase } from '@/lib/utils';
  import { claveDia, formatDiaLargo } from '@/utils/formatters';

  interface Props {
    idCurso: number;
    bitacora: Bitacora | null;
    error?: string | null;
    onReintentar?: () => void;
  }

  let { idCurso, bitacora, error = null, onReintentar }: Props = $props();

  const entradas = $derived(bitacora?.entradas ?? []);
  const actividades = $derived(bitacora?.actividades ?? []);
  const componentes = $derived(bitacora?.componentes ?? []);

  let filtroActividad = $state<number | ''>('');
  let filtroComponente = $state<number | ''>('');

  const filtradas = $derived(
    entradas.filter(
      (e) =>
        (filtroActividad === '' || e.actividad.id === filtroActividad) &&
        (filtroComponente === '' || e.componente.id === filtroComponente),
    ),
  );

  // Por día, en el orden en que llegan (de lo más reciente a lo más antiguo).
  const dias = $derived.by(() => {
    const grupos: Array<{ dia: string; entradas: Entrada[] }> = [];
    for (const e of filtradas) {
      const d = new Date(e.fecha);
      const dia = isNaN(d.getTime()) ? e.fecha.slice(0, 10) : claveDia(d);
      const ultimo = grupos[grupos.length - 1];
      if (ultimo?.dia === dia) ultimo.entradas.push(e);
      else grupos.push({ dia, entradas: [e] });
    }
    return grupos;
  });

  const TIPOS: Record<string, { etiqueta: string; pildora: string; punto: string }> = {
    'Entrega de archivo': { etiqueta: 'Entrega', pildora: 'bg-dm-accion-suave text-dm-accion-texto', punto: 'bg-dm-accion' },
    'Cancelación de entrega': { etiqueta: 'Entrega retirada', pildora: 'bg-dm-off-s text-dm-off', punto: 'bg-dm-off' },
    'Mensaje al profesor': { etiqueta: 'Mensaje', pildora: 'bg-dm-sutil text-dm-ink-2', punto: 'bg-dm-control' },
    Feedback: { etiqueta: 'Retroalimentación', pildora: 'bg-dm-info-s text-dm-info', punto: 'bg-dm-info' },
    Evaluación: { etiqueta: 'Evaluación', pildora: 'bg-dm-ok-s text-dm-ok', punto: 'bg-dm-ok' },
    'Cierre de actividad': { etiqueta: 'Cierre', pildora: 'bg-dm-off-s text-dm-off', punto: 'bg-dm-off' },
    'Solicitud de apelación': { etiqueta: 'Apelación', pildora: 'bg-dm-warn-s text-dm-warn', punto: 'bg-dm-warn' },
    'Resolución de apelación': { etiqueta: 'Apelación resuelta', pildora: 'bg-dm-warn-s text-dm-warn', punto: 'bg-dm-warn' },
  };
  const tipoDe = (t: string) => TIPOS[t] ?? { etiqueta: t, pildora: 'bg-dm-sutil text-dm-ink-2', punto: 'bg-dm-control' };

  const hora = (f: string) => {
    const d = new Date(f);
    return isNaN(d.getTime()) ? '' : d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
  };

  const autor = (e: Entrada) => (e.es_propio ? 'Tú' : `${titleCase(e.emisor)}${e.es_de_docente ? ' · docente' : ''}`);

  const esNota = (r: string) => /^\d+([.,]\d+)?$/.test(r);
  const nota = (r: string) => Number(r.replace(',', '.')).toFixed(1).replace('.', ',');

  function limpiar() {
    filtroActividad = '';
    filtroComponente = '';
  }
</script>

{#if !bitacora}
  {#if error}
    <EstadoVacio tipo="error" titulo="No se pudo cargar la bitácora" texto={error}>
      {#snippet accion()}
        {#if onReintentar}<button type="button" class={btnPrimario} onclick={onReintentar}>Reintentar</button>{/if}
      {/snippet}
    </EstadoVacio>
  {:else}
    <EstadoVacio tipo="cargando" titulo="Cargando la bitácora…" />
  {/if}
{:else if entradas.length === 0}
  <EstadoVacio
    icono={History}
    titulo="Aún no hay actividad registrada"
    texto="Aquí se irán juntando tus entregas, los mensajes y las retroalimentaciones de todas las actividades del curso."
  />
{:else}
  <div class="flex flex-col gap-5 lg:gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-wrap items-end gap-4">
        <div class="flex flex-col gap-1.5 max-sm:w-full">
          <label for="bitacora-actividad" class="text-[13px] leading-[18px] font-semibold text-dm-ink-2">Actividad</label>
          <select id="bitacora-actividad" bind:value={filtroActividad} class="{campo} cursor-pointer sm:min-w-65 lg:min-h-10">
            <option value="">Todas las actividades</option>
            {#each actividades as a (a.id)}
              <option value={a.id}>{a.nombre}</option>
            {/each}
          </select>
        </div>
        {#if componentes.length > 1}
          <div class="flex flex-col gap-1.5">
            <span id="bitacora-componente" class="text-[13px] leading-[18px] font-semibold text-dm-ink-2">Componente</span>
            <div role="group" aria-labelledby="bitacora-componente" class="flex flex-wrap gap-2">
              {#each [{ id: '' as const, tipo: 'Todos' }, ...componentes] as c (c.id)}
                {@const activo = filtroComponente === c.id}
                <button
                  type="button"
                  aria-pressed={activo}
                  onclick={() => (filtroComponente = c.id)}
                  class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-semibold lg:min-h-10 {activo
                    ? 'border-dm-amarillo bg-dm-activo-suave text-dm-activo-texto'
                    : 'border-dm-control bg-dm-tarjeta text-dm-ink hover:bg-dm-sutil'}"
                >
                  {#if activo}<Check size={16} aria-hidden="true" />{/if}{c.tipo}
                </button>
              {/each}
            </div>
          </div>
        {/if}
      </div>
      <span class="text-[13px] leading-[18px] text-dm-ink-2" aria-live="polite">{filtradas.length} de {entradas.length} registros</span>
    </div>

    {#if dias.length === 0}
      <EstadoVacio titulo="No hay registros con esos filtros" texto="Prueba con otra actividad u otro componente.">
        {#snippet accion()}
          <button type="button" class={btnSecundario} onclick={limpiar}>Ver todos los registros</button>
        {/snippet}
      </EstadoVacio>
    {:else}
      <div class="flex max-w-215 flex-col gap-6">
        {#each dias as g (g.dia)}
          <section aria-labelledby="dia-{g.dia}" class="flex flex-col gap-2.5">
            <h3 id="dia-{g.dia}" class="{etiqueta} text-dm-ink-3">{formatDiaLargo(g.dia)}</h3>
            <ol class="ml-1.75 flex flex-col gap-2.5 border-l-2 border-dm-linea pl-4 lg:pl-6">
              {#each g.entradas as e (e.id_agenda)}
                {@const t = tipoDe(e.tipo)}
                <li class="relative">
                  <span
                    aria-hidden="true"
                    class="absolute top-4 -left-6 size-3.5 rounded-full border-3 border-dm-pagina lg:-left-8 {t.punto}"
                  ></span>
                  <Link
                    href={`/estudiante/cursos/${idCurso}/actividad/${e.actividad.id}?abrir=agenda`}
                    class="{tarjeta} flex flex-col gap-1.5 px-4 py-3.5 text-dm-ink no-underline hover:border-dm-control hover:text-dm-ink lg:px-4.5"
                  >
                    <span class="flex flex-wrap items-center gap-2">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[13px] leading-[18px] font-bold {t.pildora}">{t.etiqueta}</span>
                      <span class="text-[13px] leading-[18px] text-dm-ink-3 tabular-nums">{hora(e.fecha)}</span>
                      <span class="ml-auto inline-flex items-center gap-1 text-[13px] leading-[18px] font-semibold text-dm-enlace">
                        <span class="max-sm:sr-only">Ver en la agenda</span><ChevronRight size={16} aria-hidden="true" />
                      </span>
                    </span>
                    <span class="text-[15px] leading-5 font-semibold">
                      {e.actividad.nombre}{#if e.componente.tipo && componentes.length > 1}
                        <span class="font-normal text-dm-ink-2">{' '}· {e.componente.tipo}</span>{/if}
                    </span>
                    <span class="text-[13px] leading-[18px] text-dm-ink-2">{autor(e)}</span>
                    {#if e.archivo}
                      <span class="inline-flex max-w-full items-center gap-2 self-start rounded-lg bg-dm-sutil px-2.5 py-1.5 text-sm leading-5">
                        <FileText size={16} aria-hidden="true" class="shrink-0 text-dm-ink-2" /><span class="truncate font-semibold">{e.archivo}</span>
                      </span>
                    {/if}
                    {#if e.resultado}
                      <span class="text-sm leading-5">
                        {esNota(e.resultado) ? 'Nota' : 'Resultado'}:
                        <strong class="font-dm-cond text-xl font-black tabular-nums">{esNota(e.resultado) ? nota(e.resultado) : e.resultado}</strong>
                      </span>
                    {/if}
                    {#if e.mensaje}
                      <p class="line-clamp-3 text-[15px] leading-[22px] whitespace-pre-line">{e.mensaje}</p>
                    {/if}
                  </Link>
                </li>
              {/each}
            </ol>
          </section>
        {/each}
      </div>
    {/if}
  </div>
{/if}
