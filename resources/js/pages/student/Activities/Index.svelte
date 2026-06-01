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

  const stateLabel = $derived.by(() => {
    if (stripTone === 'pass') return 'APROBADO';
    if (stripTone === 'fail') return 'REPROBADO';
    if (stripTone === 'submitted') return 'ENTREGADA';
    return 'PENDIENTE';
  });
</script>

<StudentLayout {breadcrumbs}>
  <div class="pg">
    <!-- Heritage stripe -->
    <div class="heritage-stripe">
      <div class="h-navy"></div>
      <div class="h-wine"></div>
      <div class="h-gold"></div>
      <div class="h-navy2"></div>
    </div>

    <div class="shell">
      <!-- Sidebar -->
      <aside class="sidebar" aria-label="Información de la actividad">
        <button class="side-back" onclick={() => window.history.back()}>
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

        <div class="side-course">
          <div class="side-course-eyebrow">{cod_curso}</div>
          <div class="side-course-name">{nombre_curso}</div>
        </div>

        <div class="side-divider"></div>

        <div class="side-status-block">
          <div class="side-section-label">Estado actual</div>
          <div class="side-status-pill {stripTone}">{stateLabel}</div>
          {#if ultima_nota !== null && ultima_nota !== undefined}
            <div class="side-grade-row">
              <span class="side-grade-num {ultima_nota >= 4 ? 'pass' : 'fail'}"
                >{ultima_nota.toFixed(1)}</span
              >
              <span class="side-grade-scale">&nbsp;/ 7.0</span>
            </div>
          {/if}
        </div>

        <div class="side-divider"></div>

        {#if trae_archivo}
          <button class="side-action">
            <svg
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
            <span>Ver Enunciado</span>
            <svg
              class="side-action-arrow"
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
          <button class="side-action" style="margin-top: 8px" onclick={toggleRubricaModal}>
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
              <rect x="9" y="3" width="6" height="4" rx="2" />
              <line x1="9" y1="12" x2="15" y2="12" />
              <line x1="9" y1="16" x2="12" y2="16" />
            </svg>
            <span>Ver Rúbrica</span>
            <svg
              class="side-action-arrow"
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

        <button class="side-action" style="margin-top: 8px" onclick={toggleAgendaModal}>
          <svg
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
          <span>Ver Agenda</span>
          {#if listado_interacciones.length > 0}
            <span class="side-action-badge">{listado_interacciones.length}</span>
          {/if}
          <svg
            class="side-action-arrow"
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
      </aside>

      <!-- Main -->
      <main class="main-col">
        <!-- Hero card -->
        <div class="detail-hero">
          <div class="detail-eyebrow">
            <span class="detail-eyebrow-code">{cod_actividad}</span>
            <span class="detail-eyebrow-sep">·</span>
            <span>{nombre_curso}</span>
          </div>
          <h1 class="detail-title">{nombre_actividad}</h1>
          <div class="detail-tags">
            {#if es_sumativa}
              <span class="act-tag sumativa"><span class="act-tag-dot"></span>Sumativa</span>
            {:else}
              <span class="act-tag formativa"><span class="act-tag-dot"></span>Formativa</span>
            {/if}
            {#if entrega_obligatoria}
              <span class="act-tag obligatoria"
                ><span class="act-tag-dot"></span>Entrega obligatoria</span
              >
            {/if}
            {#if trae_archivo}
              <span class="act-tag">
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
            <span class="detail-desc-label">Descripción</span>
            <p class="detail-desc">{descripcion}</p>
          {/if}
          <div class="detail-meta">
            <div class="detail-meta-item">
              <span class="detail-meta-key">Fecha límite</span>
              <span class="detail-meta-val">
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
            <div class="detail-meta-item">
              <span class="detail-meta-key">Tipo</span>
              <span class="detail-meta-val">{es_sumativa ? 'Sumativa' : 'Formativa'}</span>
            </div>
            <div class="detail-meta-item">
              <span class="detail-meta-key">Entrega</span>
              <span class="detail-meta-val">{entrega_obligatoria ? 'Obligatoria' : 'Opcional'}</span
              >
            </div>
          </div>
        </div>

        <!-- Grade / status strip -->
        <div class="grade-strip {stripTone}">
          <div class="grade-state">
            <div class="grade-state-icon">
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
                  ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
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
            <div class="grade-state-text">
              <span class="grade-state-eyebrow">Estado de tu trabajo</span>
              <span class="grade-state-label">{stateLabel}</span>
            </div>
          </div>
          <div class="grade-detail">
            {#if ultimo_estado}
              <div class="grade-detail-item">
                <span class="grade-detail-key">Estado interno</span>
                <span class="grade-detail-val">{ultimo_estado}</span>
              </div>
            {/if}
            {#if es_sumativa && (stripTone === 'pass' || stripTone === 'fail')}
              <div class="grade-detail-sep"></div>
              <div class="grade-detail-item">
                <span class="grade-detail-key">Evaluación</span>
                <span class="grade-detail-val">
                  <button class="grade-number-link" onclick={toggleRubricaModal}
                    >Ver rúbrica →</button
                  >
                </span>
              </div>
            {/if}
          </div>
          <div class="grade-number">
            {#if ultima_nota !== null && ultima_nota !== undefined}
              <span class="grade-number-big">{ultima_nota.toFixed(1)}</span>
              <span class="grade-number-scale">/ 7.0</span>
            {:else}
              <span class="grade-number-dash">–</span>
            {/if}
          </div>
        </div>

        <!-- Submission / upload area -->
        {#if stripTone === 'pending'}
          {#if trae_archivo}
            <div class="primary-actions">
              <button class="action-card primary">
                <div class="action-icon">
                  <svg
                    width="28"
                    height="28"
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
                <div class="action-text">
                  <div class="action-label">Área de entrega</div>
                  <div class="action-title">Subir trabajo</div>
                  <div class="action-sub">Adjunta tu archivo de entrega</div>
                </div>
                <div class="action-arrow">
                  <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg
                  >
                </div>
              </button>
              <button class="action-card secondary">
                <div class="action-icon">
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
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                  </svg>
                </div>
                <div class="action-text">
                  <div class="action-label">Documento</div>
                  <div class="action-title">Enunciado</div>
                  <div class="action-sub">Instrucciones de la actividad</div>
                </div>
                <div class="action-arrow">
                  <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg
                  >
                </div>
              </button>
            </div>
          {:else}
            <div class="upload-card" style="margin-bottom: 18px">
              <div class="upload-icon upload-icon-active">
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
              <div class="upload-text">
                <h3 class="upload-title">Subir trabajo</h3>
                <p class="upload-sub">Adjunta tu archivo de entrega para esta actividad</p>
              </div>
              <button class="upload-btn">
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
          <!-- Submitted or graded: locked -->
          <div class="upload-card locked">
            <div class="upload-icon">
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
            <div class="upload-text">
              <h3 class="upload-title">Tu entrega</h3>
              <p class="upload-sub">
                Tu trabajo ha sido entregado.
                {#if stripTone === 'pass' || stripTone === 'fail'}
                  No es posible volver a subir un archivo porque ya fue calificada.
                {/if}
              </p>
            </div>
            <button class="upload-btn" disabled>
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
            <div class="secondary-actions" style="margin-bottom: 4px">
              <button class="sec-action">
                <div class="sec-action-icon">
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
                <div class="sec-action-text">
                  <div class="sec-action-title">Ver Enunciado</div>
                  <div class="sec-action-sub">Instrucciones de la actividad</div>
                </div>
                <svg
                  class="sec-action-arrow"
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

        <!-- Rúbrica action button -->
        {#if es_sumativa}
          <div class="secondary-actions">
            <button class="sec-action" onclick={toggleRubricaModal}>
              <div class="sec-action-icon">
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
              <div class="sec-action-text">
                <div class="sec-action-title">Ver Rúbrica de Evaluación</div>
                <div class="sec-action-sub">
                  {rubrica ? 'Criterios y niveles de desempeño' : 'Aún no hay rúbrica asignada'}
                </div>
              </div>
              <svg
                class="sec-action-arrow"
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

        <!-- Agenda section: preview + modal trigger -->
        <div class="agenda-section">
          <div class="agenda-section-head">
            <div class="agenda-section-icon">
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
            <div style="flex:1">
              <h2 class="agenda-section-title">Agenda de la Actividad</h2>
              <p class="agenda-section-sub">
                Retroalimentaciones y mensajes entre docente y estudiante
              </p>
            </div>
            <button class="agenda-open-btn" onclick={toggleAgendaModal}>
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
          <div class="agenda-preview">
            {#if listado_interacciones.length > 0}
              {@const last = listado_interacciones[listado_interacciones.length - 1]}
              <div class="agenda-preview-row">
                <div class="agenda-preview-avatar {last.es_de_docente ? 'docente' : 'student'}">
                  {last.es_de_docente ? 'D' : 'T'}
                </div>
                <div class="agenda-preview-body">
                  <div class="agenda-preview-meta">
                    <span class="agenda-preview-who">{last.es_de_docente ? 'Docente' : 'Tú'}</span>
                    <span class="agenda-preview-dot">·</span>
                    <span class="agenda-preview-type">{last.tipo_interaccion}</span>
                    <span class="agenda-preview-count"
                      >{listado_interacciones.length} mensaje{listado_interacciones.length !== 1
                        ? 's'
                        : ''} en total</span
                    >
                  </div>
                  <p class="agenda-preview-msg">{last.mensaje}</p>
                </div>
              </div>
            {:else}
              <div class="agenda-preview-empty">
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
                <span>No hay mensajes aún. Abre la agenda para enviar una consulta.</span>
              </div>
            {/if}
          </div>
        </div>
      </main>
    </div>

    <!-- Agenda modal -->
    {#if showAgendaModal}
      <div
        class="modal-overlay"
        role="dialog"
        aria-modal="true"
        tabindex="-1"
        onclick={(e) => e.target === e.currentTarget && toggleAgendaModal()}
        onkeydown={(e) => e.key === 'Escape' && toggleAgendaModal()}
      >
        <Agenda
          onCerrar={toggleAgendaModal}
          onInteraccionEnviada={(data) => console.log(data)}
          {cod_curso}
          {nombre_curso}
          {cod_actividad}
          {nombre_actividad}
          {listado_interacciones}
        />
      </div>
    {/if}

    <!-- Rúbrica modal -->
    {#if showRubricaModal}
      <div
        class="modal-overlay"
        role="dialog"
        aria-modal="true"
        tabindex="-1"
        onclick={(e) => e.target === e.currentTarget && toggleRubricaModal()}
        onkeydown={(e) => e.key === 'Escape' && toggleRubricaModal()}
      >
        <div class="modal-rubrica">
          <div class="modal-rubrica-head">
            <div>
              <div class="modal-rubrica-eyebrow">Rúbrica de Evaluación</div>
              <div class="modal-rubrica-title">{nombre_actividad}</div>
            </div>
            <button class="modal-rubrica-close" onclick={toggleRubricaModal} aria-label="Cerrar">
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
          <div class="modal-rubrica-body">
            {#if rubrica}
              <RubricaView {rubrica} modoLectura={true} />
            {:else}
              <p class="modal-rubrica-empty">No hay rúbrica disponible para esta actividad.</p>
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

<style>
  .pg {
    --bg: #ffffff;
    --surface: #ffffff;
    --surface-2: #f4f6fa;
    --surface-3: #eeeff3;
    --primary: #1e4e8c;
    --primary-2: #2a66ac;
    --primary-3: #4a84c4;
    --primary-soft: #d0e3f5;
    --primary-tint: #eef4fb;
    --accent: #0e6494;
    --accent-2: #1a80bc;
    --accent-soft: #d0eaf8;
    --accent-tint: #eaf5fc;
    --gold: #3d78b4;
    --gold-soft: #daeaf8;
    --gold-ink: #194f82;
    --ink-1: #11141b;
    --ink-2: #2c3142;
    --ink-3: #5a6075;
    --ink-4: #8a8e9c;
    --ink-5: #b8bcc8;
    --line: #d5e2f0;
    --line-2: #b8cfe8;
    --success: #2c7a4b;
    --success-soft: #e2f0e7;
    --warning-soft: #fbefd3;
    --danger: #993244;
    --danger-soft: #f5e1e4;
    --info: #1f5ba8;
    --info-soft: #e4ecf8;
    --radius: 10px;
    --radius-lg: 14px;
    --radius-xl: 18px;
    --shadow: 0 2px 6px rgba(20, 30, 55, 0.05), 0 8px 24px rgba(20, 30, 55, 0.06);
    --shadow-lg: 0 12px 40px rgba(20, 30, 55, 0.18), 0 4px 12px rgba(20, 30, 55, 0.08);

    font-family: var(--font-sans);
    background: var(--bg);
    min-height: 100vh;
    color: var(--ink-1);
    -webkit-font-smoothing: antialiased;
  }

  /* Heritage stripe */
  .heritage-stripe {
    display: flex;
    height: 4px;
    width: 100%;
  }
  .h-navy {
    flex: 6;
    background: var(--primary);
  }
  .h-wine {
    flex: 1.6;
    background: var(--accent);
  }
  .h-gold {
    flex: 1.2;
    background: var(--gold);
  }
  .h-navy2 {
    flex: 1;
    background: var(--primary);
  }

  /* Shell */
  .shell {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 28px 80px;
    display: grid;
    grid-template-columns: 264px minmax(0, 1fr);
    gap: 36px;
  }

  /* Sidebar */
  .sidebar {
    padding-top: 28px;
    position: sticky;
    top: 0;
    align-self: start;
    max-height: 100vh;
    overflow-y: auto;
  }

  :global(.side-back) {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--ink-3);
    padding: 6px 10px 6px 4px;
    border-radius: 6px;
    margin-bottom: 18px;
    transition: color 0.15s;
    text-decoration: none;
    font-family: var(--font-sans);
  }
  :global(.side-back:hover) {
    color: var(--primary);
  }

  :global(.side-action-link) {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--surface);
    border: 1px solid var(--line);
    font-size: 13px;
    color: var(--ink-2);
    font-weight: 500;
    transition: all 0.15s;
    text-decoration: none;
    font-family: var(--font-sans);
  }
  :global(.side-action-link:hover) {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-tint);
  }
  :global(.side-action-link span) {
    flex: 1;
  }

  .side-course {
    border-left: 3px solid var(--accent);
    padding: 4px 0 4px 14px;
    margin-bottom: 24px;
  }
  .side-course-eyebrow {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .side-course-name {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 4px 0 0;
    color: var(--ink-1);
    line-height: 1.1;
  }

  .side-divider {
    height: 1px;
    background: var(--line);
    margin: 18px 0;
  }

  .side-action {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--surface);
    border: 1px solid var(--line);
    font-size: 13px;
    color: var(--ink-2);
    font-weight: 500;
    transition: all 0.15s;
    text-align: left;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .side-action span {
    flex: 1;
  }
  .side-action:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-tint);
  }
  .side-action-arrow {
    margin-left: auto;
  }

  /* Main */
  .main-col {
    padding-top: 28px;
  }

  .detail-head {
    margin-bottom: 24px;
  }

  .back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--ink-2);
    background: var(--surface);
    border: 1px solid var(--line);
    transition: all 0.15s;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .back-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
  }

  /* Hero card */
  .detail-hero {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-xl);
    padding: 32px 36px 0;
    margin-bottom: 18px;
    position: relative;
    overflow: hidden;
  }
  .detail-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(
      90deg,
      var(--primary) 0% 70%,
      var(--accent) 70% 88%,
      var(--gold) 88% 100%
    );
  }

  .detail-eyebrow {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--ink-3);
    font-weight: 600;
    margin-bottom: 12px;
  }
  .detail-eyebrow-code {
    font-family: var(--font-mono);
    font-size: 11px;
    letter-spacing: 0.04em;
    padding: 3px 8px;
    background: var(--primary);
    color: #fff;
    border-radius: 4px;
  }
  .detail-eyebrow-sep {
    color: var(--ink-5);
  }

  .detail-title {
    font-family: var(--font-display);
    font-size: 42px;
    font-weight: 600;
    letter-spacing: -0.018em;
    line-height: 1.04;
    margin: 0 0 18px;
    color: var(--ink-1);
    max-width: 760px;
  }

  .detail-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 22px;
  }

  .act-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 500;
    padding: 4px 9px;
    border-radius: 999px;
    color: var(--ink-2);
    background: var(--surface-3);
    border: 1px solid var(--line);
  }
  .act-tag.sumativa {
    background: var(--accent-tint);
    color: var(--accent);
    border-color: var(--accent-soft);
  }
  .act-tag.formativa {
    background: var(--primary-tint);
    color: var(--primary);
    border-color: var(--primary-soft);
  }
  .act-tag.obligatoria {
    background: var(--gold-soft);
    color: var(--gold-ink);
    border-color: #9dc3e8;
  }
  .act-tag-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
  }

  .detail-desc-label {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
    margin-bottom: 8px;
    display: block;
  }
  .detail-desc {
    font-size: 15px;
    line-height: 1.65;
    color: var(--ink-2);
    max-width: 720px;
    margin: 0 0 24px;
  }

  .detail-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    border-top: 1px solid var(--line);
    margin: 0 -36px;
    padding: 0;
  }
  .detail-meta-item {
    flex: 1;
    min-width: 140px;
    padding: 18px 24px;
    border-right: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .detail-meta-item:last-child {
    border-right: 0;
  }
  .detail-meta-key {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .detail-meta-val {
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-1);
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Grade strip */
  .grade-strip {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 28px;
    padding: 22px 28px;
    border-radius: var(--radius-lg);
    margin-bottom: 18px;
    border: 1px solid;
  }
  .grade-strip.fail {
    background: linear-gradient(90deg, var(--danger-soft) 0%, var(--surface-2) 100%);
    border-color: #e9c5cb;
  }
  .grade-strip.pass {
    background: linear-gradient(90deg, var(--success-soft) 0%, var(--surface-2) 100%);
    border-color: #bfe0c9;
  }
  .grade-strip.pending {
    background: linear-gradient(90deg, var(--warning-soft) 0%, var(--surface-2) 100%);
    border-color: #9dc3e8;
  }
  .grade-strip.submitted {
    background: linear-gradient(90deg, var(--info-soft) 0%, var(--surface-2) 100%);
    border-color: #b8d0f0;
  }

  .grade-state {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .grade-state-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid currentColor;
  }
  .grade-strip.fail .grade-state-icon {
    color: var(--danger);
  }
  .grade-strip.pass .grade-state-icon {
    color: var(--success);
  }
  .grade-strip.pending .grade-state-icon {
    color: var(--gold-ink);
  }
  .grade-strip.submitted .grade-state-icon {
    color: var(--info);
  }

  .grade-state-text {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
  }
  .grade-state-eyebrow {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .grade-state-label {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 600;
    letter-spacing: -0.01em;
  }
  .grade-strip.fail .grade-state-label {
    color: var(--danger);
  }
  .grade-strip.pass .grade-state-label {
    color: var(--success);
  }
  .grade-strip.pending .grade-state-label {
    color: var(--gold-ink);
  }
  .grade-strip.submitted .grade-state-label {
    color: var(--info);
  }

  .grade-detail {
    display: flex;
    align-items: center;
    gap: 14px 18px;
    flex-wrap: wrap;
    min-width: 0;
    font-size: 12.5px;
    color: var(--ink-3);
  }
  .grade-detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .grade-detail-key {
    font-size: 10.5px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .grade-detail-val {
    font-size: 13px;
    color: var(--ink-2);
    font-weight: 500;
  }
  .grade-detail-sep {
    width: 1px;
    height: 28px;
    background: currentColor;
    opacity: 0.2;
  }

  .grade-number {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    line-height: 1;
    gap: 4px;
  }
  .grade-number-big {
    font-family: var(--font-display);
    font-size: 56px;
    font-weight: 600;
    letter-spacing: -0.03em;
    line-height: 0.9;
  }
  .grade-strip.fail .grade-number-big {
    color: var(--danger);
  }
  .grade-strip.pass .grade-number-big {
    color: var(--success);
  }
  .grade-number-dash {
    font-family: var(--font-display);
    font-size: 40px;
    font-weight: 600;
    color: var(--ink-4);
  }
  .grade-number-scale {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ink-4);
    letter-spacing: 0.04em;
  }
  .grade-number-link {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--primary);
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    font-family: var(--font-sans);
  }
  .grade-number-link:hover {
    text-decoration: underline;
  }

  /* Primary actions */
  .primary-actions {
    display: grid;
    grid-template-columns: 1.7fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
  }

  .action-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-lg);
    padding: 24px 26px;
    display: flex;
    align-items: center;
    gap: 20px;
    text-align: left;
    width: 100%;
    transition: all 0.15s;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .action-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
    transform: translateY(-1px);
  }

  .action-card.primary {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    padding: 28px 30px;
  }
  .action-card.primary:hover {
    background: var(--primary-2);
    border-color: var(--primary-2);
  }
  .action-card.primary::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 180px;
    height: 100%;
    background: radial-gradient(circle at 80% 50%, rgba(255, 255, 255, 0.06) 0%, transparent 60%);
    pointer-events: none;
  }

  .action-icon {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    background: var(--primary-tint);
    color: var(--primary);
    display: grid;
    place-items: center;
    flex-shrink: 0;
  }
  .action-card.primary .action-icon {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
  }
  .action-card.secondary .action-icon {
    background: var(--accent-tint);
    color: var(--accent);
  }

  .action-text {
    flex: 1;
    min-width: 0;
  }
  .action-label {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
    margin-bottom: 4px;
  }
  .action-card.primary .action-label {
    color: rgba(255, 255, 255, 0.65);
  }
  .action-title {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 600;
    letter-spacing: -0.012em;
    line-height: 1.1;
    margin: 0 0 6px;
  }
  .action-sub {
    font-size: 13px;
    color: var(--ink-3);
    line-height: 1.4;
  }
  .action-card.primary .action-sub {
    color: rgba(255, 255, 255, 0.7);
  }
  .action-file-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ink-4);
    margin-top: 8px;
  }
  .action-file-meta-chip {
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: rgba(255, 255, 255, 0.85);
  }

  .action-arrow {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: var(--surface-3);
    color: var(--primary);
    flex-shrink: 0;
    transition: transform 0.15s;
  }
  .action-card:hover .action-arrow {
    transform: translateX(4px);
  }
  .action-card.primary .action-arrow {
    background: var(--gold);
    color: var(--primary);
  }
  .action-card.secondary .action-title {
    font-size: 19px;
  }

  /* Secondary actions */
  .secondary-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
  }
  .sec-action {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    text-align: left;
    width: 100%;
    transition: all 0.15s;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .sec-action:hover {
    border-color: var(--accent);
    background: var(--accent-tint);
  }
  .sec-action:hover .sec-action-icon {
    background: var(--accent);
    color: #fff;
  }
  .sec-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: var(--accent-tint);
    color: var(--accent);
    display: grid;
    place-items: center;
    flex-shrink: 0;
    transition: all 0.15s;
  }
  .sec-action-text {
    flex: 1;
    min-width: 0;
  }
  .sec-action-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-1);
    line-height: 1.2;
    margin-bottom: 2px;
  }
  .sec-action-sub {
    font-size: 12px;
    color: var(--ink-3);
  }
  .sec-action-arrow {
    color: var(--ink-4);
  }

  /* Upload card */
  .upload-card {
    background: var(--surface);
    border: 1px dashed var(--line-2);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 18px;
  }
  .upload-card.locked {
    border-style: solid;
    background: var(--surface-2);
  }
  .upload-icon {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    background: var(--surface-3);
    color: var(--ink-4);
    display: grid;
    place-items: center;
    flex-shrink: 0;
  }
  .upload-icon-active {
    background: var(--info-soft) !important;
    color: var(--info) !important;
  }
  .upload-text {
    flex: 1;
  }
  .upload-title {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 4px;
    color: var(--ink-1);
  }
  .upload-sub {
    font-size: 13px;
    color: var(--ink-3);
    line-height: 1.45;
  }
  .upload-btn {
    padding: 10px 18px;
    border-radius: 8px;
    background: var(--accent);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.15s;
    border: none;
    cursor: pointer;
    font-family: var(--font-sans);
    white-space: nowrap;
  }
  .upload-btn:hover {
    background: var(--accent-2);
  }
  .upload-btn:disabled {
    background: var(--ink-5);
    cursor: not-allowed;
  }

  /* Sidebar: status block */
  .side-section-label {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
    margin: 0 0 10px;
  }
  .side-status-block {
    margin-bottom: 4px;
  }
  .side-status-pill {
    display: inline-block;
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    margin-bottom: 10px;
  }
  .side-status-pill.pass {
    background: var(--success-soft);
    color: var(--success);
  }
  .side-status-pill.fail {
    background: var(--danger-soft);
    color: var(--danger);
  }
  .side-status-pill.submitted {
    background: var(--info-soft);
    color: var(--info);
  }
  .side-status-pill.pending {
    background: var(--gold-soft);
    color: var(--gold-ink);
  }
  .side-grade-row {
    display: flex;
    align-items: baseline;
    gap: 2px;
  }
  .side-grade-num {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1;
  }
  .side-grade-num.pass {
    color: var(--success);
  }
  .side-grade-num.fail {
    color: var(--danger);
  }
  .side-grade-scale {
    font-size: 13px;
    color: var(--ink-4);
  }

  /* Agenda inline section */
  .agenda-section {
    margin-top: 28px;
    border: 1px solid var(--line);
    border-radius: var(--radius-xl);
    overflow: hidden;
  }
  .agenda-section-head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 24px;
    border-bottom: 1px solid var(--line);
    background: var(--surface-2);
  }
  .agenda-section-icon {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    background: var(--primary-tint);
    color: var(--primary);
    display: grid;
    place-items: center;
    flex-shrink: 0;
  }
  .agenda-section-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--ink-1);
    margin: 0;
  }
  .agenda-section-sub {
    font-size: 12.5px;
    color: var(--ink-3);
    margin-top: 2px;
  }

  /* Agenda: open button */
  .agenda-open-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
    background: var(--primary-tint);
    border: 1px solid var(--primary-soft);
    border-radius: 8px;
    padding: 8px 14px;
    cursor: pointer;
    transition:
      background 0.15s,
      border-color 0.15s;
    white-space: nowrap;
    flex-shrink: 0;
  }
  .agenda-open-btn:hover {
    background: var(--primary-soft);
    border-color: var(--primary-3);
  }

  /* Agenda: preview panel */
  .agenda-preview {
    padding: 16px 22px;
  }
  .agenda-preview-row {
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }
  .agenda-preview-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    font-size: 13px;
    font-weight: 700;
    display: grid;
    place-items: center;
    flex-shrink: 0;
  }
  .agenda-preview-avatar.docente {
    background: var(--primary-soft);
    color: var(--primary);
  }
  .agenda-preview-avatar.student {
    background: var(--accent-soft);
    color: var(--accent);
  }
  .agenda-preview-body {
    flex: 1;
    min-width: 0;
  }
  .agenda-preview-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    flex-wrap: wrap;
  }
  .agenda-preview-who {
    font-size: 12px;
    font-weight: 700;
    color: var(--ink-2);
  }
  .agenda-preview-dot {
    color: var(--ink-5);
    font-size: 12px;
  }
  .agenda-preview-type {
    font-size: 11px;
    color: var(--ink-4);
    background: var(--surface-3);
    border-radius: 4px;
    padding: 1px 6px;
  }
  .agenda-preview-count {
    margin-left: auto;
    font-size: 11px;
    color: var(--ink-4);
    font-style: italic;
  }
  .agenda-preview-msg {
    font-size: 13px;
    color: var(--ink-2);
    line-height: 1.45;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
  }
  .agenda-preview-empty {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--ink-4);
    font-size: 13px;
    font-style: italic;
    padding: 6px 0;
  }

  /* Sidebar: badge count */
  .side-action-badge {
    margin-left: auto;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    display: grid;
    place-items: center;
    padding: 0 5px;
  }

  /* Rúbrica modal panel */
  .modal-rubrica {
    background: var(--surface);
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 900px;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
  }
  .modal-rubrica-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 22px 28px;
    border-bottom: 1px solid var(--line);
    flex-shrink: 0;
  }
  .modal-rubrica-eyebrow {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
    margin-bottom: 4px;
  }
  .modal-rubrica-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--ink-1);
    letter-spacing: -0.01em;
  }
  .modal-rubrica-close {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--surface-3);
    color: var(--ink-2);
    display: grid;
    place-items: center;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
    flex-shrink: 0;
  }
  .modal-rubrica-close:hover {
    background: var(--line);
  }
  .modal-rubrica-body {
    flex: 1;
    overflow-y: auto;
  }
  .modal-rubrica-empty {
    padding: 40px 28px;
    color: var(--ink-3);
    font-size: 14px;
    font-style: italic;
    text-align: center;
  }

  /* Modal overlay */
  .modal-overlay {
    /* CSS variables for modal scope — modals render outside .pg so variables must be redefined here */
    --bg: #ffffff;
    --surface: #ffffff;
    --surface-2: #f4f6fa;
    --surface-3: #eeeff3;
    --primary: #1e4e8c;
    --primary-2: #2a66ac;
    --primary-soft: #d0e3f5;
    --primary-tint: #eef4fb;
    --accent: #0e6494;
    --accent-2: #1a80bc;
    --accent-soft: #d0eaf8;
    --accent-tint: #eaf5fc;
    --gold: #3d78b4;
    --gold-soft: #daeaf8;
    --gold-ink: #194f82;
    --ink-1: #11141b;
    --ink-2: #2c3142;
    --ink-3: #5a6075;
    --ink-4: #8a8e9c;
    --ink-5: #b8bcc8;
    --line: #d5e2f0;
    --line-2: #b8cfe8;
    --success: #2c7a4b;
    --success-soft: #e2f0e7;
    --warning-soft: #daeaf8;
    --danger: #993244;
    --danger-soft: #f5e1e4;
    --info: #1f5ba8;
    --info-soft: #e4ecf8;
    --font-display: 'Fraunces', Georgia, serif;
    --font-mono: ui-monospace, monospace;
    --radius: 10px;
    --radius-lg: 14px;
    --radius-xl: 18px;
    --shadow-lg: 0 12px 40px rgba(20, 30, 55, 0.18), 0 4px 12px rgba(20, 30, 55, 0.08);

    position: fixed;
    inset: 0;
    background: rgba(17, 20, 27, 0.55);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 100;
    padding: 28px;
    animation: ov-fade 0.15s ease-out;
  }
  @keyframes ov-fade {
    from {
      transform: translateY(0%);
      opacity: 0;
    }

    to {
      transform: translateY(100);
      opacity: 1;
    }
  }

  .animate-slide-in {
    animation: slide-in 0.25s ease-out;
  }
</style>
