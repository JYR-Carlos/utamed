<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { Rubrica, RubricaResponse } from '@/types/rubrica';
  import type { InteraccionItem } from '@/types/agenda';
  import { AlertCircle, Users, X } from 'lucide-svelte';
  import { ConfirmDialog } from '@/components/custom/common';
  import Agenda from './Agenda/Agenda.svelte';
  import RubricaView from './Agenda/Rubrica.svelte';
  import ActivityHeaderCard from './cards/ActivityHeaderCard.svelte';
  import ActivitySubmissionCard from './cards/ActivitySubmissionCard.svelte';
  import ActivityAgendaCard from './cards/ActivityAgendaCard.svelte';
  import { router, page } from '@inertiajs/svelte';
  import { onMount } from 'svelte';
  import ActivityGradeCard from './cards/ActivityGradeCard.svelte';
  import Entrega from './Agenda/Entrega.svelte';
  import ActivityMembersCard from './cards/ActivityMembersCard.svelte';
  import Enunciado from './Agenda/Enunciado.svelte';

  interface Props {
    id_curso: number;
    cod_curso: string;
    nombre_curso: string;
    cod_actividad: string;
    nombre_actividad: string;
    descripcion: string;
    fecha_limite: string;
    es_sumativa: boolean;
    es_grupal: boolean;
    entrega_obligatoria: boolean;
    ultima_nota?: number | null;
    /** Décimas que el docente sumó o restó a la nota del grupo para este alumno. */
    ajuste_decimas?: number;
    nota_grupo?: number | null;
    ultima_entrega?: {
      id_interaccion: number;
      fecha_emision: string;
      archivo?: {
        nombre_original: string | null;
        peso_bytes: number | null;
      } | null;
    } | null;
    estado?: string | null;
    listado_interacciones?: InteraccionItem[];
    rubrica?: RubricaResponse | null;
    ultima_evaluacion?: {
      id_evaluacion: number;
      fecha_emision?: string | null;
      emisor?: string | null;
      puntaje_obtenido: number | null;
      resultado: Record<string, string> | null;
      evaluacion_obtenida?: string | null;
      retroalimentacion?: string | null;
      rubrica?: Rubrica | null;
    } | null;
    id_actividad_asignada_grupo?: number | null;
    resto_integrantes: Array<{
      id_estudiante: number;
      nombre1: string;
      nombre2: string;
      apellido1: string;
      apellido2: string;
    }>;
    archivo_enunciado?: {
      nombre_original: string;
      mime_type: string | null;
      peso_bytes: number | null;
    } | null;
    equipo_docente?: Array<{ nombre: string; es_titular: boolean }>;
  }

  let {
    id_curso,
    cod_curso,
    nombre_curso,
    cod_actividad,
    nombre_actividad,
    descripcion,
    fecha_limite,
    es_sumativa,
    es_grupal,
    entrega_obligatoria,
    ultima_nota,
    ajuste_decimas = 0,
    nota_grupo = null,
    ultima_evaluacion = null,
    ultima_entrega = null,
    estado,
    listado_interacciones = [],
    rubrica,
    id_actividad_asignada_grupo,
    resto_integrantes,
    archivo_enunciado = null,
    equipo_docente = [],
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: nombre_curso, href: `/estudiante/cursos/${id_curso}` },
    { title: nombre_actividad, href: '' },
  ]);

  let showRubricaModal = $state(false);
  let showAgendaModal = $state(false);
  let showEntregaModal = $state(false);
  let showEnunciadoModal = $state(false);
  let mensajesOptimistas = $state<InteraccionItem[]>([]);

  // Avisos en línea y confirmación propia: antes eran alert() y confirm() del
  // navegador, que bloquean la página y no siguen el diseño del producto.
  let avisoError = $state<string | null>(null);
  let temporizadorAviso: ReturnType<typeof setTimeout> | undefined;
  function mostrarError(mensaje: string) {
    avisoError = mensaje;
    clearTimeout(temporizadorAviso);
    temporizadorAviso = setTimeout(() => (avisoError = null), 8000);
  }
  let confirmandoBorrado = $state(false);

  // En una actividad grupal el docente aún puede no haber asignado al alumno a
  // un grupo: sin grupo no hay dónde colgar entregas ni mensajes.
  const sinGrupo = $derived(!id_actividad_asignada_grupo);

  const interaccionesCombinadas = $derived.by(() => {
    if (mensajesOptimistas.length === 0) return listado_interacciones ?? [];
    const delServidor = listado_interacciones ?? [];
    const pendientes = mensajesOptimistas.filter((opt) => {
      const optTime = new Date(opt.fecha_emision).getTime();
      return !delServidor.some((s) => {
        if (!s.es_propio || s.mensaje !== opt.mensaje) return false;
        const sTime = new Date(s.fecha_emision).getTime();
        return Math.abs(sTime - optTime) < 60000;
      });
    });
    return [...delServidor, ...pendientes];
  });

  // «Visto» (T07): leer la agenda es abrirla, no cargar la actividad. Se avisa al
  // servidor cada vez que el modal pasa a abierto; el docente lo ve en su hilo.
  $effect(() => {
    if (!showAgendaModal || !id_actividad_asignada_grupo) return;
    fetch(`/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/agenda/leida`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        'X-Requested-With': 'XMLHttpRequest',
      },
    }).catch(() => {
      // Sin red no pasa nada grave: se reintenta la próxima vez que abra la agenda.
    });
  });

  // Desde el dashboard («Notas y retroalimentaciones recientes») se llega con
  // ?abrir=agenda (o #agenda) para aterrizar directo en la conversación.
  onMount(() => {
    const abrir = new URLSearchParams(window.location.search).get('abrir');
    if (abrir === 'agenda' || window.location.hash === '#agenda') {
      showAgendaModal = true;
    }
  });

  // El backend ya calcula 'estado' (ACTIVA/CERRADA) considerando la holgura
  // de la actividad Y la holgura personal del grupo — ver
  // ActividadAsignadaGrupo::calcularEstadoGrupo. Antes se recalculaba acá con
  // sólo `dias_holgura` (sin la personal) y bloqueaba entregas a alumnos con
  // holgura personal vigente.
  const puedeApelar = false;

  const tieneEntregaRegistrada = $derived(ultima_entrega !== null);

  // Última interacción de tipo evaluación: alimenta el pie de la
  // card de nota ("Publicada {fecha} · {evaluador}") con datos reales, sin
  // inventar una ponderación o fecha que el backend no envía.
  const ultimaEvaluacion = $derived.by(() => {
    const interacciones = listado_interacciones ?? [];
    for (let i = interacciones.length - 1; i >= 0; i--) {
      if (interacciones[i].tipo_interaccion === 'Evaluación') return interacciones[i];
    }
    return null;
  });

  const yaEvaluada = $derived(
    (ultima_nota !== null && ultima_nota !== undefined) ||
    Boolean(ultima_evaluacion) ||
    Boolean(ultimaEvaluacion)
  );

  const puedeSubirArchivo = $derived(
    (estado === 'ACTIVA' || puedeApelar) &&
    entrega_obligatoria &&
    !yaEvaluada
  );

  // Enunciado.svelte sólo distingue 'pdf' (previsualizable en iframe) del
  // resto (prompt de descarga); no tiene una rama específica para imágenes.
  const tipoEnunciado = $derived.by((): 'pdf' | 'otro' => {
    return archivo_enunciado?.mime_type === 'application/pdf' ? 'pdf' : 'otro';
  });

  function toggleRubricaModal() {
    showRubricaModal = !showRubricaModal;
  }
  function toggleAgendaModal() {
    showAgendaModal = !showAgendaModal;
  }
  function toggleEntregaModal() {
    showAgendaModal = false;
    showRubricaModal = false;
    showEntregaModal = !showEntregaModal;
  }
  function toggleEnunciadoModal() {
    showAgendaModal = false;
    showRubricaModal = false;
    showEntregaModal = false;
    showEnunciadoModal = !showEnunciadoModal;
  }

  function handleGuardarEntrada(data: { tipo: string; mensaje: string }) {
    if (!id_actividad_asignada_grupo) {
      console.error('[handleGuardarEntrada] id_actividad_asignada_grupo es null/undefined.', {
        id_actividad_asignada_grupo,
        cod_actividad,
        cod_curso,
        data_tipo: data.tipo,
      });
      mostrarError('Todavía no formas parte de un grupo en esta actividad, así que no puedes enviar mensajes.');
      return;
    }

    const authUser = $page.props.auth?.user;
    const nombreEmisor = authUser
      ? [authUser.nombre1, authUser.apellido1, authUser.apellido2].filter(Boolean).join(' ')
      : 'Estudiante';
    const optimisticItem: InteraccionItem = {
      id_interaccion: -Date.now(),
      fecha_emision: new Date().toISOString(),
      tipo_interaccion: 'Mensaje al profesor',
      emisor: nombreEmisor,
      mensaje: data.mensaje,
      es_de_docente: false,
      es_propio: true,
    };
    mensajesOptimistas = [...mensajesOptimistas, optimisticItem];

    router.post(
      `/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/agenda`,
      { tipo: data.tipo, mensaje: data.mensaje },
      {
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
        onSuccess: () => {
          mensajesOptimistas = mensajesOptimistas.filter(
            (i) => i.id_interaccion !== optimisticItem.id_interaccion,
          );
        },
        onError: (errors) => {
          mensajesOptimistas = mensajesOptimistas.filter(
            (i) => i.id_interaccion !== optimisticItem.id_interaccion,
          );
          mostrarError(errors.error || 'No se pudo enviar el mensaje. Inténtalo nuevamente.');
        },
      },
    );
  }

  function handleBorrarEntrega() {
    if (!ultima_entrega?.id_interaccion || !id_actividad_asignada_grupo) return;
    confirmandoBorrado = true;
  }

  function borrarEntregaConfirmado() {
    confirmandoBorrado = false;
    if (!ultima_entrega?.id_interaccion || !id_actividad_asignada_grupo) return;

    router.delete(
      `/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/entregas/${ultima_entrega.id_interaccion}`,
      {
        preserveScroll: true,
        onSuccess: () => router.reload(),
        onError: (errors) => mostrarError(errors.error_general || 'No se pudo eliminar la entrega.'),
      },
    );
  }
