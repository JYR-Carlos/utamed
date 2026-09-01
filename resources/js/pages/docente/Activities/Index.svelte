<script lang="ts">
  /**
   * Activities/Index — Gestión de una actividad por parte del docente.
   *
   * Reúne en una sola pantalla las operaciones sobre los grupos de una actividad:
   * creación/eliminación de grupos, alta/baja de integrantes, notas grupales e
   * individuales (ajuste de décimas + recálculo), revisión de entregas (archivos),
   * agenda/mensajería del grupo y evaluación por rúbrica.
   *
   * Props de Inertia (ver interface `Props`):
   *   curso, actividad, grupos, rubrica, rubrica_id, estudiantesInscritos,
   *   interaccionesGrupo.
   *
   * Endpoints que usa (todos bajo /docente/cursos/{curso}/actividades/{actividad}):
   *   POST   …/grupos-create                                   crear grupo
   *   GET    …/grupos-origen/{origen}                          grupos de otra actividad, para reutilizar (en ReutilizarGruposModal)
   *   POST   …/grupos-copy                                     copiar grupos elegidos desde otra actividad
   *   DELETE …/grupos-delete/{grupo}                           eliminar grupo
   *   POST   …/grupos/{grupo}/estudiante                       agregar integrante
   *   DELETE …/grupos/{grupo}/estudiantes/{estudiante}         quitar integrante
   *   PUT    …/grupos/{grupo}/integrantes/{asignado}           guardar décimas
   *   POST   …/grupos/{grupo}/recalcular-notas                 recalcular notas
   *   PATCH  …/grupos/{grupo}                                  actualizar holgura personal
   *   GET    …/grupos/{grupo}/entregas                         entregas (lazy, en EntregasModal)
   *   GET    …/grupos/{grupo}/entregas/{agenda}/descargar      descargar archivo
   *   POST   …/grupos/{grupo}/evaluacion                       registrar evaluación
   *   POST   /docente/cursos/{curso}/grupos/{grupo}/feedback   feedback de agenda
   *   reload only:['interaccionesGrupo'] con grupo_id          mensajes del grupo
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { Rubrica } from '@/types/rubrica';
  import { ChevronLeft, Plus, Users, Pencil, Copy } from 'lucide-svelte';
  import { ConfirmDialog } from '@/components/custom/common';
  import { formatFechaHora } from '@/utils/formatters';
  import AgendaDocente from './Agenda/AgendaDocente.svelte';
  import RubricaView from '../../student/Activities/Agenda/Rubrica.svelte';
  import RubricaEditor from './RubricaEditor.svelte';
  import MatrizEvaluacion from './MatrizEvaluacion.svelte';
  import GrupoCard from './components/GrupoCard.svelte';
  import NuevoGrupoModal from './components/NuevoGrupoModal.svelte';
  import ReutilizarGruposModal from './components/ReutilizarGruposModal.svelte';
  import EntregasModal from './components/EntregasModal.svelte';

  // ─── Tipos ────────────────────────────────────────────────────────────────
  type Interaccion = {
    id_interaccion: number;
    fecha_emision: string;
    tipo_interaccion: string;
    emisor: string;
    mensaje: string;
    es_de_docente: boolean;
    es_retroalimentacion: boolean;
    // Campos es_entrega y tiene_evaluacion retornados por el endpoint mensajesGrupo.
    es_entrega?: boolean;
    tiene_evaluacion?: boolean;
    adjunta_rubrica: boolean;
    rubrica?: Rubrica;
    puntaje_obtenido?: number;
    resultado?: Record<string, string> | null;
  };

  type IntegranteData = {
    id_estudiante: number;
    nombre_completo: string;
    id_asignado_actividad?: number;
    nota_individual?: number | null;
    diferencia_decimas?: number;
  };

  type GrupoData = {
    grupo: number;
    nota: number | null;
    estado_actividad_asignada: string | null;
    nro_dias_adicionales_para_bloqueo_personal: number;
    integrantes: IntegranteData[];
  };

  type EstudianteInscrito = {
    id_estudiante: number;
    nombre_completo: string;
  };

  type ActividadOrigen = {
    id_actividad: number;
    nombre: string;
    fecha_limite: string | null;
    cantidad_grupos: number;
  };

  // ─── Props desde Inertia ──────────────────────────────────────────────────
  interface Props {
    curso: { id_curso: number; nombre: string; cod_curso: string };
    actividad: {
      id_actividad: number;
      nombre: string;
      descripcion: string;
      fecha_limite: string;
      es_sumativa: boolean;
      trae_archivo: boolean;
      es_grupal: boolean;
      max_integrantes?: number;
      es_titular: boolean;
      nro_dias_adicionales_para_bloqueo: number;
    };
    grupos: GrupoData[];
    rubrica?: Rubrica | null;
    // rubrica_id se envía al endpoint storeEvaluacion sin una consulta extra desde el frontend.
    rubrica_id?: number | null;
    estudiantesInscritos?: EstudianteInscrito[];
    interaccionesGrupo?: Interaccion[];
    /** Otras actividades grupales del curso con grupos ya formados, para reutilizar. */
    actividadesConGrupos?: ActividadOrigen[];
    /** Prop compartida globalmente por HandleInertiaRequests (mensajes de la última acción). */
    flash?: { success?: string; error?: string };
  }

  let {
    curso,
    actividad,
    grupos,
    rubrica = null,
    rubrica_id = null,
    estudiantesInscritos = [],
    interaccionesGrupo = [],
    flash,
    actividadesConGrupos = [],
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Mis Cursos', href: '/docente/cursos' },
    { title: curso.nombre, href: `/docente/cursos/${curso.id_curso}/actividades` },
    { title: actividad.nombre, href: '#' },
  ]);

  // ─── Estado del UI ────────────────────────────────────────────────────────
  let grupoSeleccionado = $state<GrupoData | null>(null);
  let showAgendaModal = $state(false);
  let showRubricaModal = $state(false);
  let isLoadingInteracciones = $state(false);
  let errorInteracciones = $state<string | null>(null);

  // Estado del diálogo de confirmación (reemplaza window.confirm) — D-09.
  type ConfirmState = {
    open: boolean;
    title: string;
    body: string;
    confirmLabel: string;
    onConfirm: () => void;
  };
  let confirmState = $state<ConfirmState>({
    open: false,
    title: '',
    body: '',
    confirmLabel: 'Confirmar',
    onConfirm: () => {},
  });

  function pedirConfirmacion(opts: { title: string; body: string; confirmLabel?: string; onConfirm: () => void }) {
    confirmState = {
      open: true,
      title: opts.title,
      body: opts.body,
      confirmLabel: opts.confirmLabel ?? 'Confirmar',
      onConfirm: opts.onConfirm,
    };
  }

  function cerrarConfirmacion() {
    confirmState = { ...confirmState, open: false };
  }

  // ─── Gestión de grupos ────────────────────────────────────────────────────
  let showRubricaEditor = $state(false);

  // Cierra el editor si Inertia cambia la actividad (ej. window.history.back())
  $effect(() => {
    actividad.id_actividad;
    showRubricaEditor = false;
  });
  let showNuevoGrupo = $state(false);
  let seleccion = $state<Set<number>>(new Set());
  let nuevoGrupoLoading = $state(false);
  let nuevoGrupoError = $state<string | null>(null);

  // Reutilizar grupos de una actividad anterior
  let showReutilizarGrupos = $state(false);
  let actividadOrigenSeleccionada = $state<number | null>(null);
  let reutilizarGruposLoading = $state(false);
  let reutilizarGruposError = $state<string | null>(null);

  // Agregar estudiante a grupo existente
  let addingToGrupo = $state<number | null>(null);
  let addingEstudianteId = $state<number>(0);
  let addingLoading = $state(false);
  let addingError = $state<string | null>(null);

  // Estudiantes que aún no pertenecen a ningún grupo de esta actividad
  const estudiantesLibres = $derived(
    estudiantesInscritos.filter(
      (e) => !grupos.some((g) => g.integrantes.some((i) => i.id_estudiante === e.id_estudiante)),
    ),
  );

  // Estudiantes libres disponibles para agregar a un grupo existente
  function estudiantesParaGrupo(grupoId: number): EstudianteInscrito[] {
    return estudiantesInscritos.filter(
      (e) =>
        !grupos.some((g) => {
          if (g.grupo === grupoId) return false; // no contar el grupo destino
          return g.integrantes.some((i) => i.id_estudiante === e.id_estudiante);
        }) &&
        !grupos
          .find((g) => g.grupo === grupoId)
          ?.integrantes.some((i) => i.id_estudiante === e.id_estudiante),
    );
  }

  function toggleSeleccion(id: number) {
    const next = new Set(seleccion);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    seleccion = next;
  }

  function crearGrupo() {
    if (seleccion.size === 0) return;
    nuevoGrupoLoading = true;
    nuevoGrupoError = null;
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos-create`,
      { estudiantes: [...seleccion] },
      {
        onSuccess: () => {
          showNuevoGrupo = false;
          seleccion = new Set();
          nuevoGrupoLoading = false;
        },
        onError: (errors) => {
          nuevoGrupoError = (Object.values(errors)[0] as string) ?? 'Error al crear el grupo.';
          nuevoGrupoLoading = false;
        },
      },
    );
  }

  function copiarGrupos(grupoIds: number[]) {
    if (actividadOrigenSeleccionada === null || grupoIds.length === 0) return;
    reutilizarGruposLoading = true;
    reutilizarGruposError = null;
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos-copy`,
      { id_actividad_origen: actividadOrigenSeleccionada, grupos: grupoIds },
      {
        onSuccess: () => {
          showReutilizarGrupos = false;
          actividadOrigenSeleccionada = null;
          reutilizarGruposLoading = false;
        },
        onError: (errors) => {
          reutilizarGruposError =
            (Object.values(errors)[0] as string) ?? 'Error al copiar los grupos.';
          reutilizarGruposLoading = false;
        },
      },
    );
  }

  function eliminarGrupo(grupoId: number) {
    pedirConfirmacion({
      title: 'Eliminar grupo',
      body: '¿Eliminar este grupo y todos sus integrantes? Esta acción no se puede deshacer.',
      confirmLabel: 'Eliminar',
      onConfirm: () => {
        cerrarConfirmacion();
        router.delete(
          `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos-delete/${grupoId}`,
        );
      },
    });
  }

  function quitarEstudiante(grupoId: number, estudianteId: number) {
    pedirConfirmacion({
      title: 'Quitar estudiante',
      body: '¿Quitar a este estudiante del grupo?',
      confirmLabel: 'Quitar',
      onConfirm: () => {
        cerrarConfirmacion();
        router.delete(
          `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/estudiantes/${estudianteId}`,
        );
      },
    });
  }

  function agregarAGrupo(grupoId: number) {
    if (!addingEstudianteId) return;
    addingLoading = true;
    addingError = null;
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/estudiante`,
      { id_estudiante: addingEstudianteId },
      {
        onSuccess: () => {
          addingToGrupo = null;
          addingEstudianteId = 0;
          addingLoading = false;
        },
        onError: (errors) => {
          addingError = (Object.values(errors)[0] as string) ?? 'Error al agregar estudiante.';
          addingLoading = false;
        },
      },
    );
  }

  // ─── Notas individuales (décimas) ─────────────────────────────────────────
  // La nota individual se deriva de la nota grupal + un ajuste decimal por
  // estudiante (diferencia_decimas, numeric(2,1): el delta real, ej. 0.3 = +tres
  // décimas), con tope 1.0–7.0. El backend recalcula y persiste nota_individual.
  let savingDecimas = $state<number | null>(null);

  function ajustarDecimas(grupoId: number, integrante: IntegranteData, delta: number) {
    if (integrante.id_asignado_actividad == null) return;
    const actuales = integrante.diferencia_decimas ?? 0;
    // Pasos de 0.1; redondear para evitar errores de coma flotante.
    const nuevas = Math.max(-9.9, Math.min(9.9, Math.round((actuales + delta) * 10) / 10));
    if (nuevas === actuales) return;
    guardarDecimas(grupoId, integrante.id_asignado_actividad, nuevas);
  }

  function guardarDecimas(grupoId: number, asignadoId: number, decimas: number) {
    savingDecimas = asignadoId;
    router.put(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/integrantes/${asignadoId}`,
      { diferencia_decimas: decimas },
      {
        // El controlador responde redirect()->back(): Inertia ya re-renderiza con
        // props frescos. Recargar aquí volvía a ejecutar showEvaluacion() entero.
        preserveScroll: true,
        onFinish: () => (savingDecimas = null),
      },
    );
  }

  function recalcularNotas(grupoId: number) {
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/recalcular-notas`,
      {},
      { preserveScroll: true },
    );
  }

  function actualizarHolguraPersonal(grupoId: number, dias: number) {
    router.patch(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}`,
      { nro_dias_adicionales_para_bloqueo_personal: dias },
      { preserveScroll: true },
    );
  }

  function formatDecimas(d: number | undefined): string {
    const v = d ?? 0;
    return `${v >= 0 ? '+' : '−'}${Math.abs(v).toFixed(1)}`;
  }

  // ─── Entregas del grupo ───────────────────────────────────────────────────
  // La carga (fetch lazy GET) y el formato de las entregas viven en EntregasModal.

  let showEntregasModal = $state(false);
  let grupoEntregasSeleccionado = $state<GrupoData | null>(null);

  // Matriz de evaluación
  let showMatrizEvaluacion = $state(false);
  let entregaParaEvaluar = $state<number | null>(null);

  function verEntregas(grupo: GrupoData) {
    grupoEntregasSeleccionado = grupo;
    showEntregasModal = true;
  }

  function cerrarEntregas() {
    showEntregasModal = false;
    grupoEntregasSeleccionado = null;
  }

  function abrirMatrizEvaluacion(idAgenda: number | null = null) {
    entregaParaEvaluar = idAgenda;
    showEntregasModal = false;
    showMatrizEvaluacion = true;
  }

  function cerrarMatrizEvaluacion() {
    showMatrizEvaluacion = false;
    entregaParaEvaluar = null;
    grupoEntregasSeleccionado = null;
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  function cargarInteracciones(grupo: GrupoData) {
    isLoadingInteracciones = true;
    errorInteracciones = null;

    router.reload({
      only: ['interaccionesGrupo'],
      data: { grupo_id: grupo.grupo },
      onSuccess: () => {
        isLoadingInteracciones = false;
      },
      onError: () => {
        errorInteracciones = 'No se pudieron cargar los mensajes del grupo.';
        isLoadingInteracciones = false;
      },
    });
  }

  function abrirAgendaGrupo(grupo: GrupoData) {
    grupoSeleccionado = grupo;
    showRubricaModal = false;
    showAgendaModal = true;
    cargarInteracciones(grupo);
  }

  function cerrarAgenda() {
    showAgendaModal = false;
    grupoSeleccionado = null;
    // router.reload will clear it later or we can let it be
  }

  function toggleRubricaModal() {
    showAgendaModal = false;
    showRubricaModal = !showRubricaModal;
  }

  // Usa router.post() de Inertia para que el token CSRF se gestione
  // automáticamente (igual que el resto del proyecto), en vez de fetch() nativo.
  function manejarInteraccionDocente(data: {
    tipo: string;
    mensaje: string;
    nota?: number;
    id_agenda_entrega?: number | null;
    resultado_rubrica?: Record<string, string>;
    puntaje_obtenido?: number;
  }) {
    if (!grupoSeleccionado) return;
    const grupoSnap = grupoSeleccionado;

    if (data.tipo === 'Evaluación') {
      if (!rubrica_id) {
        errorInteracciones = 'Esta actividad no tiene rúbrica. Crea una rúbrica antes de evaluar.';
        return;
      }
      router.post(
        `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoSnap.grupo}/evaluacion`,
        {
          id_agenda_entrega: data.id_agenda_entrega ?? null,
          id_rubrica: rubrica_id,
          nota: data.nota ?? null,
          mensaje: data.mensaje,
          resultado_rubrica: data.resultado_rubrica,
          puntaje_obtenido: data.puntaje_obtenido,
        },
        {
          preserveScroll: true,
          // `grupos` llega ya actualizado en el redirect()->back(); sólo hace
          // falta refrescar las interacciones, que son un prop lazy aparte.
          onSuccess: () => cargarInteracciones(grupoSnap),
          onError: (errors) => console.error('Error al registrar evaluación:', errors),
        },
      );
    } else {
      router.post(
        `/docente/cursos/${curso.id_curso}/grupos/${grupoSnap.grupo}/feedback`,
        { mensaje: data.mensaje },
        {
          preserveScroll: true,
          onSuccess: () => cargarInteracciones(grupoSnap),
          onError: (errors) => console.error('Error al enviar feedback:', errors),
        },
      );
    }
  }

  function getEstadoColor(estado: string) {
    const e = estado.toUpperCase();
    if (e === 'ACTIVA') return 'bg-uta-blue/10 text-uta-blue border-uta-blue/30';
    if (e === 'CERRADA') return 'bg-green-100 text-green-800 border-green-300';
    if (e === 'PLANIFICADA') return 'bg-yellow-100 text-yellow-800 border-yellow-300';
    return 'bg-gray-100 text-gray-800 border-gray-300';
  }
</script>

<DocenteLayout {breadcrumbs}>
  <div class="px-4 sm:px-6 md:px-10 lg:px-20 bg-white relative">
    <!-- Cabecera -->
    <div
      class="w-full flex flex-col lg:flex-row items-start lg:items-center gap-4 sm:gap-6 lg:gap-20 mb-6"
    >
      <button
        class="flex items-center px-4 sm:px-6 py-3 sm:py-4 bg-uta-blue text-white hover:bg-uta-blue-hover transition-colors rounded-2xl shrink-0"
        onclick={() => window.history.back()}
      >
        <ChevronLeft class="w-4 h-4 mr-2" />
        <p class="text-sm sm:text-base">Volver</p>
      </button>

      <h2 class="text-base sm:text-xl md:text-2xl font-semibold break-words leading-snug">
        {curso.nombre}: {actividad.nombre}
      </h2>
    </div>

    <!-- ── Flash messages (resultado de la última acción) ── -->
    {#if flash?.success}
      <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
        {flash.success}
      </div>
    {/if}
    {#if flash?.error}
      <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
        {flash.error}
      </div>
    {/if}

    <div class="w-full grid grid-cols-1 xl:grid-cols-2 gap-6 lg:gap-8 items-start">
      <!-- ── Columna izquierda: información de la actividad ── -->
      <div
        class="flex flex-col w-full justify-center gap-6 lg:rounded-2xl lg:border lg:border-gray-200 lg:px-10 lg:py-5 lg:shadow-sm"
      >
        <p class="text-start text-sm sm:text-base font-semibold text-uta-blue">
          Sobre esta Actividad
        </p>

        <div
          class="text-sm font-semibold text-slate-700 px-4 sm:px-6 md:px-8 py-4 rounded-2xl bg-uta-blue-light border border-uta-blue/15 break-words"
        >

          Fecha límite: {formatFechaHora(actividad.fecha_limite)}
          <br />
          Tipo Actividad: {actividad.es_sumativa ? 'Sumativa' : 'Formativa'}
          <br />
          Entrega de Archivo: {actividad.trae_archivo ? 'Sí' : 'No'}
          {#if actividad.nro_dias_adicionales_para_bloqueo > 0}
            <br />
            Holgura: {actividad.nro_dias_adicionales_para_bloqueo} día{actividad.nro_dias_adicionales_para_bloqueo !== 1 ? 's' : ''} adicional{actividad.nro_dias_adicionales_para_bloqueo !== 1 ? 'es' : ''}
          {/if}
        </div>

        <!-- Botones de acción de la actividad -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
          {#if actividad.es_titular || rubrica}
            <button
              class="w-full px-4 sm:px-6 md:px-8 py-3 sm:py-4 rounded-xl border border-uta-blue transition-all bg-white text-uta-blue hover:bg-uta-blue hover:text-white flex items-center justify-between gap-4 text-sm font-semibold sm:col-span-2"
              onclick={() => actividad.es_titular ? (showRubricaEditor = true) : toggleRubricaModal()}
            >
              <p>{rubrica ? 'Ver Rúbrica' : 'Crear Rúbrica'}</p>
              <Pencil class="size-5 shrink-0" />
            </button>
          {/if}
        </div>
      </div>

      <!-- ── Columna derecha: grupos asignados ── -->
      <div class="flex flex-col w-full gap-4">
        <div class="flex justify-between items-center">
          <p class="text-start text-sm sm:text-base font-semibold text-uta-blue">
            Grupos Asignados
          </p>
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500 font-medium">{grupos.length} grupos</span>
            {#if actividad.es_grupal && actividad.es_titular}
              {#if actividadesConGrupos.length > 0}
                <button
                  onclick={() => {
                    showReutilizarGrupos = true;
                    actividadOrigenSeleccionada = null;
                    reutilizarGruposError = null;
                  }}
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-uta-blue text-uta-blue text-xs font-semibold rounded-xl hover:bg-uta-blue/5 transition-colors"
                >
                  <Copy class="w-3.5 h-3.5" />
                  Reutilizar Grupos
                </button>
              {/if}
              <button
                onclick={() => {
                  showNuevoGrupo = true;
                  seleccion = new Set();
                  nuevoGrupoError = null;
                }}
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-uta-blue text-white text-xs font-semibold rounded-xl hover:bg-uta-blue-hover transition-colors"
              >
                <Plus class="w-3.5 h-3.5" />
                Nuevo Grupo
              </button>
            {/if}
          </div>
        </div>

        {#if actividad.es_grupal && estudiantesInscritos.length > 0}
          <div class="text-xs text-gray-500 px-1 flex items-center gap-1.5">
            <Users class="w-3.5 h-3.5" />
            {estudiantesLibres.length} de {estudiantesInscritos.length} estudiante{estudiantesInscritos.length !==
            1
              ? 's'
              : ''} sin asignar
          </div>
        {/if}

        {#each grupos as grupo (grupo.grupo)}
          <GrupoCard
            {grupo}
            esTitular={actividad.es_titular}
            traeArchivo={actividad.trae_archivo}
            {savingDecimas}
            {addingToGrupo}
            bind:addingEstudianteId
            {addingLoading}
            {addingError}
            estudiantesParaGrupo={estudiantesParaGrupo(grupo.grupo)}
            {getEstadoColor}
            {formatDecimas}
            onEliminarGrupo={eliminarGrupo}
            onQuitarEstudiante={quitarEstudiante}
            onAjustarDecimas={ajustarDecimas}
            onRecalcularNotas={recalcularNotas}
            onAbrirAddForm={(grupoId) => {
              addingToGrupo = grupoId;
              addingEstudianteId = 0;
              addingError = null;
            }}
            onCerrarAddForm={() => {
              addingToGrupo = null;
              addingError = null;
            }}
            onAgregarAGrupo={agregarAGrupo}
            onVerEntregas={verEntregas}
            onVerAgenda={abrirAgendaGrupo}
            onActualizarHolguraPersonal={actualizarHolguraPersonal}
          />
        {/each}

        {#if grupos.length === 0 && actividad.es_grupal && actividad.es_titular}
          <div
            class="flex flex-col items-center justify-center py-12 text-center border-2 border-dashed border-gray-200 rounded-3xl"
          >
            <Users class="w-10 h-10 text-gray-300 mb-3" />
            <p class="text-sm text-gray-500 font-medium">No hay grupos creados aún</p>
            <p class="text-xs text-gray-400 mt-1">
              Crea el primer grupo con los estudiantes inscritos
            </p>
            <div class="mt-4 flex items-center gap-2">
              <button
                onclick={() => {
                  showNuevoGrupo = true;
                  seleccion = new Set();
                  nuevoGrupoError = null;
                }}
                class="inline-flex items-center gap-2 px-4 py-2 bg-uta-blue text-white text-sm font-semibold rounded-xl hover:bg-uta-blue-hover transition-colors"
              >
                <Plus class="w-4 h-4" />
                Crear primer grupo
              </button>
              {#if actividadesConGrupos.length > 0}
                <button
                  onclick={() => {
                    showReutilizarGrupos = true;
                    actividadOrigenSeleccionada = null;
                    reutilizarGruposError = null;
                  }}
                  class="inline-flex items-center gap-2 px-4 py-2 border border-uta-blue text-uta-blue text-sm font-semibold rounded-xl hover:bg-uta-blue/5 transition-colors"
                >
                  <Copy class="w-4 h-4" />
                  Reutilizar grupos
                </button>
              {/if}
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>

  <!-- ── Modal: Nuevo Grupo ── -->
  {#if showNuevoGrupo}
    <NuevoGrupoModal
      {estudiantesLibres}
      maxIntegrantes={actividad.max_integrantes}
      {seleccion}
      loading={nuevoGrupoLoading}
      error={nuevoGrupoError}
      onToggleSeleccion={toggleSeleccion}
      onCrear={crearGrupo}
      onCerrar={() => (showNuevoGrupo = false)}
    />
  {/if}

  <!-- ── Modal: Reutilizar Grupos ── -->
  {#if showReutilizarGrupos}
    <ReutilizarGruposModal
      idCurso={curso.id_curso}
      idActividad={actividad.id_actividad}
      actividades={actividadesConGrupos}
      seleccionada={actividadOrigenSeleccionada}
      loading={reutilizarGruposLoading}
      error={reutilizarGruposError}
      onSeleccionar={(id) => (actividadOrigenSeleccionada = id)}
      onCopiar={copiarGrupos}
      onCerrar={() => (showReutilizarGrupos = false)}
    />
  {/if}

  <!-- Modal: Agenda del grupo (perspectiva docente) -->
  {#if showAgendaModal && grupoSeleccionado}
    <div
      class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-black/50 transition-opacity overflow-y-auto"
    >
      <div class="w-full max-w-7xl">
        <AgendaDocente
          onCerrar={cerrarAgenda}
          onInteraccionEnviada={manejarInteraccionDocente}
          cod_curso={curso.cod_curso}
          nombre_actividad={actividad.nombre}
          nombre_grupo="Grupo #{grupoSeleccionado.grupo}"
          listado_interacciones={interaccionesGrupo}
          isLoading={isLoadingInteracciones}
          errorMensaje={errorInteracciones}
          rubricaActividad={rubrica}
        />
      </div>
    </div>
  {/if}

  <!-- Modal: Rúbrica de la actividad -->
  {#if showRubricaModal}
    <div
      class="fixed inset-0 z-50 sm:relative sm:inset-auto w-full border-l bg-gray-50 h-full overflow-y-auto p-6 animate-slide-in"
    >
      <div class="flex flex-col gap-4 w-full max-w-7xl bg-white rounded-4xl">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-sm font-semibold text-uta-blue">Rúbrica de la Actividad</h2>
          <button
            class="p-2 hover:bg-gray-200 rounded-full transition-colors flex items-center gap-2 group"
            onclick={toggleRubricaModal}
            aria-label="cerrar"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="size-6"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        {#if rubrica}
          <RubricaView {rubrica} modoLectura={true} />
        {/if}
      </div>
    </div>
  {/if}
  <!-- ── Modal: Entregas del grupo ── -->
  {#if showEntregasModal && grupoEntregasSeleccionado}
    <EntregasModal
      idCurso={curso.id_curso}
      idActividad={actividad.id_actividad}
      nombreActividad={actividad.nombre}
      grupo={grupoEntregasSeleccionado}
      {rubrica}
      rubricaId={rubrica_id}
      onCerrar={cerrarEntregas}
      onEvaluar={abrirMatrizEvaluacion}
      onVerAgenda={(g) => {
        cerrarEntregas();
        abrirAgendaGrupo(g);
      }}
    />
  {/if}

  <!-- ── Matriz de evaluación (pantalla completa) ── -->
  {#if showMatrizEvaluacion && grupoEntregasSeleccionado && rubrica && rubrica_id}
    <MatrizEvaluacion
      {rubrica}
      rubricaId={rubrica_id}
      nombreActividad={actividad.nombre}
      nombreGrupo="Grupo #{grupoEntregasSeleccionado.grupo}"
      idCurso={curso.id_curso}
      idActividad={actividad.id_actividad}
      idGrupo={grupoEntregasSeleccionado.grupo}
      idAgendaEntrega={entregaParaEvaluar}
      onClose={cerrarMatrizEvaluacion}
    />
  {/if}

  {#key actividad.id_actividad}
    {#if showRubricaEditor}
      <RubricaEditor
        {rubrica}
        idCurso={curso.id_curso}
        idActividad={actividad.id_actividad}
        onClose={() => (showRubricaEditor = false)}
      />
    {/if}
  {/key}
</DocenteLayout>

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape') {
      if (showMatrizEvaluacion) cerrarMatrizEvaluacion();
      else if (showEntregasModal) cerrarEntregas();
      else showAgendaModal = false;
    }
  }}
/>

<style>
  @keyframes slide-in {
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
