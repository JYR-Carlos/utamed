<script lang="ts" module>
  interface ComponenteItem {
    id_componente: number;
    tipo: string;
    no_leidos: number;
  }
  interface Difusion {
    id_mensaje: number;
    tema: string;
    mensaje: string;
    fecha_creacion: string;
    emisor: string;
    es_mio: boolean;
    /** Llegó sin abrir: se destaca en esta visita. */
    nuevo?: boolean;
  }
  interface MensajeItem {
    id_mensaje: number;
    tema: string;
    mensaje: string;
    fecha_creacion: string;
    emisor: string;
    receptor: string | null;
    es_alumno: boolean;
    es_mio: boolean;
    nuevo?: boolean;
  }
  interface DocenteItem {
    id_usuario: number;
    nombre: string;
    es_titular: boolean;
  }
  interface Panel {
    id_componente: number;
    componente: string;
    difusiones: Difusion[];
    mensajes: MensajeItem[];
    docentes: DocenteItem[];
  }

  /** Prop diferida `mensajeria` de la ficha (MensajeriaCursoEstudiante::bandeja). */
  export interface BandejaMensajeria {
    componentes: ComponenteItem[];
    componente_activo: number | null;
    panel: Panel | null;
  }
</script>

<script lang="ts">
  /**
   * Pestaña «Mensajería» de la ficha del curso (guía dm) — hilos sostenidos
   * por el componente.
   *
   * El curso ya está elegido, así que aquí sólo se navegan sus componentes
   * (Cátedra, Laboratorio…), y dentro de cada uno hay dos vistas:
   *
   *  - Conversación : la del alumno con ese equipo docente. Elige a quién
   *                   dirige el mensaje, pero lo ven todos, así que puede
   *                   responderle cualquiera sin abrir otra conversación.
   *  - Avisos       : los que el equipo publica al componente (sólo lectura).
   *
   * No pasa por agenda.agenda: las entregas y su feedback siguen en la actividad.
   *
   * Cambiar de componente recarga sólo la prop `mensajeria` con ?componente_id=…;
   * enviar hace POST a
   * /estudiante/cursos/{curso}/mensajeria/componentes/{componente}/mensaje.
   */
  import { router } from '@inertiajs/svelte';
  import { Check, LoaderCircle, MessageSquare, Megaphone, Send } from 'lucide-svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import { btnPrimario, campo, tarjeta } from '@/lib/dm';
  import { titleCase } from '@/lib/utils';
  import { formatDiaCorto, initials } from '@/utils/formatters';

  interface Props {
    idCurso: number;
    bandeja: BandejaMensajeria | null;
    /** La ficha está pidiendo la bandeja al abrir la pestaña. */
    cargando?: boolean;
    error?: string | null;
    onReintentar?: () => void;
  }

  let { idCurso, bandeja, cargando = false, error = null, onReintentar }: Props = $props();

  const componentes = $derived(bandeja?.componentes ?? []);
  const componente_activo = $derived(bandeja?.componente_activo ?? null);
  const panel = $derived(bandeja?.panel ?? null);

  let vista = $state<'conversacion' | 'avisos'>('conversacion');
  let recargando = $state(false);

  let cuerpo = $state('');
  let receptor = $state<number | null>(null);
  let tema = $state('');
  let enviando = $state(false);
  let errorEnvio = $state<string | null>(null);

  // Sólo pide asunto cuando la conversación aún no tiene ninguno.
  const necesitaTema = $derived((panel?.mensajes.length ?? 0) === 0);
  const titular = $derived(panel?.docentes.find((d) => d.es_titular) ?? panel?.docentes[0] ?? null);
  const nuevosConversacion = $derived(panel?.mensajes.filter((m) => m.nuevo).length ?? 0);
  const nuevosAvisos = $derived(panel?.difusiones.filter((d) => d.nuevo).length ?? 0);

  /** «Lun 19 oct, 21:10». */
  function fecha(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    const dia = formatDiaCorto(d);
    const hora = d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    return `${dia.charAt(0).toUpperCase()}${dia.slice(1)}, ${hora}`;
  }

  /** Titular o docente, por el nombre (el canal no trae el id del emisor). */
  function rolDe(nombre: string): string {
    const d = panel?.docentes.find((x) => x.nombre === nombre);
    return d?.es_titular ? 'Titular' : 'Docente';
  }

  function elegirComponente(id: number) {
    if (componente_activo === id) return;
    vista = 'conversacion';
    cuerpo = '';
    tema = '';
    receptor = null;
    errorEnvio = null;
    recargando = true;
    // componente_id queda en la URL: al volver de un envío se abre el mismo.
    router.reload({
      only: ['mensajeria'],
      data: { componente_id: id },
      onFinish: () => (recargando = false),
    });
  }

  function enviar(e: SubmitEvent) {
    e.preventDefault();
    if (!componente_activo || !cuerpo.trim()) return;
    if (necesitaTema && !tema.trim()) {
      errorEnvio = 'Escribe un asunto para iniciar la conversación.';
      return;
    }
    enviando = true;
    errorEnvio = null;
    router.post(
      `/estudiante/cursos/${idCurso}/mensajeria/componentes/${componente_activo}/mensaje`,
      {
        mensaje: cuerpo.trim(),
        tema: tema.trim() || null,
        id_usuario_receptor: receptor,
      },
      {
        // El servidor vuelve a la ficha; sólo se trae la bandeja actualizada.
        only: ['mensajeria'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          cuerpo = '';
          tema = '';
        },
        onError: (errors) => {
          errorEnvio = (errors as Record<string, string>)?.mensaje ?? 'No se pudo enviar el mensaje.';
        },
        onFinish: () => (enviando = false),
      },
    );
  }

  const PILDORA = 'rounded-full px-1.5 text-[13px] leading-[18px] font-bold';
  const NUEVO = 'rounded-full bg-dm-activo-suave px-2 text-[13px] leading-[18px] font-bold text-dm-activo-texto';