</script>

<svelte:head>
  <title>{nombre_actividad} | UTAmed</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="min-h-screen bg-gray-50">
    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8 md:px-6 lg:px-8">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_300px] lg:items-start">
        <main class="flex flex-col gap-5">
          {#if avisoError}
            <div
              role="alert"
              class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-900"
            >
              <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-red-700" aria-hidden="true" />
              <p class="flex-1">{avisoError}</p>
              <button
                type="button"
                class="shrink-0 text-red-700 hover:text-red-900"
                aria-label="Cerrar aviso"
                onclick={() => (avisoError = null)}
              >
                <X class="h-4 w-4" />
              </button>
            </div>
          {/if}

          <ActivityHeaderCard
            {nombre_actividad}
            {nombre_curso}
            {descripcion}
            {es_sumativa}
            {entrega_obligatoria}
            {rubrica}
            onVerRubricaClick={toggleRubricaModal}
            {archivo_enunciado}
            onVerEnunciadoClick={toggleEnunciadoModal}
            urlDescargaEnunciado={archivo_enunciado
              ? `/estudiante/cursos/${id_curso}/actividades/${cod_actividad}/enunciado/descargar`
              : null}
          />

          {#if sinGrupo}
            <section class="flex items-start gap-3 rounded-xl border border-slate-300 bg-slate-100/70 p-5 md:p-6">
              <Users class="mt-0.5 h-5 w-5 shrink-0 text-slate-600" aria-hidden="true" />
              <div class="flex flex-col gap-1">
                <h2 class="text-base font-bold text-ink">
                  {es_grupal ? 'Aún no tienes grupo en esta actividad' : 'Tu registro en esta actividad aún no está listo'}
                </h2>
                <p class="text-[13px] text-ink-2">
                  {es_grupal
                    ? 'Cuando tu docente te asigne a un grupo podrás entregar y escribir en la agenda. Si crees que es un error, avísale.'
                    : 'Avisa a tu docente para que lo revise: sin él no puedes entregar ni escribir en la agenda.'}
                </p>
              </div>
            </section>
          {:else if fecha_limite || entrega_obligatoria}
            <ActivitySubmissionCard
              {fecha_limite}
              {estado}
              {entrega_obligatoria}
              esGrupal={es_grupal}
              {yaEvaluada}
              {puedeSubirArchivo}
              {ultima_entrega}
              urlDescarga={ultima_entrega
                ? `/estudiante/cursos/${id_curso}/actividades/${cod_actividad}/entregas/${ultima_entrega.id_interaccion}/descargar`
                : null}
              onSubirClick={toggleEntregaModal}
              onReemplazarClick={toggleEntregaModal}
              onBorrarClick={handleBorrarEntrega}
            />
          {/if}

          {#if id_actividad_asignada_grupo}
            <ActivityAgendaCard listado_interacciones={interaccionesCombinadas} onAgendaClick={toggleAgendaModal} />
          {/if}
        </main>

        <aside class="flex flex-col gap-5" aria-label="Contexto de la actividad">
          
          {#if es_sumativa ? (ultima_nota !== null && ultima_nota !== undefined) : ultima_evaluacion !== null}
            <ActivityGradeCard
              {ultima_nota}
              {ajuste_decimas}
              {nota_grupo}
              {es_sumativa}
              {ultima_evaluacion}
              fecha_evaluacion={ultima_evaluacion?.fecha_emision}
              evaluador={ultima_evaluacion?.emisor}
              onVerRubricaClick={rubrica ? toggleRubricaModal : undefined}
            />
          {/if}
          
          <ActivityMembersCard usuarios={es_grupal ? resto_integrantes : []} />
        </aside>
      </div>
      

    </div>
  </div>
</StudentLayout>

{#if showAgendaModal}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 transition-opacity"
    role="dialog"
    aria-modal="true"
    tabindex="-1"
    onclick={(e) => e.target === e.currentTarget && toggleAgendaModal()}
    onkeydown={(e) => e.key === 'Escape' && toggleAgendaModal()}
  >
    <Agenda
      onCerrar={toggleAgendaModal}
      onInteraccionEnviada={handleGuardarEntrada}
      {id_curso}
      {cod_curso}
      {nombre_curso}
      {cod_actividad}
      {nombre_actividad}
      {entrega_obligatoria}
      listado_interacciones={interaccionesCombinadas}
      {id_actividad_asignada_grupo}
      equipoDocente={equipo_docente}
      esSumativa={es_sumativa}
    />
  </div>
{/if}

{#if showRubricaModal}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 transition-opacity"
    role="dialog"
    aria-modal="true"
    tabindex="-1"
    onclick={(e) => e.target === e.currentTarget && toggleRubricaModal()}
    onkeydown={(e) => e.key === 'Escape' && toggleRubricaModal()}
  >
    <!--
      max-w-6xl y no 3xl: la rúbrica es una tabla de un criterio por fila y un
      nivel por columna, así que su ancho natural crece con la cantidad de
      niveles. A 3xl las celdas quedaban tan angostas que la descripción de cada
      nivel se partía en una palabra por línea. El scroll horizontal lo pone la
      propia tabla (RubricaView envuelve en overflow-x-auto), no este contenedor:
      así la cabecera del modal y el botón de cerrar quedan siempre a la vista.
    -->
    <div class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl">
      <div class="flex items-center justify-between border-b border-gray-200 p-5 md:px-6">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wider text-ink-2">Rúbrica de evaluación</div>
          <div class="mt-0.5 text-base font-semibold text-ink">{nombre_actividad}</div>
        </div>
        <button
          class="rounded-lg p-1 text-ink-2 transition-colors hover:bg-slate-50 hover:text-ink"
          onclick={toggleRubricaModal}
          aria-label="Cerrar"
        >
          <X class="h-[18px] w-[18px]" />
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-5 md:p-6">
        {#if rubrica || ultima_evaluacion?.rubrica || ultimaEvaluacion?.rubrica}
          <RubricaView
            rubrica={ultima_evaluacion?.rubrica ?? ultimaEvaluacion?.rubrica ?? rubrica?.rubrica}
            esSumativa={es_sumativa}
            resultado={ultima_evaluacion?.resultado ?? ultimaEvaluacion?.resultado}
            puntaje_obtenido={ultima_evaluacion?.puntaje_obtenido ?? ultimaEvaluacion?.puntaje_obtenido}
            retroalimentacion={ultima_evaluacion?.retroalimentacion ?? ultimaEvaluacion?.mensaje}
          />
        {:else}
          <p class="py-8 text-center text-sm font-medium text-ink-2">No hay rúbrica disponible para esta actividad.</p>
        {/if}
      </div>
    </div>
  </div>
{/if}

{#if showEntregaModal && id_actividad_asignada_grupo}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 transition-opacity"
    role="dialog"
    aria-modal="true"
    tabindex="-1"
    onclick={(e) => e.target === e.currentTarget && toggleEntregaModal()}
    onkeydown={(e) => e.key === 'Escape' && toggleEntregaModal()}
  >
    <Entrega
      onCerrar={toggleEntregaModal}
      onEntregaCompletada={() => {
        showEntregaModal = false;
        router.reload();
      }}
      onAvisarDocente={() => {
        showEntregaModal = false;
        showAgendaModal = true;
      }}
      {id_actividad_asignada_grupo}
      {nombre_curso}
      {nombre_actividad}
      {entrega_obligatoria}
      esReemplazo={tieneEntregaRegistrada}
      esGrupal={es_grupal}
    />
  </div>
{/if}

{#if showEnunciadoModal}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 transition-opacity"
    role="dialog"
    aria-modal="true"
    tabindex="-1"
    onclick={(e) => e.target === e.currentTarget && toggleEnunciadoModal()}
    onkeydown={(e) => e.key === 'Escape' && toggleEnunciadoModal()}
  >
    <Enunciado
      onCerrar={toggleEnunciadoModal}
      url_archivo={archivo_enunciado ? `/estudiante/cursos/${id_curso}/actividades/${cod_actividad}/enunciado/descargar` : ''}
      nombre_archivo={archivo_enunciado?.nombre_original ?? 'Enunciado de la Actividad'}
      tipo_archivo={tipoEnunciado}
    />
  </div>
{/if}

<ConfirmDialog
  open={confirmandoBorrado}
  title="Eliminar entrega"
  body="¿Seguro que quieres eliminar la entrega actual? Podrás subir otra mientras la actividad siga abierta."
  confirmLabel="Eliminar"
  onConfirm={borrarEntregaConfirmado}
  onCancel={() => (confirmandoBorrado = false)}
/>

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape') {
      showRubricaModal = false;
      showAgendaModal = false;
      showEntregaModal = false;
    }
  }}
/>
