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
  import type {
    Carrera,
    CursoFormData,
    TipoComponente,
    ComponenteDetectada,
    ResultadoPreviewComponentes,
  } from '@/types/admin.types';

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
    /** true = tiene un rol activo en el contexto de la carrera seleccionada */
    pertenece_carrera?: boolean | null;
  }

  interface CursoAnterior {
    id_curso: number;
    cod_curso: number;
    nombre: string | null;
    agno_planificado: number | null;
    semestre_planificado: number | null;
    fecha_inicio: string | null;
    total_componentes: number;
    total_actividades: number;
  }

  interface Props {
    isOpen: boolean;
    carreras: Carrera[];
    tiposComponente: TipoComponente[];
    isLoading: boolean;
    onClose: () => void;
    onSubmit: (data: CursoFormData & { id_docente_sugerido?: number }) => void;
    onCopyFromAnterior?: (cursoId: number, asignaturaNombre: string) => void;
  }

  let { isOpen, carreras, tiposComponente, isLoading, onClose, onSubmit, onCopyFromAnterior = () => {} }: Props = $props();

  // ── Step state ──────────────────────────────────────────────────────────
  let selectedCarrera = $state<Carrera | null>(null);
  let selectedPlan = $state<Plan | null>(null);
  let selectedAsig = $state<AsignaturaOption | null>(null);
  let selectedDocente = $state<DocenteOption | null>(null);
  let selectedTipos = $state<Set<number>>(new Set());

  // ── Fetched cascading data ───────────────────────────────────────────────
  let planes = $state<Plan[]>([]);
  let asignaturas = $state<AsignaturaOption[]>([]);
  let historicDocentes = $state<DocenteOption[]>([]);
  let otrosDocentes = $state<DocenteOption[]>([]);
  let cursosAnteriores = $state<CursoAnterior[]>([]);

  let loadingPlanes = $state(false);
  let loadingAsig = $state(false);
  let loadingDocentes = $state(false);
  let loadingCursosAnteriores = $state(false);

  // ── Remaining form fields ────────────────────────────────────────────────
  let codCurso = $state<number | ''>('');
  let fechaInicio = $state('');
  let agnoReal = $state(new Date().getFullYear());
  let semestreReal = $state<1 | 2>(1);
  // ¿El docente titular dicta clases en todos los componentes, o uno distinto por componente?
  let mismoDocenteTodos = $state(true);
  let docentesPorComponente = $state<Record<number, number | null>>({});
  let asigSearch = $state('');
  let docenteSearch = $state('');
  let perteneceFilter = $state<'all' | 'in' | 'out'>('all');
  // Componente (Cátedra) settings
  let generaActaPorComponente = $state<Record<number, boolean>>({});
  let aprobacionObligatoria = $state(false);
  let porcentajeAprobacion = $state<number>(60);
  let porcentajeAsistencia = $state<number>(75);
  let inscribirAutomaticamente = $state(false);
  


  // ── Letra de grupo (indice_grupo) ──────────────────────────────────────────
  let indiceGrupo = $state<number | null>(null);
  let indiceGrupoSugerido = $state<number | null>(null);
  let indicesGrupoTomados = $state<number[]>([]);
  let loadingLetra = $state(false);

  // ── Preview de componentes (Intranet → fallback Plan de Estudios) ─────────
  // Cero selección manual: el usuario ya no elige qué componentes tendrá el
  // curso, sólo ve lo detectado (con su procedencia) y asigna docentes.
  let previewComponentes = $state<ComponenteDetectada[]>([]);
  let previewOrigen = $state<'INTRANET' | 'PLAN' | null>(null);
  let previewAdvertencias = $state<string[]>([]);
  let loadingPreview = $state(false);

  // ── Computed current step (1–4) ──────────────────────────────────────────
  const currentStep = $derived(!selectedCarrera ? 1 : !selectedPlan ? 2 : !selectedAsig ? 3 : 4);

  // Nombre del curso, derivado de la asignatura y la letra del grupo.
  // Mientras la letra se está calculando (o si la petición falla) se muestra
  // sólo el nombre de la asignatura, en lugar de un «Grupo » sin letra.
  let nombre = $derived.by(() => {
    if (!selectedAsig) return '';
    const grupoLetra = intToLetters(indiceGrupo ?? 0);
    return grupoLetra ? `${selectedAsig.nombre} - Grupo ${grupoLetra}` : selectedAsig.nombre;
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

  function matchesPerteneceFilter(d: DocenteOption): boolean {
    if (perteneceFilter === 'all') return true;
    if (perteneceFilter === 'in') return d.pertenece_carrera === true;
    return d.pertenece_carrera !== true;
  }

  const filteredHistoricDocentes = $derived.by(() => {
    const term = docenteSearch.trim().toLowerCase();
    return historicDocentes.filter(
      (d) =>
        matchesPerteneceFilter(d) &&
        (!term ||
          d.nombre_completo?.toLowerCase().includes(term) ||
          d.cargo?.toLowerCase().includes(term)),
    );
  });

  const selectedTiposOrdered = $derived.by(() => {
    return tiposComponente
      .filter((tc) => selectedTipos.has(tc.id_tipo_componente))
      .sort((a, b) => (a.prioridad ?? 99) - (b.prioridad ?? 99));
  });

  const principalTipo = $derived.by(() => selectedTiposOrdered[0] ?? null);

  // Colegiado no es una opción manual: se activa solo si terminas asignando
  // más de un docente distinto entre los componentes del curso.
  const esColegiadoAuto = $derived.by(() => {
    if (mismoDocenteTodos) return false;
    const idsUnicos = new Set(
      selectedTiposOrdered
        .map((tc) => docentesPorComponente[tc.id_tipo_componente])
        .filter((id): id is number => !!id),
    );
    return idsUnicos.size > 1;
  });

  const filteredOtrosDocentes = $derived.by(() => {
    const term = docenteSearch.trim().toLowerCase();
    return otrosDocentes.filter(
      (d) =>
        matchesPerteneceFilter(d) &&
        (!term ||
          d.nombre_completo?.toLowerCase().includes(term) ||
          d.cargo?.toLowerCase().includes(term)),
    );
  });

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
    cursosAnteriores = [];
    mismoDocenteTodos = true;
    docentesPorComponente = {};
    previewComponentes = [];
    previewOrigen = null;
    previewAdvertencias = [];
    selectedTipos = new Set();

    loadingDocentes = true;
    loadingCursosAnteriores = true;

    const idCarreraQuery = selectedCarrera ? `?id_carrera=${selectedCarrera.id_carrera}` : '';

    const [docentesRes, cursosRes] = await Promise.allSettled([
      fetch(`/admin/asignaturas/${asig.id_asignatura}/docentes-sugeridos${idCarreraQuery}`, {
        headers: { Accept: 'application/json' },
      }),
      fetch(`/admin/asignaturas/${asig.id_asignatura}/cursos-anteriores`, {
        headers: { Accept: 'application/json' },
      }),
    ]);

    if (docentesRes.status === 'fulfilled' && docentesRes.value.ok) {
      const json = await docentesRes.value.json();
      historicDocentes = json.historicos ?? [];
      otrosDocentes = json.otros ?? [];
    }
    loadingDocentes = false;

    if (cursosRes.status === 'fulfilled' && cursosRes.value.ok) {
      cursosAnteriores = await cursosRes.value.json();
    }
    loadingCursosAnteriores = false;
  }

  // ── Letra de grupo (indice_grupo) sugerida ────────────────────────────────
  function intToLetters(n: number): string {
    let result = '';
    while (n > 0) {
      n -= 1;
      result = String.fromCharCode(65 + (n % 26)) + result;
      n = Math.floor(n / 26);
    }
    return result;
  }

  let letraRequestId = 0;

  async function fetchLetraSugerida() {
    if (!selectedPlan || !selectedAsig) return;
    const requestId = ++letraRequestId;
    loadingLetra = true;
    try {
      const params = new URLSearchParams({
        id_asignatura: String(selectedAsig.id_asignatura),
        id_plan: String(selectedPlan.id_plan),
        agno_real: String(agnoReal),
        semestre_real: String(semestreReal),
      });
      const res = await fetch(`/admin/cursos/proxima-letra?${params}`, {
        headers: { Accept: 'application/json' },
      });
      if (requestId !== letraRequestId) return;
      if (res.ok) {
        const json = await res.json();
        if (requestId !== letraRequestId) return;
        indiceGrupoSugerido = json.proximo_indice ?? 1;
        indicesGrupoTomados = json.indices_tomados ?? [];
        indiceGrupo = indiceGrupoSugerido;
      }
    } finally {
      if (requestId === letraRequestId) loadingLetra = false;
    }
  }

  $effect(() => {
    if (selectedPlan && selectedAsig && agnoReal && semestreReal) {
      fetchLetraSugerida();
    }
  });

  // ── Preview de componentes: "mirar antes de tocar" ────────────────────────
  // Se llama con lo que la persona YA escribió en el formulario (asignatura,
  // carrera, plan, año, semestre, letra) — no requiere que el curso exista.
  let previewRequestId = 0;

  async function fetchPreviewComponentes() {
    if (!selectedAsig || !selectedPlan || !agnoReal || !semestreReal) return;
    const requestId = ++previewRequestId;
    loadingPreview = true;
    try {
      const params = new URLSearchParams({
        id_asignatura: String(selectedAsig.id_asignatura),
        id_plan: String(selectedPlan.id_plan),
        agno_real: String(agnoReal),
        semestre_real: String(semestreReal),
      });
      if (indiceGrupo) params.set('indice_grupo', String(indiceGrupo));

      console.log('[CursoWizardModal] preview-componentes →', Object.fromEntries(params));
      const res = await fetch(`/admin/cursos/preview-componentes?${params}`, {
        headers: { Accept: 'application/json' },
      });
      if (requestId !== previewRequestId) return;

      if (res.ok) {
        const json: ResultadoPreviewComponentes = await res.json();
        if (requestId !== previewRequestId) return;
        console.log('[CursoWizardModal] preview-componentes ← OK', json);
        previewComponentes = json.componentes;
        previewOrigen = json.origen;
        previewAdvertencias = json.advertencias;
        selectedTipos = new Set(json.componentes.map((c) => c.id_tipo_componente));
      } else {
        const body = await res.text();
        console.error('[CursoWizardModal] preview-componentes ← ERROR', res.status, body);
        previewComponentes = [];
        previewOrigen = null;
        previewAdvertencias = [
          'No se pudo consultar la Intranet ni derivar componentes del Plan de Estudios. Revisa manualmente desde la ficha del curso una vez creado.',
        ];
        selectedTipos = new Set();
      }
    } catch (err) {
      console.error('[CursoWizardModal] preview-componentes ← EXCEPTION', err);
    } finally {
      if (requestId === previewRequestId) loadingPreview = false;
    }
  }

  $effect(() => {
    // Depende también de indiceGrupo: la letra filtra la consulta a Intranet,
    // así que cambiar de paralelo debe volver a detectar las componentes.
    if (selectedPlan && selectedAsig && agnoReal && semestreReal && indiceGrupo) {
      fetchPreviewComponentes();
    }
  });

  const letraOptions = $derived.by(() => {
    const maxTomado = indicesGrupoTomados.length ? Math.max(...indicesGrupoTomados) : 0;
    const maxOpcion = Math.max(maxTomado, indiceGrupoSugerido ?? 1) + 3;
    const opciones: { indice: number; letra: string; tomada: boolean }[] = [];
    for (let i = 1; i <= maxOpcion; i++) {
      opciones.push({
        indice: i,
        letra: intToLetters(i),
        tomada: indicesGrupoTomados.includes(i) && i !== indiceGrupo,
      });
    }
    return opciones;
  });

  // ── Reset when modal closes ──────────────────────────────────────────────
  $effect(() => {
    if (!isOpen) {
      selectedCarrera = null;
      selectedPlan = null;
      selectedAsig = null;
      selectedDocente = null;
      selectedTipos = new Set();
      planes = [];
      asignaturas = [];
      historicDocentes = [];
      otrosDocentes = [];
      cursosAnteriores = [];
      codCurso = '';
      nombre = '';
      fechaInicio = '';
      agnoReal = new Date().getFullYear();
      semestreReal = 1;
      mismoDocenteTodos = true;
      docentesPorComponente = {};
      asigSearch = '';
      docenteSearch = '';
      perteneceFilter = 'all';
      generaActaPorComponente = {};
      aprobacionObligatoria = false;
      porcentajeAprobacion = 60;
      porcentajeAsistencia = 75;
      indiceGrupo = null;
      indiceGrupoSugerido = null;
      indicesGrupoTomados = [];
      loadingLetra = false;
      previewComponentes = [];
      previewOrigen = null;
      previewAdvertencias = [];
      loadingPreview = false;
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
  const docentesPorComponenteIncompleto = $derived(
    !mismoDocenteTodos &&
      selectedTiposOrdered.some((tc) => !docentesPorComponente[tc.id_tipo_componente]),
  );

  // TOMÁS SILVA CONTINUAR DESDE ACA!!!!!!!!!
  function handleIntranetImportCURCODIGO() {

  }

  function handleSubmit(e: SubmitEvent) {
    e.preventDefault();
    if (
      !selectedAsig ||
      !selectedPlan ||
      codCurso === '' ||
      !selectedDocente ||
      selectedTipos.size === 0 ||
      !principalTipo ||
      docentesPorComponenteIncompleto
    ) {
      console.warn('[CursoWizardModal] Submit bloqueado por el formulario, motivo(s):', {
        sinAsignatura: !selectedAsig,
        sinPlan: !selectedPlan,
        sinCodCurso: codCurso === '',
        sinDocente: !selectedDocente,
        sinComponentesDetectadas: selectedTipos.size === 0,
        sinComponentePrincipal: !principalTipo,
        docentesPorComponenteIncompleto,
        previewOrigen,
        previewComponentes,
        previewAdvertencias,
      });
      return;
    }

    const docentesPorComponenteFinal: Record<number, number> = {};
    const generaActaFinal: Record<number, boolean> = {};
    for (const tc of selectedTiposOrdered) {
      const idDocente = mismoDocenteTodos
        ? selectedDocente.id_docente
        : docentesPorComponente[tc.id_tipo_componente];
      if (idDocente) docentesPorComponenteFinal[tc.id_tipo_componente] = idDocente;
      generaActaFinal[tc.id_tipo_componente] = generaActaPorComponente[tc.id_tipo_componente] ?? true;
    }

    onSubmit({
      id_asignatura: selectedAsig.id_asignatura,
      id_plan: selectedPlan.id_plan,
      cod_curso: Number(codCurso),
      nombre: nombre || undefined,
      fecha_inicio: toIsoDate(fechaInicio),
      numero_semestre: selectedAsig.agno_planificado,
      agno_real: agnoReal,
      semestre_real: semestreReal,
      indice_grupo: indiceGrupo ?? undefined,
      id_docente_sugerido: selectedDocente?.id_docente,
      id_tipo_componente_principal: principalTipo.id_tipo_componente,
      tipos_componente_ids: [...selectedTipos],
      docentes_por_componente: docentesPorComponenteFinal,
      genera_acta_por_componente: generaActaFinal,
      aprobacion_obligatoria: aprobacionObligatoria,
      porcentaje_aprobacion: porcentajeAprobacion,
      porcentaje_asistencia_obligatoria: porcentajeAsistencia,
      inscribir_automaticamente: inscribirAutomaticamente,
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
                  mismoDocenteTodos = true;
                  docentesPorComponente = {};
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
                  mismoDocenteTodos = true;
                  docentesPorComponente = {};
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
                  mismoDocenteTodos = true;
                  docentesPorComponente = {};
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
                <!-- ══ Cursos anteriores de esta asignatura ══ -->
                {#if loadingCursosAnteriores}
                  <div class="anteriores-loading">
                    <div class="inline-spinner"></div>
                    <span class="text-xs text-gray-400">Buscando cursos anteriores…</span>
                  </div>
                {:else if cursosAnteriores.length > 0}
                  <div class="anteriores-section">
                    <div class="anteriores-header">
                      <div class="anteriores-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                      </div>
                      <div>
                        <p class="anteriores-title">Cursos anteriores disponibles</p>
                        <p class="anteriores-subtitle">
                          Puedes copiar uno de estos cursos en lugar de crear desde cero.
                          Las fechas de actividades se eliminarán.
                        </p>
                      </div>
                    </div>
                    <div class="anteriores-list">
                      {#each cursosAnteriores as ca (ca.id_curso)}
                        <button
                          type="button"
                          class="anterior-row"
                          onclick={() => {
                            onCopyFromAnterior(ca.id_curso, selectedAsig?.nombre ?? '');
                            onClose();
                          }}
                        >
                          <div class="anterior-info">
                            <span class="anterior-cod">#{ca.cod_curso}</span>
                            {#if ca.agno_planificado && ca.semestre_planificado}
                              <span class="anterior-periodo">
                                Año {ca.agno_planificado} · Sem. {ca.semestre_planificado}
                              </span>
                            {/if}
                          </div>
                          <div class="anterior-stats">
                            <span class="anterior-stat">{ca.total_componentes} secc.</span>
                            <span class="anterior-stat">{ca.total_actividades} activ.</span>
                          </div>
                          <span class="anterior-btn">Usar como base →</span>
                        </button>
                      {/each}
                    </div>
                  </div>
                  <div class="fields-divider"></div>
                {/if}

                <!-- Periodo del curso.
                     «Año Real» y «Semestre Real» distinguían este dato del
                     año/semestre planificado en la malla, pero desde la
                     pantalla no había forma de saber frente a qué eran
                     «reales»: se nombra por lo que son. -->
                <div class="periodo-section">
                  <p class="periodo-label">¿Cuándo se dicta este curso?</p>
                  <div class="fields-grid">
                    <div class="field">
                      <label class="field-label req" for="wiz-agno">Año</label>
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
                      <label class="field-label req" for="wiz-semestre">Semestre</label>
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

                    <div class="field">
                      <label class="field-label" for="wiz-letra">Letra del grupo</label>
                      <select
                        id="wiz-letra"
                        bind:value={indiceGrupo}
                        class="field-input"
                        disabled={loadingLetra}
                      >
                        {#each letraOptions as opt (opt.indice)}
                          <option value={opt.indice} disabled={opt.tomada}>
                            {opt.letra}{opt.tomada ? ' (ocupada)' : ''}
                          </option>
                        {/each}
                      </select>
                      <p class="field-hint">
                        {#if loadingLetra}
                          Calculando letra disponible…
                        {:else}
                          Sugerida automáticamente; puedes elegir otra disponible.
                        {/if}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Divider -->
                <div class="fields-divider"></div>

                <!-- Componentes del curso: detectadas automáticamente, cero
                     selección manual — sólo se muestran con su procedencia. -->
                <div class="tipo-comp-section">
                  <p class="tipo-comp-label">Componentes del curso</p>
                  <p class="tipo-comp-sublabel">
                    {#if loadingPreview}
                      Detectando componentes…
                    {:else if previewOrigen === 'INTRANET'}
                      Detectadas en la Intranet. El componente principal se determina
                      automáticamente (Cátedra &rsaquo; Taller &rsaquo; Laboratorio).
                    {:else if previewOrigen === 'PLAN'}
                      Derivadas de las horas del Plan de Estudios (la Intranet no tiene oferta
                      para este periodo).
                    {:else}
                      Se detectarán automáticamente al completar año, semestre y letra de grupo.
                    {/if}
                  </p>

                  {#if loadingPreview}
                    <div class="inline-spinner"></div>
                  {:else if previewComponentes.length === 0}
                    <p class="step-empty">
                      No se detectó ninguna componente. Revisa el Plan de Estudios de esta
                      asignatura antes de continuar.
                    </p>
                  {:else}
                    <div class="tipo-comp-list">
                      {#each previewComponentes as pc (pc.id_tipo_componente)}
                        {@const isPrincipal = principalTipo?.id_tipo_componente === pc.id_tipo_componente}
                        <div class="tipo-comp-btn selected">
                          <span class="tipo-comp-btn-name">{pc.tipo}</span>
                          <span
                            class="origen-badge"
                            class:origen-intranet={pc.origen === 'INTRANET'}
                            class:origen-plan={pc.origen === 'PLAN'}
                          >
                            {pc.origen === 'INTRANET' ? '🟢 Intranet' : '🔵 Plan de Estudios'}
                          </span>
                          {#if isPrincipal}
                            <span class="tipo-comp-principal-badge">Principal</span>
                          {/if}
                        </div>
                      {/each}
                    </div>
                  {/if}

                  {#if previewAdvertencias.length > 0}
                    <div class="preview-advertencias">
                      <p class="preview-advertencias-title">⚠️ Advertencias</p>
                      <ul>
                        {#each previewAdvertencias as adv}
                          <li>{adv}</li>
                        {/each}
                      </ul>
                    </div>
                  {/if}
                </div>

                <!-- Divider -->
                <div class="fields-divider"></div>

                <!-- Docentes sugeridos -->
                <div class="docentes-section">
                  <p class="tipo-comp-label">Selección de docente</p>
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
                    <!-- Buscador de docentes -->
                    {#if historicDocentes.length > 0 || otrosDocentes.length > 0}
                      <div class="docente-search-bar">
                        <svg
                          class="docente-search-icon"
                          xmlns="http://www.w3.org/2000/svg"
                          width="14"
                          height="14"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        >
                          <circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />
                        </svg>
                        <input
                          type="search"
                          bind:value={docenteSearch}
                          placeholder="Buscar por nombre o cargo…"
                          class="docente-search-input"
                        />
                      </div>

                      <div class="pertenece-filter-bar">
                        <button
                          type="button"
                          class="pertenece-chip"
                          class:active={perteneceFilter === 'all'}
                          onclick={() => (perteneceFilter = 'all')}
                        >
                          Todos
                        </button>
                        <button
                          type="button"
                          class="pertenece-chip"
                          class:active={perteneceFilter === 'in'}
                          onclick={() => (perteneceFilter = 'in')}
                        >
                          De la carrera
                        </button>
                        <button
                          type="button"
                          class="pertenece-chip"
                          class:active={perteneceFilter === 'out'}
                          onclick={() => (perteneceFilter = 'out')}
                        >
                          Externos
                        </button>
                      </div>
                    {/if}

                    <!-- Docentes históricos first -->
                    {#if filteredHistoricDocentes.length > 0}
                      <div class="docentes-group-label">
                        <span class="badge-historico">Historial</span>
                        Docentes que ya han impartido esta asignatura
                      </div>
                      <div class="docentes-list">
                        {#each filteredHistoricDocentes as d}
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
                              <div class="docente-name">
                                {d.nombre_completo}
                                {#if d.pertenece_carrera}
                                  <span class="badge-pertenece">De la carrera</span>
                                {/if}
                              </div>
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

                    <!-- Sin resultados de búsqueda -->
                    {#if (docenteSearch.trim() || perteneceFilter !== 'all') && filteredHistoricDocentes.length === 0 && filteredOtrosDocentes.length === 0}
                      <p class="step-empty">
                        {#if docenteSearch.trim()}
                          No se encontraron docentes para &ldquo;{docenteSearch}&rdquo;.
                        {:else}
                          No hay docentes {perteneceFilter === 'in' ? 'de la carrera' : 'externos'} disponibles.
                        {/if}
                      </p>
                    {/if}

                    <!-- Other docentes in collapsible or always shown -->
                    {#if filteredOtrosDocentes.length > 0}
                      <details class="otros-docentes" open={!!docenteSearch.trim()}>
                        <summary class="otros-label">
                          Otros docentes <span class="otros-count">({filteredOtrosDocentes.length})</span>
                        </summary>
                        <div class="docentes-list mt-2">
                          {#each filteredOtrosDocentes as d}
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
                                <div class="docente-name">
                                  {d.nombre_completo}
                                  {#if d.pertenece_carrera}
                                    <span class="badge-pertenece">De la carrera</span>
                                  {/if}
                                </div>
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

                <!-- Asignación de docentes por componente -->
                {#if selectedDocente}
                  <div class="asignacion-docentes-section">
                    
                    <p class="jefe-imparte-label">
                      ¿{selectedDocente.nombre_completo} dictará clases en todos los componentes
                      del curso?
                    </p>
                    <p class="jefe-imparte-hint">
                      El jefe de curso siempre queda con acceso administrativo. Si eliges la
                      segunda opción, podrás asignar un docente distinto a cada componente.
                    </p>
                    <div class="jefe-imparte-toggle">
                      <label class="toggle-label">
                        <input
                          type="radio"
                          name="modo-docente-componente"
                          value={true}
                          bind:group={mismoDocenteTodos}
                          class="toggle-radio"
                        />
                        <span>Sí, en todos los componentes</span>
                      </label>
                      <label class="toggle-label">
                        <input
                          type="radio"
                          name="modo-docente-componente"
                          value={false}
                          bind:group={mismoDocenteTodos}
                          class="toggle-radio"
                        />
                        <span>No, un docente distinto por componente</span>
                      </label>
                    </div>

                    {#if !mismoDocenteTodos}
                      <div class="por-componente-list">
                        {#each selectedTiposOrdered as tc (tc.id_tipo_componente)}
                          <div class="por-componente-row">
                            <span class="por-componente-tipo">{tc.tipo}</span>
                            <select
                              class="field-input"
                              value={docentesPorComponente[tc.id_tipo_componente] ?? ''}
                              onchange={(e) => {
                                const raw = (e.currentTarget as HTMLSelectElement).value;
                                docentesPorComponente = {
                                  ...docentesPorComponente,
                                  [tc.id_tipo_componente]: raw ? Number(raw) : null,
                                };
                              }}
                            >
                              <option value="">Selecciona un docente…</option>
                              {#if historicDocentes.length > 0}
                                <optgroup label="Historial">
                                  {#each historicDocentes as d}
                                    <option value={d.id_docente}>{d.nombre_completo}</option>
                                  {/each}
                                </optgroup>
                              {/if}
                              {#if otrosDocentes.length > 0}
                                <optgroup label="Otros docentes">
                                  {#each otrosDocentes as d}
                                    <option value={d.id_docente}>{d.nombre_completo}</option>
                                  {/each}
                                </optgroup>
                              {/if}
                            </select>
                          </div>
                        {/each}
                      </div>
                    {/if}
                  </div>

                  <!-- Divider -->
                  <div class="fields-divider"></div>
                {/if}

                <!-- Remaining fields -->
                <div class="fields-stack">
                  <div class="field">
                    <span class="field-label">Nombre del Curso</span>
                    <p class="field-readonly" class:empty={!nombre}>
                      {nombre || 'Se completará con la asignatura y la letra del grupo.'}
                    </p>
                    <p class="field-hint">
                      Se genera automáticamente; cambia si eliges otra letra de grupo.
                    </p>
                  </div>

                  <div class="field">
                    <label class="field-label" for="wiz-cod-curso">Código del Curso *</label>
                    <div class="cod-curso-row">
                      <input
                        id="wiz-cod-curso"
                        type="text"
                        bind:value={codCurso}
                        class="field-input"
                        placeholder="Ej: 12345"
                        maxlength="9"
                        required
                        />
                      <button
                        type="button"
                        class="btn-import"
                        onclick={handleIntranetImportCURCODIGO}
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="14"
                          height="14"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        >
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                          <polyline points="7 10 12 15 17 10" />
                          <line x1="12" x2="12" y1="15" y2="3" />
                        </svg>
                        Importar desde Intranet
                      </button>

                    </div>
                    
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
                </div>

                <!-- Componente (Cátedra) settings -->
                <div class="fields-section-title">Configuración del Componente</div>

                <!-- Genera acta, por componente -->
                <div class="genera-acta-section">
                  <p class="field-label">¿Qué componentes generan acta?</p>
                  <div class="por-componente-list">
                    {#each selectedTiposOrdered as tc (tc.id_tipo_componente)}
                      <div class="por-componente-row">
                        <span class="por-componente-tipo">{tc.tipo}</span>
                        <label class="field-check-label">
                          <input
                            type="checkbox"
                            checked={generaActaPorComponente[tc.id_tipo_componente] ?? true}
                            onchange={(e) => {
                              generaActaPorComponente = {
                                ...generaActaPorComponente,
                                [tc.id_tipo_componente]: (e.currentTarget as HTMLInputElement)
                                  .checked,
                              };
                            }}
                            class="field-check"
                          />
                          Genera acta
                        </label>
                      </div>
                    {/each}
                  </div>
                </div>

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
                      <input
                        type="checkbox"
                        bind:checked={aprobacionObligatoria}
                        class="field-check"
                      />
                      Aprobación Obligatoria
                    </label>
                  </div>

                  <div class="field">
                    <span class="field-label">Curso Colegiado</span>
                    <p class="colegiado-auto-badge" class:active={esColegiadoAuto}>
                      {esColegiadoAuto
                        ? 'Sí — hay más de un docente asignado entre los componentes'
                        : 'No — un solo docente asignado'}
                    </p>
                  </div>
                </div>

                <!-- Operaciones Administrativas -->
                <div class="fields-section-title" style="margin-top: 1.5rem; text-align: left;">Operaciones Administrativas</div>

                <div class="admin-ops-section" style="display: flex; flex-direction: column; align-items: flex-start; text-align: left; margin-top: 0.75rem; width: 100%;">
                  <label class="field-check-label" style="display: flex; items-center; gap: 0.5rem; text-align: left; cursor: pointer;">
                    <input
                      type="checkbox"
                      bind:checked={inscribirAutomaticamente}
                      class="field-check"
                    />
                    <span>Inscribir automáticamente a los alumnos desde la Intranet</span>
                  </label>
                  <p class="field-hint" style="margin-left: 1.5rem; margin-top: 0.25rem; font-size: 0.75rem; color: #6b7280; text-align: left;">
                    Busca las componentes correspondientes en la Intranet y matricula automáticamente a los alumnos inscritos al crear el curso.
                  </p>
                </div>
              </div>
            </div>
          {/if}


        </section>
      </div>

      <!-- ── Footer (inside form so submit works) ── -->
      {#if currentStep === 4}
        <div class="wiz-footer">
          {#if selectedTipos.size === 0}
            <p class="docente-required-hint">
              No se detectó ninguna componente para este curso; revisa el Plan de Estudios de la
              asignatura antes de continuar.
            </p>
          {:else if !selectedDocente}
            <p class="docente-required-hint">Falta elegir el docente del curso.</p>
          {:else if docentesPorComponenteIncompleto}
            <p class="docente-required-hint">Falta asignar un docente a cada componente.</p>
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
                selectedTipos.size === 0 ||
                docentesPorComponenteIncompleto}
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

  /*
   * Tarjeta de opción del asistente.
   *
   * Con sólo un borde gris y el texto a la izquierda se leían como campos de
   * texto deshabilitados, no como algo que se pueda pulsar. La flecha y el
   * cambio de fondo al pasar el ratón las devuelven a parecer opciones.
   */
  .option-card {
    padding: 0.75rem 2rem 0.75rem 0.75rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s;
    position: relative;
  }
  .option-card::after {
    content: '';
    position: absolute;
    right: 0.85rem;
    top: 50%;
    width: 0.45rem;
    height: 0.45rem;
    border-top: 2px solid #9ca3af;
    border-right: 2px solid #9ca3af;
    transform: translateY(-50%) rotate(45deg);
    transition: border-color 0.15s;
  }
  .option-card:hover {
    border-color: #3b82f6;
    background: #eff6ff;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
  }
  .option-card:hover::after {
    border-color: #3b82f6;
  }
  .option-card:focus-visible {
    outline: 2px solid var(--action-primary);
    outline-offset: 2px;
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

  .docente-search-bar {
    position: relative;
    margin-bottom: 0.75rem;
  }

  .docente-search-icon {
    position: absolute;
    left: 0.625rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
  }

  .docente-search-input {
    width: 100%;
    padding: 0.5rem 0.75rem 0.5rem 2rem;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #111827;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
  }
  .docente-search-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  }
  .docente-search-input::-webkit-search-cancel-button {
    cursor: pointer;
  }

  .pertenece-filter-bar {
    display: flex;
    gap: 0.375rem;
    margin-bottom: 0.75rem;
  }

  .pertenece-chip {
    padding: 0.3rem 0.7rem;
    border: 1.5px solid #d1d5db;
    border-radius: 999px;
    background: white;
    color: #4b5563;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
  }
  .pertenece-chip:hover {
    border-color: #93c5fd;
  }
  .pertenece-chip.active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8;
  }

  .badge-pertenece {
    background: #dcfce7;
    color: #166534;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #bbf7d0;
    margin-left: 0.375rem;
    vertical-align: middle;
  }

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
  /* ── Periodo del curso (Año / Semestre real) ── */
  .periodo-section {
    margin-bottom: 0.25rem;
  }

  .periodo-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin: 0 0 0.5rem;
  }

  /* ── Asignación de docentes por componente ── */
  .asignacion-docentes-section {
    margin-bottom: 0.75rem;
  }

  .por-componente-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.875rem;
  }

  .por-componente-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .por-componente-tipo {
    flex: 0 0 130px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
  }

  .por-componente-row .field-input {
    flex: 1;
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
    margin: 0 0 0.25rem;
  }

  .tipo-comp-sublabel {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 0 0 0.75rem;
  }

  .tipo-comp-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .tipo-comp-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    font-size: 0.875rem;
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
    background: #f5f3ff;
    color: #4f46e5;
  }

  .tipo-comp-btn.selected {
    border-color: #6366f1;
    background: #eef2ff;
    color: #4338ca;
    font-weight: 600;
  }

  .tipo-comp-btn-name {
    line-height: 1;
  }

  /* Ya no son botones clicables (cero selección manual): son chips que sólo
     muestran lo que se detectó. */
  .tipo-comp-btn {
    cursor: default;
  }
  .tipo-comp-btn:hover {
    border-color: #d1d5db;
    background: #ffffff;
  }

  .tipo-comp-principal-badge {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: #6366f1;
    color: #fff;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
  }

  /* ── Origen de componente (Intranet vs Plan de Estudios) ── */
  .origen-badge {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    white-space: nowrap;
  }
  .origen-badge.origen-intranet {
    background: #dcfce7;
    color: #15803d;
  }
  .origen-badge.origen-plan {
    background: #dbeafe;
    color: #1d4ed8;
  }

  /* ── Advertencias del preview: cero skip silencioso ── */
  .preview-advertencias {
    margin-top: 0.75rem;
    padding: 0.625rem 0.75rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
  }
  .preview-advertencias-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #92400e;
    margin: 0 0 0.25rem;
  }
  .preview-advertencias ul {
    margin: 0;
    padding-left: 1.1rem;
  }
  .preview-advertencias li {
    font-size: 0.75rem;
    color: #92400e;
    line-height: 1.4;
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

  .field-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .field-hint {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
  }

  .fields-stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  /*
   * Valor que rellena el sistema (nombre del curso).
   *
   * Iba en una píldora gris muy redondeada que no se parecía a ningún otro
   * campo del asistente y se leía como un botón. Se dibuja con la misma caja
   * que el input que le sigue, pero apagada y con borde discontinuo, para que
   * quede claro que es un campo que se rellena solo y no se puede escribir.
   */
  .field-readonly {
    display: flex;
    align-items: center;
    min-height: 2.25rem;
    padding: 0.5rem 0.75rem;
    border: 1px dashed #d1d5db;
    border-radius: 6px;
    background: #f9fafb;
    font-size: 0.875rem;
    font-weight: 500;
    color: #111827;
    margin: 0;
  }

  .field-readonly.empty {
    font-weight: 400;
    color: #9ca3af;
  }

  /*
   * Código del curso + acción de importarlo desde la Intranet.
   *
   * El botón iba en ámbar y con esquinas de píldora: competía visualmente con
   * «Crear Curso» y no se parecía a ningún otro control del asistente. Es una
   * acción secundaria que rellena un campo, así que toma el azul de acento y
   * el mismo radio y altura que el input al que acompaña.
   */
  .cod-curso-row {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
  }

  .cod-curso-row .field-input {
    flex: 1;
    min-width: 0;
  }

  .btn-import {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    flex-shrink: 0;
    padding: 0.5rem 0.875rem;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    background: #eff6ff;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #1d4ed8;
    white-space: nowrap;
    cursor: pointer;
    transition:
      background 0.15s,
      border-color 0.15s,
      color 0.15s;
  }
  .btn-import:hover {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
  }
  .btn-import:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
  }

  @media (max-width: 500px) {
    .cod-curso-row {
      flex-direction: column;
      align-items: stretch;
    }
    .btn-import {
      justify-content: center;
    }
  }

  .genera-acta-section {
    margin-bottom: 1rem;
  }

  .colegiado-auto-badge {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #6b7280;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    margin: 0;
  }

  .colegiado-auto-badge.active {
    color: #4338ca;
    background: #eef2ff;
    border-color: #c7d2fe;
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

  /*
   * Qué falta para poder crear el curso.
   *
   * Iba en rojo de error, así que el paso 4 se abría en estado de fallo
   * antes de que el usuario tocara nada. Es una indicación de progreso, no
   * un error: se muestra en tono neutro y sólo explica qué queda por hacer.
   */
  .docente-required-hint {
    font-size: 0.8rem;
    color: var(--state-info);
    margin: 0;
    padding: 0.4rem 0.75rem;
    background: var(--state-info-soft);
    border-radius: 6px;
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

  /* ── Cursos anteriores ── */
  .anteriores-loading {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    margin-bottom: 0.75rem;
  }

  .anteriores-section {
    border: 1.5px solid #c7d2fe;
    border-radius: 10px;
    background: #f5f3ff;
    padding: 0.875rem 1rem;
    margin-bottom: 0.5rem;
  }

  .anteriores-header {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    margin-bottom: 0.75rem;
  }

  .anteriores-icon {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: #e0e7ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4f46e5;
    flex-shrink: 0;
    margin-top: 1px;
  }

  .anteriores-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #3730a3;
    margin: 0 0 0.2rem;
  }

  .anteriores-subtitle {
    font-size: 0.75rem;
    color: #6b7280;
    margin: 0;
    line-height: 1.4;
  }

  .anteriores-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }

  .anterior-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    border: 1.5px solid #c7d2fe;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.15s;
    text-align: left;
    width: 100%;
  }

  .anterior-row:hover {
    border-color: #6366f1;
    background: #eef2ff;
  }

  .anterior-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
  }

  .anterior-cod {
    font-family: 'Courier New', monospace;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #4338ca;
    white-space: nowrap;
  }

  .anterior-periodo {
    font-size: 0.75rem;
    color: #6b7280;
    white-space: nowrap;
  }

  .anterior-stats {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
  }

  .anterior-stat {
    font-size: 0.6875rem;
    color: #6b7280;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    padding: 0.1rem 0.5rem;
  }

  .anterior-btn {
    font-size: 0.75rem;
    font-weight: 600;
    color: #4f46e5;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .anterior-row:hover .anterior-btn {
    color: #3730a3;
  }
</style>