</script>

<div class="flex flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-[22px] leading-7 font-bold">Mensajería</h2>
    {#if componentes.length > 0}
      <div role="tablist" aria-label="Componente" class="flex flex-wrap items-center gap-2">
        {#each componentes as c (c.id_componente)}
          {@const activo = componente_activo === c.id_componente}
          <button
            type="button"
            role="tab"
            aria-selected={activo}
            onclick={() => elegirComponente(c.id_componente)}
            class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-semibold lg:min-h-9 {activo
              ? 'border-dm-amarillo bg-dm-activo-suave text-dm-activo-texto'
              : 'border-dm-control bg-dm-tarjeta text-dm-ink hover:bg-dm-sutil'}"
          >
            {#if activo}<Check size={16} aria-hidden="true" />{/if}{c.tipo}
            {#if c.no_leidos > 0}
              <span class="{PILDORA} bg-dm-accion text-dm-sobre-accion">{c.no_leidos} {c.no_leidos === 1 ? 'nuevo' : 'nuevos'}</span>
            {/if}
          </button>
        {/each}
        {#if recargando}<LoaderCircle size={18} aria-label="Cargando" class="animate-spin text-dm-ink-3" />{/if}
      </div>
    {/if}
  </div>

  {#if !bandeja}
    {#if error}
      <EstadoVacio tipo="error" titulo="No se pudo cargar la mensajería" texto={error}>
        {#snippet accion()}
          {#if onReintentar}<button type="button" class={btnPrimario} onclick={onReintentar}>Reintentar</button>{/if}
        {/snippet}
      </EstadoVacio>
    {:else}
      <EstadoVacio tipo="cargando" titulo="Cargando la mensajería…" />
    {/if}
  {:else if !panel}
    <EstadoVacio
      icono={MessageSquare}
      titulo="Sin mensajería en este curso"
      texto="No estás inscrito en ningún componente de este curso, así que no hay equipo docente a quien escribir."
    />
  {:else}
    <div class="flex flex-col gap-4 lg:grid lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start lg:gap-6" aria-busy={recargando || cargando}>
      <section aria-label="Mensajes de {panel.componente}" class="flex min-w-0 flex-col gap-4">
        <div role="tablist" aria-label="Tipo de mensajes" class="flex gap-1 rounded-xl border border-dm-linea bg-dm-sutil p-1">
          {#each [{ id: 'conversacion', etiqueta: 'Conversación con el equipo docente', corta: 'Conversación', cuenta: nuevosConversacion }, { id: 'avisos', etiqueta: 'Avisos del curso', corta: 'Avisos', cuenta: nuevosAvisos }] as v (v.id)}
            {@const activa = vista === v.id}
            <button
              type="button"
              role="tab"
              aria-selected={activa}
              onclick={() => (vista = v.id as typeof vista)}
              class="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-sm lg:min-h-10 lg:px-3 {activa
                ? 'bg-dm-tarjeta font-bold text-dm-ink shadow-[0_1px_2px_rgb(22_25_73/0.12)]'
                : 'font-semibold text-dm-ink-2 hover:text-dm-ink'}"
            >
              <span class="max-sm:hidden">{v.etiqueta}</span><span class="sm:hidden">{v.corta}</span>
              {#if v.cuenta > 0}
                <span class="{PILDORA} bg-dm-accion-suave text-dm-accion-texto">{v.cuenta}<span class="max-sm:sr-only">{' '}sin leer</span></span>
              {/if}
            </button>
          {/each}
        </div>

        {#if vista === 'conversacion'}
          {#if panel.mensajes.length > 0}
            <div class="{tarjeta} flex flex-col gap-3 p-4 lg:p-5">
              {#each panel.mensajes as m, i (m.id_mensaje)}
                {#if i === 0 || m.tema !== panel.mensajes[i - 1].tema}
                  <div class="flex items-center gap-2.5 text-center text-xs leading-4 font-bold tracking-[0.06em] text-dm-ink-3 uppercase lg:gap-3">
                    <span aria-hidden="true" class="h-px flex-1 bg-dm-linea"></span>{m.tema}<span aria-hidden="true" class="h-px flex-1 bg-dm-linea"></span>
                  </div>
                {/if}
                <article
                  class="flex max-w-[88%] flex-col gap-1.5 rounded-xl px-3.5 py-3 lg:max-w-[82%] lg:px-4 {m.es_mio
                    ? 'self-end bg-dm-accion-suave'
                    : 'self-start border border-dm-linea bg-dm-tarjeta'}"
                >
                  <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                    <span class="text-sm font-bold">{titleCase(m.emisor)}</span>
                    <span class="{PILDORA} bg-dm-sutil font-semibold text-dm-ink-2">{m.es_mio ? 'Tú' : rolDe(m.emisor)}</span>
                    <span class="text-[13px] leading-[18px] text-dm-ink-3 max-lg:hidden">{fecha(m.fecha_creacion)}</span>
                    {#if m.nuevo}<span class={NUEVO}>Nuevo</span>{/if}
                  </div>
                  <p class="text-[15px] leading-[22px] break-words whitespace-pre-wrap">{m.mensaje}</p>
                  <span class="text-[13px] leading-[18px] text-dm-ink-3 lg:hidden">{fecha(m.fecha_creacion)}</span>
                </article>
              {/each}
            </div>
          {:else}
            <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-dm-control bg-dm-tarjeta px-5 py-8 text-center lg:px-6 lg:py-10">
              <span class="flex size-12 items-center justify-center rounded-xl bg-dm-accion-suave text-dm-accion-texto">
                <MessageSquare size={22} aria-hidden="true" />
              </span>
              <h3 class="text-base leading-[22px] font-bold">Aún no hay mensajes en {panel.componente}</h3>
              <p class="max-w-105 text-sm leading-5 text-dm-ink-2">Escribe tu consulta abajo. La verá todo el equipo docente del componente.</p>
            </div>
          {/if}

          <form class="{tarjeta} flex flex-col gap-3.5 p-4 lg:p-5" onsubmit={enviar}>
            <h3 class="text-base leading-[22px] font-bold">Nuevo mensaje</h3>
            {#if panel.docentes.length > 1}
              <div class="flex flex-col gap-1.5">
                <label for="mensaje-receptor" class="text-sm font-semibold">Dirigido a</label>
                <select id="mensaje-receptor" bind:value={receptor} class="{campo} cursor-pointer">
                  <option value={null}>{titular ? `${titleCase(titular.nombre)} (titular)` : 'El titular'}</option>
                  {#each panel.docentes.filter((d) => d !== titular) as d (d.id_usuario)}
                    <option value={d.id_usuario}>{titleCase(d.nombre)}{d.es_titular ? ' (titular)' : ''}</option>
                  {/each}
                </select>
              </div>
            {/if}
            {#if necesitaTema}
              <div class="flex flex-col gap-1.5">
                <label for="mensaje-tema" class="text-sm font-semibold">Asunto</label>
                <input id="mensaje-tema" bind:value={tema} type="text" maxlength="150" class={campo} />
              </div>
            {/if}
            <div class="flex flex-col gap-1.5">
              <label for="mensaje-cuerpo" class="text-sm font-semibold">Mensaje</label>
              <textarea
                id="mensaje-cuerpo"
                bind:value={cuerpo}
                rows="4"
                maxlength="2000"
                placeholder="Escribe tu consulta…"
                class="block min-h-28 w-full resize-y rounded-[10px] border border-dm-control bg-dm-tarjeta px-3 py-2.5 font-dm text-[15px] leading-[22px] text-dm-ink placeholder:text-dm-ink-3 focus:border-dm-enlace focus:outline-2 focus:outline-offset-0 focus:outline-dm-enlace max-lg:text-base"
              ></textarea>
            </div>
            {#if errorEnvio}
              <p role="alert" class="rounded-[10px] border-l-4 border-dm-error bg-dm-error-s px-3.5 py-2.5 text-sm leading-5">{errorEnvio}</p>
            {/if}
            <div class="flex flex-wrap items-center justify-between gap-3">
              <span class="text-[13px] leading-[18px] text-dm-ink-2">Lo lee todo el equipo docente de {panel.componente}.</span>
              <button type="submit" disabled={enviando || !cuerpo.trim()} class="{btnPrimario} max-sm:w-full">
                {#if enviando}<LoaderCircle size={18} aria-hidden="true" class="animate-spin" />{:else}<Send size={18} aria-hidden="true" />{/if}
                Enviar mensaje
              </button>
            </div>
          </form>
        {:else}
          <div class="flex flex-col gap-3">
            <p class="text-sm leading-5 text-dm-ink-2">Los avisos los publica el equipo docente para todo el curso. Son de solo lectura.</p>
            {#if panel.difusiones.length === 0}
              <EstadoVacio icono={Megaphone} titulo="Sin avisos" texto={`El equipo docente todavía no publica avisos en ${panel.componente}.`} />
            {:else}
              {#each [...panel.difusiones].reverse() as a (a.id_mensaje)}
                <article class="{tarjeta} flex flex-col gap-1.5 border-l-4 border-l-(--curso) px-4 py-3.5 lg:px-5 lg:py-4">
                  <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="flex flex-wrap items-center gap-2 text-base leading-[22px] font-bold">
                      {a.tema}{#if a.nuevo}<span class={NUEVO}>Nuevo</span>{/if}
                    </h3>
                    <span class="text-[13px] leading-[18px] text-dm-ink-3">{fecha(a.fecha_creacion)}</span>
                  </div>
                  <p class="text-[15px] leading-[22px] break-words whitespace-pre-wrap">{a.mensaje}</p>
                  <span class="text-[13px] leading-[18px] text-dm-ink-2">{titleCase(a.emisor)} · {rolDe(a.emisor)}</span>
                </article>
              {/each}
            {/if}
          </div>
        {/if}
      </section>

      <aside class="flex min-w-0 flex-col gap-4">
        <section aria-labelledby="t-equipo" class="{tarjeta} overflow-hidden">
          <h3 id="t-equipo" class="border-b border-dm-linea px-4 py-3.5 text-base leading-[22px] font-bold lg:px-5">
            Equipo docente · {panel.componente}
          </h3>
          <ul>
            {#each panel.docentes as d (d.id_usuario)}
              <li class="flex items-center gap-3 border-b border-dm-linea px-4 py-3 lg:px-5">
                <span
                  aria-hidden="true"
                  class="flex size-9 shrink-0 items-center justify-center rounded-full bg-(--curso-s) text-[13px] font-bold text-(--curso-t)"
                  >{initials(titleCase(d.nombre))}</span
                >
                <span class="flex min-w-0 flex-col">
                  <span class="text-[15px] font-semibold">{titleCase(d.nombre)}</span>
                  <span class="text-[13px] leading-[18px] text-dm-ink-2">{d.es_titular ? 'Titular' : 'Docente'}</span>
                </span>
              </li>
            {/each}
          </ul>
          <p class="px-4 pt-3 pb-3.5 text-[13px] leading-[18px] text-dm-ink-2 lg:px-5 lg:pb-4">
            Todo el equipo docente del componente lee tus consultas y cualquiera puede responder.
          </p>
        </section>
      </aside>
    </div>
  {/if}
</div>
