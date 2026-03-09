<script lang="ts">
  /**
   * Panel lateral deslizante (Slide-over) para ver la malla curricular de un plan.
   *
   * Muestra el árbol de asignaturas organizadas por año y semestre sin navegar
   * a una nueva página, preservando el estado (filtros, paginación) de la tabla principal.
   */
  import type { Plan, MallaData, AsignacionPlan } from '@/types/admin.types';

  interface Props {
    isOpen: boolean;
    plan: Plan | null;
    malla: MallaData | null;
    isLoading: boolean;
    onClose: () => void;
  }

  let { isOpen, plan, malla, isLoading, onClose }: Props = $props();

  /** Organiza la malla por año → { semestre1[], semestre2[] } */
  const mallaByYear = $derived.by(() => {
    if (!malla) return {};

    const years: Record<number, { semestre1: AsignacionPlan[]; semestre2: AsignacionPlan[] }> = {};

    Object.values(malla).forEach((asignaciones) => {
      asignaciones.forEach((asig) => {
        if (!years[asig.agno_planificado]) {
          years[asig.agno_planificado] = { semestre1: [], semestre2: [] };
        }
        if (asig.semestre_planificado === 1) {
          years[asig.agno_planificado].semestre1.push(asig);
        } else {
          years[asig.agno_planificado].semestre2.push(asig);
        }
      });
    });

    return years;
  });

  const sortedYears = $derived(Object.entries(mallaByYear).sort(([a], [b]) => Number(a) - Number(b)));

  function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') onClose();
  }
</script>

<svelte:window onkeydown={handleKeydown} />

