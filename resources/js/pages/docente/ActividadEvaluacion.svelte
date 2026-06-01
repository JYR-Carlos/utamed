<script lang="ts">
  /**
   * Página de evaluación/calificación de una actividad.
   *
   * El docente puede:
   * - Ver todos los grupos (actividad_asignada) de la actividad
   * - Crear nuevos grupos y agregar estudiantes
   * - Asignar nota grupal y cambiar estado
   * - Asignar nota individual y diferencia de décimas por integrante
   * - Eliminar integrantes y grupos
   */
  import { router, Link } from '@inertiajs/svelte';
  import { untrack } from 'svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import {
    ArrowLeft,
    Plus,
    Trash2,
    UserPlus,
    Save,
    Users,
    User,
    BookOpen,
    Copy,
    Download,
    FileText,
    X,
    ChevronDown,
    ChevronUp,
    MessageSquare,
    Send,
    Loader2,
    ClipboardList,
  } from 'lucide-svelte';
  import type {
    Actividad,
    Integrante,
    Grupo,
    Estado,
    EstudianteDisponible,
    Entrega,
    ActividadResumen,
    MensajeGrupo,
  } from '@/types/actividad';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';

  interface Props {
    curso: {
      id_curso: number;
      cod_curso?: string;
      asignatura_nombre?: string;
      nombre?: string;
      es_titular_curso?: boolean;
      userPermissions?: Permission[];
    };
    actividad: Actividad;
    grupos: Grupo[];
    estudiantesDisponibles: EstudianteDisponible[];
    estados: Estado[];
  }

  // ---------------------------------------------------------------------------
  // Props & reactive state
  // ---------------------------------------------------------------------------
  let { curso, actividad, grupos: gruposInit, estudiantesDisponibles, estados }: Props = $props();

  const canCreateGroup = $derived(
    curso.es_titular_curso ||
      hasPermission(curso.userPermissions ?? [], 'actividades/grupos:crear'),
  );
  const canEditGroup = $derived(
    curso.es_titular_curso ||
      hasPermission(curso.userPermissions ?? [], 'actividades/grupos:editar'),
  );
  const canDeleteGroup = $derived(
    curso.es_titular_curso ||
      hasPermission(curso.userPermissions ?? [], 'actividades/grupos:eliminar'),
  );

  // Local mutable copy so we can edit inline without waiting for server round-trips.
  // untrack() suppresses the Svelte 5 state_referenced_locally warning – intentional
  // since Inertia props are static per page visit.
  let grupos = $state<Grupo[]>(
    untrack(() => gruposInit.map((g) => ({ ...g, integrantes: [...g.integrantes] }))),
  );

  let isLoading = $state(false);

  // Nuevo grupo
  let showNuevoGrupo = $state(false);
  let nuevoGrupoEstadoId = $state<number>(untrack(() => estados[0]?.id_estado ?? 0));
  let nuevoGrupoEstudianteId = $state<number>(0);

  // Para agregar integrante a un grupo existente
  let addingToGrupo = $state<number | null>(null);
  let addEstudianteId = $state<number>(0);

  // Ediciones en vuelo (identificadas por grupo o id_asignado)
  let savingGrupo = $state<Set<number>>(new Set());
  let savingIntegrante = $state<Set<number>>(new Set());

  // --- Copiar grupos desde otra actividad ---
  let showCopyModal = $state(false);
  let actividadesDisponibles = $state<ActividadResumen[]>([]);
  let actividadOrigenId = $state<number>(0);
  let isFetchingActividades = $state(false);
  let isCopying = $state(false);
  let copyMessage = $state<{ type: 'success' | 'error'; text: string } | null>(null);

  // --- Entregas por grupo ---
  let entregasByGrupo = $state<Map<number, Entrega[]>>(new Map());
  let loadingEntregas = $state<Set<number>>(new Set());
  let expandedEntregas = $state<Set<number>>(new Set());

  // --- Mensajes/feedback por grupo (nivel 2) ---
  let mensajesByGrupo = $state<Map<number, MensajeGrupo[]>>(new Map());
  let loadingMensajes = $state<Set<number>>(new Set());
  let expandedMensajes = $state<Set<number>>(new Set());
  let replyByGrupo = $state<Map<number, string>>(new Map());
  let sendingFeedback = $state<Set<number>>(new Set());
  let feedbackError = $state<Map<number, string>>(new Map());

  // --- Confirmación destructiva ---
  let confirmDialog = $state<{
    open: boolean;
    title: string;
    body: string;
    onConfirm: (() => void) | null;
  }>({ open: false, title: '', body: '', onConfirm: null });

  function closeConfirm() {
    confirmDialog = { open: false, title: '', body: '', onConfirm: null };
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------
  function formatDate(d: string) {
    return new Date(d).toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }

  function estadoLabel(idEstado: number | null) {
    if (!idEstado) return 'Sin estado';
    return estados.find((e) => e.id_estado === idEstado)?.titulo ?? 'Desconocido';
  }

  function estadoColor(titulo: string) {
    const t = titulo.toLowerCase();
    if (t.includes('entregad') || t.includes('completad')) return 'badge-success';
    if (t.includes('pendiente') || t.includes('asign')) return 'badge-warning';
    if (t.includes('retraso') || t.includes('tard')) return 'badge-danger';
    return 'badge-neutral';
  }

  /** Estudiantes que aún no están en ningún grupo de esta actividad */
  function estudiantesLibres(grupoIdExcluir?: number): EstudianteDisponible[] {
    const ocupados = new Set<number>();
    grupos.forEach((g) => {
      if (g.grupo === grupoIdExcluir) return;
      g.integrantes.forEach((i) => ocupados.add(i.id_estudiante));
    });
    return estudiantesDisponibles.filter((e) => !ocupados.has(e.id_estudiante));
  }

  // ---------------------------------------------------------------------------
  // Actions
  // ---------------------------------------------------------------------------

  /** Crea un nuevo grupo, opcionalmente con un primer integrante */
  function crearGrupo() {
    if (!nuevoGrupoEstadoId) return;
    isLoading = true;
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos`,
      {
        id_estado: nuevoGrupoEstadoId,
        id_estudiante: nuevoGrupoEstudianteId || null,
      },
      {
        onSuccess: () => {
          showNuevoGrupo = false;
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      },
    );
  }

  /** Guarda nota + estado de un grupo */
  function guardarGrupo(g: Grupo) {
    savingGrupo = new Set([...savingGrupo, g.grupo]);
    router.put(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${g.grupo}`,
      { nota: g.nota, id_estado: g.id_estado },
      {
        onSuccess: () => {
          savingGrupo = new Set([...savingGrupo].filter((x) => x !== g.grupo));
        },
        onError: () => {
          savingGrupo = new Set([...savingGrupo].filter((x) => x !== g.grupo));
        },
      },
    );
  }

  /** Elimina un grupo y todos sus integrantes */
  function eliminarGrupo(grupoId: number) {
    confirmDialog = {
      open: true,
      title: '¿Eliminar grupo?',
      body: 'Se eliminarán el grupo y todos sus integrantes. Esta acción no se puede deshacer.',
      onConfirm: () => {
        isLoading = true;
        router.delete(
          `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}`,
          {
            onSuccess: () => {
              isLoading = false;
            },
            onError: () => {
              isLoading = false;
            },
          },
        );
      },
    };
  }

  /** Agrega un integrante al grupo */
  function agregarIntegrante(grupoId: number) {
    if (!addEstudianteId) return;
    isLoading = true;
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/integrantes`,
      { id_estudiante: addEstudianteId },
      {
        onSuccess: () => {
          addingToGrupo = null;
          addEstudianteId = 0;
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      },
    );
  }

  /** Guarda nota individual de un integrante */
  function guardarIntegrante(grupoId: number, i: Integrante) {
    savingIntegrante = new Set([...savingIntegrante, i.id_asignado_actividad]);
    router.put(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/integrantes/${i.id_asignado_actividad}`,
      { nota_individual: i.nota_individual, diferencia_decimas: i.diferencia_decimas },
      {
        onSuccess: () => {
          savingIntegrante = new Set(
            [...savingIntegrante].filter((x) => x !== i.id_asignado_actividad),
          );
        },
        onError: () => {
          savingIntegrante = new Set(
            [...savingIntegrante].filter((x) => x !== i.id_asignado_actividad),
          );
        },
      },
    );
  }

  /** Elimina un integrante del grupo */
  function eliminarIntegrante(grupoId: number, asignadoId: number) {
    confirmDialog = {
      open: true,
      title: '¿Quitar integrante del grupo?',
      body: 'El estudiante será removido del grupo.',
      onConfirm: () => {
        isLoading = true;
        router.delete(
          `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/integrantes/${asignadoId}`,
          {
            onSuccess: () => {
              isLoading = false;
            },
            onError: () => {
              isLoading = false;
            },
          },
        );
      },
    };
  }

  // ---------------------------------------------------------------------------
  // Copiar grupos desde otra actividad
  // ---------------------------------------------------------------------------

  async function openCopyModal() {
    showCopyModal = true;
    copyMessage = null;
    actividadOrigenId = 0;
    if (actividadesDisponibles.length > 0) return;
    isFetchingActividades = true;
    try {
      const res = await fetch(`/docente/cursos/${curso.id_curso}/actividades/json`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const data: ActividadResumen[] = await res.json();
      // Exclude current activity; only show group activities
      actividadesDisponibles = data.filter(
        (a) => a.es_grupal && a.id_actividad !== actividad.id_actividad,
      );
    } catch {
      actividadesDisponibles = [];
    } finally {
      isFetchingActividades = false;
    }
  }

  function copiarGrupos() {
    if (!actividadOrigenId) return;
    isCopying = true;
    copyMessage = null;
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos-copy`,
      { id_actividad_origen: actividadOrigenId },
      {
        onSuccess: (page) => {
          const flash = (page.props as any)?.flash;
          copyMessage = {
            type: 'success',
            text: flash?.success ?? 'Grupos copiados correctamente.',
          };
          isCopying = false;
          // Reload after short delay so user can read message
          setTimeout(() => {
            showCopyModal = false;
            router.reload();
          }, 1800);
        },
        onError: (errors) => {
          copyMessage = {
            type: 'error',
            text: (Object.values(errors)[0] as string) ?? 'Error al copiar grupos.',
          };
          isCopying = false;
        },
      },
    );
  }

  // ---------------------------------------------------------------------------
  // Entregas por grupo
  // ---------------------------------------------------------------------------

  async function toggleEntregas(grupoId: number) {
    if (expandedEntregas.has(grupoId)) {
      expandedEntregas = new Set([...expandedEntregas].filter((id) => id !== grupoId));
      return;
    }
    expandedEntregas = new Set([...expandedEntregas, grupoId]);
    if (entregasByGrupo.has(grupoId)) return; // already loaded

    loadingEntregas = new Set([...loadingEntregas, grupoId]);
    try {
      const res = await fetch(
        `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/entregas`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      const data: Entrega[] = await res.json();
      const next = new Map(entregasByGrupo);
      next.set(grupoId, data);
      entregasByGrupo = next;
    } catch {
      const next = new Map(entregasByGrupo);
      next.set(grupoId, []);
      entregasByGrupo = next;
    } finally {
      loadingEntregas = new Set([...loadingEntregas].filter((id) => id !== grupoId));
    }
  }

  function formatBytes(bytes: number | null) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  function downloadUrl(grupoId: number, agendaId: number) {
    return `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/entregas/${agendaId}/descargar`;
  }

  // ---------------------------------------------------------------------------
  // Mensajes por grupo (nivel 2: retroalimentación de actividad)
  // ---------------------------------------------------------------------------

  function csrfToken() {
    return (
      (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
    );
  }

  async function toggleMensajes(grupoId: number) {
    if (expandedMensajes.has(grupoId)) {
      expandedMensajes = new Set([...expandedMensajes].filter((id) => id !== grupoId));
      return;
    }
    expandedMensajes = new Set([...expandedMensajes, grupoId]);
    if (mensajesByGrupo.has(grupoId)) return; // ya cargados

    loadingMensajes = new Set([...loadingMensajes, grupoId]);
    try {
      const res = await fetch(
        `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/mensajes`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      const data: MensajeGrupo[] = await res.json();
      const next = new Map(mensajesByGrupo);
      next.set(grupoId, data);
      mensajesByGrupo = next;
    } catch {
      const next = new Map(mensajesByGrupo);
      next.set(grupoId, []);
      mensajesByGrupo = next;
    } finally {
      loadingMensajes = new Set([...loadingMensajes].filter((id) => id !== grupoId));
    }
  }

  async function enviarFeedbackGrupo(grupoId: number) {
    const texto = replyByGrupo.get(grupoId)?.trim();
    if (!texto) return;

    sendingFeedback = new Set([...sendingFeedback, grupoId]);
    const errMap = new Map(feedbackError);
    errMap.delete(grupoId);
    feedbackError = errMap;

    try {
      const res = await fetch(`/docente/cursos/${curso.id_curso}/grupos/${grupoId}/feedback`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ mensaje: texto }),
      });
      if (!res.ok) throw new Error('Error al enviar feedback.');
      // Reset reply and reload thread
      const replyNext = new Map(replyByGrupo);
      replyNext.set(grupoId, '');
      replyByGrupo = replyNext;
      // Force reload by deleting cached entry
      const next = new Map(mensajesByGrupo);
      next.delete(grupoId);
      mensajesByGrupo = next;
      // Re-fetch
      loadingMensajes = new Set([...loadingMensajes, grupoId]);
      const res2 = await fetch(
        `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/mensajes`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      const data: MensajeGrupo[] = await res2.json();
      const next2 = new Map(mensajesByGrupo);
      next2.set(grupoId, data);
      mensajesByGrupo = next2;
    } catch (err: any) {
      const errMap2 = new Map(feedbackError);
      errMap2.set(grupoId, err.message ?? 'Error al enviar.');
      feedbackError = errMap2;
    } finally {
      sendingFeedback = new Set([...sendingFeedback].filter((id) => id !== grupoId));
      loadingMensajes = new Set([...loadingMensajes].filter((id) => id !== grupoId));
    }
  }
</script>

<DocenteLayout>
  <div class="px-4 py-6 sm:px-8 sm:py-8 max-w-4xl mx-auto">
    <!-- ── Header ─────────────────────────────────────────────────── -->
    <div class="mb-8">
      <div class="mb-3">
        <Link
          href={`/docente/cursos/${curso.id_curso}/actividades`}
          class="inline-flex items-center gap-2 text-uta-blue font-semibold text-sm hover:text-uta-blue/70 transition-colors"
        >
          <ArrowLeft size={18} />
          Volver a Actividades
        </Link>
      </div>

      <div class="flex justify-between items-start gap-4 flex-col md:flex-row">
        <div>
          <div class="flex flex-wrap gap-2 mb-2">
            {#if actividad.es_grupal}
              <span
                class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 bg-uta-blue/10 text-uta-blue rounded-full ring-1 ring-uta-blue/20"
                ><Users size={14} />Grupal · máx. {actividad.max_integrantes}</span
              >
            {:else}
              <span
                class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 bg-uta-red/10 text-uta-red rounded-full ring-1 ring-uta-red/20"
                ><User size={14} />Individual</span
              >
            {/if}
            <span
              class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 bg-green-50 text-green-700 rounded-full"
              >{actividad.tipo_entrega}</span
            >
            {#if actividad.seccion}
              <span
                class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 bg-amber-100 text-amber-700 rounded-full"
                ><BookOpen size={14} />{actividad.seccion.tipo}</span
              >
            {/if}
          </div>
          <h1 class="text-3xl font-bold text-gray-900 mb-1">{actividad.nombre}</h1>
          <p class="text-gray-600 text-sm">
            Fecha límite: <strong>{formatDate(actividad.fecha_limite)}</strong>
            {#if actividad.unidad}
              · Unidad: <strong>{actividad.unidad.nombre}</strong>{/if}
          </p>
        </div>

        {#if canCreateGroup}
          <div class="flex gap-2">
            {#if actividad.es_grupal}
              <button
                onclick={openCopyModal}
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold text-sm rounded-lg hover:bg-uta-blue-light hover:border-uta-blue hover:text-uta-blue whitespace-nowrap transition-colors"
              >
                <Copy size={16} />
                Copiar Grupos
              </button>
            {/if}
            <button
              onclick={() => {
                showNuevoGrupo = !showNuevoGrupo;
              }}
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-uta-blue text-white font-semibold text-sm rounded-lg hover:bg-uta-blue-hover disabled:opacity-60 whitespace-nowrap transition-colors"
            >
              <Plus size={18} />
              {actividad.es_grupal ? 'Nuevo Grupo' : 'Asignar Alumno'}
            </button>
          </div>
        {/if}
      </div>
    </div>

    <!-- ── Nuevo Grupo Panel ──────────────────────────────────────── -->
    {#if showNuevoGrupo}
      <div class="bg-uta-blue-light border border-uta-blue/25 rounded-xl p-5 mb-6">
        <h3 class="font-semibold text-uta-blue mb-4 text-base">
          {actividad.es_grupal ? 'Crear nuevo grupo' : 'Asignar actividad individual'}
        </h3>
        <div class="flex flex-wrap gap-4 items-end">
          <div class="flex flex-col min-w-[180px]">
            <label class="text-xs font-semibold text-gray-700 mb-1" for="ng-estado"
              >Estado inicial *</label
            >
            <select
              id="ng-estado"
              bind:value={nuevoGrupoEstadoId}
              class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
            >
              {#each estados as e}
                <option value={e.id_estado}>{e.titulo}</option>
              {/each}
            </select>
          </div>

          <div class="flex flex-col min-w-[180px]">
            <label class="text-xs font-semibold text-gray-700 mb-1" for="ng-estudiante">
              {actividad.es_grupal ? 'Primer integrante (opcional)' : 'Alumno *'}
            </label>
            <select
              id="ng-estudiante"
              bind:value={nuevoGrupoEstudianteId}
              class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
            >
              <option value={0}>-- Seleccionar --</option>
              {#each estudiantesLibres() as e}
                <option value={e.id_estudiante}>{e.nombre_completo}</option>
              {/each}
            </select>
          </div>

          <div class="flex gap-2 items-center">
            <button
              onclick={crearGrupo}
              class="inline-flex items-center gap-2 px-4 py-2 bg-uta-blue text-white font-semibold text-sm rounded-lg hover:bg-uta-blue-hover disabled:opacity-60 transition-colors"
              disabled={isLoading}
            >
              <Save size={16} /> Crear
            </button>
            <button
              onclick={() => (showNuevoGrupo = false)}
              class="px-4 py-2 bg-transparent border border-gray-300 rounded-md text-gray-700 text-sm font-medium cursor-pointer hover:bg-gray-100"
              >Cancelar</button
            >
          </div>
        </div>
      </div>
    {/if}

    <!-- ── Sin grupos ─────────────────────────────────────────────── -->
    {#if grupos.length === 0}
      <div class="text-center py-16 bg-white border border-gray-200 rounded-xl">
        <div class="flex justify-center mb-4 text-gray-300">
          <ClipboardList size={48} />
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-1">Sin grupos asignados</h3>
        <p class="text-gray-500 text-sm">
          Crea el primer grupo para comenzar a evaluar esta actividad.
        </p>
      </div>
    {/if}

    <!-- ── Lista de grupos ────────────────────────────────────────── -->
    {#each grupos as grupo, gi (grupo.grupo)}
      <div class="bg-white border border-gray-200 rounded-xl mb-5 overflow-hidden">
        <!-- Encabezado del grupo -->
        <div class="bg-slate-50 border-b border-gray-200 px-5 py-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-bold text-gray-900">
              {actividad.es_grupal
                ? `Grupo #${gi + 1}`
                : (grupo.integrantes[0]?.nombre_completo ?? `Alumno #${gi + 1}`)}
            </h3>
            {#if canDeleteGroup}
              <button
                onclick={() => eliminarGrupo(grupo.grupo)}
                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 rounded-lg text-red-500 hover:bg-red-50 hover:border-red-400 text-xs font-medium transition-colors"
                title="Eliminar grupo"
              >
                <Trash2 size={14} />
                Eliminar
              </button>
            {/if}
          </div>

          <!-- Nota y estado del grupo -->
          <div class="flex flex-wrap gap-4 items-end">
            <div class="flex flex-col">
              <label
                class="text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wider"
                for="nota-grupo-{grupo.grupo}">Nota grupal</label
              >
              <input
                id="nota-grupo-{grupo.grupo}"
                type="number"
                min="0"
                max="10"
                step="0.1"
                bind:value={grupo.nota}
                class="w-[90px] px-2 py-2 border border-gray-300 rounded-md text-sm text-center focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
                placeholder="—"
              />
            </div>

            <div class="flex flex-col">
              <label
                class="text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wider"
                for="estado-grupo-{grupo.grupo}">Estado</label
              >
              <select
                id="estado-grupo-{grupo.grupo}"
                bind:value={grupo.id_estado}
                class="px-3 py-2 border border-gray-300 rounded-md text-sm min-w-[150px] focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
              >
                <option value={null}>Sin estado</option>
                {#each estados as e}
                  <option value={e.id_estado}>{e.titulo}</option>
                {/each}
              </select>
            </div>

            {#if canEditGroup}
              <button
                onclick={() => guardarGrupo(grupo)}
                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 disabled:opacity-60 whitespace-nowrap transition-colors"
                disabled={savingGrupo.has(grupo.grupo)}
              >
                <Save size={14} />
                {savingGrupo.has(grupo.grupo) ? 'Guardando…' : 'Guardar'}
              </button>
            {/if}
          </div>
        </div>

        <!-- Tabla de integrantes -->
        <div class="px-5 py-4">
          <div class="flex justify-between items-center mb-3">
            <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wider">
              Integrantes ({grupo.integrantes.length})
            </h4>
            {#if actividad.es_grupal && canCreateGroup}
              <button
                onclick={() => {
                  addingToGrupo = addingToGrupo === grupo.grupo ? null : grupo.grupo;
                  addEstudianteId = 0;
                }}
                class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 rounded-lg text-xs font-medium text-gray-700 hover:bg-uta-blue-light hover:border-uta-blue hover:text-uta-blue transition-colors"
              >
                <UserPlus size={14} /> Agregar
              </button>
            {/if}
          </div>

          <!-- Panel agregar integrante -->
          {#if addingToGrupo === grupo.grupo}
            <div class="flex gap-2 items-center flex-wrap bg-green-50 p-3 rounded-lg mb-3">
              <select
                bind:value={addEstudianteId}
                class="px-2 py-1.5 border border-gray-300 rounded-md text-xs bg-white focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
              >
                <option value={0}>-- Seleccionar alumno --</option>
                {#each estudiantesLibres(grupo.grupo) as e}
                  <option value={e.id_estudiante}>{e.nombre_completo}</option>
                {/each}
              </select>
              <button
                onclick={() => agregarIntegrante(grupo.grupo)}
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-uta-blue text-white text-xs font-semibold rounded-lg hover:bg-uta-blue-hover disabled:opacity-60 transition-colors"
                disabled={!addEstudianteId || isLoading}
              >
                Agregar
              </button>
              <button
                onclick={() => {
                  addingToGrupo = null;
                }}
                class="px-3 py-1.5 bg-transparent border border-gray-300 rounded-md text-gray-700 text-xs font-medium cursor-pointer hover:bg-gray-100"
                >Cancelar</button
              >
            </div>
          {/if}

          {#if grupo.integrantes.length === 0}
            <p class="text-center text-gray-400 text-sm py-4">Sin integrantes asignados.</p>
          {:else}
            <div class="overflow-x-auto -mx-5 px-5">
              <table class="w-full border-collapse text-sm">
                <thead>
                  <tr>
                    <th
                      class="text-left px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200"
                      >Nombre</th
                    >
                    <th
                      class="text-left px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200 w-[110px]"
                      >Nota individual</th
                    >
                    <th
                      class="text-left px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200 w-[110px]"
                      >Dif. décimas</th
                    >
                    <th
                      class="text-left px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200 w-[80px]"
                    ></th>
                  </tr>
                </thead>
                <tbody>
                  {#each grupo.integrantes as integrante (integrante.id_asignado_actividad)}
                    <tr class="hover:bg-gray-50">
                      <td class="px-3 py-2 border-b border-gray-100 font-medium text-gray-900"
                        >{integrante.nombre_completo}</td
                      >
                      <td class="px-3 py-2 border-b border-gray-100">
                        <input
                          type="number"
                          min="0"
                          max="10"
                          step="0.1"
                          bind:value={integrante.nota_individual}
                          class="w-[75px] px-2 py-1.5 border border-gray-300 rounded-md text-xs text-center focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
                          placeholder="—"
                        />
                      </td>
                      <td class="px-3 py-2 border-b border-gray-100">
                        <input
                          type="number"
                          min="-10"
                          max="10"
                          step="1"
                          bind:value={integrante.diferencia_decimas}
                          class="w-[75px] px-2 py-1.5 border border-gray-300 rounded-md text-xs text-center focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
                          placeholder="0"
                        />
                      </td>
                      <td class="px-3 py-2 border-b border-gray-100">
                        <div class="flex gap-1 justify-end">
                          <button
                            onclick={() => guardarIntegrante(grupo.grupo, integrante)}
                            class="min-w-[40px] min-h-[40px] flex items-center justify-center bg-transparent border border-green-300 rounded-lg text-green-600 hover:bg-green-50 disabled:opacity-50"
                            title="Guardar nota"
                            disabled={savingIntegrante.has(integrante.id_asignado_actividad)}
                          >
                            <Save size={16} />
                          </button>
                          {#if actividad.es_grupal}
                            <button
                              onclick={() =>
                                eliminarIntegrante(grupo.grupo, integrante.id_asignado_actividad)}
                              class="min-w-[40px] min-h-[40px] flex items-center justify-center bg-transparent border border-red-300 rounded-lg text-red-500 hover:bg-red-50"
                              title="Quitar del grupo"
                            >
                              <Trash2 size={16} />
                            </button>
                          {/if}
                        </div>
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </div>

        <!-- ── Entregas del grupo ─────────────────────────────── -->
        <div class="border-t border-gray-100">
          <button
            onclick={() => toggleEntregas(grupo.grupo)}
            class="w-full flex items-center justify-between px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider hover:bg-gray-50 transition-colors"
          >
            <span class="flex items-center gap-2">
              <FileText size={14} />
              Entregas
              {#if entregasByGrupo.has(grupo.grupo)}
                <span class="font-bold text-uta-blue"
                  >({entregasByGrupo.get(grupo.grupo)?.length ?? 0})</span
                >
              {/if}
            </span>
            {#if expandedEntregas.has(grupo.grupo)}
              <ChevronUp size={14} />
            {:else}
              <ChevronDown size={14} />
            {/if}
          </button>

          {#if expandedEntregas.has(grupo.grupo)}
            <div class="px-5 pb-4">
              {#if loadingEntregas.has(grupo.grupo)}
                <p class="text-center text-gray-400 text-sm py-4">Cargando entregas…</p>
              {:else if (entregasByGrupo.get(grupo.grupo) ?? []).length === 0}
                <p class="text-center text-gray-400 text-sm py-4">Sin entregas registradas.</p>
              {:else}
                <div class="flex flex-col gap-2">
                  {#each entregasByGrupo.get(grupo.grupo) ?? [] as entrega (entrega.id_agenda)}
                    <div
                      class="flex items-start justify-between gap-3 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3"
                    >
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                          <span class="text-xs font-semibold text-gray-500">
                            {new Date(entrega.fecha_envio).toLocaleString('es-ES', {
                              day: '2-digit',
                              month: 'short',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit',
                            })}
                          </span>
                          {#if entrega.evaluada}
                            <span
                              class="text-xs font-semibold px-2 py-0.5 bg-green-100 text-green-700 rounded-full"
                              >Evaluada</span
                            >
                          {:else}
                            <span
                              class="text-xs font-semibold px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full"
                              >Pendiente</span
                            >
                          {/if}
                        </div>
                        {#if entrega.mensaje}
                          <p class="text-sm text-gray-700 truncate">{entrega.mensaje}</p>
                        {/if}
                        {#if entrega.archivo}
                          <p class="text-xs text-gray-500 mt-1 truncate">
                            📎 {entrega.archivo.nombre_original}
                            {#if entrega.archivo.tamanio_bytes}
                              <span class="ml-1"
                                >({formatBytes(entrega.archivo.tamanio_bytes)})</span
                              >
                            {/if}
                          </p>
                        {/if}
                      </div>
                      {#if entrega.archivo}
                        <a
                          href={downloadUrl(grupo.grupo, entrega.id_agenda)}
                          class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-uta-blue/40 text-uta-blue text-xs font-semibold rounded-lg hover:bg-uta-blue-light transition-colors"
                          download
                        >
                          <Download size={13} />
                          Descargar
                        </a>
                      {/if}
                    </div>
                  {/each}
                </div>
              {/if}
            </div>
          {/if}
        </div>

        <!-- ── Mensajes/Feedback del grupo (nivel 2) ─────────────────── -->
        <div class="border-t border-gray-100">
          <button
            onclick={() => toggleMensajes(grupo.grupo)}
            class="w-full flex items-center justify-between px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider hover:bg-gray-50 transition-colors"
          >
            <span class="flex items-center gap-2">
              <MessageSquare size={14} />
              Mensajes / Feedback
              {#if mensajesByGrupo.has(grupo.grupo)}
                <span class="font-bold text-uta-blue">
                  ({mensajesByGrupo.get(grupo.grupo)?.length ?? 0})
                </span>
              {/if}
            </span>
            {#if expandedMensajes.has(grupo.grupo)}
              <ChevronUp size={14} />
            {:else}
              <ChevronDown size={14} />
            {/if}
          </button>

          {#if expandedMensajes.has(grupo.grupo)}
            <div class="px-5 pb-4">
              {#if loadingMensajes.has(grupo.grupo)}
                <div class="flex items-center justify-center gap-2 py-6 text-gray-400 text-sm">
                  <Loader2 size={15} class="animate-spin" />
                  Cargando mensajes…
                </div>
              {:else if (mensajesByGrupo.get(grupo.grupo) ?? []).length === 0}
                <p class="text-center text-gray-400 text-sm py-4">Sin mensajes en este grupo.</p>
              {:else}
                <!-- Hilo de mensajes -->
                <div class="flex flex-col gap-2 mb-3">
                  {#each mensajesByGrupo.get(grupo.grupo) ?? [] as msg (msg.id_agenda)}
                    {@const esFeedback = msg.tipo_registro === 'Feedback'}
                    <div class="flex {esFeedback ? 'justify-end' : 'justify-start'}">
                      <div
                        class="max-w-[75%] rounded-xl px-3.5 py-2.5 text-sm
                          {esFeedback
                          ? 'bg-uta-blue text-white rounded-br-none shadow-sm'
                          : 'bg-gray-100 text-gray-800 rounded-bl-none'}"
                      >
                        <p
                          class="text-[10px] font-semibold mb-0.5 {esFeedback
                            ? 'text-white/60'
                            : 'text-gray-500'}"
                        >
                          {esFeedback ? 'Docente' : msg.emisor_nombre}
                        </p>
                        <p class="leading-relaxed">{msg.mensaje}</p>
                        <p
                          class="text-[10px] mt-1 {esFeedback
                            ? 'text-white/50'
                            : 'text-gray-400'} text-right"
                        >
                          {new Date(msg.fecha_envio).toLocaleString('es-CL', {
                            day: '2-digit',
                            month: 'short',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </p>
                      </div>
                    </div>
                  {/each}
                </div>
              {/if}

              <!-- Formulario de feedback -->
              {#if feedbackError.has(grupo.grupo)}
                <p class="text-xs text-red-500 mb-1">{feedbackError.get(grupo.grupo)}</p>
              {/if}
              <div class="flex gap-2 items-end">
                <textarea
                  value={replyByGrupo.get(grupo.grupo) ?? ''}
                  oninput={(e) => {
                    const m = new Map(replyByGrupo);
                    m.set(grupo.grupo, (e.target as HTMLTextAreaElement).value);
                    replyByGrupo = m;
                  }}
                  rows={2}
                  placeholder="Escribe feedback para el grupo…"
                  class="flex-1 resize-none px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/15 transition-shadow"
                ></textarea>
                <button
                  onclick={() => enviarFeedbackGrupo(grupo.grupo)}
                  disabled={!replyByGrupo.get(grupo.grupo)?.trim() ||
                    sendingFeedback.has(grupo.grupo)}
                  class="p-2 rounded-lg bg-uta-blue text-white hover:bg-uta-blue-hover disabled:opacity-50 transition-colors shrink-0"
                  title="Enviar feedback"
                >
                  {#if sendingFeedback.has(grupo.grupo)}
                    <Loader2 size={16} class="animate-spin" />
                  {:else}
                    <Send size={16} />
                  {/if}
                </button>
              </div>
            </div>
          {/if}
        </div>
      </div>
    {/each}
  </div>

  <!-- ── Modal: Copiar Grupos ───────────────────────────────────────── -->
  {#if showCopyModal}
    <!-- Backdrop -->
    <div
      class="fixed inset-0 bg-black/40 z-40"
      role="presentation"
      onclick={() => (showCopyModal = false)}
    ></div>

    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-5">
          <h2 class="text-lg font-bold text-gray-900">Copiar Grupos desde Actividad</h2>
          <button
            onclick={() => (showCopyModal = false)}
            class="p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100"
          >
            <X size={18} />
          </button>
        </div>

        <p class="text-sm text-gray-600 mb-4">
          Selecciona una actividad grupal del mismo curso. Se copiarán los grupos cuyos integrantes
          sigan inscritos en el curso.
        </p>

        {#if isFetchingActividades}
          <p class="text-center text-gray-500 py-4 text-sm">Cargando actividades…</p>
        {:else if actividadesDisponibles.length === 0}
          <p class="text-center text-gray-400 py-4 text-sm">
            No hay otras actividades grupales en este curso.
          </p>
        {:else}
          <div class="mb-5">
            <label class="block text-xs font-semibold text-gray-700 mb-1" for="origen-actividad">
              Actividad origen
            </label>
            <select
              id="origen-actividad"
              bind:value={actividadOrigenId}
              class="w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
            >
              <option value={0}>-- Seleccionar actividad --</option>
              {#each actividadesDisponibles as a}
                <option value={a.id_actividad}>{a.nombre}</option>
              {/each}
            </select>
          </div>
        {/if}

        {#if copyMessage}
          <div
            class="mb-4 px-4 py-3 rounded-lg text-sm font-medium {copyMessage.type === 'success'
              ? 'bg-green-50 text-green-800 border border-green-200'
              : 'bg-red-50 text-red-800 border border-red-200'}"
          >
            {copyMessage.text}
          </div>
        {/if}

        <div class="flex gap-3 justify-end">
          <button
            onclick={() => (showCopyModal = false)}
            class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Cancelar
          </button>
          <button
            onclick={copiarGrupos}
            disabled={!actividadOrigenId || isCopying || isFetchingActividades}
            class="inline-flex items-center gap-2 px-4 py-2 bg-uta-blue text-white text-sm font-semibold rounded-lg hover:bg-uta-blue-hover disabled:opacity-50 transition-colors"
          >
            <Copy size={15} />
            {isCopying ? 'Copiando…' : 'Copiar Grupos'}
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- ── Modal: Confirmación destructiva ────────────────────────────────────── -->
  {#if confirmDialog.open}
    <div class="fixed inset-0 bg-black/40 z-40" role="presentation" onclick={closeConfirm}></div>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none">
      <div
        class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 pointer-events-auto"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        aria-describedby="confirm-dialog-body"
      >
        <div class="flex items-start gap-4 mb-5">
          <div
            class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0 mt-0.5"
          >
            <Trash2 size={18} class="text-red-600" />
          </div>
          <div>
            <h2 id="confirm-dialog-title" class="text-base font-bold text-gray-900">
              {confirmDialog.title}
            </h2>
            <p id="confirm-dialog-body" class="text-sm text-gray-500 mt-1">{confirmDialog.body}</p>
          </div>
        </div>
        <div class="flex gap-3 justify-end">
          <button
            onclick={closeConfirm}
            class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Cancelar
          </button>
          <button
            onclick={() => {
              confirmDialog.onConfirm?.();
              closeConfirm();
            }}
            class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors"
          >
            <Trash2 size={14} />
            Confirmar
          </button>
        </div>
      </div>
    </div>
  {/if}
</DocenteLayout>
