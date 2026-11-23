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
  /** Una persona del equipo y el resumen de la conversación del alumno con ella. */
  interface ConversacionItem {
    id_usuario: number;
    nombre: string;
    /** null: ya no está en el equipo (sólo lectura). */
    rol: 'Titular' | 'Docente' | 'Ayudante' | null;
    en_equipo: boolean;
    ultimo_mensaje: string | null;
    ultima_fecha: string | null;
    ultimo_es_mio: boolean;
    /** Contados antes de abrir el panel. */
    no_leidos: number;
  }
  interface Panel {
    id_componente: number;
    componente: string;
    difusiones: Difusion[];
    conversaciones: ConversacionItem[];
    /** La conversación abierta (la elige el servidor si no se pidió una). */
    conversacion: { id_usuario: number; mensajes: MensajeItem[] } | null;
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
   * Pestaña «Mensajería» de la ficha del curso (guía dm).
   *
   * El curso ya está elegido, así que aquí sólo se navegan sus componentes
   * (Cátedra, Laboratorio…), y dentro de cada uno hay dos vistas:
   *
   *  - Conversaciones : una privada con cada persona del equipo (titular,
   *                     docentes y ayudantes). Cada una la ven sólo el alumno y
   *                     esa persona (OBS-49).
   *  - Avisos         : los que el equipo publica al componente (sólo lectura).
   *
   * No pasa por agenda.agenda: las entregas y su feedback siguen en la actividad.
   *
   * Cambiar de componente o de conversación recarga sólo la prop `mensajeria`
   * con ?componente_id=…&interlocutor_id=… (y ?vista=avisos abre los avisos);
   * enviar hace POST a
   * /estudiante/cursos/{curso}/mensajeria/componentes/{componente}/mensaje.
   */
  import { page, router } from '@inertiajs/svelte';
  import { Check, LoaderCircle, MessageSquare, Megaphone, Send, Users } from 'lucide-svelte';
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

  // ?vista=avisos: el buzón del alumno abre directo los avisos del componente.
  let vista = $state<'conversacion' | 'avisos'>(
    new URL($page.url, 'http://x').searchParams.get('vista') === 'avisos' ? 'avisos' : 'conversacion',
  );
  let recargando = $state(false);

  let cuerpo = $state('');
  let tema = $state('');
  let enviando = $state(false);
  let errorEnvio = $state<string | null>(null);

  const conversaciones = $derived(panel?.conversaciones ?? []);
  const abierta = $derived(conversaciones.find((c) => c.id_usuario === panel?.conversacion?.id_usuario) ?? null);
  const mensajes = $derived(panel?.conversacion?.mensajes ?? []);

  // Sólo pide asunto cuando la conversación aún no tiene ninguno.
  const necesitaTema = $derived(mensajes.length === 0);
  // Los de la abierta se cuentan antes de abrirla: son los «Nuevo» de esta visita.
  const nuevosConversaciones = $derived(conversaciones.reduce((total, c) => total + c.no_leidos, 0));
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

  /** Papel de quien publicó un aviso, por el nombre (la difusión no trae el id). */
  function rolDe(nombre: string): string {
    return conversaciones.find((x) => x.nombre === nombre)?.rol ?? 'Docente';
  }

  function limpiarRedaccion() {
    cuerpo = '';
    tema = '';
    errorEnvio = null;
  }

  /** Los parámetros quedan en la URL: al volver de un envío se abre lo mismo. */
  function recargar(data: { componente_id: number; interlocutor_id?: number }) {
    recargando = true;
    router.reload({
      only: ['mensajeria'],
      // Sin interlocutor_id el servidor abre la conversación con algo sin leer.
      data: { interlocutor_id: undefined, ...data },
      onFinish: () => (recargando = false),
    });
  }

  function elegirComponente(id: number) {
    if (componente_activo === id) return;
    vista = 'conversacion';
    limpiarRedaccion();
    recargar({ componente_id: id });
  }

  function elegirConversacion(id: number) {
    if (!componente_activo || abierta?.id_usuario === id) return;
    limpiarRedaccion();
    recargar({ componente_id: componente_activo, interlocutor_id: id });
  }

  function enviar(e: SubmitEvent) {
    e.preventDefault();
    if (!componente_activo || !abierta?.en_equipo || !cuerpo.trim()) return;
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
        id_usuario_receptor: abierta.id_usuario,
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
          const e = errors as Record<string, string>;
          errorEnvio = e?.mensaje ?? e?.id_usuario_receptor ?? 'No se pudo enviar el mensaje.';
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
    <div class="flex flex-col gap-4" aria-busy={recargando || cargando}>
      <div role="tablist" aria-label="Tipo de mensajes" class="flex gap-1 rounded-xl border border-dm-linea bg-dm-sutil p-1">
        {#each [{ id: 'conversacion', etiqueta: 'Conversaciones con el equipo docente', corta: 'Conversaciones', cuenta: nuevosConversaciones }, { id: 'avisos', etiqueta: 'Avisos del curso', corta: 'Avisos', cuenta: nuevosAvisos }] as v (v.id)}
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
        {#if conversaciones.length === 0 || !abierta}
          <EstadoVacio
            icono={Users}
            titulo="Sin equipo docente"
            texto={`${panel.componente} todavía no tiene docentes asignados, así que no hay a quién escribir.`}
          />
        {:else}
          <div class="flex flex-col gap-4 lg:grid lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start lg:gap-6">
            <!-- En móvil la lista va primero; en escritorio queda a la derecha. -->
            <nav aria-labelledby="t-equipo" class="{tarjeta} overflow-hidden lg:col-start-2 lg:row-start-1">
              <h3 id="t-equipo" class="border-b border-dm-linea px-4 py-3.5 text-base leading-[22px] font-bold lg:px-5">
                Equipo · {panel.componente}
              </h3>
              <ul>
                {#each conversaciones as c (c.id_usuario)}
                  {@const activa = c.id_usuario === abierta.id_usuario}
                  <li class="border-b border-dm-linea">
                    <button
                      type="button"
                      aria-current={activa ? 'true' : undefined}
                      onclick={() => elegirConversacion(c.id_usuario)}
                      class="flex min-h-11 w-full cursor-pointer items-center gap-3 px-4 py-3 text-left lg:px-5 {activa
                        ? 'bg-dm-activo-suave'
                        : 'hover:bg-dm-sutil'}"
                    >
                      <span
                        aria-hidden="true"
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-(--curso-s) text-[13px] font-bold text-(--curso-t)"
                        >{initials(titleCase(c.nombre))}</span
                      >
                      <span class="flex min-w-0 flex-1 flex-col">
                        <span class="flex items-center gap-2">
                          <span class="truncate text-[15px] font-semibold">{titleCase(c.nombre)}</span>
                          {#if !activa && c.no_leidos > 0}
                            <span class="{PILDORA} ml-auto shrink-0 bg-dm-accion text-dm-sobre-accion">{c.no_leidos}<span class="sr-only">{' '}sin leer</span></span>
                          {/if}
                        </span>
                        <span class="text-[13px] leading-[18px] text-dm-ink-2">{c.rol ?? 'Ya no está en el equipo'}</span>
                        <span class="truncate text-[13px] leading-[18px] {c.ultimo_mensaje ? 'text-dm-ink-2' : 'text-dm-ink-3 italic'}">
                          {#if c.ultimo_mensaje}{c.ultimo_es_mio ? 'Tú: ' : ''}{c.ultimo_mensaje}{:else}Sin mensajes{/if}
                        </span>
                      </span>
                    </button>
                  </li>
                {/each}
              </ul>
              <p class="px-4 pt-3 pb-3.5 text-[13px] leading-[18px] text-dm-ink-2 lg:px-5 lg:pb-4">
                Cada conversación es privada: la ven sólo tú y esa persona.
              </p>
            </nav>

            <section aria-labelledby="t-conversacion" class="flex min-w-0 flex-col gap-4 lg:col-start-1 lg:row-start-1">
              <div class="flex flex-col gap-1">
                <h3 id="t-conversacion" class="flex flex-wrap items-center gap-2 text-lg leading-6 font-bold">
                  {titleCase(abierta.nombre)}
                  {#if abierta.rol}<span class="{PILDORA} bg-dm-sutil font-semibold text-dm-ink-2">{abierta.rol}</span>{/if}
                </h3>
                <p class="text-sm leading-5 text-dm-ink-2">Conversación privada: sólo la ven tú y {titleCase(abierta.nombre)}.</p>
              </div>

              {#if mensajes.length > 0}
                <div class="{tarjeta} flex flex-col gap-3 p-4 lg:p-5">
                  {#each mensajes as m, i (m.id_mensaje)}
                    {#if i === 0 || m.tema !== mensajes[i - 1].tema}
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
                        {#if m.es_mio || abierta.rol}
                          <span class="{PILDORA} bg-dm-sutil font-semibold text-dm-ink-2">{m.es_mio ? 'Tú' : abierta.rol}</span>
                        {/if}
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
                  <h4 class="text-base leading-[22px] font-bold">Aún no hay mensajes con {titleCase(abierta.nombre)}</h4>
                  <p class="max-w-105 text-sm leading-5 text-dm-ink-2">Escribe tu consulta abajo. Sólo la leerá esta persona.</p>
                </div>
              {/if}

              {#if abierta.en_equipo}
                <form class="{tarjeta} flex flex-col gap-3.5 p-4 lg:p-5" onsubmit={enviar}>
                  <h4 class="text-base leading-[22px] font-bold">Nuevo mensaje</h4>
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
                    <span class="text-[13px] leading-[18px] text-dm-ink-2">Sólo lo lee {titleCase(abierta.nombre)}.</span>
                    <button type="submit" disabled={enviando || !cuerpo.trim()} class="{btnPrimario} max-sm:w-full">
                      {#if enviando}<LoaderCircle size={18} aria-hidden="true" class="animate-spin" />{:else}<Send size={18} aria-hidden="true" />{/if}
                      Enviar mensaje
                    </button>
                  </div>
                </form>
              {:else}
                <p class="rounded-[10px] border border-dm-linea bg-dm-sutil px-4 py-3 text-sm leading-5 text-dm-ink-2">
                  {titleCase(abierta.nombre)} ya no está en el equipo de {panel.componente}: la conversación queda de sólo lectura.
                </p>
              {/if}
            </section>
          </div>
        {/if}
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
    </div>
  {/if}
</div>
