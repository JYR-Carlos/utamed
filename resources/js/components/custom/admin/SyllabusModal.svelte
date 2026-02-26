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
  }

  let { isOpen = $bindable(), curso, onClose, onSuccess }: Props = $props();

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
  // Solo editable si: modo view Y estado NO es APROBADO
  let isEditable = $derived.by(() => {
    if (mode !== 'view') return true; // En modo wizard siempre es editable
    if (!programaData) return true; // Si no hay datos, permitir
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

  const STEPS = [
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

  // Detectar contexto (docente o admin) desde la URL
  const getBasePath = () => {
    const currentPath = window.location.pathname;
    return currentPath.includes('/docente/') ? '/docente/cursos' : '/admin/cursos';
  };

  // ── Init on mount: decide mode and load data ─────────────────────────────────
  onMount(() => {
    console.log('🔍 SyllabusModal onMount - curso.has_programa:', curso?.has_programa);
    if (curso?.has_programa) {
      mode = 'view';
      console.log('📖 Modo VIEW - cargando programa existente');
      loadPrograma();
    } else {
      mode = 'wizard';
      console.log('✨ Modo WIZARD - inicializando nuevo programa');
      initializeWizard();
    }
  });

  function initializeWizard() {
    // Cargar datos del curso y asignatura en el wizard
    nombre_asignatura = curso?.asignatura_nombre ?? '';

    // Intentar obtener código desde diferentes fuentes
    codigo = String(curso?.cod_curso ?? '');

    // Cargar datos de créditos y horas desde asignacionPlan.asignatura si está disponible
    const asignatura = (curso as any)?.asignacionPlan?.asignatura;

    if (asignatura) {
      creditos_sct = String(asignatura.creditos_sct ?? '');
      horas_catedra = String(asignatura.horas_catedra ?? '');
      horas_taller = String(asignatura.horas_taller ?? '');
      horas_laboratorio = String(asignatura.horas_laboratorio ?? '');
    } else {
      // Fallback a campos directos en curso (si existen)
      creditos_sct = String((curso as any)?.creditos_sct ?? '');
      horas_catedra = String((curso as any)?.horas_catedra ?? '');
      horas_taller = String((curso as any)?.horas_taller ?? '');
      horas_laboratorio = String((curso as any)?.horas_laboratorio ?? '');
    }

    // Cargar secciones del curso para Sección IX
    loadCursoSecciones();
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

  async function loadPrograma() {
    if (!curso) return;
    loadingPrograma = true;
    viewError = '';
    try {
      // Usar endpoint JSON separado para obtener datos del programa
      const basePath = getBasePath();
      const { data } = await axios.get(`${basePath}/${curso.id_curso}/programa/json`);
      programaData = data.programa;
      // Deep-clone to a mutable editing copy
      editedSections = (data.programa?.secciones ?? []).map((s: SeccionPrograma) => ({
        ...s,
        contenidos_programa: (Array.isArray(s.contenidos_programa) ? s.contenidos_programa : []).map((c: any) => ({ ...c })),
      }));
    } catch (err) {
      console.error('Error cargando programa JSON:', err);
      viewError = 'Error cargando el programa. Intente de nuevo.';
    } finally {
      loadingPrograma = false;
    }
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

      const { data } = await axios.post(`${getBasePath()}/${curso.id_curso}/programa`, {
        secciones: payload,
      });

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
    return {
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
      VII: {
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
      },
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
  }

  async function handleGenerate() {
    if (!curso) return;
    isGenerating = true;
    errorMsg = '';
    try {
      const payload = {
        secciones: buildSecciones(),
      };
      console.log('📤 Enviando payload:', JSON.stringify(payload, null, 2));

      const basePath = getBasePath();
      const { data } = await axios.post(`${basePath}/${curso.id_curso}/programa`, payload);
      console.log('✅ Respuesta exitosa:', data);
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
        errorMsg = 'Error desconocido al generar el programa.';
      }
    }
  }

  // Validation for each step
  let step1Valid = $derived(
    nombre_asignatura.trim().length > 0 && codigo.trim().length > 0 && creditos_sct.trim().length > 0 && horas_catedra.trim().length > 0,
  );
  let step2Valid = $derived(presentacion.trim().length > 0);
  let step3Valid = $derived(estandares.trim().length > 0);
  let step4Valid = $derived(
    competencias_especificas.some((c) => c.titulo.trim().length > 0) && competencias_genericas.some((c) => c.titulo.trim().length > 0),
  );
  let step5Valid = $derived(items_evaluacion.some((i) => i.titulo.trim().length > 0));
  let step6Valid = $derived(unidades.some((u) => u.titulo.trim().length > 0));
  let step7Valid = $derived(consolidatedResults.length > 0 && metodologia.trim().length > 0 && evaluacion.trim().length > 0);
  let step8Valid = $derived(recursos.filter((r) => r.descripcion.trim().length > 0).length >= 2);
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
            {mode === 'view' ? 'Programa de Cátedra' : 'Crear Programa'}
          </h2>
          <p class="text-sm text-slate-500 truncate">{curso.asignatura_nombre ?? `Curso ${curso.cod_curso}`}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          {#if mode === 'view' && programaData}
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

      <!-- VIEW / EDIT MODE (existing programa) -->
      {#if mode === 'view'}
        <div class="flex-1 overflow-y-auto px-6 py-6">
          {#if loadingPrograma}
            <div class="flex flex-col items-center justify-center py-12 gap-2">
              <div class="w-4 h-4 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin"></div>
              <span class="text-sm text-slate-500">Cargando programa...</span>
            </div>
          {:else if viewError}
            <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
              {viewError}
            </div>
            <button
              onclick={loadPrograma}
              class="mt-3 px-4 py-2 text-slate-700 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors"
              >Reintentar</button
            >
          {:else if editedSections.length === 0}
            <div class="text-center py-8 px-4">
              <p class="text-slate-600 mb-4">No se encontraron secciones en este programa.</p>
              <button
                type="button"
                onclick={() => {
                  mode = 'wizard';
                  step = 1;
                }}
                class="px-4 py-2 text-slate-700 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors"
              >
                Regenerar programa
              </button>
            </div>
          {:else}
            <!-- Alert if program is approved and cannot be edited -->
            {#if programaData && programaData.estado === 'APROBADO'}
              <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 mb-4 flex gap-3">
                <div class="flex-shrink-0">
                  <svg class="w-5 h-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                    <path
                      fill-rule="evenodd"
                      d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-amber-900">Programa Aprobado</p>
                  <p class="text-sm text-amber-800 mt-1">
                    Este programa ha sido aprobado y no puede ser editado. Para realizar cambios, debe regenerarse desde cero.
                  </p>
                </div>
              </div>
            {/if}
            <!-- Editable sections -->
            <div class="space-y-3">
              {#each editedSections as sec, i}
                <div class="rounded-lg border border-slate-200 p-4 space-y-2">
                  <div class="flex items-center gap-2 mb-3">
                    {#if sec.numeral_romano}
                      <span class="text-sm font-bold text-slate-400">{sec.numeral_romano}.</span>
                    {/if}
                    <span class="text-sm font-semibold text-slate-700">{sec.nombre_seccion}</span>
                  </div>
                  <textarea
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                    rows="3"
                    placeholder="Sin contenido — escribe aquí para agregar texto..."
                    value={joinContents(sec)}
                    oninput={(e) => updateSectionContent(i, (e.target as HTMLTextAreaElement).value)}
                    disabled={!isEditable}
                  ></textarea>
                </div>
              {/each}
            </div>
          {/if}
        </div>
        <!-- View mode footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100">
          <button
            type="button"
            onclick={() => {
              mode = 'wizard';
              step = 1;
            }}
            class="px-4 py-2 text-slate-700 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors disabled:opacity-50"
            disabled={isSaving || isApproving}
          >
            Regenerar desde cero
          </button>
          <div class="flex gap-2">
            {#if programaData?.estado === 'BORRADOR'}
              <button
                type="button"
                onclick={handleApprove}
                disabled={isApproving || isSaving}
                class="px-4 py-2 text-green-700 bg-white border border-green-300 rounded-lg text-sm font-medium hover:bg-green-50 transition-colors disabled:opacity-50 inline-flex items-center gap-2"
              >
                {#if isApproving}
                  <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-green-600 rounded-full animate-spin"></span>
                  Aprobando...
                {:else}
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
                  >
                  Aprobar Programa
                {/if}
              </button>
            {/if}
            <button
              type="button"
              onclick={handleSaveEdits}
              disabled={isSaving || loadingPrograma || isApproving || !isEditable}
              class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors disabled:bg-blue-300 inline-flex items-center gap-2"
            >
              {#if isSaving}
                <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-white rounded-full animate-spin"></span>
                Guardando...
              {:else}
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
                >
                Guardar cambios
              {/if}
            </button>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- WIZARD MODE (create from scratch)                          -->
        <!-- ══════════════════════════════════════════════════════════ -->
      {:else}
        <!-- Step indicator -->
        <div class="px-6 py-4 flex-shrink-0 border-b border-slate-100">
          <ol class="flex items-center list-none m-0 p-0 gap-0">
            {#each STEPS as s, i}
              {@const isComplete = step > s.id}
              {@const isActive = step === s.id}
              <li class="flex items-center flex-1">
                <div class="flex items-center gap-2">
                  <div
                    class="{isComplete
                      ? 'bg-blue-600 text-white'
                      : isActive
                        ? 'bg-blue-600 text-white ring-4 ring-blue-100'
                        : 'bg-slate-100 text-slate-400'} w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 transition-all"
                  >
                    {#if isComplete}<span>✓</span>{:else}<span>{s.id}</span>{/if}
                  </div>
                  <span class="text-lg">{s.icon}</span>
                </div>
                <span class="text-xs {isActive ? 'text-blue-600 font-semibold' : 'text-slate-500'} ml-2">{s.label}</span>
                {#if i < STEPS.length - 1}
                  <div class="{isComplete ? 'bg-blue-600' : 'bg-slate-200'} h-0.5 mx-2 flex-1 transition-colors"></div>
                {/if}
              </li>
            {/each}
          </ol>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-6">
          <ProgramaWizardSteps
            {step}
            bind:nombre_asignatura
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
            bind:unidades
            resultados_aprendizaje={consolidatedResults}
            bind:metodologia
            bind:evaluacion
            bind:recursos
            bind:normativa_curso
            bind:ponderacion_optativa
            bind:componentes
            {curso}
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
          <button
            type="button"
            onclick={() => {
              if (step === 1 && curso?.has_programa) {
                mode = 'view';
              } else {
                step = Math.max(1, step - 1) as 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;
              }
            }}
            disabled={isGenerating}
            class="px-3 py-2 rounded-lg text-sm font-medium bg-white border border-gray-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            ← {step === 1 && curso?.has_programa ? 'Ver existente' : 'Atrás'}
          </button>
          {#if step < 9}
            <button
              type="button"
              onclick={() => (step = Math.min(9, step + 1) as 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9)}
              disabled={(step === 1 && !step1Valid) ||
                (step === 2 && !step2Valid) ||
                (step === 3 && !step3Valid) ||
                (step === 4 && !step4Valid) ||
                (step === 5 && !step5Valid) ||
                (step === 6 && !step6Valid) ||
                (step === 7 && !step7Valid) ||
                (step === 8 && !step8Valid)}
              class="px-3 py-2 rounded-lg text-sm font-medium bg-blue-600 border-none text-white hover:bg-blue-700 disabled:bg-blue-300 disabled:cursor-not-allowed transition-colors"
            >
              Siguiente →
            </button>
          {:else}
            <button
              type="button"
              onclick={handleGenerate}
              disabled={isGenerating || !step9Valid}
              class="px-3 py-2 rounded-lg text-sm font-medium bg-blue-600 border-none text-white hover:bg-blue-700 disabled:bg-blue-300 disabled:cursor-not-allowed transition-colors inline-flex items-center gap-2"
            >
              {#if isGenerating}
                <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-white rounded-full animate-spin"></span>
              {/if}
              Generar Programa
            </button>
          {/if}
        </div>
      {/if}
    </div>
  </div>
{/if}
