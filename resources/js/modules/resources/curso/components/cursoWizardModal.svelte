<script lang="ts">
  /**
   * Wizard modal para creación de Cursos Ofertados con selectores en cascada.
   *
   * Flujo:
   *  1. Seleccionar Carrera
   *  2. Seleccionar Plan de Estudio (filtrado por carrera)
   *  3. Seleccionar Asignatura (del plan, agrupada por año/semestre)
   *  4. Seleccionar Docente sugerido + ingresar datos del curso
   */
  import type { Carrera, CursoFormData, TipoComponente } from '@/types/admin.types';

  interface Plan {
    id_plan: number;
    agno_plan: number;
    version_plan: number;
    carrera?: { nombre: string };
  }
  interface AsignaturaOption {
    id_asignatura: number;
    cod_asignatura: string;
    nombre: string;
    creditos_sct?: number;
    agno_planificado: number;
    semestre_planificado: number;
    tipo_ramo?: string;
  }
  interface DocenteOption {
    id_docente: number;
    nombre_completo: string;
    email?: string;
    grado?: string;
    titulo?: string;
    cargo?: string;
  }

  interface Props {
    isOpen: boolean;
    carreras: Carrera[];
    tiposComponente: TipoComponente[];
    isLoading: boolean;
    onClose: () => void;
    onSubmit: (data: CursoFormData & { id_docente_sugerido?: number }) => void;
  }

  let { isOpen, carreras, tiposComponente, isLoading, onClose, onSubmit }: Props = $props();

  // ── Step state ──────────────────────────────────────────────────────────
  let selectedCarrera = $state<Carrera | null>(null);
  let selectedPlan = $state<Plan | null>(null);
  let selectedAsig = $state<AsignaturaOption | null>(null);
  let selectedDocente = $state<DocenteOption | null>(null);
  let selectedTipoComponente = $state<TipoComponente | null>(null);

  // ── Fetched cascading data ───────────────────────────────────────────────
  let planes = $state<Plan[]>([]);
  let asignaturas = $state<AsignaturaOption[]>([]);
  let historicDocentes = $state<DocenteOption[]>([]);
  let otrosDocentes = $state<DocenteOption[]>([]);

  let loadingPlanes = $state(false);
  let loadingAsig = $state(false);
  let loadingDocentes = $state(false);

  // ── Remaining form fields ────────────────────────────────────────────────
  let codCurso = $state<number | ''>('');
  let nombre = $state('');
  let fechaInicio = $state('');
  let agnoReal = $state(new Date().getFullYear());
  let semestreReal = $state<1 | 2>(1);
  let jefeImpartesClases = $state(true); // ← Nuevo: ¿El jefe dicta clases?
  let asigSearch = $state('');
  // Componente (Cátedra) settings
  let generaActa = $state(true);
  let aprobacionObligatoria = $state(false);
  let porcentajeAprobacion = $state<number>(60);
  let porcentajeAsistencia = $state<number>(75);
  let esColegiado = $state(false);

  // ── Computed current step (1–4) ──────────────────────────────────────────
  const currentStep = $derived(!selectedCarrera ? 1 : !selectedPlan ? 2 : !selectedAsig ? 3 : 4);

  // ── Debug logs ──────────────────────────────────────────────────────────────
  $effect(() => {
    if (isOpen) {
      console.log('🎯 CursoWizardModal opened', {
        isOpen,
        carreras: carreras.length,
        currentStep,
        selectedCarrera: selectedCarrera?.nombre,
      });
    }
  });

  // Grouped asignaturas by year → semester
  const asigByYear = $derived.by(() => {
    const map: Record<number, { s1: AsignaturaOption[]; s2: AsignaturaOption[] }> = {};
    for (const a of asignaturas) {
      if (!map[a.agno_planificado]) map[a.agno_planificado] = { s1: [], s2: [] };
      (a.semestre_planificado === 1 ? map[a.agno_planificado].s1 : map[a.agno_planificado].s2).push(
        a,
      );
    }
    return map;
  });

  // Filtered (by search term) asignaturas grouped by year → semester
  const filteredAsigByYear = $derived.by(() => {
    const term = asigSearch.trim().toLowerCase();
    if (!term) return asigByYear;
    const result: Record<number, { s1: AsignaturaOption[]; s2: AsignaturaOption[] }> = {};
    for (const [yr, sems] of Object.entries(asigByYear)) {
      const s1 = sems.s1.filter(
        (a) =>
          a.cod_asignatura.toLowerCase().includes(term) || a.nombre.toLowerCase().includes(term),
      );
      const s2 = sems.s2.filter(
        (a) =>
          a.cod_asignatura.toLowerCase().includes(term) || a.nombre.toLowerCase().includes(term),
      );
      if (s1.length || s2.length) result[Number(yr)] = { s1, s2 };
    }
    return result;
  });

  const filteredCount = $derived(
    Object.values(filteredAsigByYear).reduce((n, s) => n + s.s1.length + s.s2.length, 0),
  );

  // ── Cascade fetchers ────────────────────────────────────────────────────
  async function onSelectCarrera(carrera: Carrera) {
    selectedCarrera = carrera;
    selectedPlan = null;
    selectedAsig = null;
    selectedDocente = null;
    planes = [];
    asignaturas = [];
    historicDocentes = [];
    otrosDocentes = [];

    loadingPlanes = true;
    try {
      const res = await fetch(`/admin/carreras/${carrera.id_carrera}/planes`, {
        headers: { Accept: 'application/json' },
      });
      planes = res.ok ? await res.json() : [];
    } finally {
      loadingPlanes = false;
    }
  }

  async function onSelectPlan(plan: Plan) {
    selectedPlan = plan;
    selectedAsig = null;
    selectedDocente = null;
    asignaturas = [];
    historicDocentes = [];
    otrosDocentes = [];
    asigSearch = '';

    loadingAsig = true;
    try {
      const res = await fetch(`/admin/cursos/${plan.id_plan}/asignaturas-disponibles`, {
        headers: { Accept: 'application/json' },
      });
      asignaturas = res.ok ? await res.json() : [];
    } finally {
      loadingAsig = false;
    }
  }

  async function onSelectAsig(asig: AsignaturaOption) {
    selectedAsig = asig;
    selectedDocente = null;
    historicDocentes = [];
    otrosDocentes = [];

    loadingDocentes = true;
    try {
      const res = await fetch(`/admin/asignaturas/${asig.id_asignatura}/docentes-sugeridos`, {
        headers: { Accept: 'application/json' },
      });
      if (res.ok) {
        const json = await res.json();
        historicDocentes = json.historicos ?? [];
        otrosDocentes = json.otros ?? [];
      }
    } finally {
      loadingDocentes = false;
    }
  }

  // ── Reset when modal closes ──────────────────────────────────────────────
  $effect(() => {
    if (!isOpen) {
      selectedCarrera = null;
      selectedPlan = null;
      selectedAsig = null;
      selectedDocente = null;
      selectedTipoComponente = null;
      planes = [];
      asignaturas = [];
      historicDocentes = [];
      otrosDocentes = [];
      codCurso = '';
      nombre = '';
      fechaInicio = '';
      agnoReal = new Date().getFullYear();
      semestreReal = 1;
      jefeImpartesClases = true; // ← Reset
      asigSearch = '';
      generaActa = true;
      aprobacionObligatoria = false;
      porcentajeAprobacion = 60;
      porcentajeAsistencia = 75;
      esColegiado = false;
    }
  });

  // ── Date helpers (dd/mm/aaaa ↔ yyyy-mm-dd) ────────────────────────────────
  function handleDateInput(e: Event) {
    const input = e.currentTarget as HTMLInputElement;
    let v = input.value.replace(/\D/g, '').slice(0, 8);
    if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
    if (v.length >= 6) v = v.slice(0, 5) + '/' + v.slice(5);
    fechaInicio = v;
  }

  function toIsoDate(dmy: string): string | undefined {
    if (!dmy || dmy.length !== 10) return undefined;
    const [d, m, y] = dmy.split('/');
    if (!d || !m || !y) return undefined;
    return `${y}-${m}-${d}`;
  }

  // ── Submit ───────────────────────────────────────────────────────────────
  function handleSubmit(e: SubmitEvent) {
    e.preventDefault();
    if (
      !selectedAsig ||
      !selectedPlan ||
      codCurso === '' ||
      !selectedDocente ||
      !selectedTipoComponente
    )
      return;

    onSubmit({
      id_asignatura: selectedAsig.id_asignatura,
      id_plan: selectedPlan.id_plan,
      cod_curso: Number(codCurso),
      nombre: nombre || undefined,
      fecha_inicio: toIsoDate(fechaInicio),
      numero_semestre: selectedAsig.agno_planificado,
      agno_real: agnoReal,
      semestre_real: semestreReal,
      id_docente_sugerido: selectedDocente?.id_docente,
      id_tipo_componente_principal: selectedTipoComponente.id_tipo_componente,
      genera_acta: generaActa,
      aprobacion_obligatoria: aprobacionObligatoria,
      porcentaje_aprobacion: porcentajeAprobacion,
      porcentaje_asistencia_obligatoria: porcentajeAsistencia,
      es_colegiado: esColegiado,
    } as any);
  }

  function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') onClose();
  }