<!-- Backdrop -->
{#if isOpen}
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div class="slideover-backdrop" onclick={onClose} role="presentation"></div>
{/if}

<!-- Slide-over panel -->
<div class="slideover-panel" class:is-open={isOpen} role="dialog" aria-modal="true" aria-label="Malla Curricular">
  <!-- Header -->
  <div class="slideover-header">
    <div class="slideover-title-block">
      <h2 class="slideover-title">Malla Curricular</h2>
      {#if plan}
        <p class="slideover-subtitle">
          {plan.carrera?.nombre ?? ''} — Año {plan.agno} v{plan.version_plan}
        </p>
      {/if}
    </div>
    <div class="slideover-header-right">
      {#if plan}
        <div class="credits-chip">
          <span class="credits-chip-label">SCT</span>
          <span class="credits-chip-value">{plan.creditos_sct_totales ?? 0}</span>
        </div>
      {/if}
      <button class="close-btn" onclick={onClose} aria-label="Cerrar panel">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
  </div>

  <!-- Body -->
  <div class="slideover-body">
    {#if isLoading}
      <div class="loading-state">
        <div class="spinner"></div>
        <p>Cargando malla...</p>
      </div>
    {:else if sortedYears.length === 0}
      <div class="empty-state">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="48"
          height="48"
          viewBox="0 0 24 24"
          fill="none"
          stroke="#d1d5db"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        <p>Este plan no tiene asignaturas asignadas.</p>
      </div>
    {:else}
      <div class="malla-scroll">
        {#each sortedYears as [year, semesters]}
          <section class="year-section">
            <h3 class="year-heading">
              <span class="year-badge">Año {year}</span>
            </h3>

            <div class="semesters-grid">
              <!-- Semestre 1 -->
              <div class="semester-col">
                <div class="semester-label">
                  <span class="semester-dot dot-1"></span>
                  Semestre 1
                </div>
                <div class="asignaturas-list">
                  {#if semesters.semestre1.length === 0}
                    <p class="empty-semester">Sin asignaturas</p>
                  {:else}
                    {#each semesters.semestre1 as asig}
                      <div class="asig-card">
                        <div class="asig-top">
                          <span class="asig-code">{asig.asignatura?.cod_asignatura}</span>
                          <span class="asig-sct">{asig.asignatura?.creditos_sct ?? 0} SCT</span>
                        </div>
                        <p class="asig-name">{asig.asignatura?.nombre}</p>
                        {#if asig.tipo_ramo}
                          <span class="asig-tipo">{asig.tipo_ramo}</span>
                        {/if}
                      </div>
                    {/each}
                  {/if}
                </div>
              </div>

              <!-- Semestre 2 -->
              <div class="semester-col">
                <div class="semester-label">
                  <span class="semester-dot dot-2"></span>
                  Semestre 2
                </div>
                <div class="asignaturas-list">
                  {#if semesters.semestre2.length === 0}
                    <p class="empty-semester">Sin asignaturas</p>
                  {:else}
                    {#each semesters.semestre2 as asig}
                      <div class="asig-card">
                        <div class="asig-top">
                          <span class="asig-code">{asig.asignatura?.cod_asignatura}</span>
                          <span class="asig-sct">{asig.asignatura?.creditos_sct ?? 0} SCT</span>
                        </div>
                        <p class="asig-name">{asig.asignatura?.nombre}</p>
                        {#if asig.tipo_ramo}
                          <span class="asig-tipo">{asig.tipo_ramo}</span>
                        {/if}
                      </div>
                    {/each}
                  {/if}
                </div>
              </div>
            </div>
          </section>
        {/each}
      </div>
    {/if}
  </div>
</div>

<style>
  /* ---------- Backdrop ---------- */
  .slideover-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    z-index: 40;
    animation: fadeIn 0.2s ease;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  /* ---------- Panel ---------- */
  .slideover-panel {
    position: fixed;
    top: 0;
    right: 0;
    height: 100dvh;
    width: min(600px, 100vw);
    background: #ffffff;
    box-shadow: -4px 0 24px rgba(0, 0, 0, 0.12);
    z-index: 50;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
  }

  .slideover-panel.is-open {
    transform: translateX(0);
  }

  /* ---------- Header ---------- */
  .slideover-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
    flex-shrink: 0;
  }

  .slideover-title-block {
    min-width: 0;
  }

  .slideover-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
  }

  .slideover-subtitle {
    font-size: 0.8125rem;
    color: #6b7280;
    margin: 0.25rem 0 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .slideover-header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
  }

  .credits-chip {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    padding: 0.25rem 0.75rem;
  }

  .credits-chip-label {
    font-size: 0.6875rem;
    font-weight: 600;
    color: #3b82f6;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .credits-chip-value {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #1e40af;
  }

  .close-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    transition:
      background 0.15s,
      color 0.15s;
  }

  .close-btn:hover {
    background: #f3f4f6;
    color: #111827;
  }

  /* ---------- Body ---------- */
  .slideover-body {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
  }

  /* ---------- States ---------- */
  .loading-state,
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    height: 100%;
    padding: 3rem 2rem;
    color: #9ca3af;
    text-align: center;
  }

  .spinner {
    width: 2.5rem;
    height: 2.5rem;
    border: 3px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
  }

  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }

  /* ---------- Malla ---------- */
  .malla-scroll {
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .year-heading {
    margin: 0 0 1rem 0;
  }

  .year-badge {
    display: inline-block;
    background: #1e40af;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
  }

  .semesters-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  @media (max-width: 480px) {
    .semesters-grid {
      grid-template-columns: 1fr;
    }
  }

  .semester-col {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .semester-label {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
    padding: 0.375rem 0;
    border-bottom: 1px solid #f3f4f6;
    margin-bottom: 0.25rem;
  }

  .semester-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .dot-1 {
    background: #3b82f6;
  }
  .dot-2 {
    background: #10b981;
  }

  .asignaturas-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .empty-semester {
    font-size: 0.75rem;
    color: #d1d5db;
    font-style: italic;
    padding: 0.5rem 0;
    margin: 0;
  }

  /* ---------- Asignatura card ---------- */
  .asig-card {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.625rem 0.75rem;
    transition:
      border-color 0.15s,
      box-shadow 0.15s;
  }

  .asig-card:hover {
    border-color: #93c5fd;
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.08);
  }

  .asig-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.25rem;
  }

  .asig-code {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    font-weight: 700;
    color: #2563eb;
  }

  .asig-sct {
    font-size: 0.6875rem;
    font-weight: 600;
    background: #dbeafe;
    color: #1e40af;
    padding: 0.125rem 0.5rem;
    border-radius: 999px;
  }

  .asig-name {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #111827;
    margin: 0;
    line-height: 1.35;
  }

  .asig-tipo {
    display: inline-block;
    margin-top: 0.375rem;
    font-size: 0.6875rem;
    background: #f3f4f6;
    color: #6b7280;
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
  }
</style>
