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
  import { untrack } from 'svelte';
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
  let fechaInicio = $state('');
  let agnoReal = $state(new Date().getFullYear());
  let semestreReal = $state<1 | 2>(1);
  // Docentes que dictan cada componente (id_tipo_componente → id_docente[]).
  // El primero de la lista queda como titular del componente.
  let docentesPorComponente = $state<Record<number, number[]>>({});
  let asigSearch = $state('');
  let docenteSearch = $state('');
  let perteneceFilter = $state<'all' | 'in' | 'out'>('all');
  // Componente settings por componente
  let generaActaPorComponente = $state<Record<number, boolean>>({});
  let aprobacionObligatoriaPorComponente = $state<Record<number, boolean>>({});
  let porcentajeAprobacionPorComponente = $state<Record<number, number>>({});
  let porcentajeAsistenciaPorComponente = $state<Record<number, number>>({});
  let inscribirAutomaticamente = $state(false);
  


  // ── Letra de grupo (indice_grupo) ──────────────────────────────────────────
  let indiceGrupo = $state<number | null>(null);
  let indiceGrupoSugerido = $state<number | null>(null);
  let indicesGrupoTomados = $state<number[]>([]);
  let loadingLetra = $state(false);

  // ── Oferta de la Intranet para el periodo (autocompletado) ─────────────────
  interface OfertaIntranet {
    disponible: boolean;
    grupos: {
      letra: string;
      indice_grupo: number | null;
      componentes: { id_tipo_componente: number | null; tipo: string; cur_codigo: number }[];
    }[];
    advertencias: string[];
  }
  let ofertaIntranet = $state<OfertaIntranet | null>(null);
  let loadingOferta = $state(false);

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
  let nombreSugerido = $derived.by(() => {
    if (!selectedAsig) return '';
    const grupoLetra = intToLetters(indiceGrupo ?? 0);
    return grupoLetra ? `${selectedAsig.nombre} - Grupo ${grupoLetra}` : selectedAsig.nombre;
  });

  let nombreManual = $state(false);
  let nombre = $state('');

  $effect(() => {
    if (!nombreManual) {
      nombre = nombreSugerido;
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

  // Colegiado no es una opción manual: un curso es colegiado cuando algún
  // componente lo dictan dos o más docentes. Un docente distinto en cada
  // componente NO lo hace colegiado.
  const componentesColegiados = $derived(
    selectedTiposOrdered.filter((tc) => (docentesPorComponente[tc.id_tipo_componente]?.length ?? 0) > 1),
  );
  const esColegiadoAuto = $derived(componentesColegiados.length > 0);

  const todosLosDocentes = $derived([...historicDocentes, ...otrosDocentes]);

  function nombreDocente(id: number): string {
    return todosLosDocentes.find((d) => d.id_docente === id)?.nombre_completo ?? `Docente #${id}`;
  }

  function agregarDocenteComponente(idTipo: number, idDocente: number) {
    const actuales = docentesPorComponente[idTipo] ?? [];
    if (actuales.includes(idDocente)) return;
    docentesPorComponente = { ...docentesPorComponente, [idTipo]: [...actuales, idDocente] };
  }

  function quitarDocenteComponente(idTipo: number, idDocente: number) {
    docentesPorComponente = {
      ...docentesPorComponente,
      [idTipo]: (docentesPorComponente[idTipo] ?? []).filter((id) => id !== idDocente),
    };
  }

  // Al elegir (o cambiar) el titular del curso, pasa a dictar los componentes
  // que aún no tienen docente y reemplaza al titular anterior donde estaba.
  let titularPrevio: number | null = null;
  $effect(() => {
    const titular = selectedDocente?.id_docente ?? null;
    const tipos = selectedTiposOrdered.map((tc) => tc.id_tipo_componente);
    untrack(() => {
      const siguiente: Record<number, number[]> = {};
      for (const idTipo of tipos) {
        let lista = (docentesPorComponente[idTipo] ?? []).filter((id) => id !== titularPrevio);
        if (titular && lista.length === 0) lista = [titular];
        else if (titular && titularPrevio && (docentesPorComponente[idTipo] ?? []).includes(titularPrevio)) {
          lista = [titular, ...lista.filter((id) => id !== titular)];
        }
        siguiente[idTipo] = lista;
      }
      docentesPorComponente = siguiente;
      titularPrevio = titular;
    });
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

  // Autocompletado desde la Intranet: con asignatura, año y semestre se piden
  // los grupos que la Intranet oferta. Si hay alguno que aún no existe en
  // UTAMED, la letra se completa con ese grupo (y el preview trae sus
  // componentes y CUR_CODIGO); si no, se sugiere la próxima letra libre.
  async function fetchLetraSugerida() {
    if (!selectedPlan || !selectedAsig) return;
    const requestId = ++letraRequestId;
    loadingLetra = true;
    loadingOferta = true;
    try {
      const params = new URLSearchParams({
        id_asignatura: String(selectedAsig.id_asignatura),
        id_plan: String(selectedPlan.id_plan),
        agno_real: String(agnoReal),
        semestre_real: String(semestreReal),
      });
      const [resLetra, resOferta] = await Promise.all([
        fetch(`/admin/cursos/proxima-letra?${params}`, { headers: { Accept: 'application/json' } }),
        fetch(`/admin/cursos/oferta-intranet?${params}`, { headers: { Accept: 'application/json' } }).catch(
          () => null,
        ),
      ]);
      if (requestId !== letraRequestId) return;

      const oferta: OfertaIntranet | null = resOferta?.ok ? await resOferta.json() : null;
      ofertaIntranet = oferta ?? {
        disponible: false,
        grupos: [],
        advertencias: ['No fue posible consultar la Intranet.'],
      };

      if (resLetra.ok) {
        const json = await resLetra.json();
        if (requestId !== letraRequestId) return;
        indiceGrupoSugerido = json.proximo_indice ?? 1;
        indicesGrupoTomados = json.indices_tomados ?? [];
        const grupoIntranetLibre = ofertaIntranet.grupos.find(
          (g) => g.indice_grupo && !indicesGrupoTomados.includes(g.indice_grupo),
        );
        indiceGrupo = grupoIntranetLibre?.indice_grupo ?? indiceGrupoSugerido;
      }
    } finally {
      if (requestId === letraRequestId) {
        loadingLetra = false;
        loadingOferta = false;
      }
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
    const maxOferta = Math.max(0, ...(ofertaIntranet?.grupos ?? []).map((g) => g.indice_grupo ?? 0));
    const maxOpcion = Math.max(maxTomado, indiceGrupoSugerido ?? 1, maxOferta) + 3;
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
      fechaInicio = '';
      agnoReal = new Date().getFullYear();
      semestreReal = 1;
      docentesPorComponente = {};
      asigSearch = '';
      docenteSearch = '';
      perteneceFilter = 'all';
      generaActaPorComponente = {};
      aprobacionObligatoriaPorComponente = {};
      porcentajeAprobacionPorComponente = {};
      porcentajeAsistenciaPorComponente = {};
      indiceGrupo = null;
      indiceGrupoSugerido = null;
      indicesGrupoTomados = [];
      loadingLetra = false;
      previewComponentes = [];
      previewOrigen = null;
      previewAdvertencias = [];
      loadingPreview = false;
      ofertaIntranet = null;
      loadingOferta = false;
      nombreManual = false;
      nombre = '';
    }
  });

  // ── Submit ───────────────────────────────────────────────────────────────
  const docentesPorComponenteIncompleto = $derived(
    selectedTiposOrdered.some((tc) => (docentesPorComponente[tc.id_tipo_componente]?.length ?? 0) === 0),
  );

  function handleSubmit(e: SubmitEvent) {
    e.preventDefault();
    if (
      !selectedAsig ||
      !selectedPlan ||
      !selectedDocente ||
      selectedTipos.size === 0 ||
      !principalTipo ||
      docentesPorComponenteIncompleto
    ) {
      console.warn('[CursoWizardModal] Submit bloqueado por el formulario, motivo(s):', {
        sinAsignatura: !selectedAsig,
        sinPlan: !selectedPlan,
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

    const docentesPorComponenteFinal: Record<number, number[]> = {};
    const generaActaFinal: Record<number, boolean> = {};
    const curCodigosFinal: Record<number, number> = {};
    const porcentajeAprobacionFinal: Record<number, number> = {};
    const porcentajeAsistenciaFinal: Record<number, number> = {};
    const aprobacionObligatoriaFinal: Record<number, boolean> = {};

    for (let i = 0; i < selectedTiposOrdered.length; i++) {
      const tc = selectedTiposOrdered[i];
      docentesPorComponenteFinal[tc.id_tipo_componente] = docentesPorComponente[tc.id_tipo_componente] ?? [];
      generaActaFinal[tc.id_tipo_componente] = generaActaPorComponente[tc.id_tipo_componente] ?? (i === 0);
      porcentajeAprobacionFinal[tc.id_tipo_componente] = porcentajeAprobacionPorComponente[tc.id_tipo_componente] ?? 60;
      porcentajeAsistenciaFinal[tc.id_tipo_componente] = porcentajeAsistenciaPorComponente[tc.id_tipo_componente] ?? 75;
      aprobacionObligatoriaFinal[tc.id_tipo_componente] = aprobacionObligatoriaPorComponente[tc.id_tipo_componente] ?? false;
      const pc = previewComponentes.find((p) => p.id_tipo_componente === tc.id_tipo_componente);
      if (pc?.cur_codigo) {
        curCodigosFinal[tc.id_tipo_componente] = pc.cur_codigo;
      }
    }

    onSubmit({
      id_asignatura: selectedAsig.id_asignatura,
      id_plan: selectedPlan.id_plan,
      nombre: (nombre?.trim() || nombreSugerido) || undefined,
      fecha_inicio: fechaInicio || undefined,
      numero_semestre: selectedAsig.agno_planificado,
      agno_real: agnoReal,
      semestre_real: semestreReal,
      indice_grupo: indiceGrupo ?? undefined,
      id_docente_sugerido: selectedDocente?.id_docente,
      id_tipo_componente_principal: principalTipo.id_tipo_componente,
      tipos_componente_ids: [...selectedTipos],
      docentes_por_componente: docentesPorComponenteFinal,
      genera_acta_por_componente: generaActaFinal,
      cur_codigos_por_componente: curCodigosFinal,
      porcentaje_aprobacion_por_componente: porcentajeAprobacionFinal,
      porcentaje_asistencia_por_componente: porcentajeAsistenciaFinal,
      aprobacion_obligatoria_por_componente: aprobacionObligatoriaFinal,
      // Fallbacks retrocompatibles basados en el componente principal
      aprobacion_obligatoria: aprobacionObligatoriaFinal[principalTipo.id_tipo_componente] ?? false,
      porcentaje_aprobacion: porcentajeAprobacionFinal[principalTipo.id_tipo_componente] ?? 60,
      porcentaje_asistencia_obligatoria: porcentajeAsistenciaFinal[principalTipo.id_tipo_componente] ?? 75,
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
  <div class="fixed inset-0 z-50 bg-gray-900/40 backdrop-blur-[2px] transition-opacity" onclick={onClose} role="presentation"></div>

  <!-- Dialog -->
  <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[60] bg-white rounded-2xl shadow-2xl w-[min(740px,calc(100vw-2rem))] h-[85dvh] max-h-[90dvh] min-h-[400px] flex flex-col overflow-hidden" role="dialog" aria-modal="true" aria-label="Nuevo Curso">
    <!-- ── Header ── -->
    <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-gray-100 shrink-0">
      <div>
        <h2 class="text-lg font-bold text-gray-900 m-0">Nuevo Curso Ofertado</h2>
        <p class="text-xs text-gray-500 mt-1 mb-0">Flujo lineal de configuración paso a paso para el nuevo curso</p>
      </div>
      <button class="p-1.5 text-gray-400 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition shrink-0 cursor-pointer" onclick={onClose} aria-label="Cerrar">
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

    <!-- ── Step progress (flujo lineal entre etapas) ── -->
    <nav class="flex items-center px-6 py-3 bg-slate-50 border-b border-gray-200 shrink-0" aria-label="Progreso del flujo">
      {#each [{ n: 1, label: 'Carrera' }, { n: 2, label: 'Plan' }, { n: 3, label: 'Asignatura' }, { n: 4, label: 'Docente & Datos' }] as s}
        <div class="flex items-center gap-1.5 shrink-0 {currentStep === s.n ? 'text-blue-700 font-semibold' : currentStep > s.n ? 'text-emerald-600 font-medium' : 'text-gray-400 font-medium'}">
          <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold transition-colors {currentStep > s.n ? 'bg-emerald-500 text-white' : currentStep === s.n ? 'border-2 border-blue-600 bg-blue-600 text-white shadow-xs' : 'border-2 border-gray-300 bg-white text-gray-400'}">
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
          <span class="text-xs max-sm:hidden {currentStep === s.n ? 'max-sm:inline' : ''}">{s.label}</span>
        </div>
        {#if s.n < 4}
          <div class="flex-1 h-0.5 mx-2 min-w-2 rounded transition-colors {currentStep > s.n ? 'bg-emerald-500' : 'bg-gray-200'}"></div>
        {/if}
      {/each}
    </nav>

    <!-- ── Scrollable body + footer ── -->
    <form class="flex-1 min-h-0 flex flex-col overflow-hidden" onsubmit={handleSubmit}>
      <div class="flex-1 min-h-0 overflow-y-auto px-6 py-4 flex flex-col gap-3">
        <!-- ══ Step 1: Carrera ══ -->
        <section class="border border-gray-200 rounded-xl overflow-hidden transition shrink-0 {currentStep > 1 ? 'bg-gray-50/50' : 'bg-white shadow-2xs'}">
          <div class="flex items-center gap-2.5 px-4 py-3 bg-gray-50 border-b {currentStep === 1 ? 'border-gray-200' : 'border-transparent'} flex-wrap">
            <span class="w-5 h-5 rounded-full text-[11px] font-bold flex items-center justify-center shrink-0 {currentStep > 1 ? 'bg-emerald-500 text-white' : 'bg-blue-600 text-white'}">
              {#if currentStep > 1}✓{:else}1{/if}
            </span>
            <span class="text-sm font-semibold text-gray-800">1. Carrera</span>
            {#if selectedCarrera}
              <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-0.5 rounded-full border border-blue-200 max-w-[280px] truncate">{selectedCarrera.nombre}</span>
              <button
                type="button"
                class="ml-auto text-xs text-gray-500 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 rounded-md px-2 py-0.5 transition cursor-pointer"
                onclick={() => {
                  selectedCarrera = null;
                  selectedPlan = null;
                  selectedAsig = null;
                  selectedDocente = null;
                  planes = [];
                  asignaturas = [];
                  docentesPorComponente = {};
                }}
              >
                Cambiar
              </button>
            {/if}
          </div>

          {#if currentStep === 1}
            <div class="p-4">
              {#if carreras.length === 0}
                <div class="text-sm bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-lg">
                  ⚠️ No hay carreras disponibles en el sistema.
                </div>
              {:else}
                <div class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-2.5">
                  {#each carreras as c (c.id_carrera)}
                    <button
                      type="button"
                      class="p-3 text-left border border-gray-200 hover:border-blue-500 hover:bg-blue-50/50 rounded-xl bg-white transition cursor-pointer relative group shadow-2xs"
                      onclick={() => onSelectCarrera(c)}
                    >
                      <span class="text-xs font-semibold text-gray-900 block group-hover:text-blue-700">{c.nombre}</span>
                      {#if c.sede || c.jornada}
                        <span class="text-[11px] text-gray-500 mt-1 block"
                          >{[c.sede, c.jornada].filter(Boolean).join(' · ')}</span
                        >
                      {/if}
                      <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 group-hover:text-blue-500 transition text-sm">→</span>
                    </button>
                  {/each}
                </div>
              {/if}
            </div>
          {/if}
        </section>

        <!-- ══ Step 2: Plan ══ -->
        <section class="border border-gray-200 rounded-xl overflow-hidden transition shrink-0 {currentStep < 2 ? 'opacity-50 pointer-events-none' : currentStep > 2 ? 'bg-gray-50/50' : 'bg-white shadow-2xs'}">
          <div class="flex items-center gap-2.5 px-4 py-3 bg-gray-50 border-b {currentStep === 2 ? 'border-gray-200' : 'border-transparent'} flex-wrap">
            <span class="w-5 h-5 rounded-full text-[11px] font-bold flex items-center justify-center shrink-0 {currentStep > 2 ? 'bg-emerald-500 text-white' : currentStep === 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'}">
              {#if currentStep > 2}✓{:else}2{/if}
            </span>
            <span class="text-sm font-semibold text-gray-800">2. Plan de Estudio</span>
            {#if selectedPlan}
              <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-0.5 rounded-full border border-blue-200 max-w-[280px] truncate"
                >{selectedPlan.carrera?.nombre ?? ''}
                {selectedPlan.agno_plan} v{selectedPlan.version_plan}</span
              >
              <button
                type="button"
                class="ml-auto text-xs text-gray-500 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 rounded-md px-2 py-0.5 transition cursor-pointer"
                onclick={() => {
                  selectedPlan = null;
                  selectedAsig = null;
                  selectedDocente = null;
                  asignaturas = [];
                  docentesPorComponente = {};
                }}
              >
                Cambiar
              </button>
            {/if}
          </div>

          {#if currentStep === 2}
            <div class="p-4">
              {#if loadingPlanes}
                <div class="w-6 h-6 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin my-4 mx-auto"></div>
              {:else if planes.length === 0}
                <p class="text-sm text-gray-500 m-0">Esta carrera no tiene planes de estudio activos.</p>
              {:else}
                <div class="flex flex-col gap-2">
                  {#each planes as p}
                    <button
                      type="button"
                      class="flex items-center gap-3 p-3 border border-gray-200 hover:border-blue-500 hover:bg-blue-50/50 rounded-xl bg-white text-left transition cursor-pointer w-full group shadow-2xs"
                      onclick={() => onSelectPlan(p)}
                    >
                      <span class="text-lg">📋</span>
                      <div>
                        <div class="text-sm font-semibold text-gray-900 group-hover:text-blue-700">
                          Plan {p.agno_plan} — versión {p.version_plan}
                        </div>
                        {#if p.carrera}
                          <div class="text-xs text-gray-500 mt-0.5">{p.carrera.nombre}</div>
                        {/if}
                      </div>
                      <svg
                        class="ml-auto text-gray-400 group-hover:text-blue-500 shrink-0 transition"
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
        <section class="border border-gray-200 rounded-xl overflow-hidden transition shrink-0 {currentStep < 3 ? 'opacity-50 pointer-events-none' : currentStep > 3 ? 'bg-gray-50/50' : 'bg-white shadow-2xs'}">
          <div class="flex items-center gap-2.5 px-4 py-3 bg-gray-50 border-b {currentStep === 3 ? 'border-gray-200' : 'border-transparent'} flex-wrap">
            <span class="w-5 h-5 rounded-full text-[11px] font-bold flex items-center justify-center shrink-0 {currentStep > 3 ? 'bg-emerald-500 text-white' : currentStep === 3 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'}">
              {#if currentStep > 3}✓{:else}3{/if}
            </span>
            <span class="text-sm font-semibold text-gray-800">3. Asignatura</span>
            {#if selectedAsig}
              <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-0.5 rounded-full border border-blue-200 max-w-[280px] truncate">{selectedAsig.cod_asignatura} – {selectedAsig.nombre}</span>
              <button
                type="button"
                class="ml-auto text-xs text-gray-500 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 rounded-md px-2 py-0.5 transition cursor-pointer"
                onclick={() => {
                  selectedAsig = null;
                  selectedDocente = null;
                  historicDocentes = [];
                  otrosDocentes = [];
                  docentesPorComponente = {};
                }}
              >
                Cambiar
              </button>
            {/if}
          </div>

          {#if currentStep === 3}
            <div class="p-4">
              {#if loadingAsig}
                <div class="w-6 h-6 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin my-4 mx-auto"></div>
              {:else if asignaturas.length === 0}
                <p class="text-sm text-gray-500 m-0">Este plan no tiene asignaturas asignadas.</p>
              {:else}
                <div class="mb-3">
                  <input
                    type="search"
                    bind:value={asigSearch}
                    placeholder="Buscar por código o nombre…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/15 transition"
                  />
                </div>
                <div class="max-h-80 overflow-y-auto pr-1">
                  {#if filteredCount === 0}
                    <p class="text-sm text-gray-500 m-0">
                      No se encontraron resultados para &ldquo;{asigSearch}&rdquo;.
                    </p>
                  {:else}
                    <div class="flex flex-col gap-4">
                      {#each Object.entries(filteredAsigByYear).sort(([a], [b]) => Number(a) - Number(b)) as [yr, sems]}
                        <div>
                          <span class="inline-block bg-blue-900 text-white text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full mb-2">Año {yr}</span>
                          {#each [{ label: 'Semestre 1', list: sems.s1 }, { label: 'Semestre 2', list: sems.s2 }] as sem}
                            {#if sem.list.length > 0}
                              <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider my-1.5 pl-0.5">{sem.label}</div>
                              <div class="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-2 mb-2">
                                {#each sem.list as a}
                                  <button
                                    type="button"
                                    class="p-2.5 border border-gray-200 hover:border-blue-500 hover:bg-blue-50/50 rounded-lg bg-white text-left transition cursor-pointer w-full group shadow-2xs"
                                    onclick={() => onSelectAsig(a)}
                                  >
                                    <div class="flex justify-between items-center mb-1">
                                      <span class="font-mono text-xs font-bold text-blue-600 group-hover:text-blue-700">{a.cod_asignatura}</span>
                                      {#if a.creditos_sct}
                                        <span class="text-[11px] bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded-full font-semibold">{a.creditos_sct} SCT</span>
                                      {/if}
                                    </div>
                                    <div class="text-xs font-medium text-gray-900 leading-snug">{a.nombre}</div>
                                    {#if a.tipo_ramo}
                                      <div class="text-[11px] text-gray-500 mt-1">{a.tipo_ramo}</div>
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
        <section class="border border-gray-200 rounded-xl overflow-hidden transition shrink-0 {currentStep < 4 ? 'opacity-50 pointer-events-none' : 'bg-white shadow-2xs'}">
          <div class="flex items-center gap-2.5 px-4 py-3 bg-gray-50 border-b border-gray-200">
            <span class="w-5 h-5 rounded-full text-[11px] font-bold flex items-center justify-center shrink-0 {currentStep === 4 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'}">4</span>
            <span class="text-sm font-semibold text-gray-800">4. Docente y Datos del Curso</span>
          </div>

          {#if currentStep === 4}
            <div class="p-4 space-y-4">
              <!-- ══ Cursos anteriores de esta asignatura ══ -->
              {#if loadingCursosAnteriores}
                <div class="flex items-center gap-2.5 mb-3">
                  <div class="w-5 h-5 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
                  <span class="text-xs text-gray-400">Buscando cursos anteriores…</span>
                </div>
              {:else if cursosAnteriores.length > 0}
                <div class="border border-indigo-200 rounded-xl bg-violet-50/40 p-3.5 mb-2">
                  <div class="flex items-start gap-2.5 mb-3">
                    <div class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 mt-0.5">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    </div>
                    <div>
                      <p class="text-xs font-bold text-indigo-900 mb-0.5">Cursos anteriores disponibles</p>
                      <p class="text-xs text-gray-500 m-0 leading-normal">
                        Puedes copiar uno de estos cursos en lugar de crear desde cero.
                        Las fechas de actividades se eliminarán.
                      </p>
                    </div>
                  </div>
                  <div class="flex flex-col gap-1.5">
                    {#each cursosAnteriores as ca (ca.id_curso)}
                      <button
                        type="button"
                        class="flex items-center gap-3 p-2.5 border border-indigo-200 hover:border-indigo-400 hover:bg-indigo-50/50 rounded-lg bg-white cursor-pointer transition text-left w-full group shadow-2xs"
                        onclick={() => {
                          onCopyFromAnterior(ca.id_curso, selectedAsig?.nombre ?? '');
                          onClose();
                        }}
                      >
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                          <span class="font-mono text-xs font-bold text-indigo-700 whitespace-nowrap">#{ca.cod_curso}</span>
                          {#if ca.agno_planificado && ca.semestre_planificado}
                            <span class="text-xs text-gray-500 whitespace-nowrap">
                              Año {ca.agno_planificado} · Sem. {ca.semestre_planificado}
                            </span>
                          {/if}
                        </div>
                        <div class="flex gap-1.5 shrink-0">
                          <span class="text-[11px] text-gray-500 bg-gray-100 border border-gray-200 rounded-full px-2 py-0.5">{ca.total_componentes} secc.</span>
                          <span class="text-[11px] text-gray-500 bg-gray-100 border border-gray-200 rounded-full px-2 py-0.5">{ca.total_actividades} activ.</span>
                        </div>
                        <span class="text-xs font-semibold text-indigo-600 group-hover:text-indigo-800 whitespace-nowrap shrink-0">Usar como base →</span>
                      </button>
                    {/each}
                  </div>
                </div>
                <div class="border-t border-gray-200 my-4"></div>
              {/if}

              <!-- Periodo del curso -->
              <div>
                <p class="text-sm font-semibold text-gray-700 mb-2">¿Cuándo se dicta este curso?</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-700" for="wiz-agno">Año *</label>
                    <input
                      id="wiz-agno"
                      type="number"
                      bind:value={agnoReal}
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 bg-white outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10 transition"
                      min="2000"
                      max="2100"
                      required
                    />
                  </div>

                  <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-700" for="wiz-semestre">Semestre *</label>
                    <select
                      id="wiz-semestre"
                      bind:value={semestreReal}
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 bg-white outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10 transition"
                      required
                    >
                      <option value={1}>1</option>
                      <option value={2}>2</option>
                    </select>
                  </div>

                  <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-700" for="wiz-letra">Letra del grupo</label>
                    <select
                      id="wiz-letra"
                      bind:value={indiceGrupo}
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 bg-white outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10 transition disabled:opacity-60 disabled:cursor-not-allowed"
                      disabled={loadingLetra}
                    >
                      {#each letraOptions as opt (opt.indice)}
                        <option value={opt.indice} disabled={opt.tomada}>
                          {opt.letra}{opt.tomada ? ' (ocupada)' : ''}
                        </option>
                      {/each}
                    </select>
                    <p class="text-xs text-gray-400 m-0">
                      {#if loadingLetra}
                        Calculando letra disponible…
                      {:else if ofertaIntranet?.grupos.some((g) => g.indice_grupo === indiceGrupo)}
                        Completada con un grupo de la Intranet; puedes elegir otra.
                      {:else}
                        Sugerida automáticamente; puedes elegir otra disponible.
                      {/if}
                    </p>
                  </div>
                </div>

                <!-- Grupos ofertados en la Intranet -->
                <div class="mt-3">
                  {#if loadingOferta}
                    <p class="text-xs text-gray-500 mb-1.5">Consultando la Intranet…</p>
                  {:else if ofertaIntranet && !ofertaIntranet.disponible}
                    <div class="p-2.5 mb-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                      <span class="font-semibold block">⚠️ No se pudo consultar la Intranet:</span>
                      <span>{ofertaIntranet.advertencias?.[0] || 'Error de conexión con el servicio de Oracle.'}</span>
                      <span class="block mt-0.5 text-gray-600">Se derivarán las componentes según las horas del Plan de Estudios sin números de acta oficiales.</span>
                    </div>
                  {:else if ofertaIntranet && ofertaIntranet.grupos.length === 0}
                    <div class="p-2.5 mb-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800">
                      <span class="font-semibold block">ℹ️ Sin oferta oficial en Intranet:</span>
                      <span>La Intranet no registra oferta para {selectedAsig?.cod_asignatura} en el período {agnoReal}-{semestreReal}.</span>
                      <span class="block mt-0.5 text-gray-600">Puedes continuar; las componentes se configurarán según las horas del Plan de Estudios.</span>
                    </div>
                  {:else if ofertaIntranet}
                    <p class="text-xs text-emerald-800 font-medium mb-1.5">🟢 Oferta oficial encontrada en Intranet ({agnoReal}-{semestreReal}):</p>
                    <div class="flex flex-wrap gap-2">
                      {#each ofertaIntranet.grupos as g (g.letra)}
                        {@const yaCreado = g.indice_grupo !== null && indicesGrupoTomados.includes(g.indice_grupo)}
                        <button
                          type="button"
                          class="flex flex-col items-start gap-0.5 px-3 py-2 border rounded-lg text-left transition {g.indice_grupo === indiceGrupo ? 'border-blue-600 bg-blue-50 text-blue-900' : 'border-gray-200 bg-white hover:border-blue-300'} disabled:opacity-60 disabled:cursor-not-allowed"
                          disabled={yaCreado || g.indice_grupo === null}
                          title={yaCreado ? 'Ya existe un curso con este grupo en UTAMED' : 'Usar este grupo'}
                          onclick={() => (indiceGrupo = g.indice_grupo)}
                        >
                          <span class="text-xs font-semibold text-gray-900">Grupo {g.letra}</span>
                          <span class="text-xs text-gray-500 tabular-nums">
                            {g.componentes.map((c) => `${c.tipo} ${c.cur_codigo}`).join(' · ')}
                          </span>
                          {#if yaCreado}<span class="text-[11px] text-amber-700 font-medium">ya creado</span>{/if}
                        </button>
                      {/each}
                    </div>
                  {/if}
                </div>
              </div>

              <!-- Divider -->
              <div class="border-t border-gray-200 my-4"></div>

              <!-- Componentes del curso -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <p class="text-sm font-semibold text-gray-700">Componentes del curso</p>
                  {#if previewOrigen === 'INTRANET'}
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800 border border-emerald-300">
                      🟢 Intranet conectada: Actas oficiales
                    </span>
                  {:else if previewOrigen === 'PLAN'}
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-800 border border-amber-300">
                      {ofertaIntranet && !ofertaIntranet.disponible ? '🔴 Sin conexión Intranet: Plan de Estudios' : '🔵 Plan de Estudios (Sin acta oficial)'}
                    </span>
                  {/if}
                </div>
                <p class="text-xs text-gray-500 mb-3">
                  {#if loadingPreview}
                    Detectando componentes…
                  {:else if previewOrigen === 'INTRANET'}
                    Detectadas en la Intranet con sus números de acta oficial (CUR_CODIGO). El componente principal se determina automáticamente (Cátedra &rsaquo; Taller &rsaquo; Laboratorio).
                  {:else if previewOrigen === 'PLAN'}
                    Derivadas de las horas del Plan de Estudios: las componentes se crearán sin acta oficial de Intranet.
                  {:else}
                    Se detectarán automáticamente al completar año, semestre y letra de grupo.
                  {/if}
                </p>

                {#if loadingPreview}
                  <div class="w-5 h-5 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin my-2"></div>
                {:else if previewComponentes.length === 0}
                  <p class="text-sm text-gray-400 m-0">
                    No se detectó ninguna componente. Revisa el Plan de Estudios de esta
                    asignatura antes de continuar.
                  </p>
                {:else}
                  <div class="flex flex-wrap gap-2">
                    {#each previewComponentes as pc (pc.id_tipo_componente)}
                      {@const isPrincipal = principalTipo?.id_tipo_componente === pc.id_tipo_componente}
                      <div class="flex items-center gap-2 px-3.5 py-2 border-2 border-indigo-200 rounded-lg bg-indigo-50/50 text-indigo-900 text-sm font-medium">
                        <span class="leading-none font-semibold">{pc.tipo}</span>
                        <span
                          class="text-[11px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap {pc.origen === 'INTRANET' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'}"
                        >
                          {pc.origen === 'INTRANET' ? '🟢 Intranet' : '🔵 Plan de Estudios'}
                        </span>
                        {#if pc.cur_codigo}
                          <span class="text-xs text-gray-500 tabular-nums" title="CUR_CODIGO de la Intranet">
                            Acta {pc.cur_codigo}
                          </span>
                        {/if}
                        {#if isPrincipal}
                          <span class="text-[10px] font-bold uppercase tracking-wider bg-indigo-600 text-white px-1.5 py-0.5 rounded-full">Principal</span>
                        {/if}
                      </div>
                    {/each}
                  </div>
                {/if}

                {#if previewAdvertencias.length > 0}
                  <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-xs font-bold text-amber-800 mb-1">⚠️ Advertencias</p>
                    <ul class="m-0 pl-4 list-disc text-xs text-amber-800 leading-relaxed">
                      {#each previewAdvertencias as adv}
                        <li>{adv}</li>
                      {/each}
                    </ul>
                  </div>
                {/if}
              </div>

              <!-- Divider -->
              <div class="border-t border-gray-200 my-4"></div>

              <!-- Docentes sugeridos -->
              <div>
                <p class="text-sm font-semibold text-gray-700 mb-1">Selección de docente</p>
                <p class="text-xs text-gray-700 bg-green-50 border border-green-200 rounded-md p-2.5 mb-3">
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
                  <div class="w-5 h-5 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin my-2"></div>
                {:else}
                  <!-- Buscador de docentes -->
                  {#if historicDocentes.length > 0 || otrosDocentes.length > 0}
                    <div class="relative mb-3">
                      <svg
                        class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
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
                        class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/15 transition"
                      />
                    </div>

                    <div class="flex gap-1.5 mb-3">
                      <button
                        type="button"
                        class="px-2.5 py-1 border rounded-full text-xs font-semibold cursor-pointer transition {perteneceFilter === 'all' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'bg-white border-gray-300 text-gray-600 hover:border-blue-300'}"
                        onclick={() => (perteneceFilter = 'all')}
                      >
                        Todos
                      </button>
                      <button
                        type="button"
                        class="px-2.5 py-1 border rounded-full text-xs font-semibold cursor-pointer transition {perteneceFilter === 'in' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'bg-white border-gray-300 text-gray-600 hover:border-blue-300'}"
                        onclick={() => (perteneceFilter = 'in')}
                      >
                        De la carrera
                      </button>
                      <button
                        type="button"
                        class="px-2.5 py-1 border rounded-full text-xs font-semibold cursor-pointer transition {perteneceFilter === 'out' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'bg-white border-gray-300 text-gray-600 hover:border-blue-300'}"
                        onclick={() => (perteneceFilter = 'out')}
                      >
                        Externos
                      </button>
                    </div>
                  {/if}

                  <!-- Docentes históricos first -->
                  {#if filteredHistoricDocentes.length > 0}
                    <div class="flex items-center gap-2 text-xs font-semibold text-gray-700 mb-2">
                      <span class="bg-amber-100 text-amber-800 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border border-amber-200">Historial</span>
                      Docentes que ya han impartido esta asignatura
                    </div>
                    <div class="flex flex-col gap-1.5 mb-3">
                      {#each filteredHistoricDocentes as d}
                        <button
                          type="button"
                          class="flex items-center gap-3 p-2.5 border rounded-lg bg-white cursor-pointer transition text-left w-full shadow-2xs {selectedDocente?.id_docente === d.id_docente ? 'border-blue-500 bg-blue-50/50 ring-2 ring-blue-500/20' : 'border-gray-200 hover:border-blue-400 hover:bg-sky-50/40'}"
                          onclick={() =>
                            (selectedDocente =
                              selectedDocente?.id_docente === d.id_docente ? null : d)}
                        >
                          <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-sm font-bold flex items-center justify-center shrink-0">
                            {(d.nombre_completo?.[0] ?? '?').toUpperCase()}
                          </div>
                          <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-gray-900 flex items-center flex-wrap gap-1.5">
                              {d.nombre_completo}
                              {#if d.pertenece_carrera}
                                <span class="bg-green-100 text-green-800 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border border-green-200">De la carrera</span>
                              {/if}
                            </div>
                            {#if d.cargo}<div class="text-xs text-gray-500 mt-0.5">{d.cargo}</div>{/if}
                          </div>
                          {#if selectedDocente?.id_docente === d.id_docente}
                            <svg
                              class="text-blue-600 shrink-0"
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
                    <p class="text-sm text-gray-400 my-2">
                      {#if docenteSearch.trim()}
                        No se encontraron docentes para &ldquo;{docenteSearch}&rdquo;.
                      {:else}
                        No hay docentes {perteneceFilter === 'in' ? 'de la carrera' : 'externos'} disponibles.
                      {/if}
                    </p>
                  {/if}

                  <!-- Other docentes in collapsible -->
                  {#if filteredOtrosDocentes.length > 0}
                    <details class="mt-3" open={!!docenteSearch.trim()}>
                      <summary class="text-xs font-semibold text-gray-600 cursor-pointer list-none flex items-center gap-1 hover:text-gray-900 select-none">
                        <span class="text-gray-400">▶</span> Otros docentes <span class="text-gray-400 font-normal">({filteredOtrosDocentes.length})</span>
                      </summary>
                      <div class="flex flex-col gap-1.5 mt-2">
                        {#each filteredOtrosDocentes as d}
                          <button
                            type="button"
                            class="flex items-center gap-3 p-2.5 border rounded-lg bg-white cursor-pointer transition text-left w-full shadow-2xs {selectedDocente?.id_docente === d.id_docente ? 'border-blue-500 bg-blue-50/50 ring-2 ring-blue-500/20' : 'border-gray-200 hover:border-blue-400 hover:bg-sky-50/40'}"
                            onclick={() =>
                              (selectedDocente =
                                selectedDocente?.id_docente === d.id_docente ? null : d)}
                          >
                            <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 text-sm font-bold flex items-center justify-center shrink-0">
                              {(d.nombre_completo?.[0] ?? '?').toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                              <div class="text-sm font-semibold text-gray-900 flex items-center flex-wrap gap-1.5">
                                {d.nombre_completo}
                                {#if d.pertenece_carrera}
                                  <span class="bg-green-100 text-green-800 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border border-green-200">De la carrera</span>
                                {/if}
                              </div>
                              {#if d.cargo}<div class="text-xs text-gray-500 mt-0.5">{d.cargo}</div>{/if}
                            </div>
                            {#if selectedDocente?.id_docente === d.id_docente}
                              <svg
                                class="text-blue-600 shrink-0"
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
              <div class="border-t border-gray-200 my-4"></div>

              <!-- Docentes por componente -->
              {#if selectedDocente}
                <div>
                  <p class="text-sm font-semibold text-gray-700 mb-1">¿Quién dicta cada componente?</p>
                  <p class="text-xs text-gray-500 mb-3">
                    {selectedDocente.nombre_completo} queda como jefe del curso, con acceso
                    administrativo aunque no dicte clases. Si un componente lo dictan dos o más
                    docentes, el curso es colegiado.
                  </p>

                  <div class="flex flex-col gap-2 mt-3">
                    {#each selectedTiposOrdered as tc (tc.id_tipo_componente)}
                      {@const asignados = docentesPorComponente[tc.id_tipo_componente] ?? []}
                      <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-3 py-2.5 border-t border-gray-100 first:border-t-0">
                        <span class="sm:w-32 shrink-0 text-xs font-semibold text-gray-700 sm:pt-1.5">{tc.tipo}</span>
                        <div class="flex-1 min-w-0 flex flex-wrap items-center gap-1.5">
                          {#if asignados.length === 0}
                            <span class="inline-flex items-center px-2.5 py-1 border border-dashed border-amber-300 rounded-full text-xs text-amber-700 bg-amber-50">Sin docente</span>
                          {/if}
                          {#each asignados as idDocente, i (idDocente)}
                            <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-1 rounded-full text-xs {i === 0 ? 'bg-blue-50 border border-blue-200 text-blue-900' : 'bg-gray-50 border border-gray-200 text-gray-700'}">
                              {nombreDocente(idDocente)}
                              {#if i === 0 && asignados.length > 1}
                                <small class="text-[10px] text-blue-600 font-semibold">titular</small>
                              {/if}
                              <button
                                type="button"
                                class="w-5 h-5 rounded-full inline-flex items-center justify-center text-gray-400 hover:text-gray-900 hover:bg-gray-200 cursor-pointer transition text-sm leading-none"
                                aria-label="Quitar a {nombreDocente(idDocente)} de {tc.tipo}"
                                onclick={() => quitarDocenteComponente(tc.id_tipo_componente, idDocente)}
                              >
                                ×
                              </button>
                            </span>
                          {/each}
                          <select
                            class="flex-1 min-w-40 px-3 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-900 bg-white outline-hidden focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                            aria-label="Agregar docente a {tc.tipo}"
                            value=""
                            onchange={(e) => {
                              const sel = e.currentTarget as HTMLSelectElement;
                              if (sel.value) agregarDocenteComponente(tc.id_tipo_componente, Number(sel.value));
                              sel.value = '';
                            }}
                          >
                            <option value="">+ Agregar docente…</option>
                            {#if historicDocentes.some((d) => !asignados.includes(d.id_docente))}
                              <optgroup label="Historial">
                                {#each historicDocentes.filter((d) => !asignados.includes(d.id_docente)) as d (d.id_docente)}
                                  <option value={d.id_docente}>{d.nombre_completo}</option>
                                {/each}
                              </optgroup>
                            {/if}
                            {#if otrosDocentes.some((d) => !asignados.includes(d.id_docente))}
                              <optgroup label="Otros docentes">
                                {#each otrosDocentes.filter((d) => !asignados.includes(d.id_docente)) as d (d.id_docente)}
                                  <option value={d.id_docente}>{d.nombre_completo}</option>
                                {/each}
                              </optgroup>
                            {/if}
                          </select>
                        </div>
                      </div>
                    {/each}
                  </div>
                </div>

                <!-- Divider -->
                <div class="border-t border-gray-200 my-4"></div>
              {/if}

              <!-- Remaining fields -->
              <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold text-gray-700" for="wiz-nombre-curso">Nombre del Curso *</label>
                    {#if nombreManual && nombre !== nombreSugerido}
                      <button
                        type="button"
                        class="text-xs text-blue-600 hover:text-blue-800 underline cursor-pointer"
                        onclick={() => {
                          nombreManual = false;
                          nombre = nombreSugerido;
                        }}
                      >
                        Restablecer sugerido
                      </button>
                    {/if}
                  </div>
                  <input
                    id="wiz-nombre-curso"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 bg-white outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10 transition"
                    placeholder={nombreSugerido || 'Se completará con la asignatura y la letra del grupo.'}
                    bind:value={nombre}
                    oninput={() => {
                      nombreManual = true;
                    }}
                  />
                  <p class="text-xs text-gray-400 m-0">
                    Sugerido automáticamente a partir de la asignatura y el grupo. Puedes modificarlo si lo requieres.
                  </p>
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-gray-700" for="wiz-fecha">Fecha de Inicio</label>
                  <input
                    id="wiz-fecha"
                    type="date"
                    bind:value={fechaInicio}
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 bg-white outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10 transition"
                  />
                </div>
              </div>

              <!-- Componente settings por componente -->
              <div class="text-xs font-bold text-gray-700 uppercase tracking-wider pt-3 border-t border-gray-200">Configuración por Componente</div>

              <div class="flex flex-col gap-3 mt-2">
                {#each selectedTiposOrdered as tc, i (tc.id_tipo_componente)}
                  <div class="p-3 border border-gray-200 rounded-xl bg-gray-50/80">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                      <span class="text-sm font-bold text-gray-800">{tc.tipo}</span>
                      <div class="flex items-center gap-4 flex-wrap">
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer select-none">
                          <input
                            type="checkbox"
                            checked={generaActaPorComponente[tc.id_tipo_componente] ?? (i === 0)}
                            onchange={(e) => {
                              generaActaPorComponente = {
                                ...generaActaPorComponente,
                                [tc.id_tipo_componente]: (e.currentTarget as HTMLInputElement).checked,
                              };
                            }}
                            class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 cursor-pointer"
                          />
                          Genera acta
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer select-none">
                          <input
                            type="checkbox"
                            checked={aprobacionObligatoriaPorComponente[tc.id_tipo_componente] ?? false}
                            onchange={(e) => {
                              aprobacionObligatoriaPorComponente = {
                                ...aprobacionObligatoriaPorComponente,
                                [tc.id_tipo_componente]: (e.currentTarget as HTMLInputElement).checked,
                              };
                            }}
                            class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 cursor-pointer"
                          />
                          Aprobación obligatoria
                        </label>
                      </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                      <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700" for="wiz-pct-aprobacion-{tc.id_tipo_componente}">
                          % Aprobación *
                        </label>
                        <input
                          id="wiz-pct-aprobacion-{tc.id_tipo_componente}"
                          type="number"
                          value={porcentajeAprobacionPorComponente[tc.id_tipo_componente] ?? 60}
                          oninput={(e) => {
                            porcentajeAprobacionPorComponente = {
                              ...porcentajeAprobacionPorComponente,
                              [tc.id_tipo_componente]: Number((e.currentTarget as HTMLInputElement).value),
                            };
                          }}
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 bg-white outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10 transition"
                          min="0"
                          max="100"
                          step="0.5"
                          required
                        />
                      </div>

                      <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700" for="wiz-pct-asistencia-{tc.id_tipo_componente}">
                          % Asistencia Obligatoria *
                        </label>
                        <input
                          id="wiz-pct-asistencia-{tc.id_tipo_componente}"
                          type="number"
                          value={porcentajeAsistenciaPorComponente[tc.id_tipo_componente] ?? 75}
                          oninput={(e) => {
                            porcentajeAsistenciaPorComponente = {
                              ...porcentajeAsistenciaPorComponente,
                              [tc.id_tipo_componente]: Number((e.currentTarget as HTMLInputElement).value),
                            };
                          }}
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 bg-white outline-hidden focus:border-blue-500 focus:ring-3 focus:ring-blue-500/10 transition"
                          min="0"
                          max="100"
                          step="0.5"
                          required
                        />
                      </div>
                    </div>
                  </div>
                {/each}
              </div>

              <div class="flex flex-col gap-1.5 mt-3">
                <span class="text-xs font-semibold text-gray-700">Curso Colegiado</span>
                <p class="text-xs font-semibold p-2.5 border rounded-lg m-0 {esColegiadoAuto ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : 'text-gray-600 bg-gray-100 border-gray-200'}">
                  {esColegiadoAuto
                    ? `Sí — ${componentesColegiados.map((tc) => tc.tipo).join(', ')} con dos o más docentes`
                    : 'No — cada componente con un solo docente'}
                </p>
              </div>

              <!-- Operaciones Administrativas -->
              <div class="text-xs font-bold text-gray-700 uppercase tracking-wider pt-4 mt-2 border-t border-gray-200 text-left">Operaciones Administrativas</div>

              <div class="flex flex-col items-start text-left mt-2 w-full">
                <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer select-none">
                  <input
                    type="checkbox"
                    bind:checked={inscribirAutomaticamente}
                    class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 cursor-pointer"
                  />
                  <span>Inscribir automáticamente a los alumnos desde la Intranet</span>
                </label>
                <p class="ml-6 mt-1 text-xs text-gray-400 text-left">
                  Busca las componentes correspondientes en la Intranet y matricula automáticamente a los alumnos inscritos al crear el curso.
                </p>
              </div>
            </div>
          {/if}
        </section>
      </div>

      <!-- ── Footer (inside form so submit works) ── -->
      {#if currentStep === 4}
        <div class="flex flex-col gap-2 px-6 py-3.5 border-t border-gray-200 bg-white shrink-0">
          {#if selectedTipos.size === 0}
            <p class="text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-md p-2 m-0">
              No se detectó ninguna componente para este curso; revisa el Plan de Estudios de la
              asignatura antes de continuar.
            </p>
          {:else if !selectedDocente}
            <p class="text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-md p-2 m-0">Falta elegir el docente del curso.</p>
          {:else if docentesPorComponenteIncompleto}
            <p class="text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-md p-2 m-0">Falta asignar un docente a cada componente.</p>
          {/if}
          <div class="flex justify-end gap-3">
            <button
              type="button"
              class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition cursor-pointer"
              onclick={onClose}
              disabled={isLoading}
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-lg text-sm font-semibold shadow-xs hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed transition cursor-pointer"
              disabled={isLoading ||
                !selectedAsig ||
                !selectedPlan ||
                !selectedDocente ||
                selectedTipos.size === 0 ||
                docentesPorComponenteIncompleto}
            >
              {#if isLoading}
                <span class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
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