</script>

<svelte:window onkeydown={handleKeydown} />

{#if isOpen}
  <!-- Backdrop -->
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div class="wizard-backdrop" onclick={onClose} role="presentation"></div>

  <!-- Dialog -->
  <div class="wizard-dialog" role="dialog" aria-modal="true" aria-label="Nuevo Curso">
    <!-- ── Header ── -->
    <div class="wiz-header">
      <div>
        <h2 class="wiz-title">Nuevo Curso Ofertado</h2>
        <p class="wiz-subtitle">Sigue los pasos para configurar el curso correctamente</p>
      </div>
      <button class="wiz-close" onclick={onClose} aria-label="Cerrar">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M18 6 6 18" /><path d="m6 6 12 12" />
        </svg>
      </button>
    </div>

    <!-- ── Step progress ── -->
    <div class="step-bar">
      {#each [{ n: 1, label: 'Carrera' }, { n: 2, label: 'Plan' }, { n: 3, label: 'Asignatura' }, { n: 4, label: 'Docente & Datos' }] as s}
        <div class="step-item" class:done={currentStep > s.n} class:active={currentStep === s.n}>
          <div class="step-circle">
            {#if currentStep > s.n}
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="13"
                height="13"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="20 6 9 17 4 12" />
              </svg>
            {:else}
              {s.n}
            {/if}
          </div>
          <span class="step-label">{s.label}</span>
        </div>
        {#if s.n < 4}
          <div class="step-connector" class:done={currentStep > s.n}></div>
        {/if}
      {/each}
    </div>

    <!-- ── Scrollable body + footer ── -->
    <form class="wiz-form-layout" onsubmit={handleSubmit}>
      <div class="wiz-body">
        <!-- ══ Step 1: Carrera ══ -->
        <section class="wiz-step" class:collapsed={currentStep > 1}>
          <div class="step-head">
            <span class="step-num" class:filled={currentStep > 1}>1</span>
            <span class="step-head-label">Carrera</span>
            {#if selectedCarrera}
              <span class="step-chip">{selectedCarrera.nombre}</span>
              <button
                type="button"
                class="step-change"
                onclick={() => {
                  selectedCarrera = null;
                  selectedPlan = null;
                  selectedAsig = null;
                  selectedDocente = null;
                  planes = [];
                  asignaturas = [];
                }}
              >
                Cambiar
              </button>
            {/if}
          </div>

          {#if currentStep === 1}
            <div class="step-body">
              {#if carreras.length === 0}
                <div class="step-empty bg-yellow-50 border border-yellow-200 p-4 rounded">
                  ⚠️ No hay carreras disponibles en el sistema.
                </div>
              {:else}
                <div class="option-grid">
                  {#each carreras as c (c.id_carrera)}
                    <button type="button" class="option-card" onclick={() => onSelectCarrera(c)}>
                      <span class="option-card-name">{c.nombre}</span>
                      {#if c.sede || c.jornada}
                        <span class="option-card-meta"
                          >{[c.sede, c.jornada].filter(Boolean).join(' · ')}</span
                        >
                      {/if}
                    </button>
                  {/each}
                </div>
              {/if}
            </div>
          {/if}
        </section>

        <!-- ══ Step 2: Plan ══ -->
        <section class="wiz-step" class:collapsed={currentStep > 2} class:locked={currentStep < 2}>
          <div class="step-head">
            <span class="step-num" class:filled={currentStep > 2}>2</span>
            <span class="step-head-label">Plan de Estudio</span>
            {#if selectedPlan}
              <span class="step-chip"
                >{selectedPlan.carrera?.nombre ?? ''}
                {selectedPlan.agno_plan} v{selectedPlan.version_plan}</span
              >
              <button
                type="button"
                class="step-change"
                onclick={() => {
                  selectedPlan = null;
                  selectedAsig = null;
                  selectedDocente = null;
                  asignaturas = [];
                }}
              >
                Cambiar
              </button>
            {/if}
          </div>

          {#if currentStep === 2}
            <div class="step-body">
              {#if loadingPlanes}
                <div class="inline-spinner"></div>
              {:else if planes.length === 0}
                <p class="step-empty">Esta carrera no tiene planes de estudio activos.</p>
              {:else}
                <div class="option-list">
                  {#each planes as p}
                    <button type="button" class="option-row" onclick={() => onSelectPlan(p)}>
                      <span class="option-row-icon">📋</span>
                      <div>
                        <div class="option-row-name">
                          Plan {p.agno_plan} — versión {p.version_plan}
                        </div>
                        {#if p.carrera}
                          <div class="option-row-sub">{p.carrera.nombre}</div>
                        {/if}
                      </div>
                      <svg
                        class="option-row-arrow"
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      >
                        <path d="m9 18 6-6-6-6" />
                      </svg>
                    </button>
                  {/each}
                </div>
              {/if}
            </div>
          {/if}
        </section>

        <!-- ══ Step 3: Asignatura ══ -->
        <section class="wiz-step" class:collapsed={currentStep > 3} class:locked={currentStep < 3}>
          <div class="step-head">
            <span class="step-num" class:filled={currentStep > 3}>3</span>
            <span class="step-head-label">Asignatura</span>
            {#if selectedAsig}
              <span class="step-chip">{selectedAsig.cod_asignatura} – {selectedAsig.nombre}</span>
              <button
                type="button"
                class="step-change"
                onclick={() => {
                  selectedAsig = null;
                  selectedDocente = null;
                  historicDocentes = [];
                  otrosDocentes = [];
                }}
              >
                Cambiar
              </button>
            {/if}
          </div>

          {#if currentStep === 3}
            <div class="step-body">
              {#if loadingAsig}
                <div class="inline-spinner"></div>
              {:else if asignaturas.length === 0}
                <p class="step-empty">Este plan no tiene asignaturas asignadas.</p>
              {:else}
                <div class="asig-search-bar">
                  <input
                    type="search"
                    bind:value={asigSearch}
                    placeholder="Buscar por código o nombre…"
                    class="asig-search-input"
                  />
                </div>
                <div class="asig-scroll-area">
                  {#if filteredCount === 0}
                    <p class="step-empty">
                      No se encontraron resultados para &ldquo;{asigSearch}&rdquo;.
                    </p>
                  {:else}
                    <div class="asig-groups">
                      {#each Object.entries(filteredAsigByYear).sort(([a], [b]) => Number(a) - Number(b)) as [yr, sems]}
                        <div class="asig-year-group">
                          <div class="asig-year-badge">Año {yr}</div>
                          {#each [{ label: 'Semestre 1', list: sems.s1 }, { label: 'Semestre 2', list: sems.s2 }] as sem}
                            {#if sem.list.length > 0}
                              <div class="asig-sem-label">{sem.label}</div>
                              <div class="asig-cards">
                                {#each sem.list as a}
                                  <button
                                    type="button"
                                    class="asig-card"
                                    onclick={() => onSelectAsig(a)}
                                  >
                                    <div class="asig-card-top">
                                      <span class="asig-card-code">{a.cod_asignatura}</span>
                                      {#if a.creditos_sct}
                                        <span class="asig-card-sct">{a.creditos_sct} SCT</span>
                                      {/if}
                                    </div>
                                    <div class="asig-card-name">{a.nombre}</div>
                                    {#if a.tipo_ramo}
                                      <div class="asig-card-tipo">{a.tipo_ramo}</div>
                                    {/if}
                                  </button>
                                {/each}
                              </div>
                            {/if}
                          {/each}
                        </div>
                      {/each}
                    </div>
                  {/if}
                </div>
              {/if}
            </div>
          {/if}
        </section>

        <!-- ══ Step 4: Docente + Datos ══ -->
        <section class="wiz-step" class:locked={currentStep < 4}>
          <div class="step-head">
            <span class="step-num" class:filled={false}>4</span>
            <span class="step-head-label">Docente y Datos del Curso</span>
          </div>

          {#if currentStep === 4}
            <div class="step-body">
              <div class="step4-scroll">
                <!-- ¿El jefe imparte clases? -->
                <div class="jefe-imparte-section">
                  <p class="jefe-imparte-label">¿El jefe de curso imparte clases en este curso?</p>
                  <p class="jefe-imparte-hint">
                    Si selecciona NO, el jefe solo tendrá acceso administrativo y no estará asignado
                    a ningún componente.
                  </p>
                  <div class="jefe-imparte-toggle">
                    <label class="toggle-label">
                      <input
                        type="radio"
                        name="jefe-imparte"
                        value={true}
                        bind:group={jefeImpartesClases}
                        class="toggle-radio"
                      />
                      <span>Sí, imparte clases</span>
                    </label>
                    <label class="toggle-label">
                      <input
                        type="radio"
                        name="jefe-imparte"
                        value={false}
                        bind:group={jefeImpartesClases}
                        class="toggle-radio"
                      />
                      <span>No, solo administrativo</span>
                    </label>
                  </div>
                </div>

                <!-- Divider -->
                <div class="fields-divider"></div>

                <!-- Tipo de Componente Principal -->
                <div class="tipo-comp-section">
                  <p class="tipo-comp-label">¿Qué tipo de componente es el principal?</p>
                  <div class="tipo-comp-list">
                    {#each tiposComponente as tc}
                      <button
                        type="button"
                        class="tipo-comp-btn"
                        class:selected={selectedTipoComponente?.id_tipo_componente ===
                          tc.id_tipo_componente}
                        onclick={() => (selectedTipoComponente = tc)}
                      >
                        {tc.tipo}
                      </button>
                    {/each}
                  </div>
                </div>

                <!-- Divider -->
                <div class="fields-divider"></div>

                <!-- Docentes sugeridos -->
                <div class="docentes-section">
                  <p class="docentes-hint">
                    {#if loadingDocentes}
                      Buscando docentes sugeridos...
                    {:else if historicDocentes.length > 0}
                      <strong>{historicDocentes.length}</strong> docente{historicDocentes.length > 1
                        ? 's han'
                        : ' ha'} impartido esta asignatura anteriormente.
                    {:else}
                      No hay historial previo. Selecciona cualquier docente.
                    {/if}
                  </p>

                  {#if loadingDocentes}
                    <div class="inline-spinner"></div>
                  {:else}
                    <!-- Docentes históricos first -->
                    {#if historicDocentes.length > 0}
                      <div class="docentes-group-label">
                        <span class="badge-historico">Historial</span>
                        Docentes que ya han impartido esta asignatura
                      </div>
                      <div class="docentes-list">
                        {#each historicDocentes as d}
                          <button
                            type="button"
                            class="docente-row"
                            class:selected={selectedDocente?.id_docente === d.id_docente}
                            onclick={() =>
                              (selectedDocente =
                                selectedDocente?.id_docente === d.id_docente ? null : d)}
                          >
                            <div class="docente-avatar">
                              {(d.nombre_completo?.[0] ?? '?').toUpperCase()}
                            </div>
                            <div class="docente-info">
                              <div class="docente-name">{d.nombre_completo}</div>
                              {#if d.cargo}<div class="docente-meta">{d.cargo}</div>{/if}
                            </div>
                            {#if selectedDocente?.id_docente === d.id_docente}
                              <svg
                                class="docente-check"
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                              >
                                <polyline points="20 6 9 17 4 12" />
                              </svg>
                            {/if}
                          </button>
                        {/each}
                      </div>
                    {/if}

                    <!-- Other docentes in collapsible or always shown -->
                    {#if otrosDocentes.length > 0}
                      <details class="otros-docentes">
                        <summary class="otros-label">
                          Otros docentes <span class="otros-count">({otrosDocentes.length})</span>
                        </summary>
                        <div class="docentes-list mt-2">
                          {#each otrosDocentes as d}
                            <button
                              type="button"
                              class="docente-row"
                              class:selected={selectedDocente?.id_docente === d.id_docente}
                              onclick={() =>
                                (selectedDocente =
                                  selectedDocente?.id_docente === d.id_docente ? null : d)}
                            >
                              <div class="docente-avatar alt">
                                {(d.nombre_completo?.[0] ?? '?').toUpperCase()}
                              </div>
                              <div class="docente-info">
                                <div class="docente-name">{d.nombre_completo}</div>
                                {#if d.cargo}<div class="docente-meta">{d.cargo}</div>{/if}
                              </div>
                              {#if selectedDocente?.id_docente === d.id_docente}
                                <svg
                                  class="docente-check"
                                  xmlns="http://www.w3.org/2000/svg"
                                  width="18"
                                  height="18"
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  stroke="currentColor"
                                  stroke-width="2.5"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"
                                >
                                  <polyline points="20 6 9 17 4 12" />
                                </svg>
                              {/if}
                            </button>
                          {/each}
                        </div>
                      </details>
                    {/if}
                  {/if}
                </div>

                <!-- Divider -->
                <div class="fields-divider"></div>

                <!-- Remaining fields -->
                <div class="fields-grid">
                  <div class="field">
                    <label class="field-label" for="wiz-cod-curso">Código del Curso *</label>
                    <input
                      id="wiz-cod-curso"
                      type="number"
                      bind:value={codCurso}
                      class="field-input"
                      placeholder="Ej: 12345"
                      required
                    />
                  </div>

                  <div class="field">
                    <label class="field-label" for="wiz-nombre">Nombre del Curso</label>
                    <input
                      id="wiz-nombre"
                      type="text"
                      bind:value={nombre}
                      class="field-input"
                      placeholder="Nombre personalizado (opcional)"
                    />
                  </div>

                  <div class="field">
                    <label class="field-label" for="wiz-fecha">Fecha de Inicio</label>
                    <input
                      id="wiz-fecha"
                      type="text"
                      value={fechaInicio}
                      oninput={handleDateInput}
                      class="field-input"
                      placeholder="dd/mm/aaaa"
                      maxlength="10"
                      autocomplete="off"
                    />
                  </div>

                  <div class="field">
                    <label class="field-label" for="wiz-agno">Año Real *</label>
                    <input
                      id="wiz-agno"
                      type="number"
                      bind:value={agnoReal}
                      class="field-input"
                      min="2000"
                      max="2100"
                      required
                    />
                  </div>

                  <div class="field">
                    <label class="field-label" for="wiz-semestre">Semestre Real *</label>
                    <select
                      id="wiz-semestre"
                      bind:value={semestreReal}
                      class="field-input"
                      required
                    >
                      <option value={1}>1</option>
                      <option value={2}>2</option>
                    </select>
                  </div>
                </div>

                <!-- Componente (Cátedra) settings -->
                <div class="fields-section-title">Configuración del Componente</div>
                <div class="fields-grid">
                  <div class="field">
                    <label class="field-label" for="wiz-pct-aprobacion">% Aprobación *</label>
                    <input
                      id="wiz-pct-aprobacion"
                      type="number"
                      bind:value={porcentajeAprobacion}
                      class="field-input"
                      min="0"
                      max="100"
                      step="0.5"
                      required
                    />
                  </div>

                  <div class="field">
                    <label class="field-label" for="wiz-pct-asistencia"
                      >% Asistencia Obligatoria *</label
                    >
                    <input
                      id="wiz-pct-asistencia"
                      type="number"
                      bind:value={porcentajeAsistencia}
                      class="field-input"
                      min="0"
                      max="100"
                      step="0.5"
                      required
                    />
                  </div>

                  <div class="field field-checkbox">
                    <label class="field-check-label">
                      <input type="checkbox" bind:checked={generaActa} class="field-check" />
                      Genera Acta
                    </label>
                  </div>

                  <div class="field field-checkbox">
                    <label class="field-check-label">
                      <input
                        type="checkbox"
                        bind:checked={aprobacionObligatoria}
                        class="field-check"
                      />
                      Aprobación Obligatoria
                    </label>
                  </div>

                  <div class="field field-checkbox">
                    <label class="field-check-label">
                      <input type="checkbox" bind:checked={esColegiado} class="field-check" />
                      Curso Colegiado
                    </label>
                    <p class="text-xs text-slate-500 mt-0.5 ml-6">
                      Múltiples docentes por componente. Deberás configurar titular por componente.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          {/if}
        </section>
      </div>

      <!-- ── Footer (inside form so submit works) ── -->
      {#if currentStep === 4}
        <div class="wiz-footer">
          {#if !selectedTipoComponente}
            <p class="docente-required-hint">Debes seleccionar el tipo de componente principal.</p>
          {:else if !selectedDocente}
            <p class="docente-required-hint">Debes seleccionar un docente para continuar.</p>
          {/if}
          <div class="wiz-footer-actions">
            <button type="button" class="btn-cancel" onclick={onClose} disabled={isLoading}>
              Cancelar
            </button>
            <button
              type="submit"
              class="btn-submit"
              disabled={isLoading ||
                !selectedAsig ||
                !selectedPlan ||
                codCurso === '' ||
                !selectedDocente ||
                !selectedTipoComponente}
            >
              {#if isLoading}
                <span class="btn-spinner"></span>
                Creando...
              {:else}
                Crear Curso
              {/if}
            </button>
          </div>
        </div>
      {/if}
    </form>
  </div>
{/if}

<style>
  /* ── Backdrop ── */
  .wizard-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
    z-index: 50;
    animation: fadeIn 0.15s ease;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  /* ── Dialog ── */
  .wizard-dialog {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 60;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
    width: min(740px, calc(100vw - 2rem));
    height: 85dvh;
    max-height: 90dvh;
    min-height: 400px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.18s ease;
    padding-bottom: 0;
  }

  @keyframes slideUp {
    from {
      transform: translate(-50%, calc(-50% + 20px));
      opacity: 0;
    }
    to {
      transform: translate(-50%, -50%);
      opacity: 1;
    }
  }

  /* ── Header ── */
  .wiz-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.25rem 1.5rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
  }

  .wiz-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
  }
  .wiz-subtitle {
    font-size: 0.8125rem;
    color: #6b7280;
    margin: 0.2rem 0 0;
  }

  .wiz-close {
    padding: 0.375rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    color: #9ca3af;
    cursor: pointer;
    transition:
      background 0.15s,
      color 0.15s;
    flex-shrink: 0;
  }
  .wiz-close:hover {
    background: #f3f4f6;
    color: #111827;
  }

  /* ── Step bar ── */
  .step-bar {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
    gap: 0;
  }

  .step-item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    flex-shrink: 0;
  }

  .step-circle {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 700;
    border: 2px solid #d1d5db;
    color: #9ca3af;
    background: white;
    transition: all 0.2s;
  }

  .step-item.done .step-circle,
  .step-item.active .step-circle {
    border-color: #3b82f6;
    background: #3b82f6;
    color: white;
  }

  .step-label {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 500;
  }
  .step-item.active .step-label {
    color: #1d4ed8;
    font-weight: 600;
  }
  .step-item.done .step-label {
    color: #059669;
  }

  .step-connector {
    flex: 1;
    height: 2px;
    background: #e5e7eb;
    margin: 0 0.5rem;
    border-radius: 1px;
    transition: background 0.2s;
  }
  .step-connector.done {
    background: #3b82f6;
  }

  /* ── Form layout wrapper ── */
  .wiz-form-layout {
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  /* ── Body (scrollable) ── */
  .wiz-body {
    flex: 1 1 0;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
  }

  /* ── Steps ── */
  .wiz-step {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.2s;
    flex-shrink: 0;
  }

  .wiz-step.locked {
    opacity: 0.45;
    pointer-events: none;
  }

  .wiz-step.collapsed .step-body {
    display: none;
  }

  .step-head {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem 1rem;
    background: #f9fafb;
    border-bottom: 1px solid transparent;
    flex-wrap: wrap;
  }

  .wiz-step:not(.collapsed) .step-head {
    border-bottom-color: #e5e7eb;
  }

  .step-num {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #e5e7eb;
    color: #6b7280;
    font-size: 0.6875rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .step-num.filled {
    background: #10b981;
    color: white;
  }

  .step-head-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
  }

  .step-chip {
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.125rem 0.625rem;
    border-radius: 999px;
    border: 1px solid #bfdbfe;
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .step-change {
    font-size: 0.75rem;
    color: #6b7280;
    background: none;
    border: 1px solid #d1d5db;
    border-radius: 5px;
    padding: 0.125rem 0.5rem;
    cursor: pointer;
    transition: all 0.15s;
    margin-left: auto;
  }

  .step-change:hover {
    background: #f3f4f6;
    color: #111827;
  }

  .step-body {
    padding: 1rem;
  }

  /* Step 4: sin scroll anidado — el outer .wiz-body maneja todo el scroll */
  .step4-scroll {
    padding-right: 4px;
  }

  .step-empty {
    color: #9ca3af;
    font-size: 0.875rem;
    margin: 0;
  }

  /* ── Carrera cards ── */
  .option-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.625rem;
  }

  .option-card {
    padding: 0.75rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s;
  }
  .option-card:hover {
    border-color: #3b82f6;
    background: #eff6ff;
  }

  .option-card-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #111827;
    display: block;
  }
  .option-card-meta {
    font-size: 0.6875rem;
    color: #9ca3af;
    margin-top: 0.25rem;
    display: block;
  }

  /* ── Plan list ── */
  .option-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .option-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.875rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s;
    width: 100%;
  }
  .option-row:hover {
    border-color: #3b82f6;
    background: #eff6ff;
  }

  .option-row-icon {
    font-size: 1.125rem;
  }
  .option-row-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
  }
  .option-row-sub {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.1rem;
  }
  .option-row-arrow {
    margin-left: auto;
    color: #9ca3af;
    flex-shrink: 0;
  }

  /* ── Asignatura search + scroll ── */
  .asig-search-bar {
    margin-bottom: 0.625rem;
  }

  .asig-search-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #111827;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
  }
  .asig-search-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  }
  .asig-search-input::-webkit-search-cancel-button {
    cursor: pointer;
  }

  .asig-scroll-area {
    max-height: 320px;
    overflow-y: auto;
    padding-right: 4px;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
  }

  /* ── Asignaturas ── */
  .asig-groups {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .asig-year-badge {
    display: inline-block;
    background: #1e3a8a;
    color: white;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.2rem 0.625rem;
    border-radius: 999px;
    margin-bottom: 0.625rem;
  }

  .asig-sem-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    margin: 0.5rem 0 0.375rem;
    padding-left: 0.125rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .asig-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.25rem;
  }

  .asig-card {
    padding: 0.625rem 0.75rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s;
    width: 100%;
  }
  .asig-card:hover {
    border-color: #3b82f6;
    background: #eff6ff;
  }

  .asig-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.25rem;
  }
  .asig-card-code {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    font-weight: 700;
    color: #2563eb;
  }
  .asig-card-sct {
    font-size: 0.6875rem;
    background: #dbeafe;
    color: #1e40af;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    font-weight: 600;
  }
  .asig-card-name {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #111827;
    line-height: 1.3;
  }
  .asig-card-tipo {
    font-size: 0.6875rem;
    color: #6b7280;
    margin-top: 0.25rem;
  }

  /* ── Docentes ── */

  .docentes-section {
    margin-bottom: 0.75rem;
  }

  .docentes-hint {
    font-size: 0.8125rem;
    color: #374151;
    margin: 0 0 0.75rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
  }

  .docentes-group-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
  }

  .badge-historico {
    background: #fef3c7;
    color: #92400e;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #fde68a;
  }

  .docentes-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }

  .docente-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.875rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.15s;
    width: 100%;
    text-align: left;
  }
  .docente-row:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
  }
  .docente-row.selected {
    border-color: #3b82f6;
    background: #eff6ff;
  }

  .docente-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 0.875rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .docente-avatar.alt {
    background: #f3f4f6;
    color: #6b7280;
  }

  .docente-info {
    flex: 1;
    min-width: 0;
  }
  .docente-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
  }
  .docente-meta {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.1rem;
  }

  .docente-check {
    color: #3b82f6;
    flex-shrink: 0;
  }

  .otros-docentes {
    margin-top: 0.75rem;
  }
  .otros-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }
  .otros-label::-webkit-details-marker {
    display: none;
  }
  .otros-count {
    color: #9ca3af;
    font-weight: 400;
  }
  .mt-2 {
    margin-top: 0.5rem;
  }

  /* ── Fields ── */
  /* ── ¿El jefe imparte clases? ── */
  .jefe-imparte-section {
    margin-bottom: 0.75rem;
  }

  .jefe-imparte-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.375rem;
  }

  .jefe-imparte-hint {
    font-size: 0.8125rem;
    color: #6b7280;
    margin-bottom: 0.75rem;
    margin-top: 0;
  }

  .jefe-imparte-toggle {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
  }

  .toggle-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.8875rem;
    font-weight: 500;
    color: #374151;
    user-select: none;
  }

  .toggle-radio {
    cursor: pointer;
    width: 16px;
    height: 16px;
  }

  .toggle-label:hover {
    color: #1f2937;
  }

  /* ── Tipo Componente selector ── */
  .tipo-comp-section {
    margin-bottom: 0.5rem;
  }

  .tipo-comp-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.625rem;
  }

  .tipo-comp-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .tipo-comp-btn {
    padding: 0.5rem 1.125rem;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    font-size: 0.9rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition:
      border-color 0.15s,
      background 0.15s,
      color 0.15s;
  }

  .tipo-comp-btn:hover {
    border-color: #6366f1;
    color: #4f46e5;
  }

  .tipo-comp-btn.selected {
    border-color: #6366f1;
    background: #eef2ff;
    color: #4338ca;
    font-weight: 700;
  }

  .fields-section-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 1rem 0 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e5e7eb;
  }

  .fields-divider {
    border-top: 1px solid #e5e7eb;
    margin: 1rem 0;
  }

  .fields-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
  }

  @media (max-width: 500px) {
    .fields-grid {
      grid-template-columns: 1fr;
    }
  }

  .field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }

  .field-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
  }

  .field-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    color: #111827;
    background: white;
    transition:
      border-color 0.15s,
      box-shadow 0.15s;
    box-sizing: border-box;
  }
  .field-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }

  .field-checkbox {
    display: flex;
    align-items: center;
    padding-top: 1.5rem;
  }

  .field-check-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    user-select: none;
  }

  .field-check {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #3b82f6;
  }

  /* ── Footer ── */
  .wiz-footer {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem 1rem;
    border-top: 1px solid #e5e7eb;
    background: #fff;
    flex-shrink: 0;
  }

  .wiz-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
  }

  .docente-required-hint {
    font-size: 0.8rem;
    color: #b91c1c;
    margin: 0;
    padding: 0.4rem 0.75rem;
    background: #fef2f2;
    border-radius: 6px;
    border: 1px solid #fecaca;
  }

  .btn-cancel {
    padding: 0.5rem 1.25rem;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.15s;
  }
  .btn-cancel:hover:not(:disabled) {
    background: #f9fafb;
  }
  .btn-cancel:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .btn-submit {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.5rem;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border: none;
    border-radius: 7px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    box-shadow: 0 1px 3px rgba(59, 130, 246, 0.35);
  }
  .btn-submit:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
  }
  .btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
  }

  .btn-spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }

  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }

  /* ── Inline spinner ── */
  .inline-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    margin: 0.5rem 0;
  }
</style>
