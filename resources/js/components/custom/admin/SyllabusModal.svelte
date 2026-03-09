<script lang="ts">
  /**
   * SyllabusModal — View/edit an existing Programa or generate a new one.
   *
   * Populated state (has_programa = true):
   *   Loads sections via GET /admin/cursos/{id}/programa, renders them as editable
   *   text areas. "Guardar cambios" POSTs updated sections (creates new version).
   *
   * Empty state (has_programa = false):
   *   9-step wizard → generate.
   */
  import axios, { AxiosError } from 'axios';
  import { onMount } from 'svelte';
  import ProgramaWizardSteps from './ProgramaWizardSteps.svelte';
  import type { Curso, Programa } from '@/types/admin.types';

  interface ContenidoPrograma {
    id_contenido_programa?: number;
    texto_contenido: string | null;
    valor_numerico?: number | null;
    orden_item: number;
  }

  interface SeccionPrograma {
    id_estructura_programa?: number;
    nombre_seccion: string;
    numeral_romano?: string;
    orden: number;
    es_lista?: boolean;
    es_actual?: boolean;
    contenidos_programa: ContenidoPrograma[];
  }

  interface ProgramaFull extends Programa {
    es_plantilla: boolean;
    version_programa: number;
    secciones: SeccionPrograma[];
  }

  interface WizardSection {
    nombre_seccion: string;
    numeral_romano: string;
    orden: number;
    contenidos: { texto_contenido: string; orden_item: number }[];
  }

  interface Props {
    isOpen: boolean;
    curso: Curso | null;
    onClose: () => void;
    onSuccess: (programa: Programa) => void;
    syllabusType?: 'simplified' | 'combined' | 'complete' | null;
    canApprove?: boolean;
  }

  let { isOpen = $bindable(), curso, onClose, onSuccess, syllabusType = null, canApprove = false }: Props = $props();

  // ── Syllabus Type tracking ───────────────────────────────────────────────
  let selectedSyllabusType = $state<'simplified' | 'combined' | 'complete' | null>(null);

  // Sincronizar selectedSyllabusType cuando syllabusType prop cambie
  $effect(() => {
    console.log('🔄 Effect en SyllabusModal: syllabusType prop cambió a:', syllabusType);
    // 'combined' = continuar BASICO → usar pasos COMPLETO (9 steps)
    selectedSyllabusType = syllabusType === 'combined' ? 'complete' : syllabusType;
    console.log('🔄 selectedSyllabusType ahora es:', selectedSyllabusType, 'STEPS.length:', STEPS.length);
  });

  // ── Mode ────────────────────────────────────────────────────────────────────
  // 'view' = showing existing programa (editable)
  // 'wizard' = generating new programa (3-step wizard)
  let mode = $state<'view' | 'wizard'>('view');

  // ── View/edit state (populated) ─────────────────────────────────────────────
  let loadingPrograma = $state(false);
  let programaData = $state<ProgramaFull | null>(null);
  let editedSections = $state<SeccionPrograma[]>([]);
  let isSaving = $state(false);
  let isApproving = $state(false);
  let isEditMode = $state(false);
  let viewError = $state('');

  // ── Wizard state (9 secciones) ───────────────────────────────────────────────
  let step = $state<1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9>(1);
  let isGenerating = $state(false);
  let errorMsg = $state('');

  // Secció I: Identificación
  let nombre_asignatura = $state('');
  let codigo = $state('');
  let creditos_sct = $state('');
  let horas_catedra = $state('');
  let horas_taller = $state('');
  let horas_laboratorio = $state('');
  let categoria = $state('Obligatorio');

  // Sección II: Presentación
  let presentacion = $state('');

  // Sección III: Estándares
  let estandares = $state('');

  // Sección IV: Competencias
  let competencias_especificas = $state<{ titulo: string }[]>([{ titulo: '' }]);
  let competencias_genericas = $state<{ titulo: string }[]>([{ titulo: '' }]);
  let subcompetencias = $state<{ titulo: string }[]>([]);

  // Sección V: Evaluación Diagnóstica
  let items_evaluacion = $state<{ titulo: string; descripcion: string }[]>([{ titulo: '', descripcion: '' }]);

  // Sección VII (BASICO): Actividades de Aprendizaje
  let actividades = $state<
    {
      id_actividad: number | null;
      nombre: string;
      tipo: string;
      id_unidad?: number | null;
      id_seccion?: number | null;
      nombre_unidad?: string;
    }[]
  >([{ id_actividad: null, nombre: '', tipo: 'participación', id_unidad: null, id_seccion: null, nombre_unidad: '' }]);
  let existingActividades = $state<{ id_actividad: number; nombre: string; fecha_limite: string }[]>([]);

  // Sección VI: Unidades (con resultados de aprendizaje por unidad)
  let unidades = $state<{ numero: number; titulo: string; contenidos: string; resultados_aprendizaje: { resultado: string }[] }[]>([
    { numero: 1, titulo: '', contenidos: '', resultados_aprendizaje: [{ resultado: '' }] },
  ]);

  // Sección VII: Planificación (consolida resultados de aprendizaje desde unidades)
  let consolidatedResults = $derived.by(() => {
    const allResults = new Map<string, boolean>();
    unidades.forEach((u) => {
      u.resultados_aprendizaje.forEach((r) => {
        if (r.resultado.trim()) {
          allResults.set(r.resultado.trim(), true);
        }
      });
    });
    return Array.from(allResults.keys()).map((r) => ({ resultado: r }));
  });

  // Determinar si el programa puede ser editado
  // Solo editable si: programa en estado NO APROBADO
  let isEditable = $derived.by(() => {
    if (!isEditMode || !programaData) return true; // Nuevo programa siempre editable
    return programaData.estado !== 'APROBADO'; // Deshabilitar si está aprobado
  });

  let metodologia = $state('');
  let evaluacion = $state('');

  // Sección VIII: Recursos
  let recursos = $state<{ descripcion: string; tipo: string; ubicacion: string }[]>([
    { descripcion: '', tipo: 'Libro', ubicacion: '' },
    { descripcion: '', tipo: 'Libro', ubicacion: '' },
  ]);

  // Sección IX: Aspectos Administrativos
  let ponderacion_optativa = $state('0');
  let componentes = $state<
    { componente: string; porcentaje: number; genera_acta: boolean; aprobacion_obligatoria: boolean; asistencia_obligatoria: number }[]
  >([{ componente: '', porcentaje: 0, genera_acta: false, aprobacion_obligatoria: false, asistencia_obligatoria: 0 }]);
  let normativa_curso = $state('');

  const ALL_STEPS = [
    { id: 1, label: 'I. Identificación', icon: '📚' },
    { id: 2, label: 'II. Presentación', icon: '📝' },
    { id: 3, label: 'III. Estándares', icon: '📋' },
    { id: 4, label: 'IV. Competencias', icon: '🎯' },
    { id: 5, label: 'V. Evaluación Diagnóstica', icon: '📊' },
    { id: 6, label: 'VI. Unidades', icon: '📖' },
    { id: 7, label: 'VII. Planificación', icon: '📅' },
    { id: 8, label: 'VIII. Recursos', icon: '📚' },
    { id: 9, label: 'IX. Aspectos Admin.', icon: '⚙️' },
  ] as const;

  // Filter steps based on syllabus type
  const STEPS = $derived.by(() => {
    if (selectedSyllabusType === 'simplified' || selectedSyllabusType === 'combined') {
      // BASICO: 5 steps → I, II, VI (with activities), VIII, IX
      return [ALL_STEPS[0], ALL_STEPS[1], ALL_STEPS[5], ALL_STEPS[7], ALL_STEPS[8]];
    }
    return ALL_STEPS; // All 9 steps for COMPLETO
  });

  // Detectar contexto (docente, ayudante o admin) desde la URL
  const getBasePath = () => {
    const currentPath = window.location.pathname;
    if (currentPath.includes('/docente/')) return '/docente/cursos';
    if (currentPath.includes('/ayudante/')) return '/ayudante/cursos';
    return '/admin/cursos';
  };

  // ── Init on mount: decide mode and load data ─────────────────────────────────
  onMount(() => {
    console.log(
      '🔍 SyllabusModal onMount - syllabusType (prop):',
      syllabusType,
      'selectedSyllabusType:',
      selectedSyllabusType,
      'has_programa:',
      curso?.has_programa,
    );
    console.log(
      `📊 STEPS derivation test: selectedSyllabusType=${selectedSyllabusType}, STEPS.length=${STEPS.length}, ALL_STEPS.length=${ALL_STEPS.length}`,
    );
    console.log(`📈 Contexto: modo wizard=${mode === 'wizard'}, curso_id=${curso?.id_curso}, curso_asignatura=${curso?.asignatura_nombre}`);

    // Si es 'combined' y existe un programa BASICO, iniciar wizard COMPLETO pre-poblado
    if (syllabusType === 'combined' && curso?.has_programa) {
      mode = 'wizard';
      console.log('✨ Modo WIZARD COMPLETO - continuando desde BASICO');
      loadAndPrefillFromBasico();
    }
    // Si existe programa general, cargar en modo view
    else if (curso?.has_programa) {
      mode = 'view';
      console.log('📖 Modo VIEW - cargando programa existente');
      loadPrograma();
    }
    // Si no existe programa, iniciar wizard
    else {
      mode = 'wizard';
      console.log('✨ Modo WIZARD - inicializando nuevo programa', 'syllabusType:', syllabusType, 'STEPS.length que se va a usar:', STEPS.length);
      initializeWizard();
    }

    // Force update of selectedSyllabusType in case it hasn't been set by $effect yet
    const effectiveType = syllabusType === 'combined' ? 'complete' : syllabusType;
    if (selectedSyllabusType !== effectiveType) {
      console.log('🔄 Forcing selectedSyllabusType sync:', effectiveType);
      selectedSyllabusType = effectiveType;
      console.log(`📊 After sync: STEPS.length=${STEPS.length}`);
    }
  });

  function initializeWizard() {
    // Cargar datos del curso y asignatura en el wizard
    nombre_asignatura = curso?.asignatura_nombre ?? '';

    // Intentar obtener código desde diferentes fuentes
    codigo = String(curso?.cod_curso ?? '');

    // Buscar créditos/horas en orden: campos directos → asignatura embebida → asignacionPlan.asignatura
    const c = curso as any;
    const asignatura = c?.asignatura ?? c?.asignacionPlan?.asignatura;

    creditos_sct = String(c?.creditos_sct ?? asignatura?.creditos_sct ?? '');
    horas_catedra = String(c?.horas_catedra ?? asignatura?.horas_catedra ?? '');
    horas_taller = String(c?.horas_taller ?? asignatura?.horas_taller ?? '');
    horas_laboratorio = String(c?.horas_laboratorio ?? asignatura?.horas_laboratorio ?? '');

    // Cargar secciones del curso para Sección IX
    loadCursoSecciones();
    // Cargar actividades existentes del curso para Sección VII
    loadCursoActividades();
  }

  async function loadCursoSecciones() {
    if (!curso) return;
    try {
      const basePath = getBasePath();
      const { data } = await axios.get(`${basePath}/${curso.id_curso}/secciones`);
      if (data.secciones && data.secciones.length > 0) {
        componentes = data.secciones;
      }
    } catch (error) {
      console.warn('Error cargando secciones del curso:', error);
      // Si falla, mantener la inicialización por defecto
    }
  }

  async function loadCursoActividades() {
    if (!curso) return;
    try {
      const basePath = getBasePath();
      const { data } = await axios.get(`${basePath}/${curso.id_curso}/actividades/json`);
      if (Array.isArray(data)) {
        existingActividades = data;
        console.log('✅ Actividades cargadas:', existingActividades);
      }
    } catch (error) {
      console.warn('Error cargando actividades del curso:', error);
      // Si falla, solo no mostrar actividades existentes
    }
  }

  /**
   * Pre-fills all wizard fields from a JSONB secciones object.
   */
  function prefillWizardFromData(raw: Record<string, any>) {
    const cI = raw?.I?.contenido ?? {};
    if (cI.nombre_asignatura) nombre_asignatura = cI.nombre_asignatura;
    if (cI.codigo) codigo = String(cI.codigo);
    if (cI.creditos_sct != null) creditos_sct = String(cI.creditos_sct);
    if (cI.horas?.catedra != null) horas_catedra = String(cI.horas.catedra);
    if (cI.horas?.taller != null) horas_taller = String(cI.horas.taller);
    if (cI.horas?.laboratorio != null) horas_laboratorio = String(cI.horas.laboratorio);
    if (cI.categoria) categoria = cI.categoria;

    const cII = raw?.II?.contenido ?? {};
    if (cII.texto) presentacion = cII.texto;

    const cIII = raw?.III?.contenido ?? {};
    if (cIII.texto) estandares = cIII.texto;

    const cIV = raw?.IV?.contenido ?? {};
    if (Array.isArray(cIV.competencias_especificas) && cIV.competencias_especificas.length > 0)
      competencias_especificas = cIV.competencias_especificas.map((c: any) => ({ titulo: c.titulo ?? '' }));
    if (Array.isArray(cIV.competencias_genericas) && cIV.competencias_genericas.length > 0)
      competencias_genericas = cIV.competencias_genericas.map((c: any) => ({ titulo: c.titulo ?? '' }));
    if (Array.isArray(cIV.subcompetencias) && cIV.subcompetencias.length > 0)
      subcompetencias = cIV.subcompetencias.map((s: any) => ({ titulo: s.titulo ?? '' }));

    const cV = raw?.V?.contenido ?? {};
    if (Array.isArray(cV.items) && cV.items.length > 0)
      items_evaluacion = cV.items.map((i: any) => ({ titulo: i.titulo ?? '', descripcion: i.descripcion ?? '' }));

    const cVI = raw?.VI?.contenido ?? {};
    if (Array.isArray(cVI.unidades) && cVI.unidades.length > 0) {
      unidades = cVI.unidades.map((u: any) => ({
        numero: u.numero ?? 1,
        titulo: u.titulo ?? '',
        contenidos: u.contenidos_items?.[0]?.item ?? '',
        resultados_aprendizaje:
          Array.isArray(u.resultados_aprendizaje) && u.resultados_aprendizaje.length > 0
            ? u.resultados_aprendizaje.map((r: any) => ({ resultado: r.resultado ?? '' }))
            : [{ resultado: '' }],
      }));
    }

    const cVII = raw?.VII?.contenido ?? {};
    if (cVII.metodologia?.tipo_estrategia) metodologia = cVII.metodologia.tipo_estrategia;
    if (cVII.evaluacion?.tipo_evaluacion) evaluacion = cVII.evaluacion.tipo_evaluacion;

    const cVIII = raw?.VIII?.contenido ?? {};
    if (Array.isArray(cVIII.recursos) && cVIII.recursos.length > 0)
      recursos = cVIII.recursos.map((r: any) => ({ descripcion: r.descripcion ?? '', tipo: r.tipo ?? 'Libro', ubicacion: r.ubicacion ?? '' }));

    const cIX = raw?.IX?.contenido ?? {};
    if (cIX.descripcion) normativa_curso = cIX.descripcion;
    if (cIX.ponderacion_optativa?.porcentaje != null) ponderacion_optativa = String(cIX.ponderacion_optativa.porcentaje);
    if (Array.isArray(cIX.tabla_componentes) && cIX.tabla_componentes.length > 0)
      componentes = cIX.tabla_componentes.map((c: any) => ({
        componente: c.componente ?? '',
        porcentaje: c.porcentaje ?? 0,
        genera_acta: c.genera_acta ?? false,
        aprobacion_obligatoria: c.aprobacion_obligatoria ?? false,
        asistencia_obligatoria: c.asistencia_obligatoria ?? 0,
      }));
  }

  async function loadPrograma() {
    if (!curso) return;
    loadingPrograma = true;
    viewError = '';
    try {
      const basePath = getBasePath();
      const { data } = await axios.get(`${basePath}/${curso.id_curso}/programa/json`);
      programaData = data.programa;
      const raw: Record<string, any> = data.programa?.data_syllabus?.secciones ?? {};

      // Determinar tipo desde data_syllabus.metadata.tipo_syllabus ("BASICO" | "COMPLETO")
      // normalizado a los valores del frontend ('simplified' | 'complete')
      const metaTipo = (data.programa?.data_syllabus?.metadata?.tipo_syllabus ?? '') as string;
      const normalizedStoredType =
        metaTipo === 'BASICO' ? 'simplified' :
        metaTipo === 'COMPLETO' ? 'complete' : '';

      // Inferir desde qué secciones existen en el JSONB (formato nuevo: objeto con claves romanas)
      const hasComplexSections = !!(raw.III || raw.IV || raw.V || raw.IX);
      const inferredType: 'simplified' | 'complete' = hasComplexSections ? 'complete' : 'simplified';

      if (normalizedStoredType) {
        selectedSyllabusType = normalizedStoredType as 'simplified' | 'complete';
      } else if (syllabusType && syllabusType !== 'combined') {
        selectedSyllabusType = syllabusType;
      } else {
        selectedSyllabusType = inferredType;
      }

      prefillWizardFromData(raw);

      // Rellenar créditos/horas desde el objeto curso cuando no estén en el JSONB
      if (!nombre_asignatura) nombre_asignatura = curso?.asignatura_nombre ?? '';
      if (!codigo) codigo = String(curso?.cod_curso ?? '');
      const c = curso as any;
      const asig = c?.asignatura ?? c?.asignacionPlan?.asignatura;
      if (!creditos_sct) creditos_sct = String(c?.creditos_sct ?? asig?.creditos_sct ?? '');
      if (!horas_catedra) horas_catedra = String(c?.horas_catedra ?? asig?.horas_catedra ?? '');
      if (!horas_taller) horas_taller = String(c?.horas_taller ?? asig?.horas_taller ?? '');
      if (!horas_laboratorio) horas_laboratorio = String(c?.horas_laboratorio ?? asig?.horas_laboratorio ?? '');

      isEditMode = true;
      mode = 'wizard';
      step = 1;
      loadCursoActividades();
    } catch (err) {
      console.error('Error cargando programa JSON:', err);
      viewError = 'Error cargando el programa. Intente de nuevo.';
    } finally {
      loadingPrograma = false;
    }
  }

  /**
   * Carga el programa BASICO existente y pre-rellena el wizard COMPLETO con sus datos.
   * Se usa cuando syllabusType === 'combined' y existe un programa.
   */
  async function loadAndPrefillFromBasico() {
    if (!curso) return;
    try {
      const basePath = getBasePath();
      const { data } = await axios.get(`${basePath}/${curso.id_curso}/programa/json`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });

      const raw: Record<string, any> = data.programa?.data_syllabus?.secciones ?? {};
      prefillWizardFromData(raw);
      console.log('✅ Pre-relleno desde BASICO completado');
    } catch (err) {
      console.warn('No se pudo pre-cargar datos del programa básico, iniciando en blanco:', err);
    }

    // Fallback: asegurar que los campos de identificación estén rellenos desde el curso
    if (!nombre_asignatura) nombre_asignatura = curso?.asignatura_nombre ?? '';
    if (!codigo) codigo = String(curso?.cod_curso ?? '');

    // Cargar secciones y actividades del curso para los selectores del wizard
    loadCursoSecciones();
    loadCursoActividades();
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────
  function handleClose() {
    resetAll();
    onClose();
  }

  function resetAll() {
    mode = 'view';
    programaData = null;
    editedSections = [];
    isSaving = false;
    isEditMode = false;
    viewError = '';
    step = 1;
    isGenerating = false;
    errorMsg = '';

    // Reset all wizard fields
    nombre_asignatura = '';
    codigo = '';
    creditos_sct = '';
    horas_catedra = '';
    horas_taller = '';
    horas_laboratorio = '';
    categoria = 'Obligatorio';

    presentacion = '';
    estandares = '';

    competencias_especificas = [{ titulo: '' }];
    competencias_genericas = [{ titulo: '' }];
    subcompetencias = [];

    items_evaluacion = [{ titulo: '', descripcion: '' }];

    unidades = [{ numero: 1, titulo: '', contenidos: '', resultados_aprendizaje: [{ resultado: '' }] }];

    metodologia = '';
    evaluacion = '';

    recursos = [
      { descripcion: '', tipo: 'Libro', ubicacion: '' },
      { descripcion: '', tipo: 'Libro', ubicacion: '' },
    ];

    ponderacion_optativa = '0';
    componentes = [{ componente: '', porcentaje: 0, genera_acta: false, aprobacion_obligatoria: false, asistencia_obligatoria: 0 }];
    normativa_curso = '';
  }

  function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') handleClose();
  }

  // ── Save edited sections (creates new version) ───────────────────────────────
  async function handleSaveEdits() {
    if (!curso) return;
    isSaving = true;
    viewError = '';
    try {
      const payload = editedSections.map((s, i) => ({
        nombre_seccion: s.nombre_seccion,
        numeral_romano: s.numeral_romano ?? '',
        orden: i + 1,
        contenidos: s.contenidos_programa.map((c, j) => ({
          texto_contenido: c.texto_contenido ?? '',
          orden_item: j + 1,
        })),
      }));

      const { data } = await axios.post(
        `${getBasePath()}/${curso.id_curso}/programa`,
        {
          secciones: payload,
        },
        { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
      );

      if (typeof data === 'string' && data.trimStart().startsWith('<!DOCTYPE')) {
        throw new Error('El servidor respondió con HTML (error de validación o sesión expirada). Revisa los datos e intenta de nuevo.');
      }

      isSaving = false;
      resetAll();
      onSuccess(data.programa as Programa);
    } catch (err) {
      isSaving = false;
      viewError = err instanceof AxiosError ? (err.response?.data?.error ?? err.message) : 'Error guardando los cambios.';
    }
  }

  // ── Approve (mark as definitivo) ─────────────────────────────────────────────
  async function handleApprove() {
    if (!curso) return;
    isApproving = true;
    viewError = '';
    try {
      const basePath = getBasePath();
      await axios.put(`${basePath}/${curso.id_curso}/programa/aprobar`);
      // Flip local state so the badge updates instantly
      if (programaData) {
        programaData = { ...programaData, estado: 'APROBADO' };
      }
    } catch (err) {
      viewError = err instanceof AxiosError ? (err.response?.data?.error ?? err.message) : 'Error al aprobar el programa.';
    } finally {
      isApproving = false;
    }
  }

  // ── Regenerate from scratch (admin only) ────────────────────────────────────
  function handleRegenerateFromScratch() {
    resetAll();
    mode = 'wizard';
    step = 1;
    initializeWizard();
  }

  // ── Wizard helpers ───────────────────────────────────────────────────────────
  function addUnidad() {
    unidades = [...unidades, { numero: unidades.length + 1, titulo: '', contenidos: '', resultados_aprendizaje: [{ resultado: '' }] }];
  }

  function removeUnidad(i: number) {
    unidades = unidades.filter((_, idx) => idx !== i);
  }

  function toRoman(n: number): string {
    const map: [number, string][] = [
      [10, 'X'],
      [9, 'IX'],
      [5, 'V'],
      [4, 'IV'],
      [1, 'I'],
    ];
    let result = '';
    for (const [val, sym] of map) {
      while (n >= val) {
        result += sym;
        n -= val;
      }
    }
    return result;
  }

  function buildSecciones() {
    // Determinar Sección VII basada en el tipo
    const seccionVII =
      selectedSyllabusType === 'simplified' || selectedSyllabusType === 'combined'
        ? {
            contenido: {
              actividades: actividades
                .filter((a) => a.nombre.trim() || a.id_actividad)
                .map((a) => ({
                  id_actividad: a.id_actividad || null,
                  nombre: a.nombre.trim() || 'Actividad',
                  tipo: a.tipo,
                  nombre_unidad: a.nombre_unidad?.trim() || '',
                })),
            },
          }
        : {
            contenido: {
              resultados_aprendizaje: {
                titulo: 'Resultados de Aprendizaje',
                items: consolidatedResults
                  .filter((r) => r.resultado.trim())
                  .map((r) => ({
                    resultado: r.resultado.trim(),
                  })),
              },
              metodologia: {
                titulo: 'Metodología',
                tipo_estrategia: metodologia.trim(),
              },
              evaluacion: {
                titulo: 'Evaluación',
                tipo_evaluacion: evaluacion.trim(),
              },
            },
          };

    const baseSecciones = {
      I: {
        contenido: {
          nombre_asignatura: nombre_asignatura.trim(),
          codigo: codigo.trim(),
          creditos_sct: parseInt(creditos_sct) || 0,
          horas: {
            catedra: parseInt(horas_catedra) || 0,
            taller: parseInt(horas_taller) || 0,
            laboratorio: parseInt(horas_laboratorio) || 0,
          },
          categoria: categoria,
        },
      },
      II: {
        contenido: {
          texto: presentacion.trim(),
        },
      },
      III: {
        contenido: {
          texto: estandares.trim(),
        },
      },
      IV: {
        contenido: {
          competencias_especificas: competencias_especificas
            .filter((c) => c.titulo.trim())
            .map((c) => ({
              titulo: c.titulo.trim(),
            })),
          competencias_genericas: competencias_genericas
            .filter((c) => c.titulo.trim())
            .map((c) => ({
              titulo: c.titulo.trim(),
            })),
          subcompetencias: subcompetencias
            .filter((s) => s.titulo.trim())
            .map((s) => ({
              titulo: s.titulo.trim(),
            })),
        },
      },
      V: {
        contenido: {
          items: items_evaluacion
            .filter((i) => i.titulo.trim())
            .map((i, idx) => ({
              titulo: i.titulo.trim(),
              descripcion: i.descripcion.trim() || null,
            })),
        },
      },
      VI: {
        contenido: {
          unidades: unidades
            .filter((u) => u.titulo.trim())
            .map((u) => ({
              numero: u.numero,
              titulo: u.titulo.trim(),
              contenidos_items: u.contenidos.trim() ? [{ item: u.contenidos.trim() }] : [],
              resultados_aprendizaje: u.resultados_aprendizaje
                .filter((r) => r.resultado.trim())
                .map((r) => ({
                  resultado: r.resultado.trim(),
                })),
            })),
        },
      },
      VII: seccionVII,
      VIII: {
        contenido: {
          recursos: recursos
            .filter((r) => r.descripcion.trim())
            .map((r) => ({
              descripcion: r.descripcion.trim(),
              tipo: r.tipo,
              ubicacion: r.ubicacion.trim() || null,
            })),
        },
      },
      IX: {
        contenido: {
          descripcion: normativa_curso.trim(),
          ponderacion_optativa: {
            porcentaje: parseFloat(ponderacion_optativa) || 0,
          },
          tabla_componentes: componentes
            .filter((c) => c.componente.trim())
            .map((c) => ({
              componente: c.componente.trim(),
              porcentaje: c.porcentaje || 0,
              genera_acta: c.genera_acta || false,
              aprobacion_obligatoria: c.aprobacion_obligatoria || false,
              asistencia_obligatoria: c.asistencia_obligatoria || 0,
            })),
        },
      },
    };

    // For BASICO types, only return sections I, II, VI, VII, VIII (skip III, IV, V, IX)
    if (selectedSyllabusType === 'simplified' || selectedSyllabusType === 'combined') {
      const { III: _, IV: __, V: ___, IX: ____, ...basicSecciones } = baseSecciones;
      console.log('📋 buildSecciones - BASICO mode, returning sections:', Object.keys(basicSecciones));
      return basicSecciones;
    }

    console.log('📋 buildSecciones - COMPLETO mode, returning all sections:', Object.keys(baseSecciones));
    return baseSecciones;
  }

  async function handleGenerate() {
    if (!curso) return;
    isGenerating = true;
    errorMsg = '';
    try {
      // Separar actividades nuevas (id_actividad = null) de las existentes
      const actividadesNuevas = actividades.filter((a) => !a.id_actividad && a.nombre.trim());
      const actividadesExistentes = actividades.filter((a) => a.id_actividad);

      const payload = {
        secciones: buildSecciones(),
        syllabus_type: selectedSyllabusType ?? 'complete',
        // Enviar actividades nuevas para crear después
        actividades_to_create: actividadesNuevas.map((a) => ({
          nombre: a.nombre.trim(),
          tipo_actividad: 1, // Tipo por defecto
          tipo_entrega: 'online',
          es_grupal: false,
          max_integrantes: 1,
          nombre_unidad: a.nombre_unidad?.trim() || '',
        })),
      };
      console.log('📤 Enviando payload:', JSON.stringify(payload, null, 2));

      const basePath = getBasePath();
      const { data } = await axios.post(`${basePath}/${curso.id_curso}/programa`, payload, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      console.log('✅ Respuesta exitosa:', data);

      if (typeof data === 'string' && data.trimStart().startsWith('<!DOCTYPE')) {
        throw new Error('El servidor respondió con HTML (error de validación o sesión expirada). Revisa los datos e intenta de nuevo.');
      }
      isGenerating = false;
      resetAll();
      onSuccess(data.programa as Programa);
    } catch (err) {
      isGenerating = false;
      if (err instanceof AxiosError) {
        // Log completo de error para debugging
        console.error('❌ Error completo:', {
          status: err.response?.status,
          data: err.response?.data,
          message: err.message,
        });

        // Mostrar errores de validación si existen
        if (err.response?.status === 422 && err.response?.data?.errors) {
          const errors = err.response.data.errors;
          const errorList = Object.entries(errors)
            .map(([key, msgs]) => `${key}: ${Array.isArray(msgs) ? msgs[0] : msgs}`)
            .join('\n');
          errorMsg = errorList;
        } else {
          errorMsg = err.response?.data?.error ?? err.message;
        }
      } else {
        errorMsg = err instanceof Error ? err.message : 'Error desconocido al generar el programa.';
      }
    }
  }

  // Validation for each step
  let step1Valid = $derived(
    nombre_asignatura.trim().length > 0 && codigo.trim().length > 0 && creditos_sct.trim().length > 0 && horas_catedra.trim().length > 0,
  );
  let step2Valid = $derived(
    selectedSyllabusType === 'simplified' || selectedSyllabusType === 'combined'
      ? true // Presentación es opcional en BASICO
      : presentacion.trim().length > 0, // Requerida en COMPLETO
  );
  let step3Valid = $derived(estandares.trim().length > 0);
  let step4Valid = $derived(
    competencias_especificas.some((c) => c.titulo.trim().length > 0) && competencias_genericas.some((c) => c.titulo.trim().length > 0),
  );
  let step5Valid = $derived(items_evaluacion.some((i) => i.titulo.trim().length > 0));
  let step6Valid = $derived(
    selectedSyllabusType === 'simplified' || selectedSyllabusType === 'combined'
      ? true // Unidades es opcional en BASICO
      : unidades.some((u) => u.titulo.trim().length > 0), // Requerida en COMPLETO
  );
  // Step 7 es Actividades (opcional) para BASICO, y Planificación (requerida) para COMPLETO
  let step7Valid = $derived(
    selectedSyllabusType === 'simplified' || selectedSyllabusType === 'combined'
      ? true // Actividades es completamente opcional en BASICO
      : consolidatedResults.length > 0 && metodologia.trim().length > 0 && evaluacion.trim().length > 0,
  );
  let step8Valid = $derived(
    selectedSyllabusType === 'simplified' || selectedSyllabusType === 'combined'
      ? true // Recursos es opcional en BASICO
      : recursos.filter((r) => r.descripcion.trim().length > 0).length >= 2, // Requerido en COMPLETO
  );
  let step9Valid = $derived(normativa_curso.trim().length > 0 && componentes.some((c) => c.componente.trim().length > 0));

  // Helper: get the first content text of a section (for display)
  function firstContent(sec: SeccionPrograma): string {
    return sec.contenidos_programa?.[0]?.texto_contenido ?? '';
  }

  // Helper: join all content texts
  function joinContents(sec: SeccionPrograma): string {
    return sec.contenidos_programa
      .map((c) => c.texto_contenido ?? '')
      .filter(Boolean)
      .join('\n');
  }

  function updateSectionContent(secIdx: number, text: string) {
    const sec = editedSections[secIdx];
    if (sec.contenidos_programa.length === 0) {
      editedSections[secIdx] = {
        ...sec,
        contenidos_programa: [{ texto_contenido: text, orden_item: 1 }],
      };
    } else {
      // Update the first / only content item; preserve any extra items
      editedSections[secIdx] = {
        ...sec,
        contenidos_programa: sec.contenidos_programa.map((c, i) => (i === 0 ? { ...c, texto_contenido: text } : c)),
      };
    }
  }
</script>

<svelte:window onkeydown={handleKeydown} />

{#if isOpen && curso}
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    onclick={handleClose}
    onkeydown={handleKeydown}
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    aria-labelledby="syllabus-modal-title"
  >
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col" onclick={(e) => e.stopPropagation()}>
      <!-- ── Header ──────────────────────────────────────────────── -->
      <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-100 flex-shrink-0">
        <div class="flex items-center justify-center w-9 h-9 bg-blue-50 rounded-lg text-blue-600 flex-shrink-0">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></svg
          >
        </div>
        <div class="flex-1 min-w-0">
          <h2 id="syllabus-modal-title" class="text-lg font-bold text-slate-900">
            {isEditMode ? 'Programa de Cátedra' : 'Nuevo Programa de Cátedra'}
          </h2>
          <p class="text-sm text-slate-500 truncate">{curso.asignatura_nombre ?? `Curso ${curso.cod_curso}`}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          {#if isEditMode && programaData}
            {#if programaData.estado === 'BORRADOR'}
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="10"
                  height="10"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg
                >
                Borrador v{programaData.version_programa}
              </span>
            {:else}
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="10"
                  height="10"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
                >
                Aprobado v{programaData.version_programa}
              </span>
            {/if}
          {/if}
          <button type="button" onclick={handleClose} title="Cerrar" class="p-1 hover:bg-slate-100 rounded transition-colors">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
            >
          </button>
        </div>
      </div>

      <!-- PROGRAM WIZARD (create / edit) -->
      {#if loadingPrograma}
        <div class="flex-1 flex flex-col items-center justify-center py-12 gap-2">
          <div class="w-4 h-4 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin"></div>
          <span class="text-sm text-slate-500">Cargando programa...</span>
        </div>
      {:else if viewError}
        <div class="flex-1 overflow-y-auto px-6 py-6">
          <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
            {viewError}
          </div>
          <button
            onclick={loadPrograma}
            class="mt-3 px-4 py-2 text-slate-700 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors"
          >Reintentar</button>
        </div>
      {:else}
        <!-- Step indicator - Dynamic carousel showing only visible steps -->
        <div class="px-4 py-3 flex-shrink-0 border-b border-slate-100 bg-slate-50">
          <p class="text-xs text-slate-600 mb-2">
            📍 Modo: <strong
              >{selectedSyllabusType === 'simplified'
                ? 'Simplificado (5 secciones)'
                : selectedSyllabusType === 'combined'
                  ? 'Combinado'
                  : 'Completo (9 secciones)'}</strong
            >
            — Paso {step} de {STEPS.length}
          </p>
          <ol class="flex items-center justify-center list-none m-0 p-0 gap-2">
            <!-- Botón anterior si hay pasos previos -->
            {#if STEPS.findIndex((s) => s.id === step) > 0}
              <button
                type="button"
                onclick={() => {
                  const idx = STEPS.findIndex((s) => s.id === step);
                  if (idx > 0) step = STEPS[idx - 1].id as 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;
                }}
                class="px-2 py-1 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-200 rounded transition-all"
              >
                ← Anterior
              </button>
            {/if}

            <!-- Mostrar solo 3 pasos visibles: anterior, actual, siguiente -->
            {#each STEPS as s, i}
              {@const currentIdx = STEPS.findIndex((st) => st.id === step)}
              {@const isComplete = i < currentIdx}
              {@const isActive = s.id === step}
              {@const relPos = i - currentIdx}
              {@const isVisible = Math.abs(relPos) <= 1}

              {#if isVisible}
                <li class="flex items-center gap-0.5">
                  <button
                    type="button"
                    title={`${s.label}`}
                    onclick={() => {
                      if (isComplete || isActive) {
                        console.log(`🔄 Saltando al paso ${s.id}`);
                        step = s.id as 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;
                      }
                    }}
                    disabled={!isComplete && !isActive}
                    class="disabled:cursor-not-allowed transition-all"
                  >
                    <div
                      class="{isActive
                        ? 'bg-blue-600 text-white ring-2 ring-blue-300 w-8 h-8'
                        : isComplete
                          ? 'bg-blue-500 text-white w-6 h-6'
                          : 'bg-slate-300 text-slate-600 w-6 h-6'} rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 transition-all"
                    >
                      {#if isComplete}<span class="text-sm">✓</span>{:else}<span>{s.id}</span>{/if}
                    </div>
                  </button>
                  {#if isVisible && relPos < 1}
                    <div class="h-0.5 w-3 bg-slate-300 flex-shrink-0 mx-1"></div>
                  {/if}
                </li>
              {/if}
            {/each}

            <!-- Botón siguiente si hay pasos siguientes -->
            {#if STEPS.findIndex((s) => s.id === step) < STEPS.length - 1}
              <button
                type="button"
                onclick={() => {
                  const idx = STEPS.findIndex((s) => s.id === step);
                  if (idx < STEPS.length - 1) {
                    step = STEPS[idx + 1].id as 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;
                  }
                }}
                class="px-2 py-1 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-200 rounded transition-all"
              >
                Siguiente →
              </button>
            {/if}
          </ol>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-6">
          {#if isEditMode && programaData?.estado === 'APROBADO'}
            <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 flex gap-2 items-start mb-4">
              <svg class="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
              <div>
                <p class="font-medium text-sm text-amber-900">Programa Aprobado</p>
                <p class="text-xs text-amber-800 mt-0.5">Este programa ya fue aprobado. Guardar generará una nueva versión en borrador.</p>
              </div>
            </div>
          {/if}
          <ProgramaWizardSteps
            {step}
            bind:codigo
            bind:creditos_sct
            bind:horas_catedra
            bind:horas_taller
            bind:horas_laboratorio
            bind:categoria
            bind:presentacion
            bind:estandares
            bind:competencias_especificas
            bind:competencias_genericas
            bind:subcompetencias
            bind:items_evaluacion
            bind:actividades
            {existingActividades}
            bind:unidades
            resultados_aprendizaje={consolidatedResults}
            bind:metodologia
            bind:evaluacion
            bind:recursos
            bind:normativa_curso
            bind:ponderacion_optativa
            bind:componentes
            {curso}
            syllabusType={selectedSyllabusType}
          />

          {#if errorMsg}
            <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm mt-4" role="alert">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="15"
                height="15"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg
              >
              {errorMsg}
            </div>
          {/if}
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-6 py-3.5 border-t border-slate-100 flex-shrink-0 gap-3">
          <div class="flex items-center gap-2">
            {#if isEditMode && canApprove}
              <button
                type="button"
                onclick={handleRegenerateFromScratch}
                disabled={isGenerating}
                class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 disabled:opacity-40 transition-colors"
              >
                Regenerar...
              </button>
            {/if}
            <button
              type="button"
              onclick={() => {
                const idx = STEPS.findIndex((s) => s.id === step);
                if (idx > 0) {
                  step = STEPS[idx - 1].id as 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;
                } else {
                  handleClose();
                }
              }}
              disabled={isGenerating}
              class="px-3 py-2 rounded-lg text-sm font-medium bg-white border border-gray-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              {#if STEPS.findIndex((s) => s.id === step) === 0}Cancelar{:else}← Atrás{/if}
            </button>
          </div>

          <div class="flex items-center gap-2">
            {#if canApprove && isEditMode && programaData?.estado === 'BORRADOR'}
              <button
                type="button"
                onclick={handleApprove}
                disabled={isApproving || isGenerating}
                class="px-3 py-2 text-green-700 bg-white border border-green-300 rounded-lg text-sm font-medium hover:bg-green-50 transition-colors disabled:opacity-50 inline-flex items-center gap-2"
              >
                {#if isApproving}
                  <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-green-600 rounded-full animate-spin"></span>
                  Aprobando...
                {:else}
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
                  Aprobar
                {/if}
              </button>
            {/if}

            <!-- Show Next button only if not at the last step -->
            {#if STEPS.findIndex((s) => s.id === step) < STEPS.length - 1}
              <button
                type="button"
                onclick={() => {
                  const idx = STEPS.findIndex((s) => s.id === step);
                  if (idx < STEPS.length - 1) {
                    step = STEPS[idx + 1].id as 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;
                  }
                }}
                disabled={(step === 1 && !step1Valid) ||
                  (step === 2 && !step2Valid) ||
                  (step === 3 && !step3Valid) ||
                  (step === 4 && !step4Valid) ||
                  (step === 5 && !step5Valid) ||
                  (step === 6 && !step6Valid) ||
                  (step === 7 && !step7Valid) ||
                  (step === 8 && !step8Valid) ||
                  (step === 9 && !step9Valid)}
                class="px-3 py-2 rounded-lg text-sm font-medium bg-blue-600 border-none text-white hover:bg-blue-700 disabled:bg-blue-300 disabled:cursor-not-allowed transition-colors"
              >
                Siguiente →
              </button>
            {:else}
              <!-- Show Save button when at the last step -->
              <button
                type="button"
                onclick={handleGenerate}
                disabled={isGenerating || !isEditable}
                class="px-3 py-2 rounded-lg text-sm font-medium bg-blue-600 border-none text-white hover:bg-blue-700 disabled:bg-blue-300 disabled:cursor-not-allowed transition-colors inline-flex items-center gap-2"
              >
                {#if isGenerating}
                  <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-white rounded-full animate-spin"></span>
                {/if}
                {isEditMode ? 'Guardar cambios' : 'Generar Programa'}
              </button>
            {/if}
          </div>
        </div>
      {/if}
    </div>
  </div>
{/if}
