<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { Rubrica, RubricaResponse } from '@/types/rubrica';
  import { FileText, Info, X } from 'lucide-svelte';
  import Agenda from './Agenda/Agenda.svelte';
  import RubricaView from './Agenda/Rubrica.svelte';
  import ActivityHeaderCard from './cards/ActivityHeaderCard.svelte';
  import ActivityDeadlineCard from './cards/ActivityDeadlineCard.svelte';
  import ActivityPendingCard from './cards/ActivityPendingCard.svelte';
  import ActivitySubmittedCard from './cards/ActivitySubmittedCard.svelte';
  import ActivityRubricaCard from './cards/ActivityRubricaCard.svelte';
  import ActivityAgendaCard from './cards/ActivityAgendaCard.svelte';
  import { router } from '@inertiajs/svelte';
  import ActivityGradeCard from './cards/ActivityGradeCard.svelte';
  import Entrega from './Agenda/Entrega.svelte';
  import ActivityMembersCard from './cards/ActivityMembersCard.svelte';
  import Enunciado from './Agenda/Enunciado.svelte';
  import { formatFechaCorta, parseFechaSoloDia } from '@/utils/formatters';

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
    dias_holgura: number;
    dias_holgura_personal?: number;
    entrega_obligatoria: boolean;
    ultima_nota?: number | null;
    ultima_entrega?: {
      id_interaccion: number;
      fecha_emision: string;
      archivo?: {
        nombre_original: string | null;
        peso_bytes: number | null;
      } | null;
    } | null;
    estado?: string | null;
    listado_interacciones?: Array<{
      id_interaccion: number;
      fecha_emision: string;
      tipo_interaccion: string;
      emisor: string;
      mensaje: string;
      es_de_docente: boolean;
      es_retroalimentacion: boolean;
      adjunta_rubrica: boolean;
      rubrica?: Rubrica | null;
      puntaje_obtenido?: number | null;
    }>;
    rubrica?: RubricaResponse | null;
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
    dias_holgura,
    dias_holgura_personal = 0,
    entrega_obligatoria,
    ultima_nota,
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
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: nombre_curso, href: '/estudiante/cursos' },
    { title: nombre_actividad, href: '' },
  ]);

  let showRubricaModal = $state(false);
  let showAgendaModal = $state(false);
  let showEntregaModal = $state(false);
  let showEnunciadoModal = $state(false);

  // El backend ya calcula 'estado' (ACTIVA/CERRADA) considerando la holgura
  // de la actividad Y la holgura personal del grupo — ver
  // ActividadAsignadaGrupo::calcularEstadoGrupo. Antes se recalculaba acá con
  // sólo `dias_holgura` (sin la personal) y bloqueaba entregas a alumnos con
  // holgura personal vigente.
  const puedeApelar = false;
  const puedeSubirArchivo = $derived((estado === 'ACTIVA' || puedeApelar) && entrega_obligatoria);

  const tieneEntregaRegistrada = $derived(ultima_entrega !== null);

  // Última interacción de tipo evaluación/feedback: alimenta el pie de la
  // card de nota ("Publicada {fecha} · {evaluador}") con datos reales, sin
  // inventar una ponderación o fecha que el backend no envía.
  const ultimaEvaluacion = $derived.by(() => {
    for (let i = listado_interacciones.length - 1; i >= 0; i--) {
      if (listado_interacciones[i].es_retroalimentacion) return listado_interacciones[i];
    }
    return null;
  });

  const fechaEfectiva = $derived.by(() => {
    const base = parseFechaSoloDia(fecha_limite);
    const d = new Date(base);
    d.setDate(d.getDate() + (dias_holgura || 0) + (dias_holgura_personal || 0));
    return d;
  });

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
      alert(
        'Error: No se encontró el grupo asignado para esta actividad.\n' +
          `(cod_actividad=${cod_actividad}, id_actividad_asignada_grupo=${id_actividad_asignada_grupo})\n` +
          'Revisa la consola del navegador para más detalles.',
      );
      return;
    }

    router.post(
      `/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/agenda`,
      { tipo: data.tipo, mensaje: data.mensaje },
      {
        onSuccess: () => router.reload(),
        onError: (errors) => alert(errors.error || 'Error al enviar mensaje'),
      },
    );
  }
</script>

