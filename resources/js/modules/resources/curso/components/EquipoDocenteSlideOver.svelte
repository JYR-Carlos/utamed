<script lang="ts">
  /**
   * EquipoDocenteSlideOver
   * Panel lateral derecho para que el Docente Titular gestione su equipo y permisos.
   *
   * - Lista de miembros como acordeones expansibles.
   * - Toggle switches (estilo iOS) para cada permiso, agrupados lógicamente.
   * - Búsqueda predictiva para añadir ayudantes/co-docentes.
   * - "Remover del equipo" con confirmación inline.
   */
  import { router } from '@inertiajs/svelte';
  import {
    X,
    Plus,
    ChevronDown,
    Crown,
    AlertTriangle,
    Loader2,
    Search,
    UserMinus,
    UsersRound,
  } from 'lucide-svelte';
  import {
    getSyllabusPermisos,
    syncSyllabusPermiso,
    type DocenteConPermisos,
    type PermisoSlug,
  } from '../services/permisosApi';
  import { initials } from '@/utils/formatters';

  interface Props {
    isOpen: boolean;
    onClose: () => void;
    cursoId: number;
    cursoNombre: string;
    cursoCodigo?: string;
    basePath?: string;
  }

  let {
    isOpen = $bindable(),
    onClose,
    cursoId,
    cursoNombre,
    cursoCodigo = '',
    basePath = '/docente',
  }: Props = $props();

  // ─── Estado principal ────────────────────────────────────────────────────
  let docentes = $state<DocenteConPermisos[]>([]);
  let slugs = $state<PermisoSlug[]>([]);
  let loading = $state(false);
  let error = $state<string | null>(null);

  // IDs de tarjetas expandidas (como número[])
  let expandedIds = $state<number[]>([]);
  let togglingKey = $state<string | null>(null);
  let removingId = $state<number | null>(null);

  // ─── Buscar / añadir miembro ─────────────────────────────────────────────
  let showAddSearch = $state(false);
  let searchTerm = $state('');
  let searchResults = $state<any[]>([]);
  let isSearching = $state(false);
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;

  // ─── Mapa de etiquetas y grupos de permisos ──────────────────────────────
  const PERM_LABEL: Record<string, string> = {
    'actividades:evaluar': 'Ingresar Notas',
    'actividades:editar': 'Crear / Editar Actividades',
    'componentes/asistencia:registrar': 'Registrar Asistencia',
    'componentes/asistencia:editar': 'Editar Asistencia',
    'syllabus:editar': 'Modificar Syllabus',
  };

  const PERM_GROUP: Record<string, string> = {
    'actividades:evaluar': 'Evaluaciones',
    'componentes/asistencia:registrar': 'Evaluaciones',
    'componentes/asistencia:editar': 'Evaluaciones',
    'actividades:editar': 'Contenido',
    'syllabus:editar': 'Contenido',
  };

  // Permisos críticos que muestran ícono de advertencia ámbar
  const PERM_CRITICAL = new Set(['syllabus:editar']);

  // ─── Carga de datos ──────────────────────────────────────────────────────
  $effect(() => {
    if (isOpen && cursoId) {
      loadData();
    }
    if (!isOpen) {
      expandedIds = [];
      showAddSearch = false;
      searchTerm = '';
      searchResults = [];
      error = null;
    }
  });

  async function loadData() {
    loading = true;
    error = null;
    try {
      const res = await getSyllabusPermisos(cursoId, basePath);
      docentes = res.docentes;
      slugs = res.slugs_disponibles;
    } catch (e: any) {
      error = e.message ?? 'Error al cargar el equipo';
    } finally {
      loading = false;
    }
  }

  // ─── Helpers ─────────────────────────────────────────────────────────────
  function permLabel(slug: string): string {
    return PERM_LABEL[slug] ?? slug;
  }

  function permGroup(slug: string): string {
    return PERM_GROUP[slug] ?? 'Otros';
  }

  function countPermisos(doc: DocenteConPermisos): number {
    return Object.values(doc.permisos).filter(Boolean).length;
  }

  // ─── Grupos de slugs (derivado) ──────────────────────────────────────────
  const groupedSlugs = $derived.by(() => {
    const groups: Record<string, PermisoSlug[]> = {};
    for (const s of slugs) {
      const g = permGroup(s.slug);
      if (!groups[g]) groups[g] = [];
      groups[g].push(s);
    }
    return groups;
  });

  // ─── Acordeón ────────────────────────────────────────────────────────────
  function toggleExpand(id: number) {
    if (expandedIds.includes(id)) {
      expandedIds = expandedIds.filter((x) => x !== id);
    } else {
      expandedIds = [...expandedIds, id];
    }
  }

  // ─── Toggle de permiso (optimistic update) ───────────────────────────────
  async function togglePerm(doc: DocenteConPermisos, slug: PermisoSlug) {
    if (doc.es_titular) return; // titular siempre tiene todos
    const key = `${doc.id_usuario}-${slug.id_permiso}`;
    const current = doc.permisos[slug.id_permiso] ?? false;
    togglingKey = key;

    // Optimistic update
    const idx = docentes.findIndex((d) => d.id_usuario === doc.id_usuario);
    if (idx !== -1) {
      docentes[idx] = {
        ...docentes[idx],
        permisos: { ...docentes[idx].permisos, [slug.id_permiso]: !current },
      };
    }

    try {
      await syncSyllabusPermiso(
        cursoId,
        { id_usuario: doc.id_usuario, slug: slug.slug, otorgar: !current },
        basePath,
      );
    } catch {
      // Rollback
      if (idx !== -1) {
        docentes[idx] = {
          ...docentes[idx],
          permisos: { ...docentes[idx].permisos, [slug.id_permiso]: current },
        };
      }
    } finally {
      togglingKey = null;
    }
  }

  // ─── Remover miembro ─────────────────────────────────────────────────────
  function removeMember(doc: DocenteConPermisos) {
    if (!confirm(`¿Quitar a ${doc.nombre} del equipo del curso?`)) return;
    removingId = doc.id_usuario;
    router.delete(`${basePath}/cursos/${cursoId}/team/${doc.id_usuario}`, {
      preserveScroll: true,
      onSuccess: () => loadData(),
      onFinish: () => {
        removingId = null;
      },
    });
  }

  // ─── Búsqueda predictiva ─────────────────────────────────────────────────
  function onSearchInput() {
    if (debounceTimer) clearTimeout(debounceTimer);
    searchResults = [];
    if (searchTerm.length >= 3) {
      debounceTimer = setTimeout(doSearch, 350);
    }
  }

  async function doSearch() {
    isSearching = true;
    try {
      const res = await fetch(
        `${basePath}/cursos/${cursoId}/team/search-assistants?search=${encodeURIComponent(searchTerm)}`,
        { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
      );
      if (!res.ok) return;
      const data = await res.json();
      searchResults = Array.isArray(data) ? data : [];
    } catch {
      searchResults = [];
    } finally {
      isSearching = false;
    }
  }

  function addMember(user: any) {
    router.post(
      `${basePath}/cursos/${cursoId}/team`,
      { id_usuario: user.id_usuario, role_name: 'ayudante' },
      {
        preserveScroll: true,
        onSuccess: () => {
          searchTerm = '';
          searchResults = [];
          showAddSearch = false;
          loadData();
        },
      },
    );
  }
</script>

{#if isOpen}
  <!-- ── Overlay 30 % ──────────────────────────────────────────────────────── -->
  <div
    class="fixed inset-0 z-40"
    style="background:rgba(0,0,0,0.30); backdrop-filter:blur(1px);"
    onclick={onClose}
    role="presentation"
    aria-hidden="true"
  ></div>

  <!-- ── Panel lateral ─────────────────────────────────────────────────────── -->
  <div
    class="slide-over fixed inset-y-0 right-0 z-50 flex flex-col bg-white shadow-2xl"
    style="width:min(35vw,520px); min-width:340px;"
    role="dialog"
    aria-modal="true"
    aria-label="Equipo Docente"
  >
    <!-- ════════════════════════════════════
         CABECERA STICKY
         ════════════════════════════════════ -->
    <div
      class="sticky top-0 z-10 bg-white border-b border-gray-200 shrink-0"
      style="box-shadow:0 1px 6px rgba(0,0,0,0.06);"
    >
      <div class="px-5 py-4">
        <div class="flex items-start gap-3">
          <!-- Icono del panel -->
          <div
            class="flex items-center justify-center w-9 h-9 rounded-xl shrink-0 mt-0.5"
            style="background:#EEF4FB;"
          >
            <UsersRound size={17} style="color:#2A66AC;" />
          </div>

          <!-- Título + subtítulo -->
          <div class="flex-1 min-w-0">
            <h2 class="text-base font-bold text-gray-900 leading-tight">Equipo Docente</h2>
            <p class="text-xs text-gray-400 mt-0.5 truncate">
              {cursoNombre}{cursoCodigo ? ` · ${cursoCodigo}` : ''}
            </p>
          </div>

          <!-- Cerrar -->
          <button
            onclick={onClose}
            class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition shrink-0"
            aria-label="Cerrar panel"
          >
            <X size={16} />
          </button>
        </div>

        <!-- Buscador / Añadir miembro -->
        <div class="mt-3.5">
          {#if !showAddSearch}
            <button
              onclick={() => (showAddSearch = true)}
              class="inline-flex items-center gap-2 px-3.5 py-1.5 text-sm font-medium rounded-lg border transition-colors"
              style="border-color:#2A66AC; color:#2A66AC;"
              onmouseenter={(e) => ((e.currentTarget as HTMLElement).style.background = '#EEF4FB')}
              onmouseleave={(e) =>
                ((e.currentTarget as HTMLElement).style.background = 'transparent')}
            >
              <Plus size={13} />
              Añadir Ayudante / Co-Docente
            </button>
          {:else}
            <div class="relative">
              <!-- Input de búsqueda -->
              <div
                class="flex items-center gap-2 px-3 py-2 border rounded-lg bg-white"
                style="border-color:#2A66AC; box-shadow:0 0 0 3px rgba(42,102,172,0.08);"
              >
                <Search size={13} class="text-gray-400 shrink-0" />
                <input
                  type="text"
                  bind:value={searchTerm}
                  oninput={onSearchInput}
                  placeholder="Buscar docente por nombre..."
                  class="flex-1 text-sm bg-transparent outline-none placeholder:text-gray-400 min-w-0"
                />
                <button
                  onclick={() => {
                    showAddSearch = false;
                    searchTerm = '';
                    searchResults = [];
                  }}
                  class="text-gray-400 hover:text-gray-600 transition shrink-0"
                  aria-label="Cancelar búsqueda"
                >
                  <X size={13} />
                </button>
              </div>

              <!-- Resultados -->
              {#if isSearching}
                <div
                  class="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-gray-200 shadow-lg p-3 flex items-center justify-center gap-2 text-xs text-gray-400 z-20"
                >
                  <Loader2 size={12} class="animate-spin" />
                  Buscando...
                </div>
              {:else if searchResults.length > 0}
                <div
                  class="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden z-20 max-h-52 overflow-y-auto"
                >
                  {#each searchResults as user}
                    <button
                      onclick={() => addMember(user)}
                      class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-blue-50 transition-colors text-left"
                    >
                      <div
                        class="flex items-center justify-center w-7 h-7 rounded-full text-[11px] font-bold text-white shrink-0"
                        style="background:#2A66AC;"
                      >
                        {initials(user.nombre ?? user.nombre_completo ?? '?')}
                      </div>
                      <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">
                          {user.nombre ?? user.nombre_completo}
                        </p>
                        <p class="text-xs text-gray-400">{user.username ?? user.email ?? ''}</p>
                      </div>
                      <Plus size={13} class="text-gray-400 shrink-0 ml-auto" />
                    </button>
                  {/each}
                </div>
              {:else if searchTerm.length >= 3}
                <div
                  class="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-gray-200 shadow-lg p-3 text-xs text-gray-400 text-center z-20"
                >
                  Sin resultados para "{searchTerm}"
                </div>
              {/if}
            </div>
          {/if}
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════
         CUERPO — Lista de miembros
         ════════════════════════════════════ -->
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-2">
      {#if loading}
        <div class="flex flex-col items-center justify-center py-20 gap-3 text-gray-400">
          <Loader2 size={22} class="animate-spin" style="color:#2A66AC;" />
          <p class="text-sm">Cargando equipo...</p>
        </div>
      {:else if error}
        <div class="flex flex-col items-center justify-center py-14 text-center gap-3">
          <p class="text-sm text-red-500">{error}</p>
          <button
            onclick={loadData}
            class="text-xs font-medium transition hover:underline"
            style="color:#2A66AC;">Reintentar</button
          >
        </div>
      {:else if docentes.length === 0}
        <div
          class="flex flex-col items-center justify-center py-20 text-gray-400 text-center gap-2"
        >
          <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-1">
            <UsersRound size={26} class="text-gray-300" />
          </div>
          <p class="text-sm font-medium text-gray-500">Sin docentes en el equipo</p>
          <p class="text-xs text-gray-400">Añade un ayudante o co-docente para comenzar.</p>
        </div>
      {:else}
        <!-- Contador total -->
        <p class="text-xs text-gray-400 px-1 pb-1">
          {docentes.length} miembro{docentes.length !== 1 ? 's' : ''} en el equipo
        </p>

        {#each docentes as doc (doc.id_usuario)}
          {@const expanded = expandedIds.includes(doc.id_usuario)}
          {@const permCount = countPermisos(doc)}

          <div
            class="rounded-xl border overflow-hidden transition-all duration-200"
            style="border-color:{expanded ? '#BFDBFE' : '#E2E8F0'};"
          >
            <!-- ── Tarjeta contraída ──────────────────────────────────────── -->
            <button
              onclick={() => toggleExpand(doc.id_usuario)}
              class="w-full flex items-center gap-3 px-4 py-3.5 bg-white hover:bg-gray-50 transition-colors text-left"
              aria-expanded={expanded}
            >
              <!-- Avatar -->
              <div
                class="flex items-center justify-center w-10 h-10 rounded-full text-sm font-bold text-white shrink-0"
                style="background:#2A66AC;"
              >
                {initials(doc.nombre)}
              </div>

              <!-- Nombre + badge + resumen -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-sm font-semibold text-gray-900 truncate">{doc.nombre}</span>
                  {#if doc.es_titular}
                    <span
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold leading-none"
                      style="background:#FFF8EC; color:#F0AD4E; border:1px solid rgba(240,173,78,0.3);"
                    >
                      <Crown size={9} />
                      Titular
                    </span>
                  {:else}
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-600 border border-blue-100"
                    >
                      Co-Docente
                    </span>
                  {/if}
                </div>
                <p class="text-xs text-gray-400 mt-0.5">
                  {#if doc.es_titular}
                    Todos los permisos activos
                  {:else}
                    {permCount} permiso{permCount !== 1 ? 's' : ''} asignado{permCount !== 1
                      ? 's'
                      : ''}
                  {/if}
                </p>
              </div>

              <!-- Chevron animado -->
              <div
                class="shrink-0 text-gray-400 transition-transform duration-200"
                style={expanded ? 'transform:rotate(180deg);' : 'transform:rotate(0deg);'}
              >
                <ChevronDown size={16} />
              </div>
            </button>

            <!-- ── Tarjeta expandida: gestión de permisos ────────────────── -->
            {#if expanded}
              <div class="border-t border-gray-100 px-4 py-4 space-y-5" style="background:#F8FAFC;">
                {#if doc.es_titular}
                  <!-- Titular: solo información, sin toggles -->
                  <p class="text-xs text-gray-400 italic">
                    El Titular tiene acceso completo a todos los permisos del curso. No se pueden
                    modificar individualmente.
                  </p>
                {:else}
                  <!-- Permisos agrupados -->
                  {#each Object.entries(groupedSlugs) as [groupName, groupSlugs]}
                    <div>
                      <!-- Encabezado de grupo -->
                      <p
                        class="text-[10px] font-extrabold uppercase tracking-widest mb-3"
                        style="color:#94A3B8;"
                      >
                        {groupName}
                      </p>

                      <div class="space-y-3">
                        {#each groupSlugs as slug}
                          {@const isOn = doc.permisos[slug.id_permiso] ?? false}
                          {@const key = `${doc.id_usuario}-${slug.id_permiso}`}
                          {@const isCritical = PERM_CRITICAL.has(slug.slug)}
                          {@const isToggling = togglingKey === key}

                          <div class="flex items-center justify-between gap-3">
                            <!-- Etiqueta del permiso -->
                            <div class="flex items-center gap-1.5 min-w-0">
                              <span class="text-sm text-gray-700">{permLabel(slug.slug)}</span>
                              {#if isCritical}
                                <span
                                  title="Acción crítica: permite editar el Syllabus del curso"
                                  class="shrink-0"
                                >
                                  <AlertTriangle size={13} style="color:#F0AD4E;" />
                                </span>
                              {/if}
                            </div>

                            <!-- Toggle switch estilo iOS -->
                            <button
                              role="switch"
                              aria-checked={isOn}
                              onclick={() => togglePerm(doc, slug)}
                              disabled={isToggling}
                              class="relative shrink-0 w-11 h-6 rounded-full transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 disabled:cursor-wait"
                              style="background:{isOn
                                ? '#2A66AC'
                                : '#CBD5E1'}; --tw-ring-color:#2A66AC;"
                              aria-label="{permLabel(slug.slug)}: {isOn
                                ? 'Activado'
                                : 'Desactivado'}"
                            >
                              <!-- Thumb -->
                              <span
                                class="absolute top-[3px] left-[3px] w-[18px] h-[18px] bg-white rounded-full shadow-md transition-transform duration-200"
                                style={isOn ? 'transform:translateX(20px);' : ''}
                              ></span>
                              <!-- Spinner de guardado -->
                              {#if isToggling}
                                <span class="absolute inset-0 flex items-center justify-center">
                                  <span
                                    class="w-3 h-3 rounded-full border-2 border-white/60 border-t-transparent animate-spin"
                                  ></span>
                                </span>
                              {/if}
                            </button>
                          </div>
                        {/each}
                      </div>
                    </div>
                  {/each}
                {/if}

                <!-- Remover del equipo -->
                {#if !doc.es_titular}
                  <div class="pt-3 border-t border-gray-200">
                    <button
                      onclick={() => removeMember(doc)}
                      disabled={removingId === doc.id_usuario}
                      class="inline-flex items-center gap-1.5 text-xs font-medium text-red-400 hover:text-red-600 transition-colors disabled:opacity-50"
                    >
                      {#if removingId === doc.id_usuario}
                        <Loader2 size={12} class="animate-spin" />
                        Removiendo...
                      {:else}
                        <UserMinus size={12} />
                        Remover del equipo
                      {/if}
                    </button>
                  </div>
                {/if}
              </div>
            {/if}
          </div>
        {/each}
      {/if}
    </div>
  </div>
{/if}

<style>
  .slide-over {
    animation: slide-in 220ms cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
  }

  @keyframes slide-in {
    from {
      transform: translateX(100%);
      opacity: 0.6;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
</style>
