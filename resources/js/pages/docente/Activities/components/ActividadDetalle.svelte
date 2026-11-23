<script lang="ts" module>
  export type IntegranteData = {
    id_asignado_actividad: number;
    id_estudiante: number;
    nombre_completo: string;
    nota_individual: number | null;
    diferencia_decimas: number | null;
    url_foto?: string | null;
  };

  export type GrupoData = {
    grupo: number;
    nombre_grupo?: string | null;
    /** Nombre del estudiante (individual) o nombre propio / «Grupo N» (grupal). */
    etiqueta: string;
    nota: number | null;
    estado_actividad_asignada: string | null;
    nro_dias_adicionales_para_bloqueo_personal: number;
    no_leidos: number;
    con_feedback: boolean;
    entrega: { id_agenda: number; fecha: string; fuera_de_plazo: boolean; cantidad: number } | null;
    integrantes: IntegranteData[];
  };

  export type ActividadAbierta = {
    id_actividad: number;
    nombre: string;
    descripcion: string;
    fecha_limite: string | null;
    es_sumativa: boolean;
    trae_archivo: boolean;
    es_grupal: boolean;
    max_integrantes: number | null;
    es_titular: boolean;
    nro_dias_adicionales_para_bloqueo: number;
    archivo_enunciado: {
      nombre_original: string;
      mime_type: string | null;
      peso_bytes: number | null;
    } | null;
  };
</script>

