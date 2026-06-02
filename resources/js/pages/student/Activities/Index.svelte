<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import ActividadInteraccion from './ActividadInteraccion.svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { Rubrica } from '@/types/rubrica';
  import { Link } from '@inertiajs/svelte';
  import { ChevronLeft } from 'lucide-svelte';
  import Agenda from './Agenda/Agenda.svelte';
  import RubricaView from './Agenda/Rubrica.svelte';
  import Entrega from './Agenda/Entrega.svelte';
  import Enunciado from './Agenda/Enunciado.svelte';
  import Informaciones from './Agenda/Informaciones.svelte';

  interface Props {
    cod_curso: string;
    nombre_curso: string;
    cod_actividad: string;
    nombre_actividad: string;
    descripcion: string;
    fecha_limite: string;
    es_sumativa: boolean;
    trae_archivo: boolean;
    entrega_obligatoria: boolean;
    ultima_nota?: number | null;
    ultimo_estado?: string | null;
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
    rubrica?: Rubrica | null;
    id_actividad_asignada_grupo?: number | null;
  }

  let {
    cod_curso,
    nombre_curso,
    cod_actividad,
    nombre_actividad,
    descripcion,
    fecha_limite,
    es_sumativa,
    trae_archivo,
    entrega_obligatoria,
    ultima_nota,
    ultimo_estado,
    entradas: _entradas,
    listado_interacciones = [],
    rubrica,
    id_actividad_asignada_grupo,
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: nombre_curso, href: '/estudiante/cursos' },
    { title: nombre_actividad, href: '' },
  ]);

  let stripTone = 'pass';

  let showRubricaModal = $state(false);
  let showAgendaModal = $state(false);

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

  let showInfoModal = $state(false);
  function toggleInfoModal() {
    showAgendaModal = false;
    showRubricaModal = false;
    showEntregaModal = false;
    showInfoModal = !showInfoModal;
  }

  async function handleGuardarEntrada(data: { tipo: string; mensaje: string }) {
    if (!id_actividad_asignada_grupo) {
      alert('Error: No se pudo encontrar la actividad asignada');
      return;
    }

    try {
      const response = await fetch('/estudiante/actividades/agenda/guardar-entrada', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN':
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          id_actividad_asignada_grupo,
          tipo: data.tipo,
          mensaje: data.mensaje,
        }),
      });

      const result = await response.json();

      if (response.ok && result.success) {
        // Recargar la página para mostrar el nuevo mensaje
        window.location.reload();
      } else {
        alert('Error al guardar la entrada: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      console.error('Error al guardar entrada:', error);
      alert(
        'Error al guardar la entrada: ' +
          (error instanceof Error ? error.message : 'Error desconocido'),
      );
    }
  }

  const stateLabel = $derived.by(() => {
    if (stripTone === 'pass') return 'APROBADO';
    if (stripTone === 'fail') return 'REPROBADO';
    if (stripTone === 'submitted') return 'ENTREGADA';
    return 'PENDIENTE';
  });
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
              Estado actual
            </div>

            <div
              class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm
              {stripTone === 'pass'
                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                : ''}
              {stripTone === 'fail' ? 'bg-rose-50 text-rose-700 border border-rose-200' : ''}
              {stripTone === 'submitted' ? 'bg-blue-50 text-blue-700 border border-blue-200' : ''}
              {stripTone === 'pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' : ''}
            "
            >
              {stateLabel}
            </div>

            {#if ultima_nota !== null && ultima_nota !== undefined}
              <div class="flex items-baseline gap-1 pt-1">
                <span
                  class="text-3xl font-extrabold tracking-tight
                  {ultima_nota >= 4 ? 'text-emerald-600' : 'text-rose-600'}"
                >
                  {ultima_nota.toFixed(1)}
                </span>
                <span class="text-sm font-medium text-slate-400">&nbsp;/ 7.0</span>
              </div>
            {/if}
          </div>

          <hr class="border-slate-200" />

          <div class="flex flex-col gap-2">
            {#if trae_archivo}
              <button
                class="group flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 text-left text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
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
            {/if}

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

            {#if id_actividad_asignada_grupo}
              <button
                class="group flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 text-left text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                onclick={toggleAgendaModal}
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
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
                <span class="flex-1">Ver Agenda</span>
                {#if listado_interacciones.length > 0}
                  <span
                    class="inline-flex h-5 items-center justify-center rounded-full bg-blue-600 px-2 text-xs font-bold text-white"
                  >
                    {listado_interacciones.length}
                  </span>
                {/if}
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
        </aside>

        <main class="space-y-6 md:col-span-3">
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
            <div class="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-500">
              <span class="font-mono text-slate-700">{cod_actividad}</span>
              <span class="text-slate-300">·</span>
              <span>{nombre_curso}</span>
            </div>

            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">
              {nombre_actividad}
            </h1>

            <div class="mt-4 flex flex-wrap gap-2">
              {#if es_sumativa}
                <span
                  class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 border border-amber-100"
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Sumativa
                </span>
              {:else}
                <span
                  class="inline-flex items-center gap-1.5 rounded-md bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 border border-sky-100"
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>Formativa
                </span>
              {/if}
              {#if entrega_obligatoria}
                <span
                  class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-800 border border-rose-100"
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>Entrega obligatoria
                </span>
              {/if}
              {#if trae_archivo}
                <span
                  class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                >
                  <svg
                    width="10"
                    height="10"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                  >
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                  </svg>
                  Con enunciado
                </span>
              {/if}
            </div>

            {#if descripcion}
              <div class="mt-6">
                <span class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                  >Descripción</span
                >
                <p class="mt-1 text-sm leading-relaxed text-slate-600">{descripcion}</p>
              </div>
            {/if}

            <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3 border-t border-slate-100 pt-6">
              <div class="space-y-1">
                <span class="text-xs font-medium text-slate-400">Fecha límite</span>
                <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                  <svg
                    width="13"
                    height="13"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  {fecha_limite}
                </span>
              </div>
              <div class="space-y-1">
                <span class="text-xs font-medium text-slate-400">Tipo</span>
                <span class="block text-sm font-semibold text-slate-700"
                  >{es_sumativa ? 'Sumativa' : 'Formativa'}</span
                >
              </div>
              <div class="space-y-1">
                <span class="text-xs font-medium text-slate-400">Entrega</span>
                <span class="block text-sm font-semibold text-slate-700"
                  >{entrega_obligatoria ? 'Obligatoria' : 'Opcional'}</span
                >
              </div>
            </div>
          </div>

          <div
            class="flex flex-col items-start justify-between gap-4 rounded-xl p-5 shadow-sm sm:flex-row sm:items-center border
            {stripTone === 'pass' ? 'bg-emerald-50/60 border-emerald-100 text-emerald-900' : ''}
            {stripTone === 'fail' ? 'bg-rose-50/60 border-rose-100 text-rose-900' : ''}
            {stripTone === 'submitted' ? 'bg-blue-50/60 border-blue-100 text-blue-900' : ''}
            {stripTone === 'pending' ? 'bg-amber-50/60 border-amber-100 text-amber-900' : ''}
          "
          >
            <div class="flex items-center gap-3.5">
              <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg shadow-sm
                {stripTone === 'pass' ? 'bg-emerald-600 text-white' : ''}
                {stripTone === 'fail' ? 'bg-rose-600 text-white' : ''}
                {stripTone === 'submitted' ? 'bg-blue-600 text-white' : ''}
                {stripTone === 'pending' ? 'bg-amber-600 text-white' : ''}
              "
              >
                {#if stripTone === 'pass'}
                  <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
                  >
                {:else if stripTone === 'fail'}
                  <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    ><line x1="18" y1="6" x2="6" y2="18" /><line
                      x1="6"
                      y1="6"
                      x2="18"
                      y2="18"
                    /></svg
                  >
                {:else if stripTone === 'submitted'}
                  <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    ><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline
                      points="17 8 12 3 7 8"
                    /><line x1="12" y1="3" x2="12" y2="15" /></svg
                  >
                {:else}
                  <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line
                      x1="12"
                      y1="16"
                      x2="12.01"
                      y2="16"
                    /></svg
                  >
                {/if}
              </div>
              <div>
                <span class="block text-xs font-medium opacity-70">Estado de tu trabajo</span>
                <span class="block text-base font-bold">{stateLabel}</span>
              </div>
            </div>

            <div class="flex flex-1 flex-wrap items-center gap-4 sm:justify-end">
              {#if ultimo_estado}
                <div class="text-sm">
                  <span class="opacity-60 block text-xs">Estado interno</span>
                  <span class="font-semibold">{ultimo_estado}</span>
                </div>
              {/if}
              {#if es_sumativa && (stripTone === 'pass' || stripTone === 'fail')}
                <div class="hidden h-8 w-px bg-current opacity-20 sm:block"></div>
                <div class="text-sm">
                  <span class="opacity-60 block text-xs">Evaluación</span>
                  <button
                    class="font-bold underline transition-opacity hover:opacity-80"
                    onclick={toggleRubricaModal}>Ver rúbrica →</button
                  >
                </div>
              {/if}
            </div>

            <div
              class="flex items-baseline gap-0.5 border-t border-current/10 pt-3 text-right sm:border-0 sm:pt-0"
            >
              {#if ultima_nota !== null && ultima_nota !== undefined}
                <span class="text-3xl font-black tracking-tight">{ultima_nota.toFixed(1)}</span>
                <span class="text-xs font-bold opacity-60">/ 7.0</span>
              {:else}
                <span class="text-2xl font-bold opacity-40">–</span>
              {/if}
            </div>
          </div>

          {#if stripTone === 'pending'}
            {#if trae_archivo}
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <button
                  class="group flex items-center gap-4 rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50/50 to-white p-5 text-left transition-all hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md hover:shadow-blue-500/5"
                >
                  <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm shadow-blue-500/20"
                  >
                    <svg
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                      <polyline points="17 8 12 3 7 8" />
                      <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="text-xs font-bold tracking-wider text-blue-600 uppercase">
                      Área de entrega
                    </div>
                    <div class="text-base font-bold text-slate-900">Subir trabajo</div>
                    <div class="text-xs text-slate-500">Adjunta tu archivo de entrega</div>
                  </div>
                  <svg
                    class="text-slate-300 transition-transform group-hover:translate-x-1 group-hover:text-slate-400"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg
                  >
                </button>

                <button
                  class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 text-left transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md hover:shadow-slate-500/5"
                >
                  <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-600"
                  >
                    <svg
                      width="22"
                      height="22"
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
                  </div>
                  <div class="flex-1">
                    <div class="text-xs font-bold tracking-wider text-slate-400 uppercase">
                      Documento
                    </div>
                    <div class="text-base font-bold text-slate-900">Enunciado</div>
                    <div class="text-xs text-slate-500">Instrucciones de la actividad</div>
                  </div>
                  <svg
                    class="text-slate-300 transition-transform group-hover:translate-x-1 group-hover:text-slate-400"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg
                  >
                </button>
              </div>
            {:else}
              <div
                class="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-slate-200 bg-white p-6 text-center sm:flex-row sm:text-left mb-[18px]"
              >
                <div
                  class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
                >
                  <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                  </svg>
                </div>
                <div class="flex-1">
                  <h3 class="text-base font-bold text-slate-900">Subir trabajo</h3>
                  <p class="text-xs text-slate-500">
                    Adjunta tu archivo de entrega para esta actividad
                  </p>
                </div>
                <button
                  class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                >
                  <svg
                    width="15"
                    height="15"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                  </svg>
                  Subir archivo
                </button>
              </div>
            {/if}
          {:else}
            <div
              class="flex flex-col items-center gap-4 rounded-2xl border border-slate-200 bg-slate-100/60 p-5 sm:flex-row"
            >
              <div
                class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-200 text-slate-500"
              >
                {#if stripTone === 'pass' || stripTone === 'fail'}
                  <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
                  >
                {:else}
                  <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    ><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline
                      points="7 10 12 15 17 10"
                    /><line x1="12" y1="15" x2="12" y2="3" /></svg
                  >
                {/if}
              </div>
              <div class="flex-1 text-center sm:text-left">
                <h3 class="text-sm font-bold text-slate-900">Tu entrega</h3>
                <p class="text-xs text-slate-500">
                  Tu trabajo ha sido entregado.
                  {#if stripTone === 'pass' || stripTone === 'fail'}
                    No es posible volver a subir un archivo porque ya fue calificada.
                  {/if}
                </p>
              </div>
              <button
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 disabled:opacity-50 disabled:hover:bg-white"
                disabled
              >
                <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.6"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Descargar entrega
              </button>
            </div>

            {#if trae_archivo}
              <div class="space-y-3 mb-1">
                <button
                  class="group flex w-full items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50"
                >
                  <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                  >
                    <svg
                      width="18"
                      height="18"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      ><path
                        d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                      /><polyline points="14 2 14 8 20 8" /><line
                        x1="16"
                        y1="13"
                        x2="8"
                        y2="13"
                      /><line x1="16" y1="17" x2="8" y2="17" /></svg
                    >
                  </div>
                  <div class="flex-1">
                    <div class="text-sm font-bold text-slate-900">Ver Enunciado</div>
                    <div class="text-xs text-slate-500">Instrucciones de la actividad</div>
                  </div>
                  <svg
                    class="text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-slate-400"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg
                  >
                </button>
              </div>
            {/if}
          {/if}

          {#if es_sumativa}
            <div class="space-y-3">
              <button
                class="group flex w-full items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50"
                onclick={toggleRubricaModal}
              >
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                >
                  <svg
                    width="18"
                    height="18"
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
                </div>
                <div class="flex-1">
                  <div class="text-sm font-bold text-slate-900">Ver Rúbrica de Evaluación</div>
                  <div class="text-xs text-slate-500">
                    {rubrica ? 'Criterios y niveles de desempeño' : 'Aún no hay rúbrica asignada'}
                  </div>
                </div>
                <svg
                  class="text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-slate-400"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg
                >
              </button>
            </div>
          {/if}

          {#if id_actividad_asignada_grupo}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
              <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                <div
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600"
                >
                  <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <h2 class="text-base font-bold text-slate-900">Agenda de la Actividad</h2>
                  <p class="text-xs text-slate-500">
                    Retroalimentaciones y mensajes entre docente y estudiante
                  </p>
                </div>
                <button
                  class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                  onclick={toggleAgendaModal}
                >
                  <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                  </svg>
                  Abrir mensajes
                </button>
              </div>

              <div class="rounded-xl bg-slate-50 p-4">
                {#if listado_interacciones.length > 0}
                  {@const last = listado_interacciones[listado_interacciones.length - 1]}
                  <div class="flex items-start gap-3">
                    <div
                      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm
                      {last.es_de_docente ? 'bg-indigo-600' : 'bg-emerald-600'}"
                    >
                      {last.es_de_docente ? 'D' : 'T'}
                    </div>
                    <div class="flex-1 space-y-1">
                      <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="font-bold text-slate-900"
                          >{last.es_de_docente ? 'Docente' : 'Tú'}</span
                        >
                        <span class="text-slate-300">·</span>
                        <span class="font-medium text-slate-500">{last.tipo_interaccion}</span>
                        <span class="ml-auto text-slate-400 font-medium">
                          {listado_interacciones.length} mensaje{listado_interacciones.length !== 1
                            ? 's'
                            : ''} en total
                        </span>
                      </div>
                      <p class="text-sm leading-relaxed text-slate-600 line-clamp-2">
                        {last.mensaje}
                      </p>
                    </div>
                  </div>
                {:else}
                  <div
                    class="flex flex-col items-center justify-center gap-2 py-4 text-center text-slate-400"
                  >
                    <svg
                      width="20"
                      height="20"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                    <span class="text-xs font-medium"
                      >No hay mensajes aún. Abre la agenda para enviar una consulta.</span
                    >
                  </div>
                {/if}
              </div>
            </div>
          {/if}
        </main>
      </div>
    </div>

    {#if showAgendaModal}
      <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-opacity"
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
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-opacity"
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
              <RubricaView {rubrica} modoLectura={true} />
            {:else}
              <p class="text-center text-sm font-medium text-slate-400 py-8">
                No hay rúbrica disponible para esta actividad.
              </p>
            {/if}
          </div>
        </div>
      </div>
    {/if}
  </div>
</StudentLayout>

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape') {
      showRubricaModal = false;
      showAgendaModal = false;
    }
  }}
/>
