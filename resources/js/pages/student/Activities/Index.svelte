<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { Rubrica, RubricaResponse } from '@/types/rubrica';
  import Agenda from './Agenda/Agenda.svelte';
  import RubricaView from './Agenda/Rubrica.svelte';
  import ActivityHeaderCard from './cards/ActivityHeaderCard.svelte';
  import ActivityStateCard from './cards/ActivityStateCard.svelte';
  import ActivityPendingCard from './cards/ActivityPendingCard.svelte';
  import ActivityRubricaCard from './cards/ActivityRubricaCard.svelte';
  import ActivityAgendaCard from './cards/ActivityAgendaCard.svelte';
  import { router, page } from '@inertiajs/svelte';
  import ActivityGradeCard from './cards/ActivityGradeCard.svelte';
  import Entrega from './Agenda/Entrega.svelte';
  import ActivityMembersCard from './cards/ActivityMembersCard.svelte';
  import Enunciado from './Agenda/Enunciado.svelte';

  interface Props {
    cod_curso: string;
    nombre_curso: string;
    cod_actividad: string;
    nombre_actividad: string;
    descripcion: string;
    fecha_limite: string;
    es_sumativa: boolean;
    dias_holgura: number;
    entrega_obligatoria: boolean;
    ultima_nota?: number | null;
    estado?: string | null;
    entradas: Array<{ id: number }>;
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
  }

  let {
    cod_curso,
    nombre_curso,
    cod_actividad,
    nombre_actividad,
    descripcion,
    fecha_limite,
    es_sumativa,
    dias_holgura,
    entrega_obligatoria,
    ultima_nota,
    estado,
    entradas: _entradas,
    listado_interacciones = [],
    rubrica,
    id_actividad_asignada_grupo,
    resto_integrantes,
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    //{ title: 'Dashboard', href: '/estudiante/dashboard' },
    //{ title: 'Mis Cursos', href: '/estudiante/cursos' },
    //{ title: nombre_curso, href: '/estudiante/cursos' },
    { title: nombre_actividad, href: '' },
  ]);

  let showRubricaModal = $state(false);
  let showAgendaModal = $state(false);

  //
  const exedioFechaLimite = $derived(() => {
    return new Date(fecha_limite) < new Date();
  });
  const excedioHolgura = $derived.by(() => {
    const limite = new Date(fecha_limite);
    const limiteConHolgura = new Date(limite);
    limiteConHolgura.setDate(limiteConHolgura.getDate() + dias_holgura);
    return limiteConHolgura < new Date();
  });
  const estaActiva = $derived.by(() => {
    return estado === 'ACTIVA' || ultima_nota;
  });
  const puedeApelar = $derived.by(() => {
    return false;
  })
  const puedeSubirArchivo = $derived.by(() => {
    return (estado === 'ACTIVA' && excedioHolgura) || (puedeApelar) 
  })

  function toggleRubricaModal() {
    showRubricaModal = !showRubricaModal;
  }
  function toggleAgendaModal() {
    showAgendaModal = !showAgendaModal;
  }

  let showEntregaModal = $state(false);
  function toggleEntregaModal() {
    showAgendaModal = false;
    showRubricaModal = false;
    showEntregaModal = !showEntregaModal;
  }

  let showEnunciadoModal = $state(false);
  function toggleEnunciadoModal() {
    showAgendaModal = false;
    showRubricaModal = false;
    showEntregaModal = false;
    showEnunciadoModal = !showEnunciadoModal;
  }

  
  function handleGuardarEntrada(data: {
    tipo: string;
    mensaje: string;
    archivo?: File;
  }) {
    console.log('[handleGuardarEntrada] llamado con:', {
      id_actividad_asignada_grupo,
      tipo: data.tipo,
      tiene_archivo: !!data.archivo,
      nombre_archivo: data.archivo?.name,
    });

    if (!id_actividad_asignada_grupo) {
      console.error('[handleGuardarEntrada] id_actividad_asignada_grupo es null/undefined.', {
        id_actividad_asignada_grupo,
        cod_actividad,
        cod_curso,
        data_tipo: data.tipo,
        tiene_archivo: !!data.archivo,
      });
      alert(
        'Error: No se encontró el grupo asignado para esta actividad.\n' +
        `(cod_actividad=${cod_actividad}, id_actividad_asignada_grupo=${id_actividad_asignada_grupo})\n` +
        'Revisa la consola del navegador para más detalles.'
      );
      return;
    }

    // Entrega de avance con archivo
    if (data.tipo === 'Entrega de Avance' && data.archivo) {
      router.post(
        `/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/entregas`,
        {
          tipo: data.tipo,
          mensaje: data.mensaje,
          archivo: data.archivo,
        },
        {
          forceFormData: true,
          onSuccess: () => {
            router.reload();
          },
          onError: (errors) => {
            if (errors.archivo) {
              alert(errors.archivo);
              return;
            }
            if (errors.error_general) {
              alert(errors.error_general);
              return;
            }
            alert('Error al enviar la entrega');
          },
        },
      );

      return;
    }
    // Mensaje normal de agenda
    router.post(
      `/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/agenda`,
      {
        tipo: data.tipo,
        mensaje: data.mensaje,
      },
      {
        onSuccess: () => {
          router.reload();
        },

        onError: (errors) => {
          alert(errors.error || 'Error al enviar mensaje');
        },
      },
    );
  }