<script lang="ts">
  /**
   * La actividad desplegada dentro de su fila (revisión «Mi UTAmed»): lo que
   * antes era la hoja de la actividad. Fechas, enunciado y rúbrica arriba; la
   * tabla de estudiantes con su estado, entrega, nota, «Ver agenda» y
   * «Acciones personales» (Calificar y Holgura). Todo lo que se abre desde aquí
   * es un modal sobre Actividades, con la actividad desplegada detrás.
   */
  import { onMount } from 'svelte';
  import { Link, page, router } from '@inertiajs/svelte';
  import {
    ChevronDown,
    ClipboardCheck,
    Clock,
    FileText,
    Info,
    ListChecks,
    Lock,
    MessageSquare,
    Pencil,
    Printer,
    Send,
    Upload,
  } from 'lucide-svelte';
  import type { Rubrica } from '@/types/rubrica';
  import type { InteraccionItem } from '@/types/agenda';
  import type { Actividad } from '@/types/actividad';
  import ModalInst from '@/components/docente/ModalInst.svelte';
  import RubricaMatriz from '@/components/docente/RubricaMatriz.svelte';
  import { imprimirRubrica } from '@/lib/imprimirRubrica';
  import { puntajeMinimoAprobacion } from '@/lib/notas';
  import EnunciadoModal from '@/components/docente/EnunciadoModal.svelte';
  import AgendaDocente, { type EnvioAgenda } from '../Agenda/AgendaDocente.svelte';
  import {
    conOptimistas,
    fmtDecimas as fmtDec,
    guardarEvaluacion,
    mensajeOptimista,
    primerError,
    urlFeedback,
    visitaInertia,
  } from '../Agenda/guardarAgenda';
  import RubricaEditor from '../RubricaEditor.svelte';
  import EntregasModal from './EntregasModal.svelte';
  import HolguraModal from './HolguraModal.svelte';
  import {
    aviso,
    avisoTono,
    btnPrimario,
    btnSecundario,
    campo,
    chico,
    chip,
    etiqueta,
    tarjeta,
    tarjetaCabecera,
    tarjetaPie,
    titulo,
    tono,
  } from '@/lib/inst';
  import {
    estadoEntrega,
    textosEntrega,
    TONO_ENTREGA,
    type EstadoEntrega,
  } from '@/lib/estadoEntrega';
  import AvatarAlumno from '@/components/docente/AvatarAlumno.svelte';
  import { formatNota } from '@/utils/formatters';

  interface Props {
    curso: {
      id_curso: number;
      nombre: string;
      etiqueta?: string;
      asignatura_nombre?: string | null;
    };
    actividad: ActividadAbierta;
    /** La misma actividad tal como viene en la lista (ponderación, visibilidad). */
    fila: Actividad;
    grupos: GrupoData[];
    rubrica: Rubrica | null;
    rubrica_id: number | null;
    tiene_evaluaciones: boolean;
    puede_editar_rubrica: boolean;
    interaccionesGrupo?: InteraccionItem[];
    reglas_enunciado: {
      extensiones_pdf: string[];
      extensiones_img: string[];
      max_mb_pdf: number;
      max_mb_imagen: number;
    };
    puedeEditar: boolean;
    onEditar: () => void;
  }

  let {
    curso,
    actividad,
    fila,
    grupos,
    rubrica,
    rubrica_id,
    tiene_evaluaciones,
    puede_editar_rubrica,
    interaccionesGrupo = [],
    reglas_enunciado,
    puedeEditar,
    onEditar,
  }: Props = $props();

  const base = $derived(`/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}`);
  const textos = $derived(textosEntrega(actividad.es_sumativa));
  const tipoTexto = $derived(actividad.es_sumativa ? 'Sumativa' : 'Formativa');

  // ── Fechas ────────────────────────────────────────────────────────────────
  const DIA = 86_400_000;
  const fmtCierre = new Intl.DateTimeFormat('es-CL', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
  });
  const fmtEntrega = new Intl.DateTimeFormat('es-CL', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Santiago',
  });
  const utc = (iso: string) => {
    const [a, m, d] = iso.slice(0, 10).split('-').map(Number);
    return Date.UTC(a, m - 1, d);
  };
  const holguraHasta = (g: GrupoData) =>
    actividad.fecha_limite
      ? fmtCierre.format(
          utc(actividad.fecha_limite) +
            (actividad.nro_dias_adicionales_para_bloqueo +
              g.nro_dias_adicionales_para_bloqueo_personal) *
              DIA,
        )
      : null;

  // ── Filas: una por estudiante (en las grupales, cada integrante con su grupo) ──
  type Fila = {
    clave: string;
    nombre: string;
    grupo: GrupoData;
    integrante: IntegranteData | null;
    estado: EstadoEntrega;
  };

  const filas = $derived.by((): Fila[] => {
    const out: Fila[] = [];
    for (const g of grupos) {
      const estado = estadoEntrega(g, actividad.es_sumativa, actividad.trae_archivo);
      if (g.integrantes.length === 0) {
        out.push({ clave: `g${g.grupo}`, nombre: g.etiqueta, grupo: g, integrante: null, estado });
        continue;
      }
      for (const i of g.integrantes) {
        out.push({
          clave: `g${g.grupo}-${i.id_asignado_actividad}`,
          nombre: i.nombre_completo,
          grupo: g,
          integrante: i,
          estado,
        });
      }
    }
    return out;
  });

  const conteo = $derived(
    filas.reduce((c, f) => ((c[f.estado] += 1), c), { ok: 0, pend: 0, no: 0 } as Record<
      EstadoEntrega,
      number
    >),
  );

  let filtro = $state<'todos' | EstadoEntrega>('todos');
  let verTodos = $state(false);
  const LIMITE = 10;
  const filtradas = $derived(filtro === 'todos' ? filas : filas.filter((f) => f.estado === filtro));
  const visibles = $derived(verTodos ? filtradas : filtradas.slice(0, LIMITE));

  function notaDeFila(f: Fila): number | null {
    return f.integrante?.nota_individual ?? f.grupo.nota;
  }

  // ── Mensajes no leídos por grupo (se apagan al abrir su agenda) ───────────
  let agendasAbiertas = $state<Set<number>>(new Set());
  const noLeidosDe = (g: GrupoData) => (agendasAbiertas.has(g.grupo) ? 0 : g.no_leidos);

  // ── Agenda ────────────────────────────────────────────────────────────────
  let agenda = $state<{ grupo: GrupoData; nombre: string; pestana: 'escribir' | 'evaluar' } | null>(
    null,
  );
  let cargandoHilo = $state(false);
  let errorHilo = $state<string | null>(null);
  let hilo = $state<InteraccionItem[]>([]);
  let optimistas = $state<InteraccionItem[]>([]);
  let guardando = $state(false);

  $effect(() => {
    if (Array.isArray(interaccionesGrupo)) hilo = [...interaccionesGrupo];
  });

  const hiloVisible = $derived(conOptimistas(hilo, optimistas));

  function abrirHolgura(f: Fila) {
    // Recuperar los datos antes de cerrar el menú.
    const grupo = f.grupo;
    const nombre = f.nombre;

    menuAbierto = null;

    holguraDe = { grupo, nombre };
  }
  /** El grupo abierto, con los datos frescos que vuelven tras cada guardado. */
  const grupoAgenda = $derived(
    agenda ? (grupos.find((g) => g.grupo === agenda!.grupo.grupo) ?? agenda.grupo) : null,
  );

  function abrirAgenda(g: GrupoData, nombre: string, pestana: 'escribir' | 'evaluar' = 'escribir') {
    agendasAbiertas = new Set([...agendasAbiertas, g.grupo]);
    if (agenda?.grupo.grupo !== g.grupo) {
      hilo = [];
      optimistas = [];
    }
    agenda = { grupo: g, nombre, pestana };
    cargandoHilo = hilo.length === 0;
    errorHilo = null;
    router.reload({
      only: ['interaccionesGrupo'],
      data: { grupo_id: g.grupo },
      preserveUrl: true,
      replace: true,
      showProgress: false,
      onSuccess: () => (cargandoHilo = false),
      onError: () => {
        errorHilo = 'No se pudieron cargar los mensajes.';
        cargandoHilo = false;
      },
    });
  }

  function cerrarAgenda() {
    agenda = null;
    hilo = [];
    optimistas = [];
  }

  const visita = visitaInertia(['interaccionesGrupo', 'grupos', 'actividades', 'flash']);

  async function enviarDesdeAgenda(data: EnvioAgenda) {
    if (!agenda) return;
    const g = grupoAgenda ?? agenda.grupo;

    if (data.tipo === 'Feedback') {
      const temporal = mensajeOptimista(($page.props as any).auth?.user, data.mensaje);
      optimistas = [...optimistas, temporal];
      visita('post', urlFeedback(curso.id_curso, g.grupo), { mensaje: data.mensaje })
        .catch(() => (errorHilo = 'No se pudo enviar el mensaje.'))
        .finally(
          () =>
            (optimistas = optimistas.filter((o) => o.id_interaccion !== temporal.id_interaccion)),
        );
      return;
    }

    guardando = true;
    errorHilo = null;
    try {
      await guardarEvaluacion(visita, {
        idCurso: curso.id_curso,
        idActividad: actividad.id_actividad,
        grupo: g,
        esSumativa: actividad.es_sumativa,
        rubricaId: rubrica_id,
        envio: data,
      });
    } catch (e) {
      errorHilo = primerError(e, 'No se pudo guardar la evaluación.');
    } finally {
      guardando = false;
    }
  }

  // ── Acciones personales ──────────────────────────────────────────────────
  let menuAbierto = $state<{
    fila: Fila;
    top: number;
    bottom: number;
    right: number;
    openUpwards: boolean;
  } | null>(null);
  let entregasDe = $state<{ grupo: GrupoData; nombre: string } | null>(null);
  let holguraDe = $state<{ grupo: GrupoData; nombre: string } | null>(null);
  let guardandoHolgura = $state(false);

  function toggleMenuPersonal(f: Fila, e: MouseEvent) {
    if (menuAbierto?.fila.clave === f.clave) {
      menuAbierto = null;
      return;
    }
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const openUpwards = rect.bottom + 120 > window.innerHeight && rect.top > 120;
    menuAbierto = {
      fila: f,
      top: rect.bottom + 4,
      bottom: window.innerHeight - rect.top + 4,
      right: window.innerWidth - rect.right,
      openUpwards,
    };
  }

  function calificar(f: Fila) {
    menuAbierto = null;
    if (actividad.trae_archivo && f.grupo.entrega)
      entregasDe = { grupo: f.grupo, nombre: f.nombre };
    else abrirAgenda(f.grupo, f.nombre, 'evaluar');
  }

  function guardarHolgura(dias: number) {
    if (!holguraDe) return;
    guardandoHolgura = true;
    router.patch(
      `${base}/grupos/${holguraDe.grupo.grupo}`,
      { nro_dias_adicionales_para_bloqueo_personal: dias },
      {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => (holguraDe = null),
        onFinish: () => (guardandoHolgura = false),
      },
    );
  }

  // ── Mensaje a todos ──────────────────────────────────────────────────────
  let mensajeTodos = $state<string | null>(null);
  let enviandoTodos = $state(false);
  let errorTodos = $state<string | null>(null);

  function enviarATodos() {
    if (!mensajeTodos?.trim()) return;
    enviandoTodos = true;
    errorTodos = null;
    router.post(
      `/docente/mensajes/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/enviar`,
      { destino: 'todos', mensaje: mensajeTodos.trim() },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (mensajeTodos = null),
        onError: (e) =>
          (errorTodos = (e as Record<string, string>).mensaje ?? 'No se pudo enviar el mensaje.'),
        onFinish: () => (enviandoTodos = false),
      },
    );
  }

  // ── Rúbrica y enunciado ──────────────────────────────────────────────────
  // «Ver rúbrica» abre la tabla entera; desde ahí se edita, si todavía se puede.
  let verRubrica = $state(false);
  let errorImprimir = $state(false);
  let editorRubrica = $state(false);
  let showEnunciado = $state(false);
  const puntajeRubrica = $derived(
    rubrica?.detalles_evaluacion?.puntaje_total ??
      rubrica?.niveles?.reduce((a, n) => a + n.puntaje_total, 0) ??
      0,
  );

  // Desde la bandeja (?grupo=) se abre esa agenda; desde el menú «⋯» (?rubrica=1), el editor.
  onMount(() => {
    const q = new URLSearchParams($page.url.split('?')[1] ?? '');
    const g = grupos.find((x) => x.grupo === Number(q.get('grupo')));
    if (g)
      abrirAgenda(
        g,
        actividad.es_grupal ? (g.integrantes[0]?.nombre_completo ?? g.etiqueta) : g.etiqueta,
      );
    if (q.get('rubrica') === '1' && actividad.es_sumativa) editorRubrica = true;
  });

  const TH =
    'px-4 py-2.5 text-left text-xs font-extrabold tracking-[0.06em] text-inst-ink-3 uppercase';
