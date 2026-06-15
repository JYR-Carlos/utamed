<script lang="ts">
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { Rubrica } from '@/types/rubrica';
  import { ChevronLeft, Plus, Trash2, UserPlus, X, Users, Pencil } from 'lucide-svelte';
  import AgendaDocente from './Agenda/AgendaDocente.svelte';
  import RubricaView from '../../student/Activities/Agenda/Rubrica.svelte';
  import RubricaEditor from './RubricaEditor.svelte';

  // ─── Tipos ────────────────────────────────────────────────────────────────
  type Interaccion = {
    id_interaccion: number;
    fecha_emision: string;
    tipo_interaccion: string;
    emisor: string;
    mensaje: string;
    es_de_docente: boolean;
    es_retroalimentacion: boolean;
    // 1. Autor: Juan Y.
    // 2. Fecha: 04/06/2025
    // 3. Se añaden campos es_entrega y tiene_evaluacion retornados por el
    //    endpoint getGrupoMensajes actualizado.
    es_entrega?: boolean;
    tiene_evaluacion?: boolean;
    adjunta_rubrica: boolean;
    rubrica?: Rubrica;
    puntaje_obtenido?: number;
    resultado?: Record<string, string> | null;
  };

  type GrupoData = {
    grupo: number;
    nota: number | null;
    estado_actividad_asignada: string | null;
    integrantes: { id_estudiante: number; nombre_completo: string }[];
  };

  type EstudianteInscrito = {
    id_estudiante: number;
    nombre_completo: string;
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
    };
    grupos: GrupoData[];
    rubrica?: Rubrica | null;
    // 1. Autor: Juan Y.
    // 2. Fecha: 04/06/2025
    // 3. Se agrega rubrica_id para enviarlo al endpoint storeEvaluacion sin
    //    necesidad de una consulta adicional desde el frontend.
    rubrica_id?: number | null;
    estudiantesInscritos?: EstudianteInscrito[];
    interaccionesGrupo?: Interaccion[];
  }

  let {
    curso,
    actividad,
    grupos,
    rubrica = null,
    rubrica_id = null,
    estudiantesInscritos = [],
    interaccionesGrupo = [],
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

  function eliminarGrupo(grupoId: number) {
    if (!confirm('¿Eliminar este grupo y todos sus integrantes?')) return;
    router.delete(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos-delete/${grupoId}`,
    );
  }

  function quitarEstudiante(grupoId: number, estudianteId: number) {
    if (!confirm('¿Quitar a este estudiante del grupo?')) return;
    router.delete(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/estudiantes/${estudianteId}`,
    );
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

  // 1. Autor: GitHub Copilot
  // 2. Fecha: 02/06/2026
  // 3. Se migra de fetch() nativo a router.post() de Inertia.js para que el
  //    token CSRF se gestione automáticamente (igual que el resto del proyecto).
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
        console.error('No hay rúbrica asociada a esta actividad para evaluar.');
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
          onSuccess: () => {
            router.reload({ only: ['grupos'] });
            cargarInteracciones(grupoSnap);
          },
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

  function handleSubirArchivo(data: { archivo: File | null; descripcion: string }) {}
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

    <div class="w-full grid grid-cols-1 xl:grid-cols-2 gap-6 lg:gap-8 items-start">
      <!-- ── Columna izquierda: información de la actividad ── -->
      <div
        class="flex flex-col w-full justify-center gap-6 lg:rounded-2xl lg:border lg:border-gray-200 lg:px-10 lg:py-5 lg:shadow-sm"
      >
        <p class="text-start text-sm sm:text-base font-semibold text-uta-blue">
          Sobre esta Actividad
        </p>

        <div
          class="text-sm text-slate-700 px-4 sm:px-6 md:px-8 py-4 rounded-2xl bg-uta-blue-light border border-uta-blue/15 break-words"
        >
          Descripción:
          <br />
          {actividad.descripcion}
          <br class="mb-4" />
          Fecha límite: {actividad.fecha_limite}
          <br />
          Tipo Actividad: {actividad.es_sumativa ? 'Sumativa' : 'Formativa'}
          <br />
          Entrega de Archivo: {actividad.trae_archivo ? 'Sí' : 'No'}
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

        {#each grupos as grupo}
          <div
            class="flex flex-col w-full text-sm font-semibold text-slate-800 px-4 sm:px-6 py-4 rounded-2xl bg-white border border-gray-200 shadow-sm gap-3"
          >
            <!-- Número de grupo + estado + eliminar -->
            <div class="flex items-center justify-between gap-2">
              <p class="font-bold text-base">Grupo #{grupo.grupo}</p>
              <div class="flex items-center gap-2">
                {#if grupo.estado_actividad_asignada}
                  <span
                    class="text-[11px] font-bold px-3 py-1 rounded-full border {getEstadoColor(
                      grupo.estado_actividad_asignada,
                    )}"
                  >
                    {grupo.estado_actividad_asignada.toUpperCase()}
                  </span>
                {:else}
                  <span
                    class="text-[11px] font-bold px-3 py-1 rounded-full border bg-gray-100 text-gray-800 border-gray-300"
                  >
                    SIN ESTADO
                  </span>
                {/if}
                {#if actividad.es_titular}
                  <button
                    onclick={() => eliminarGrupo(grupo.grupo)}
                    class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition"
                    title="Eliminar grupo"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                {/if}
              </div>
            </div>

            <!-- Nota grupal -->
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-600 font-normal">Nota grupal:</span>
              {#if grupo.nota !== null}
                <span
                  class="font-bold text-lg {grupo.nota >= 4 ? 'text-green-700' : 'text-red-700'}"
                >
                  {grupo.nota.toPrecision(2)}
                </span>
              {:else}
                <span class="font-normal text-gray-400 italic text-xs">Sin calificar</span>
              {/if}
            </div>

            <!-- Integrantes -->
            <div>
              <p class="text-xs text-gray-600 font-normal mb-1">Integrantes:</p>
              <div class="flex flex-wrap gap-2">
                {#each grupo.integrantes as integrante}
                  <span
                    class="inline-flex items-center gap-1 text-xs bg-gray-50 border border-uta-blue/20 px-2 py-1 rounded-full text-slate-700"
                  >
                    {integrante.nombre_completo}
                    {#if actividad.es_titular}
                      <button
                        onclick={() => quitarEstudiante(grupo.grupo, integrante.id_estudiante)}
                        class="ml-0.5 text-gray-400 hover:text-red-500 transition"
                        title="Quitar del grupo"
                      >
                        <X class="w-3 h-3" />
                      </button>
                    {/if}
                  </span>
                {/each}
                {#if grupo.integrantes.length === 0}
                  <span class="text-xs text-gray-400 italic">Sin integrantes</span>
                {/if}
              </div>
            </div>

            <!-- Agregar estudiante a grupo existente (titular) -->
            {#if actividad.es_titular}
              {#if addingToGrupo === grupo.grupo}
                <div class="flex items-center gap-2 mt-1">
                  <select
                    bind:value={addingEstudianteId}
                    class="flex-1 text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white text-gray-700 focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
                  >
                    <option value={0}>Seleccionar estudiante…</option>
                    {#each estudiantesParaGrupo(grupo.grupo) as e}
                      <option value={e.id_estudiante}>{e.nombre_completo}</option>
                    {/each}
                  </select>
                  <button
                    onclick={() => agregarAGrupo(grupo.grupo)}
                    disabled={!addingEstudianteId || addingLoading}
                    class="px-3 py-1.5 text-xs font-semibold bg-uta-blue text-white rounded-lg hover:bg-uta-blue-hover transition-colors disabled:opacity-50"
                  >
                    {addingLoading ? '…' : 'Agregar'}
                  </button>
                  <button
                    onclick={() => {
                      addingToGrupo = null;
                      addingError = null;
                    }}
                    class="p-1.5 text-gray-400 hover:text-gray-600 transition"
                  >
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
                {#if addingError}
                  <p class="text-xs text-red-600">{addingError}</p>
                {/if}
              {:else}
                <button
                  onclick={() => {
                    addingToGrupo = grupo.grupo;
                    addingEstudianteId = 0;
                    addingError = null;
                  }}
                  class="inline-flex items-center gap-1.5 text-xs font-medium text-uta-blue/60 hover:text-uta-blue transition-colors"
                >
                  <UserPlus class="w-3.5 h-3.5" />
                  Agregar estudiante
                </button>
              {/if}
            {/if}

            <!-- Botones de acción del grupo -->
            <div
              class="grid gap-2 mt-1"
              class:grid-cols-2={actividad.trae_archivo}
              class:grid-cols-1={!actividad.trae_archivo}
            >
              {#if actividad.trae_archivo}
                <button
                  class="w-full px-3 py-2 rounded-lg border border-uta-blue/20 transition-all bg-uta-blue text-white hover:bg-uta-blue-hover flex items-center justify-between gap-2 text-xs font-semibold"
                  onclick={() =>
                    window.open(
                      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupo.grupo}/entregas`,
                      '_blank',
                    )}
                >
                  <p>Ver Entregas</p>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-4 shrink-0"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"
                    />
                  </svg>
                </button>
              {/if}

              <button
                class="w-full px-3 py-2 rounded-lg border border-uta-blue/20 transition-all bg-uta-blue text-white hover:bg-uta-blue-hover flex items-center justify-between gap-2 text-xs font-semibold"
                onclick={() => abrirAgendaGrupo(grupo)}
              >
                <p>Ver Agenda</p>
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="size-4 shrink-0"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"
                  />
                </svg>
              </button>
            </div>
          </div>
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
            <button
              onclick={() => {
                showNuevoGrupo = true;
                seleccion = new Set();
                nuevoGrupoError = null;
              }}
              class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-uta-blue text-white text-sm font-semibold rounded-xl hover:bg-uta-blue-hover transition-colors"
            >
              <Plus class="w-4 h-4" />
              Crear primer grupo
            </button>
          </div>
        {/if}
      </div>
    </div>
  </div>

  <!-- ── Modal: Nuevo Grupo ── -->
  {#if showNuevoGrupo}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="text-base font-bold text-gray-900">Crear nuevo grupo</h3>
          <button
            onclick={() => (showNuevoGrupo = false)}
            class="p-1.5 rounded-full hover:bg-gray-100 transition"
          >
            <X class="w-4 h-4 text-gray-500" />
          </button>
        </div>

        <!-- Body: lista de estudiantes libres -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
          {#if actividad.max_integrantes}
            <p class="text-xs text-gray-500 mb-3">
              Máximo {actividad.max_integrantes} integrante{actividad.max_integrantes !== 1
                ? 's'
                : ''} por grupo.
            </p>
          {/if}

          {#if estudiantesLibres.length === 0}
            <p class="text-sm text-gray-500 italic text-center py-6">
              Todos los estudiantes ya están asignados a un grupo.
            </p>
          {:else}
            <p class="text-xs text-gray-500 mb-3">
              Selecciona los estudiantes que conformarán este grupo:
            </p>
            <div class="flex flex-col gap-2">
              {#each estudiantesLibres as e}
                <label
                  class="flex items-center gap-3 p-2.5 rounded-xl border cursor-pointer hover:bg-gray-50 transition {seleccion.has(
                    e.id_estudiante,
                  )
                    ? 'border-uta-blue bg-uta-blue/5'
                    : 'border-gray-200'}"
                >
                  <input
                    type="checkbox"
                    checked={seleccion.has(e.id_estudiante)}
                    onchange={() => toggleSeleccion(e.id_estudiante)}
                    class="accent-[#002855] w-4 h-4 shrink-0"
                  />
                  <span class="text-sm text-gray-800">{e.nombre_completo}</span>
                </label>
              {/each}
            </div>
          {/if}

          {#if nuevoGrupoError}
            <p class="mt-3 text-xs text-red-600">{nuevoGrupoError}</p>
          {/if}
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between gap-3 px-6 py-4 border-t">
          <span class="text-xs text-gray-500"
            >{seleccion.size} seleccionado{seleccion.size !== 1 ? 's' : ''}</span
          >
          <div class="flex gap-2">
            <button
              onclick={() => (showNuevoGrupo = false)}
              class="px-4 py-2 text-sm border rounded-xl text-gray-600 hover:bg-gray-50 transition"
            >
              Cancelar
            </button>
            <button
              onclick={crearGrupo}
              disabled={seleccion.size === 0 || nuevoGrupoLoading}
              class="px-4 py-2 text-sm font-semibold bg-uta-blue text-white rounded-xl hover:bg-uta-blue-hover transition-colors disabled:opacity-50"
            >
              {nuevoGrupoLoading ? 'Creando…' : 'Crear Grupo'}
            </button>
          </div>
        </div>
      </div>
    </div>
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

<svelte:window onkeydown={(e) => e.key === 'Escape' && (showAgendaModal = false)} />

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