<StudentLayout {breadcrumbs}>
  <div class="min-h-screen bg-[#FAFBFC]">
    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8 md:px-6 lg:px-8">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_300px] lg:items-start">
        <main class="flex flex-col gap-5">
          <ActivityHeaderCard
            {nombre_actividad}
            {nombre_curso}
            {descripcion}
            {es_sumativa}
            {entrega_obligatoria}
            {estado}
          />

          {#if fecha_limite}
            <ActivityDeadlineCard {fecha_limite} {dias_holgura} {dias_holgura_personal} {estado} />
          {/if}

          {#if entrega_obligatoria}
            {#if tieneEntregaRegistrada}
              <ActivitySubmittedCard
                esGrupal={es_grupal}
                fecha_entrega={ultima_entrega?.fecha_emision}
                archivo={ultima_entrega?.archivo}
                urlDescarga={ultima_entrega
                  ? `/estudiante/cursos/${id_curso}/actividades/${cod_actividad}/entregas/${ultima_entrega.id_interaccion}/descargar`
                  : null}
                puedeReemplazar={puedeSubirArchivo}
                onReemplazarClick={toggleEntregaModal}
              />
            {:else}
              <ActivityPendingCard disponible={puedeSubirArchivo} esGrupal={es_grupal} onSubirClick={toggleEntregaModal} />
            {/if}
          {/if}

          {#if ultima_nota !== null && ultima_nota !== undefined}
            <ActivityGradeCard
              {ultima_nota}
              {es_sumativa}
              fecha_evaluacion={ultimaEvaluacion?.fecha_emision}
              evaluador={ultimaEvaluacion?.emisor}
              onVerRubricaClick={rubrica ? toggleRubricaModal : undefined}
            />
          {/if}

          {#if es_sumativa}
            <ActivityRubricaCard rubrica={rubrica?.rubrica} onRubricaClick={toggleRubricaModal} />
          {/if}

          {#if id_actividad_asignada_grupo}
            <ActivityAgendaCard {listado_interacciones} onAgendaClick={toggleAgendaModal} />
          {/if}
        </main>

        <aside class="flex flex-col gap-5" aria-label="Contexto de la actividad">
          {#if fecha_limite}
            <section class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
              <div class="flex items-center gap-2">
                <Info class="h-[15px] w-[15px] text-[#5A5E6E]" />
                <h3 class="text-[13px] font-semibold text-[#1A1A24]">Cómo se calcula tu fecha</h3>
              </div>
              <div class="flex flex-col">
                <div class="flex gap-2.5">
                  <div class="flex flex-none flex-col items-center">
                    <span class="mt-1 h-[7px] w-[7px] rounded-full bg-[#C9D6E6]"></span>
                    <span class="w-px flex-1 bg-[#E5E7EB]"></span>
                  </div>
                  <div class="flex flex-col pb-3">
                    <span class="text-[11px] text-[#5A5E6E]">Fecha del curso</span>
                    <span class="text-[12.5px] font-semibold text-[#1A1A24]">{formatFechaCorta(fecha_limite)}</span>
                  </div>
                </div>
                {#if dias_holgura > 0}
                  <div class="flex gap-2.5">
                    <div class="flex flex-none flex-col items-center">
                      <span class="mt-1 h-[7px] w-[7px] rounded-full bg-[#C9D6E6]"></span>
                      <span class="w-px flex-1 bg-[#E5E7EB]"></span>
                    </div>
                    <div class="flex flex-col pb-3">
                      <span class="text-[11px] text-[#5A5E6E]">Holgura de la actividad</span>
                      <span class="text-[12.5px] font-semibold text-[#1A1A24]"
                        >+{dias_holgura} {dias_holgura === 1 ? 'día' : 'días'}</span
                      >
                    </div>
                  </div>
                {/if}
                {#if dias_holgura_personal > 0}
                  <div class="flex gap-2.5">
                    <div class="flex flex-none flex-col items-center">
                      <span class="mt-1 h-[7px] w-[7px] rounded-full bg-[#C9D6E6]"></span>
                      <span class="w-px flex-1 bg-[#E5E7EB]"></span>
                    </div>
                    <div class="flex flex-col pb-3">
                      <span class="text-[11px] text-[#5A5E6E]">Tu holgura personal</span>
                      <span class="text-[12.5px] font-semibold text-[#1A1A24]"
                        >+{dias_holgura_personal} {dias_holgura_personal === 1 ? 'día' : 'días'}</span
                      >
                    </div>
                  </div>
                {/if}
                <div class="flex gap-2.5">
                  <div class="flex flex-none flex-col items-center">
                    <span class="mt-1 h-[7px] w-[7px] rounded-full bg-emerald-600"></span>
                  </div>
                  <div class="flex flex-col">
                    <span class="text-[11px] font-semibold text-emerald-700">Tu fecha efectiva</span>
                    <span class="text-[13px] font-semibold text-[#1A1A24]">
                      {formatFechaCorta(fechaEfectiva.toISOString())}
                    </span>
                  </div>
                </div>
              </div>
            </section>
          {/if}

          {#if archivo_enunciado}
            <button
              class="group flex w-full items-center gap-3 rounded-xl border border-[#E5E7EB] bg-white p-3.5 text-left shadow-sm transition-colors hover:bg-[#F8FAFC]"
              onclick={toggleEnunciadoModal}
            >
              <FileText class="h-4 w-4 shrink-0 text-[#5A5E6E]" />
              <span class="flex-1 truncate text-sm font-semibold text-[#1A1A24]">Ver enunciado</span>
            </button>
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
      {listado_interacciones}
      {id_actividad_asignada_grupo}
      equipoDocente={equipo_docente}
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
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl">
      <div class="flex items-center justify-between border-b border-[#E5E7EB] p-5 md:px-6">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wider text-[#5A5E6E]">Rúbrica de evaluación</div>
          <div class="mt-0.5 text-base font-semibold text-[#1A1A24]">{nombre_actividad}</div>
        </div>
        <button
          class="rounded-lg p-1 text-[#5A5E6E] transition-colors hover:bg-[#F8FAFC] hover:text-[#1A1A24]"
          onclick={toggleRubricaModal}
          aria-label="Cerrar"
        >
          <X class="h-[18px] w-[18px]" />
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-5 md:p-6">
        {#if rubrica}
          <RubricaView rubrica={rubrica?.rubrica} />
        {:else}
          <p class="py-8 text-center text-sm font-medium text-[#5A5E6E]">No hay rúbrica disponible para esta actividad.</p>
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
      {cod_curso}
      {nombre_actividad}
      {entrega_obligatoria}
      esReemplazo={tieneEntregaRegistrada}
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

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape') {
      showRubricaModal = false;
      showAgendaModal = false;
      showEntregaModal = false;
    }
  }}
/>