</script>

<svelte:window
  onclick={(e) => {
    if (menuAbierto && !(e.target as HTMLElement).closest('[data-menu-personal]'))
      menuAbierto = null;
  }}
  onscroll={() => {
    if (menuAbierto) menuAbierto = null;
  }}
  onkeydown={(e) => {
    if (e.key === 'Escape' && menuAbierto) menuAbierto = null;
  }}
/>

<!-- Fechas, ponderación y acciones de la actividad -->
<div class="flex flex-wrap items-end gap-x-7 gap-y-2.5">
  <span class="flex flex-col">
    <span class={etiqueta}>Cierra</span>
    <span class="font-bold"
      >{actividad.fecha_limite
        ? `${fmtCierre.format(utc(actividad.fecha_limite))}, 23:59`
        : 'Sin fecha límite'}</span
    >
  </span>
  {#if actividad.nro_dias_adicionales_para_bloqueo > 0}
    <span class="flex flex-col">
      <span class={etiqueta}>Holgura general</span>
      <span class="font-bold"
        >{actividad.nro_dias_adicionales_para_bloqueo}
        {actividad.nro_dias_adicionales_para_bloqueo === 1 ? 'día' : 'días'}</span
      >
    </span>
  {/if}
  <span class="flex flex-col">
    <span class={etiqueta}>Ponderación</span>
    <span class="font-bold"
      >{actividad.es_sumativa && fila.ponderacion ? `${fila.ponderacion} %` : 'Sin nota'}</span
    >
  </span>
  <span class="ml-auto flex flex-wrap gap-2">
    {#if puedeEditar}
      <button type="button" class="{btnSecundario} {chico}" onclick={onEditar}
        ><Pencil size={16} aria-hidden="true" />Editar actividad</button
      >
    {/if}
    {#if actividad.es_titular && grupos.length > 0}
      <button
        type="button"
        class="{btnSecundario} {chico}"
        onclick={() => ((mensajeTodos = ''), (errorTodos = null))}
      >
        <MessageSquare size={16} aria-hidden="true" />Mensaje a todos
      </button>
    {/if}
  </span>
</div>

<!-- Enunciado y rúbrica -->
<div class="flex flex-wrap gap-3">
  <div class="{tarjeta} flex flex-[1_1_320px] items-center gap-3 px-3.5 py-2.5">
    <span
      aria-hidden="true"
      class="flex size-9 shrink-0 items-center justify-center rounded-md bg-inst-error-s text-inst-error"
      ><FileText size={18} /></span
    >
    <span class="flex min-w-0 flex-1 flex-col">
      <span class={etiqueta}>Enunciado</span>
      <span class="font-bold break-all"
        >{actividad.archivo_enunciado?.nombre_original ??
          (actividad.descripcion ? 'Sólo instrucciones escritas' : 'Sin enunciado')}</span
      >
    </span>
    {#if actividad.archivo_enunciado}
      <a
        href="{base}/enunciado/descargar"
        target="_blank"
        rel="noopener noreferrer"
        class="{btnSecundario} {chico}">Ver enunciado</a
      >
    {:else if actividad.es_titular}
      <button type="button" class="{btnSecundario} {chico}" onclick={() => (showEnunciado = true)}
        ><Upload size={16} aria-hidden="true" />Subir</button
      >
    {/if}
  </div>
  {#if actividad.es_sumativa}
    <div class="{tarjeta} flex flex-[1_1_320px] items-center gap-3 px-3.5 py-2.5">
      <span
        aria-hidden="true"
        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-inst-accion-s text-inst-accion-t"
        ><ListChecks size={18} /></span
      >
      <span class="flex min-w-0 flex-1 flex-col">
        <span class={etiqueta}>Rúbrica</span>
        <span class="font-bold">
          {rubrica
            ? `${rubrica.niveles?.length ?? 0} criterios · ${puntajeRubrica} puntos`
            : 'Todavía no se crea'}
        </span>
        {#if rubrica && tiene_evaluaciones}
          <span class="inline-flex items-center gap-1 text-xs text-inst-ink-2"
            ><Lock size={12} aria-hidden="true" />Bloqueada: ya hay evaluaciones</span
          >
        {/if}
      </span>
      {#if rubrica}
        <button
          type="button"
          class="{btnSecundario} {chico}"
          aria-haspopup="dialog"
          onclick={() => ((errorImprimir = false), (verRubrica = true))}
        >
          Ver rúbrica
        </button>
      {:else if actividad.es_titular}
        <button type="button" class="{btnPrimario} {chico}" onclick={() => (editorRubrica = true)}
          >Crear rúbrica</button
        >
      {/if}
    </div>
  {/if}
</div>

{#if !fila.visible}
  <div class="{aviso} {avisoTono.info} items-center">
    <Info size={18} class="shrink-0 text-inst-info" aria-hidden="true" />
    <span
      >La actividad está planificada: los estudiantes no la ven ni pueden entregar hasta que la
      publiques.</span
    >
  </div>
{:else if grupos.length === 0}
  <div class="{aviso} {avisoTono.info} items-center">
    <Info size={18} class="shrink-0 text-inst-info" aria-hidden="true" />
    <span class="min-w-0 flex-1">
      {actividad.es_grupal
        ? 'Esta actividad grupal todavía no tiene grupos.'
        : 'Todavía no hay estudiantes asignados a esta actividad.'}
    </span>
    {#if actividad.es_grupal}
      <Link
        href="/docente/cursos/{curso.id_curso}/gestion/grupos?actividad={actividad.id_actividad}"
        class="{btnSecundario} {chico}">Gestión de grupos</Link
      >
    {/if}
  </div>
{:else}
  <section class={tarjeta} aria-label="Estudiantes de {actividad.nombre}">
    <div class={tarjetaCabecera}>
      <h3 class="{titulo} m-0 text-[17px] leading-[22px]">Estudiantes</h3>
      <div
        class="ml-auto inline-flex flex-wrap overflow-hidden rounded-md border border-inst-control"
        role="group"
        aria-label="Filtrar por estado"
      >
        {#each [['todos', `Todos · ${filas.length}`], ['pend', `${textos.pend} · ${conteo.pend}`], ['ok', `${textos.ok} · ${conteo.ok}`], ['no', `${textos.no} · ${conteo.no}`]] as [clave, texto] (clave)}
          <button
            type="button"
            aria-pressed={filtro === clave}
            class="min-h-11 border-0 border-r border-inst-linea px-3 text-[13px] font-bold last:border-r-0 lg:min-h-9 {filtro ===
            clave
              ? 'bg-inst-accion text-inst-sobre-accion'
              : 'bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil'}"
            onclick={() => ((filtro = clave as typeof filtro), (verTodos = false))}
          >
            {texto}
          </button>
        {/each}
      </div>
    </div>

    <div class="overflow-x-auto" onscroll={() => (menuAbierto = null)}>
      <table class="w-full border-collapse text-sm">
        <thead class="bg-inst-sutil">
          <tr>
            <th scope="col" class={TH}>Estudiante</th>
            {#if actividad.es_grupal}<th scope="col" class={TH}>Grupo</th>{/if}
            <th scope="col" class={TH}>Estado</th>
            <th scope="col" class={TH}>Entrega</th>
            <th scope="col" class="{TH} text-right"
              >{actividad.es_sumativa ? 'Nota' : 'Calificación'}</th
            >
            <th scope="col" class={TH}>Agenda</th>
            <th scope="col" class="{TH} text-right"
              ><span class="sr-only">Acciones personales</span></th
            >
          </tr>
        </thead>
        <tbody>
          {#each visibles as f (f.clave)}
            {@const nota = notaDeFila(f)}
            {@const dec = Math.round((f.integrante?.diferencia_decimas ?? 0) * 10)}
            {@const nl = noLeidosDe(f.grupo)}
            <tr class="border-t border-inst-linea align-middle">
              <td class="px-4 py-2.5">
                <span class="flex items-center gap-2.5">
                  <AvatarAlumno
                    src={f.integrante?.url_foto ?? null}
                    nombre={f.nombre}
                    class="size-8 bg-inst-sel text-xs font-extrabold text-inst-ink"
                  />
                  <span class="flex min-w-0 flex-col">
                    <span class="font-bold">{f.nombre}</span>
                    {#if f.grupo.nro_dias_adicionales_para_bloqueo_personal > 0 && holguraHasta(f.grupo)}
                      <span class="text-[13px] font-bold text-inst-warn"
                        >Holgura hasta {holguraHasta(f.grupo)}</span
                      >
                    {/if}
                  </span>
                </span>
              </td>
              {#if actividad.es_grupal}<td class="px-4 py-2.5 whitespace-nowrap"
                  >{f.grupo.etiqueta}</td
                >{/if}
              <td class="px-4 py-2.5"
                ><span class="{chip} {tono[TONO_ENTREGA[f.estado]]}">{textos[f.estado]}</span></td
              >
              <td class="px-4 py-2.5 text-[13px] whitespace-nowrap">
                {#if f.grupo.entrega}
                  {fmtEntrega.format(new Date(f.grupo.entrega.fecha))}
                  {#if f.grupo.entrega.fuera_de_plazo}<span class="block font-bold text-inst-warn"
                      >Fuera de plazo</span
                    >{/if}
                {:else}
                  {actividad.trae_archivo ? '—' : 'Presencial'}
                {/if}
              </td>
              <td class="px-4 py-2.5 text-right">
                {#if actividad.es_sumativa && nota !== null}
                  <span class="flex flex-col items-end">
                    <span class="{titulo} text-base">{formatNota(nota)}</span>
                    {#if dec !== 0}<span class="text-[13px] font-bold text-inst-warn"
                        >{fmtDec(dec)}</span
                      >{/if}
                  </span>
                {:else}
                  <span class="text-inst-ink-3">—</span>
                {/if}
              </td>
              <td class="px-4 py-2.5">
                <button
                  type="button"
                  class="{btnSecundario} {chico} whitespace-nowrap"
                  onclick={() => abrirAgenda(f.grupo, f.nombre)}
                >
                  <MessageSquare size={16} aria-hidden="true" />Ver agenda
                  {#if nl > 0}<span
                      class="ml-0.5 rounded-full bg-inst-accion px-1.5 text-xs font-extrabold text-inst-sobre-accion"
                      aria-label="{nl} sin leer">{nl}</span
                    >{/if}
                </button>
              </td>
              <td class="px-4 py-2.5 text-right">
                <span class="inline-flex" data-menu-personal>
                  <button
                    type="button"
                    class="{btnSecundario} {chico} whitespace-nowrap"
                    aria-haspopup="menu"
                    aria-expanded={menuAbierto?.fila.clave === f.clave}
                    onclick={(e) => toggleMenuPersonal(f, e)}
                  >
                    Acciones personales<ChevronDown size={16} aria-hidden="true" />
                  </button>
                </span>
              </td>
            </tr>
          {:else}
            <tr
              ><td colspan="7" class="px-4 py-6 text-center text-inst-ink-2"
                >Nadie está en «{filtro !== 'todos' ? textos[filtro] : ''}».</td
              ></tr
            >
          {/each}
        </tbody>
      </table>
    </div>

    <div class={tarjetaPie}>
      <span class="text-[13px] text-inst-ink-2"
        >Mostrando {visibles.length} de {filtradas.length} estudiantes</span
      >
      {#if !verTodos && filtradas.length > LIMITE}
        <button
          type="button"
          class="{btnSecundario} {chico} ml-auto border-inst-oro bg-inst-oro text-inst-ink hover:bg-inst-oro"
          onclick={() => (verTodos = true)}
        >
          Ver los {filtradas.length} estudiantes
        </button>
      {/if}
    </div>
  </section>

  {#if menuAbierto}
    {@const f = menuAbierto.fila}
    <div
      role="menu"
      data-menu-personal
      aria-label="Acciones personales de {f.nombre}"
      class="fixed z-50 flex min-w-[200px] flex-col rounded-md border border-inst-linea bg-inst-tarjeta py-1 text-left shadow-lg"
      style="right: {menuAbierto.right}px; {menuAbierto.openUpwards
        ? `bottom: ${menuAbierto.bottom}px;`
        : `top: ${menuAbierto.top}px;`}"
    >
      <button
        type="button"
        role="menuitem"
        class="flex min-h-11 items-center gap-2.5 border-0 bg-transparent px-3.5 text-sm font-bold text-inst-ink hover:bg-inst-sutil lg:min-h-10"
        onclick={() => calificar(f)}
      >
        <ClipboardCheck size={16} aria-hidden="true" />{actividad.es_sumativa
          ? 'Calificar'
          : 'Revisar'}
      </button>
      {#if actividad.fecha_limite}
        <button
          type="button"
          role="menuitem"
          class="flex min-h-11 items-center gap-2.5 border-0 bg-transparent px-3.5 text-sm font-bold text-inst-ink hover:bg-inst-sutil lg:min-h-10"
          onclick={() => abrirHolgura(f)}
        >
          <Clock size={16} aria-hidden="true" />Holgura
        </button>
      {/if}
    </div>
  {/if}
{/if}

{#if agenda && grupoAgenda}
  {@const g = grupoAgenda}
  <AgendaDocente
    nombre={agenda.nombre}
    subtitulo={[
      curso.asignatura_nombre ?? curso.nombre,
      actividad.nombre,
      tipoTexto,
      actividad.es_grupal ? g.etiqueta : null,
    ]
      .filter(Boolean)
      .join(' · ')}
    estado={estadoEntrega(g, actividad.es_sumativa, actividad.trae_archivo)}
    esSumativa={actividad.es_sumativa}
    esGrupal={actividad.es_grupal}
    nombreGrupo={g.etiqueta}
    integrantes={g.integrantes}
    notaActual={g.nota}
    {rubrica}
    idGrupo={g.grupo}
    urlEntrega={(id, ver) =>
      `${base}/grupos/${g.grupo}/entregas/${id}/descargar${ver ? '?ver=1' : ''}`}
    listado_interacciones={hiloVisible}
    isLoading={cargandoHilo}
    errorMensaje={errorHilo}
    pestanaInicial={agenda.pestana}
    pieCerrar=""
    {guardando}
    onEnviar={enviarDesdeAgenda}
    onCerrar={cerrarAgenda}
    onCrearRubrica={actividad.es_titular ? () => (editorRubrica = true) : undefined}
  />
{/if}

{#if entregasDe}
  <EntregasModal
    idCurso={curso.id_curso}
    idActividad={actividad.id_actividad}
    nombreActividad={actividad.nombre}
    grupo={{ ...entregasDe.grupo, etiqueta: entregasDe.nombre }}
    esGrupal={actividad.es_grupal}
    onCerrar={() => (entregasDe = null)}
    onCalificar={() => {
      const d = entregasDe!;
      entregasDe = null;
      abrirAgenda(d.grupo, d.nombre, 'evaluar');
    }}
  />
{/if}

{#if holguraDe && actividad.fecha_limite}
  <HolguraModal
    nombre={holguraDe.nombre}
    subtitulo={[actividad.nombre, actividad.es_grupal ? holguraDe.grupo.etiqueta : null]
      .filter(Boolean)
      .join(' · ')}
    fechaLimite={actividad.fecha_limite}
    holguraGeneral={actividad.nro_dias_adicionales_para_bloqueo}
    holguraPersonal={holguraDe.grupo.nro_dias_adicionales_para_bloqueo_personal}
    guardando={guardandoHolgura}
    onGuardar={guardarHolgura}
    onCerrar={() => (holguraDe = null)}
  />
{/if}

{#if mensajeTodos !== null}
  <ModalInst
    titulo="Mensaje a todos"
    subtitulo="Llega a la agenda de {grupos.length} {actividad.es_grupal
      ? grupos.length === 1
        ? 'grupo'
        : 'grupos'
      : grupos.length === 1
        ? 'estudiante'
        : 'estudiantes'} de «{actividad.nombre}»."
    onCerrar={() => !enviandoTodos && (mensajeTodos = null)}
  >
    <label class="flex flex-col gap-1.5 font-bold">
      Mensaje
      <textarea
        bind:value={mensajeTodos}
        rows={5}
        maxlength={2000}
        placeholder="Escribe tu mensaje."
        class="{campo} resize-y font-normal"></textarea>
    </label>
    {#if errorTodos}<p class="m-0 text-[13px] text-inst-error" role="alert">{errorTodos}</p>{/if}
    {#snippet pie()}
      <button
        type="button"
        class="{btnSecundario} ml-auto"
        disabled={enviandoTodos}
        onclick={() => (mensajeTodos = null)}>Cancelar</button
      >
      <button
        type="button"
        class={btnPrimario}
        disabled={!mensajeTodos?.trim() || enviandoTodos}
        onclick={enviarATodos}
      >
        <Send size={16} aria-hidden="true" />Enviar
      </button>
    {/snippet}
  </ModalInst>
{/if}

{#if verRubrica && rubrica}
  <ModalInst
    titulo="Rúbrica · {actividad.nombre}"
    subtitulo="{rubrica.niveles?.length ??
      0} criterios · {puntajeRubrica} puntos{actividad.es_sumativa
      ? ` · nota 4,0 con ${puntajeMinimoAprobacion(puntajeRubrica)}`
      : ''}"
    ancho={1376}
    onCerrar={() => (verRubrica = false)}
  >
    {#if tiene_evaluaciones}
      <div id="rubrica-bloqueo" class="{aviso} {avisoTono.info} items-center">
        <Lock size={18} class="shrink-0 text-inst-info" aria-hidden="true" />
        <span class="min-w-0 flex-1">
          <strong>No se puede editar una rúbrica con evaluaciones ya registradas.</strong> Tus estudiantes
          ven esta misma tabla en la pestaña «Rúbrica» de la actividad.
        </span>
      </div>
    {/if}
    <RubricaMatriz {rubrica} esSumativa={actividad.es_sumativa} />
    {#if errorImprimir}
      <p class="m-0 text-[13px] text-inst-error" role="alert">
        El navegador no dejó abrir la ventana para imprimir. Permite las ventanas emergentes de este
        sitio e inténtalo otra vez.
      </p>
    {/if}
    {#snippet pie()}
      <button
        type="button"
        class={btnSecundario}
        onclick={() => {
          errorImprimir = !imprimirRubrica(
            rubrica,
            { titulo: actividad.nombre, subtitulo: curso.asignatura_nombre ?? curso.nombre },
            { esSumativa: actividad.es_sumativa },
          );
        }}
      >
        <Printer size={16} aria-hidden="true" />Imprimir
      </button>
      {#if actividad.es_titular}
        <button
          type="button"
          class={btnSecundario}
          aria-disabled={!puede_editar_rubrica}
          aria-describedby={tiene_evaluaciones ? 'rubrica-bloqueo' : undefined}
          title={tiene_evaluaciones
            ? 'No se puede editar una rúbrica con evaluaciones ya registradas'
            : undefined}
          onclick={() => {
            if (!puede_editar_rubrica) return;
            verRubrica = false;
            editorRubrica = true;
          }}
        >
          {#if puede_editar_rubrica}<Pencil size={16} aria-hidden="true" />{:else}<Lock
              size={16}
              aria-hidden="true"
            />{/if}Editar rúbrica
        </button>
      {/if}
      <button type="button" class="{btnPrimario} ml-auto" onclick={() => (verRubrica = false)}
        >Cerrar</button
      >
    {/snippet}
  </ModalInst>
{/if}

{#if editorRubrica}
  <RubricaEditor
    {rubrica}
    bloqueada={!puede_editar_rubrica}
    idCurso={curso.id_curso}
    idActividad={actividad.id_actividad}
    nombreActividad={actividad.nombre}
    esSumativa={actividad.es_sumativa}
    onClose={() => (editorRubrica = false)}
  />
{/if}

<EnunciadoModal
  bind:isOpen={showEnunciado}
  idCurso={curso.id_curso}
  actividad={{
    id_actividad: actividad.id_actividad,
    nombre: actividad.nombre,
    archivo_enunciado: actividad.archivo_enunciado,
  } as unknown as Actividad}
  reglas={reglas_enunciado}
  onClose={() => (showEnunciado = false)}
/>
