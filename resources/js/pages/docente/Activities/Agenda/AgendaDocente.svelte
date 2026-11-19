<script lang="ts" module>
  export type IntegranteAgenda = {
    id_asignado_actividad: number;
    nombre_completo: string;
    diferencia_decimas: number | null;
    url_foto?: string | null;
  };

  /** Lo que la agenda pide guardar; el padre hace las peticiones. */
  export type EnvioAgenda = {
    tipo: 'Feedback' | 'Evaluación';
    mensaje: string;
    nota?: number;
    /** Resultado cualitativo con el que cierra una formativa. */
    evaluacion_obtenida?: string | null;
    resultado_rubrica?: Record<string, string>;
    puntaje_obtenido?: number;
    /** Sumativa grupal: décimas de cada integrante y la retroalimentación que las explica. */
    decimas?: Array<{ id_asignado_actividad: number; nombre: string; decimas: number; retro: string }>;
  };
</script>

<script lang="ts">
  /**
   * Agenda del estudiante (perspectiva docente), revisión «Mi UTAmed».
   *
   * Título con el nombre y su avatar; subtítulo curso · actividad · tipo. A la
   * izquierda el estado (No entregó → Por revisar/calificar → Revisado/
   * Calificado) y la conversación; a la derecha «Escribir» y «Evaluar».
   *
   * En una sumativa la matriz de evaluación está dentro de «Evaluar»: se elige
   * un nivel por criterio, la nota se calcula y en las grupales se replica a
   * los integrantes, con la opción de diferenciarla por décimas. Cada
   * integrante con décimas exige una retroalimentación que se publica en la
   * agenda al grabar. En una formativa se califica con Bueno, Regular o Malo.
   */
  import { router } from '@inertiajs/svelte';
  import { Check, ChevronRight, ClipboardCheck, Info, MessageSquare, Pencil, Save, Send, X } from 'lucide-svelte';
  import type { Rubrica } from '@/types/rubrica';
  import type { InteraccionItem } from '@/types/agenda';
  import AgendaHilo from '../../../student/Activities/Agenda/AgendaHilo.svelte';
  import RubricaView from '../../../student/Activities/Agenda/Rubrica.svelte';
  import { calcularNotaChilena } from '@/lib/notas';
  import { formatNota } from '@/utils/formatters';
  import {
    aviso,
    avisoTono,
    btnFantasma,
    btnPrimario,
    btnSecundario,
    campo,
    chico,
    chip,
    etiqueta,
    tarjeta,
    titulo as claseTitulo,
    tono,
  } from '@/lib/inst';
  import { textosEntrega, type EstadoEntrega } from '@/lib/estadoEntrega';
  import AvatarAlumno from '@/components/docente/AvatarAlumno.svelte';

  interface Props {
    /** Estudiante (o grupo) dueño de la agenda. */
    nombre: string;
    /** Curso · actividad · tipo (· grupo). */
    subtitulo: string;
    estado: EstadoEntrega;
    esSumativa: boolean;
    esGrupal: boolean;
    /** Rótulo del grupo en las grupales («Grupo 2»). */
    nombreGrupo?: string;
    integrantes?: IntegranteAgenda[];
    notaActual?: number | null;
    rubrica?: Rubrica | null;
    idGrupo: number;
    urlEntrega: (idAgenda: number, ver?: boolean) => string;
    listado_interacciones: InteraccionItem[];
    isLoading?: boolean;
    errorMensaje?: string | null;
    pestanaInicial?: 'escribir' | 'evaluar';
    /** A dónde vuelve el docente al cerrar. */
    pieCerrar: string;
    guardando?: boolean;
    onEnviar: (data: EnvioAgenda) => void;
    onCerrar: () => void;
    onCrearRubrica?: () => void;
  }

  let {
    nombre,
    subtitulo,
    estado,
    esSumativa,
    esGrupal,
    nombreGrupo = '',
    integrantes = [],
    notaActual = null,
    rubrica = null,
    idGrupo,
    urlEntrega,
    listado_interacciones,
    isLoading = false,
    errorMensaje = null,
    pestanaInicial = 'escribir',
    pieCerrar,
    guardando = false,
    onEnviar,
    onCerrar,
    onCrearRubrica,
  }: Props = $props();

  const corto = $derived(nombre.split(/\s+/)[0] ?? nombre);
  const fotoCabecera = $derived(!esGrupal ? (integrantes[0]?.url_foto ?? null) : null);
  const textos = $derived(textosEntrega(esSumativa));
  const pasos = $derived(
    (['no', 'pend', 'ok'] as EstadoEntrega[]).map((e, i, xs) => ({
      texto: textos[e],
      hecho: xs.indexOf(estado) > i || estado === 'ok',
      actual: e === estado,
    })),
  );

  // Mensajes nuevos y «Visto por» sin recargar la pantalla.
  $effect(() => {
    if (!idGrupo) return;
    const poll = router.poll(3000, {
      only: ['interaccionesGrupo'],
      data: { grupo_id: idGrupo },
      preserveUrl: true,
      replace: true,
      showProgress: false,
    });
    return () => poll.stop();
  });

  // ── Pestañas ───────────────────────────────────────────────────────────────
  let pestana = $state<'escribir' | 'evaluar'>('escribir');
  $effect.pre(() => {
    pestana = pestanaInicial;
  });

  // ── Escribir ───────────────────────────────────────────────────────────────
  let mensaje = $state('');

  function enviarMensaje() {
    if (!mensaje.trim()) return;
    onEnviar({ tipo: 'Feedback', mensaje: mensaje.trim() });
    mensaje = '';
  }

  // ── Evaluar · formativa ────────────────────────────────────────────────────
  type Opinion = 'Bueno' | 'Regular' | 'Malo';
  let opinion = $state<Opinion | null>(null);
  let retroFormativa = $state('');

  function guardarFormativa() {
    if (!opinion || !retroFormativa.trim()) return;
    onEnviar({ tipo: 'Evaluación', mensaje: retroFormativa.trim(), evaluacion_obtenida: opinion });
    opinion = null;
    retroFormativa = '';
  }

  // ── Evaluar · sumativa con la matriz ───────────────────────────────────────
  let seleccion = $state<Record<string, string>>({});
  let notaManual = $state<number | null>(null);
  let retroSumativa = $state('');

  const puntaje = $derived(
    rubrica?.niveles?.reduce((acc, n) => {
      const e = n.escalas.find((x) => x.id === seleccion[n.id]);
      return acc + (e ? e.puntos : 0);
    }, 0) ?? 0,
  );
  const puntajeMaximo = $derived(
    rubrica?.detalles_evaluacion?.puntaje_total ?? rubrica?.niveles?.reduce((a, n) => a + n.puntaje_total, 0) ?? 0,
  );
  const totalCriterios = $derived(rubrica?.niveles?.length ?? 0);
  const criteriosElegidos = $derived(rubrica?.niveles?.filter((n) => !!seleccion[n.id]).length ?? 0);
  const matrizCompleta = $derived(totalCriterios > 0 && criteriosElegidos === totalCriterios);
  const notaMatriz = $derived(
    matrizCompleta && puntajeMaximo > 0 ? calcularNotaChilena(puntaje, puntajeMaximo) : null,
  );
  const nota = $derived(notaManual ?? notaMatriz);

  // Décimas por integrante: en décimas enteras (−5 = −0,5 de nota).
  let diferenciar = $state(false);
  let decimas = $state<Record<number, number>>({});
  let retros = $state<Record<number, string>>({});
  let precargado = false;

  $effect.pre(() => {
    if (precargado) return;
    precargado = true;
    for (const i of integrantes) {
      const d = Math.round((i.diferencia_decimas ?? 0) * 10);
      decimas[i.id_asignado_actividad] = d;
      if (d !== 0) diferenciar = true;
    }
  });

  const notaDe = (d: number) => (nota === null ? null : Math.min(7, Math.max(1, Math.round((nota + d / 10) * 10) / 10)));
  const fmtDecimas = (d: number) => (d > 0 ? `+${d}` : d < 0 ? `−${Math.abs(d)}` : '0');

  function moverDecimas(id: number, delta: number) {
    decimas[id] = Math.max(-99, Math.min(99, (decimas[id] ?? 0) + delta));
  }

  const faltaRetro = $derived(
    diferenciar && integrantes.some((i) => (decimas[i.id_asignado_actividad] ?? 0) !== 0 && !retros[i.id_asignado_actividad]?.trim()),
  );
  const puedeGrabar = $derived(!!rubrica && matrizCompleta && nota !== null && !faltaRetro && !guardando);

  function grabarRubrica() {
    if (!puedeGrabar || nota === null) return;
    const data: EnvioAgenda = {
      tipo: 'Evaluación',
      mensaje: retroSumativa.trim(),
      nota,
      resultado_rubrica: { ...seleccion },
      puntaje_obtenido: puntaje,
    };
    if (esGrupal && integrantes.length > 0) {
      data.decimas = integrantes.map((i) => ({
        id_asignado_actividad: i.id_asignado_actividad,
        nombre: i.nombre_completo,
        decimas: diferenciar ? (decimas[i.id_asignado_actividad] ?? 0) : 0,
        retro: diferenciar ? (retros[i.id_asignado_actividad] ?? '').trim() : '',
      }));
    }
    onEnviar(data);
    seleccion = {};
    notaManual = null;
    retroSumativa = '';
    retros = {};
  }

  // ── Detalle de una evaluación anterior (desde el hilo) ─────────────────────
  type Detalle = { rubrica: Rubrica; puntaje_obtenido?: number; retroalimentacion?: string; resultado?: Record<string, string> | null };
  let detalle = $state<Detalle | null>(null);

  const BTN_TAB =
    'inline-flex min-h-11 cursor-pointer items-center gap-2 border-0 border-b-[3px] bg-transparent px-1 text-sm font-bold lg:min-h-12';