</script>

<StudentLayout {breadcrumbs}>
  <div
    class="min-h-screen bg-slate-50 text-slate-800 antialiased selection:bg-blue-500 selection:text-white"
  >
    <div class="mx-auto max-w-7xl px-4 py-8 md:px-6 lg:px-8">
      <div class="grid grid-cols-1 gap-8 md:grid-cols-4">
        <aside class="space-y-6 md:col-span-1" aria-label="Información de la actividad">
          <button
            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50 hover:text-slate-900"
            onclick={() => window.history.back()}
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="15 18 9 12 15 6" />
            </svg>
            Volver
          </button>

          <div class="space-y-1">
            <div class="text-xs font-semibold tracking-wider text-slate-400 uppercase">
              {cod_curso}
            </div>
            <div class="text-base font-bold text-slate-900 leading-tight">{nombre_curso}</div>
          </div>

          <hr class="border-slate-200" />

          <div class="space-y-3">
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
              Estado de la actividad
            </div>

            <div
              class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm"
            >
              {estado?.toUpperCase()}
            </div>
            {#if exedioFechaLimite()}
              <div
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm bg-red-400"
              >
                Ha excedido la fecha límite
              </div>
            {:else if !exedioFechaLimite() && puedeSubirArchivo}
              <div
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm bg-green-400"
              >
                Aún se aceptan entregas
              </div>
            {/if}
          </div>

          <hr class="border-slate-200" />

          <div class="flex flex-col gap-2">
            <button
              class="group flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 text-left text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
              onclick={toggleEnunciadoModal}
            >
              <svg
                class="text-slate-400 group-hover:text-slate-500"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg>
              <span class="flex-1">Ver Enunciado</span>
              <svg
                class="text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-slate-500"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="9 18 15 12 9 6" />
              </svg>
            </button>

            {#if es_sumativa}
              <button
                class="group flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 text-left text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                onclick={toggleRubricaModal}
              >
                <svg
                  class="text-slate-400 group-hover:text-slate-500"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path
                    d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"
                  />
                  <rect x="9" y="3" width="6" height="4" rx="2" />
                  <line x1="9" y1="12" x2="15" y2="12" />
                  <line x1="9" y1="16" x2="12" y2="16" />
                </svg>
                <span class="flex-1">Ver Rúbrica</span>
                <svg
                  class="text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-slate-500"
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <polyline points="9 18 15 12 9 6" />
                </svg>
              </button>
            {/if}
          </div>

          <ActivityMembersCard usuarios={resto_integrantes} />
        </aside>

        <main class="space-y-6 md:col-span-3">
          <ActivityHeaderCard
            {cod_actividad}
            {nombre_actividad}
            {nombre_curso}
            {descripcion}
            {fecha_limite}
            {es_sumativa}
            {entrega_obligatoria}
          />

          {#if estaActiva}
            <div class="grid sm:grid-cols-2 grid-cols-1 gap-6">
              <ActivityGradeCard {ultima_nota} {es_sumativa} />
              <ActivityRubricaCard rubrica={rubrica?.rubrica} onRubricaClick={toggleRubricaModal} />
            </div>
          {/if}

          <ActivityPendingCard
            disponible={puedeSubirArchivo}
            onSubirClick={() => toggleEntregaModal()}
          />

          {#if id_actividad_asignada_grupo}
            <ActivityAgendaCard
              {cod_curso}
              {nombre_curso}
              {cod_actividad}
              {nombre_actividad}
              {listado_interacciones}
              {id_actividad_asignada_grupo}
              onAgendaClick={toggleAgendaModal}
            />
          {/if}
        </main>
      </div>
    </div>
  </div>
</StudentLayout>

<!-- 
  SECCIÓN DE MODALES  
  -->
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
      {cod_curso}
      {nombre_curso}
      {cod_actividad}
      {nombre_actividad}
      {listado_interacciones}
      {id_actividad_asignada_grupo}
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
    <div
      class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl overflow-hidden border border-slate-200"
    >
      <div class="flex items-center justify-between border-b border-slate-100 p-5 md:px-6">
        <div>
          <div class="text-xs font-bold tracking-wider text-slate-400 uppercase">
            Rúbrica de Evaluación
          </div>
          <div class="text-base font-black text-slate-900 mt-0.5">{nombre_actividad}</div>
        </div>
        <button
          class="rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
          onclick={toggleRubricaModal}
          aria-label="Cerrar"
        >
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-5 md:p-6">
        {#if rubrica}
          <RubricaView rubrica={rubrica?.rubrica} />
        {:else}
          <p class="text-center text-sm font-medium text-slate-400 py-8">
            No hay rúbrica disponible para esta actividad.
          </p>
        {/if}
      </div>
    </div>
  </div>
{/if}

{#if showEntregaModal}
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
      onEntregaEnviada={handleGuardarEntrada}
      {cod_curso}
      {nombre_curso}
      {cod_actividad}
      {nombre_actividad}
      {entrega_obligatoria}
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
      url_archivo={''}
      nombre_archivo={''}
      tipo_archivo={'pdf'}
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