</script>

<svelte:window
  onkeydown={(e) => {
    if (e.key !== 'Escape') return;
    if (detalle) detalle = null;
    else onCerrar();
  }}
/>

<div
  class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-inst-velo p-2 sm:items-center sm:p-4"
  role="presentation"
  onclick={(e) => e.target === e.currentTarget && onCerrar()}
>
  <div
    role="dialog"
    aria-modal="true"
    aria-labelledby="t-agenda-docente"
    class="relative flex w-full flex-col overflow-hidden rounded-lg border border-inst-linea bg-inst-tarjeta text-inst-ink shadow-[0_18px_48px_rgba(16,24,40,.28)] lg:h-[min(92dvh,900px)] {esSumativa
      ? 'max-w-[1360px]'
      : 'max-w-[1100px]'}"
  >
    <!-- Cabecera: nombre con avatar; curso · actividad · tipo -->
    <div class="flex items-center gap-3.5 border-b border-inst-linea px-5 py-4">
      <AvatarAlumno
        src={fotoCabecera}
        {nombre}
        class="size-14 bg-gradient-to-br from-[#678FCA] to-[#303880] text-lg font-extrabold text-white shadow-[0_0_0_3px_var(--color-inst-tarjeta),0_0_0_4px_var(--color-inst-linea)]"
      />
      <div class="flex min-w-0 flex-1 flex-col gap-0.5">
        <h2 id="t-agenda-docente" class="{claseTitulo} m-0 text-[21px] leading-[26px] break-words">
          {`Agenda del estudiante · ${nombre}`}
        </h2>
        <span class="text-inst-ink-2">{subtitulo}</span>
      </div>
      <button type="button" class="{btnFantasma} size-11 shrink-0 px-0! lg:size-10" aria-label="Cerrar la agenda" onclick={onCerrar}>
        <X size={20} aria-hidden="true" />
      </button>
    </div>

    <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
      <!-- Conversación -->
      <section
        aria-label="Conversación"
        class="flex min-h-[420px] min-w-0 flex-col gap-4 bg-inst-pagina px-5 py-5 lg:min-h-0 {esSumativa
          ? 'lg:w-[360px] lg:shrink-0'
          : 'lg:flex-1'}"
      >
        <div class="flex flex-wrap items-center gap-2">
          <span class={etiqueta}>Estado</span>
          {#each pasos as p, i (p.texto)}
            {#if i > 0}<ChevronRight size={14} class="text-inst-ink-3" aria-hidden="true" />{/if}
            <span
              class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[13px] font-bold {p.actual
                ? 'border-inst-accion bg-inst-accion-s text-inst-accion-t'
                : p.hecho
                  ? 'border-inst-ok bg-inst-ok-s text-inst-ok'
                  : 'border-inst-linea bg-inst-tarjeta text-inst-ink-3'}"
              aria-current={p.actual ? 'step' : undefined}
            >
              {#if p.hecho && !p.actual}<Check size={14} aria-hidden="true" />{/if}
              {p.texto}
            </span>
          {/each}
        </div>
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-inst-linea bg-inst-tarjeta">
          <AgendaHilo
            {listado_interacciones}
            esDocente={true}
            urlDescargaEntrega={urlEntrega}
            onVerRubrica={(d) => {
              const rub = d.rubrica ?? rubrica;
              detalle = rub
                ? { rubrica: rub, puntaje_obtenido: d.puntaje_obtenido ?? undefined, retroalimentacion: d.retroalimentacion, resultado: d.resultado }
                : null;
            }}
            {isLoading}
            {errorMensaje}
          />
        </div>
      </section>

      <!-- Escribir o evaluar -->
      <section
        aria-label="Escribir o evaluar"
        class="flex min-h-0 min-w-0 flex-col border-t border-inst-linea lg:border-t-0 lg:border-l {esSumativa
          ? 'lg:flex-1'
          : 'lg:w-[440px] lg:shrink-0'}"
      >
        <div class="flex gap-6 border-b border-inst-linea px-5" role="tablist">
          <button
            type="button"
            role="tab"
            aria-selected={pestana === 'escribir'}
            class="{BTN_TAB} {pestana === 'escribir' ? 'border-inst-accion text-inst-ink' : 'border-transparent text-inst-ink-2 hover:text-inst-ink'}"
            onclick={() => (pestana = 'escribir')}
          >
            <Pencil size={16} aria-hidden="true" />Escribir
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={pestana === 'evaluar'}
            class="{BTN_TAB} {pestana === 'evaluar' ? 'border-inst-accion text-inst-ink' : 'border-transparent text-inst-ink-2 hover:text-inst-ink'}"
            onclick={() => (pestana = 'evaluar')}
          >
            <ClipboardCheck size={16} aria-hidden="true" />Evaluar
          </button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5" role="tabpanel">
          {#if pestana === 'escribir'}
            <label class="flex flex-col gap-1.5 font-bold">
              Escribe en la agenda de {corto}
              <textarea
                bind:value={mensaje}
                rows={7}
                maxlength={4000}
                placeholder="Escribe tu mensaje."
                class="{campo} min-h-[180px] resize-y font-normal"
              ></textarea>
            </label>
            <span class="self-end text-[13px] text-inst-ink-3">{mensaje.length.toLocaleString('es-CL')} / 4.000 caracteres</span>
            <button type="button" class="{btnPrimario} self-end" disabled={!mensaje.trim()} onclick={enviarMensaje}>
              <Send size={16} aria-hidden="true" />Enviar
            </button>
          {:else if !esSumativa}
            <div class="flex flex-col gap-2">
              <span class="font-bold">Califica el trabajo</span>
              <div class="inline-flex self-start overflow-hidden rounded-md border border-inst-control" role="group" aria-label="Califica el trabajo">
                {#each ['Bueno', 'Regular', 'Malo'] as op (op)}
                  <button
                    type="button"
                    aria-pressed={opinion === op}
                    class="min-h-11 border-0 border-r border-inst-linea px-4 text-sm font-bold last:border-r-0 lg:min-h-10 {opinion === op
                      ? 'bg-inst-accion text-inst-sobre-accion'
                      : 'bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil'}"
                    onclick={() => (opinion = op as Opinion)}
                  >
                    {op}
                  </button>
                {/each}
              </div>
            </div>
            <label class="flex flex-col gap-1.5 font-bold">
              Escribe la retroalimentación para la actividad formativa
              <textarea bind:value={retroFormativa} rows={6} maxlength={4000} class="{campo} min-h-[150px] resize-y font-normal"></textarea>
            </label>
            <div class="{aviso} {avisoTono.info} items-center py-2.5">
              <Info size={16} class="shrink-0 text-inst-info" aria-hidden="true" />
              <span class="text-[13px]">Al guardar la evaluación, el estado pasa a <strong>Revisado</strong>.</span>
            </div>
            <button
              type="button"
              class="{btnPrimario} self-end"
              disabled={!opinion || !retroFormativa.trim() || guardando}
              onclick={guardarFormativa}
            >
              Guardar evaluación
            </button>
          {:else if !rubrica}
            <div class="{aviso} {avisoTono.warn}">
              <span class="min-w-0 flex-1">
                <strong>Esta actividad no tiene rúbrica.</strong> Las sumativas se califican con la matriz: créala primero.
              </span>
              {#if onCrearRubrica}
                <button type="button" class="{btnPrimario} {chico}" onclick={onCrearRubrica}>Crear rúbrica</button>
              {/if}
            </div>
          {:else}
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
              <span class="{claseTitulo} text-base">Matriz de evaluación</span>
              <span class="text-[13px] text-inst-ink-2">Elige un nivel por criterio · {criteriosElegidos} de {totalCriterios}</span>
            </div>
            <RubricaView
              {rubrica}
              resultado={seleccion}
              puntaje_obtenido={puntaje}
              onSeleccionarEscala={(nivel, escala) => {
                seleccion[nivel] = escala;
                notaManual = null;
              }}
              modoLectura={false}
              esSumativa={true}
            />

            <div class="flex flex-wrap items-start gap-4">
              <div class="{tarjeta} flex flex-[1_1_220px]">
                <div class="flex flex-1 flex-col gap-0.5 border-r border-inst-linea px-4 py-3">
                  <span class={etiqueta}>Puntaje</span>
                  <span class="{claseTitulo} text-[26px] leading-8">
                    {puntaje}<span class="text-sm font-normal text-inst-ink-2"> / {puntajeMaximo}</span>
                  </span>
                </div>
                <label class="flex flex-1 flex-col gap-0.5 px-4 py-3">
                  <span class={etiqueta}>Nota</span>
                  <input
                    type="number"
                    min="1"
                    max="7"
                    step="0.1"
                    value={nota ?? ''}
                    oninput={(e) => {
                      const v = (e.currentTarget as HTMLInputElement).valueAsNumber;
                      notaManual = Number.isFinite(v) ? v : null;
                    }}
                    placeholder="—"
                    aria-describedby="nota-ayuda"
                    class="w-20 border-0 bg-transparent p-0 font-inst-titulo text-[26px] leading-8 font-bold text-inst-ink focus:outline-2 focus:outline-inst-foco"
                  />
                  <span id="nota-ayuda" class="text-xs text-inst-ink-3">
                    {#if notaManual !== null && notaMatriz !== null}
                      <button type="button" class="cursor-pointer border-0 bg-transparent p-0 text-xs font-bold text-inst-enlace underline" onclick={() => (notaManual = null)}>
                        Volver a {formatNota(notaMatriz)}
                      </button>
                    {:else}
                      {matrizCompleta ? 'calculada con la matriz' : 'completa la matriz'}
                    {/if}
                  </span>
                </label>
              </div>

              {#if esGrupal && integrantes.length > 0}
                <fieldset class="{tarjeta} m-0 min-w-0 flex-[2_1_440px] p-0">
                  <legend class="sr-only">Nota de los integrantes de {nombreGrupo}</legend>
                  <div class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1 border-b border-inst-linea px-3.5 py-3">
                    <span class="font-bold">Nota del {nombreGrupo}</span>
                    <span class="text-[13px] text-inst-ink-2">
                      {nota !== null
                        ? `la matriz da ${formatNota(nota)} y se replica a ${integrantes.length === 1 ? 'su integrante' : `los ${integrantes.length} integrantes`}`
                        : 'se replica a los integrantes al grabar'}
                    </span>
                  </div>
                  <ul class="m-0 list-none px-3.5 py-1">
                    {#each integrantes as i (i.id_asignado_actividad)}
                      {@const d = diferenciar ? (decimas[i.id_asignado_actividad] ?? 0) : 0}
                      {@const n = notaDe(d)}
                      {@const nombreCorto = i.nombre_completo.split(/\s+/)[0]}
                      <li class="flex items-center gap-2.5 border-b border-inst-linea py-2 last:border-b-0">
                        <AvatarAlumno src={i.url_foto ?? null} nombre={i.nombre_completo} class="size-8 bg-inst-sel text-xs font-extrabold text-inst-ink" />
                        <span class="min-w-0 flex-1">{i.nombre_completo}</span>
                        {#if diferenciar}
                          <span class="inline-flex items-center overflow-hidden rounded-md border border-inst-control" role="group" aria-label="Décimas de {i.nombre_completo}">
                            <button type="button" class="size-9 border-0 bg-inst-tarjeta text-lg font-bold hover:bg-inst-sutil" aria-label="Bajar una décima" onclick={() => moverDecimas(i.id_asignado_actividad, -1)}>−</button>
                            <span class="min-w-9 text-center text-sm font-extrabold {d ? 'text-inst-warn' : ''}">{fmtDecimas(d)}</span>
                            <button type="button" class="size-9 border-0 bg-inst-tarjeta text-lg font-bold hover:bg-inst-sutil" aria-label="Subir una décima" onclick={() => moverDecimas(i.id_asignado_actividad, 1)}>+</button>
                          </span>
                        {/if}
                        <span class="{claseTitulo} w-10 text-right text-[17px] {d ? 'text-inst-warn' : ''}">{n !== null ? formatNota(n) : '—'}</span>
                      </li>
                      {#if diferenciar && d !== 0}
                        <li class="pb-3 pl-[42px]">
                          <div class="flex flex-col gap-2 rounded-md border border-l-4 border-inst-linea border-l-inst-warn bg-inst-warn-s px-3.5 py-3">
                            <span class="flex flex-wrap items-center gap-2">
                              <MessageSquare size={16} class="text-inst-warn" aria-hidden="true" />
                              <span class="font-bold">Retroalimentación para {nombreCorto}</span>
                              <span class="{chip} {tono.warn}">Se activó al {d < 0 ? 'bajar' : 'subir'} {Math.abs(d)} {Math.abs(d) === 1 ? 'décima' : 'décimas'}</span>
                            </span>
                            <textarea
                              bind:value={retros[i.id_asignado_actividad]}
                              rows={2}
                              maxlength={2000}
                              aria-label="Retroalimentación para {i.nombre_completo}"
                              placeholder="Explica por qué {d < 0 ? 'bajas' : 'subes'} {Math.abs(d)} {Math.abs(d) === 1 ? 'décima' : 'décimas'} a {nombreCorto}"
                              class="{campo} min-h-16 resize-y bg-inst-tarjeta"
                            ></textarea>
                            <span class="text-[13px] text-inst-ink-2">
                              Obligatoria mientras haya décimas. Se publica en la agenda del grupo al grabar la evaluación.
                            </span>
                          </div>
                        </li>
                      {/if}
                    {/each}
                  </ul>
                  <div class="flex flex-col gap-0.5 rounded-b-lg border-t border-inst-linea bg-inst-sutil px-3.5 py-2.5">
                    <label class="inline-flex cursor-pointer items-center gap-2.5 font-bold">
                      <input type="checkbox" role="switch" bind:checked={diferenciar} class="peer sr-only" />
                      <span
                        aria-hidden="true"
                        class="relative h-6 w-10 shrink-0 rounded-full bg-inst-control transition-colors peer-checked:bg-inst-accion peer-focus-visible:outline-2 peer-focus-visible:outline-inst-foco after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-4"
                      ></span>
                      Diferenciar la nota en el grupo
                    </label>
                    <span class="pl-[50px] text-[13px] text-inst-ink-2">
                      Sube o baja décimas a un integrante; al hacerlo se activa su retroalimentación.
                    </span>
                  </div>
                </fieldset>
              {/if}
            </div>

            <label class="flex flex-col gap-1.5 font-bold">
              Escribe la retroalimentación para la actividad sumativa
              <textarea bind:value={retroSumativa} rows={3} maxlength={4000} class="{campo} resize-y font-normal"></textarea>
            </label>
            <div class="flex flex-wrap items-center gap-3">
              <span class="text-[13px] text-inst-ink-2">
                {#if faltaRetro}
                  Falta la retroalimentación de quienes tienen décimas.
                {:else if notaActual !== null}
                  Ya tiene nota {formatNota(notaActual)}; grabar la reemplaza.
                {:else}
                  Al grabar, {esGrupal ? nombreGrupo : corto} pasa a «Calificado».
                {/if}
              </span>
              <button type="button" class="{btnPrimario} ml-auto" disabled={!puedeGrabar} onclick={grabarRubrica}>
                <Save size={16} aria-hidden="true" />Grabar rúbrica
              </button>
            </div>
          {/if}
        </div>
      </section>
    </div>

    <div class="flex flex-wrap items-center gap-2 border-t border-inst-linea bg-inst-sutil px-5 py-3">
      <span class="text-[13px] text-inst-ink-2">{pieCerrar}</span>
      <button type="button" class="{btnSecundario} ml-auto" onclick={onCerrar}>Cerrar</button>
    </div>

    {#if detalle}
      <div class="absolute inset-0 z-10 flex flex-col bg-inst-tarjeta" role="dialog" aria-modal="true" aria-labelledby="t-detalle-eval">
        <div class="flex items-center gap-3 border-b border-inst-linea px-5 py-4">
          <h3 id="t-detalle-eval" class="{claseTitulo} m-0 flex-1 text-lg">Detalle de la evaluación</h3>
          <button type="button" class="{btnSecundario} {chico}" onclick={() => (detalle = null)}>Volver a la agenda</button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto p-5">
          <RubricaView
            rubrica={detalle.rubrica}
            puntaje_obtenido={detalle.puntaje_obtenido ?? 0}
            retroalimentacion={detalle.retroalimentacion}
            resultado={detalle.resultado}
            modoLectura={true}
            {esSumativa}
          />
        </div>
      </div>
    {/if}
  </div>
</div>
