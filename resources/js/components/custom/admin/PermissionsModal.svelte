<script lang="ts">
  import type { UsuarioData } from '@/types';

  // ─── Types ────────────────────────────────────────────────────────────
  interface Props {
    isOpen: boolean;
    onClose: () => void;
    usuario: UsuarioData;
    /** @deprecated Use the wizard flow instead */
    availableRoles?: any[];
    /** @deprecated Use the wizard flow instead */
    availablePermissions?: Record<string, any[]>;
    loadPath?: string;
    savePath?: string;
    hideRoles?: boolean;
    isCourseContext?: boolean;
  }

  interface ContextType {
    key: string;
    label: string;
    description: string;
    count?: number;
  }

  interface ContextObject {
    id: number;
    label: string;
    context_id: number;
  }

  interface RoleItem {
    id_rol: number;
    nombre: string;
  }

  interface PermissionItem {
    id_permiso: number;
    slug: string;
    nombre: string;
    descripcion?: string;
    /** Tipos de contexto válidos para asignar este permiso (ej: ['GLOBAL', 'CURSO']) */
    valid_context_types?: string[];
  }

  type FlowType = 'role' | 'permission' | null;

  // ─── Props ────────────────────────────────────────────────────────────
  let {
    isOpen = $bindable(),
    onClose,
    usuario,
    availableRoles: _legacyRoles = [],
    availablePermissions: _legacyPerms = {},
    loadPath = '',
    savePath = '',
    hideRoles = false,
    isCourseContext = false,
  }: Props = $props();

  // ─── Wizard State ─────────────────────────────────────────────────────
  let currentStep = $state(1);
  let flow = $state<FlowType>(null);
  let slideDirection = $state<'forward' | 'backward'>('forward');
  let isAnimating = $state(false);

  // Step 2 selections
  let selectedRole = $state<RoleItem | null>(null);
  let selectedPermission = $state<PermissionItem | null>(null);

  // Step 3 selections (context)
  let selectedContextType = $state<ContextType | null>(null);
  let selectedContextObject = $state<ContextObject | null>(null);

  // Step 4 parameters
  let startDate = $state('');
  let endDate = $state('');
  let permAllowed = $state(true);
  let permCanDelegate = $state(false);

  // ─── Data from backend ────────────────────────────────────────────────
  let isLoading = $state(false);
  let isSaving = $state(false);
  let errorMsg = $state('');
  let successMsg = $state('');
  let roles = $state<RoleItem[]>([]);
  let permissions = $state<Record<string, PermissionItem[]>>({});
  let contextTypes = $state<ContextType[]>([]);
  let contextObjects = $state<ContextObject[]>([]);
  let contextObjectsLoading = $state(false);

  // ─── Search filters ───────────────────────────────────────────────────
  let roleSearch = $state('');
  let permSearch = $state('');
  let contextObjectSearch = $state('');

  // ─── Derived ──────────────────────────────────────────────────────────
  let totalSteps = $derived(4);

  let stepTitles = $derived.by(() => {
    switch (currentStep) {
      case 1:
        return {
          heading: '¿Qué tipo de asignación deseas realizar?',
          subtitle: 'Elige entre asignar un rol completo o un permiso especial individual',
        };
      case 2:
        return {
          heading: flow === 'role' ? 'Selecciona el rol a asignar' : 'Selecciona el permiso a asignar',
          subtitle:
            flow === 'role' ? 'Un rol agrupa varios permisos relacionados' : 'Un permiso especial otorga acceso granular a una acción específica',
        };
      case 3:
        return {
          heading: 'Selecciona el contexto de la asignación',
          subtitle: 'Define sobre qué recurso del sistema aplicará esta asignación',
        };
      case 4:
        return {
          heading: 'Configura los parámetros y confirma',
          subtitle: 'Revisa los detalles y establece las fechas de vigencia',
        };
      default:
        return { heading: '', subtitle: '' };
    }
  });

  let canGoNext = $derived.by(() => {
    switch (currentStep) {
      case 1:
        return flow !== null;
      case 2:
        return flow === 'role' ? selectedRole !== null : selectedPermission !== null;
      case 3:
        return selectedContextType !== null && (selectedContextType.key === 'GLOBAL' || selectedContextObject !== null);
      case 4:
        return true;
      default:
        return false;
    }
  });

  let filteredRoles = $derived.by(() => {
    if (!roleSearch.trim()) return roles;
    const q = roleSearch.toLowerCase();
    return roles.filter((r) => r.nombre.toLowerCase().includes(q));
  });

  let filteredPermissions = $derived.by(() => {
    if (!permSearch.trim()) return permissions;
    const q = permSearch.toLowerCase();
    const result: Record<string, PermissionItem[]> = {};
    for (const [mod, perms] of Object.entries(permissions)) {
      const filtered = perms.filter((p) => p.nombre.toLowerCase().includes(q) || p.slug.toLowerCase().includes(q));
      if (filtered.length > 0) result[mod] = filtered;
    }
    return result;
  });

  let filteredContextObjects = $derived.by(() => {
    if (!contextObjectSearch.trim()) return contextObjects;
    const q = contextObjectSearch.toLowerCase();
    return contextObjects.filter((o) => o.label.toLowerCase().includes(q));
  });

  /**
   * Cuando el flujo es 'permission' y hay un permiso seleccionado con valid_context_types,
   * filtra los tipos de contexto disponibles en el paso 3 para mostrar solo los válidos.
   * Para el flujo 'role', muestra todos los tipos (los roles son genéricos entre contextos).
   */
  let availableContextTypes = $derived.by(() => {
    if (flow !== 'permission' || !selectedPermission?.valid_context_types?.length) {
      return contextTypes;
    }
    return contextTypes.filter((ct) => selectedPermission.valid_context_types!.includes(ct.key));
  });

  let today = $derived(new Date().toISOString().split('T')[0]);

  // ─── Effects ──────────────────────────────────────────────────────────
  $effect(() => {
    if (isOpen && usuario?.id_usuario) {
      resetWizard();
      loadInitialData();
    }
  });

  // Auto-skip step 1 if hideRoles forces permission flow
  $effect(() => {
    if (hideRoles && currentStep === 1) {
      flow = 'permission';
    }
  });

  // Load context objects when context type changes
  let _lastCtxKey = '';
  $effect(() => {
    const key = selectedContextType?.key ?? '';
    if (key && key !== 'GLOBAL' && key !== _lastCtxKey) {
      _lastCtxKey = key;
      loadContextObjects(key);
    }
  });

  // Cuando el permiso cambia, resetear selectedContextType si ya no es válido
  $effect(() => {
    if (flow === 'permission' && selectedContextType && selectedPermission?.valid_context_types?.length) {
      if (!selectedPermission.valid_context_types.includes(selectedContextType.key)) {
        selectedContextType = null;
        selectedContextObject = null;
        contextObjects = [];
        _lastCtxKey = '';
      }
    }
  });

  // ─── Functions ────────────────────────────────────────────────────────
  function resetWizard() {
    currentStep = 1;
    flow = null;
    selectedRole = null;
    selectedPermission = null;
    selectedContextType = null;
    selectedContextObject = null;
    startDate = '';
    endDate = '';
    permAllowed = true;
    permCanDelegate = false;
    errorMsg = '';
    successMsg = '';
    roleSearch = '';
    permSearch = '';
    contextObjectSearch = '';
    _lastCtxKey = '';
  }

  function goNext() {
    if (!canGoNext || isAnimating) return;
    slideDirection = 'forward';
    isAnimating = true;
    setTimeout(() => {
      currentStep = Math.min(currentStep + 1, totalSteps);
      // Set default date when entering step 4
      if (currentStep === 4 && !startDate) {
        startDate = today;
      }
      setTimeout(() => (isAnimating = false), 50);
    }, 200);
  }

  function goBack() {
    if (currentStep <= 1 || isAnimating) return;
    slideDirection = 'backward';
    isAnimating = true;

    const prevStep = currentStep;
    setTimeout(() => {
      if (prevStep === 2) {
        flow = null;
        selectedRole = null;
        selectedPermission = null;
      } else if (prevStep === 3) {
        selectedContextType = null;
        selectedContextObject = null;
        contextObjects = [];
        _lastCtxKey = '';
      }
      currentStep = Math.max(currentStep - 1, 1);
      setTimeout(() => (isAnimating = false), 50);
    }, 200);
  }

  function selectFlow(type: FlowType) {
    flow = type;
  }

  function getXsrfToken(): string {
    return decodeURIComponent(
      document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    );
  }

  async function loadInitialData() {
    isLoading = true;
    errorMsg = '';
    try {
      const [rolesRes, permsRes, ctxRes] = await Promise.all([
        fetch('/admin/assignment/roles'),
        fetch('/admin/assignment/permissions'),
        fetch('/admin/assignment/context-types'),
      ]);

      if (!rolesRes.ok || !permsRes.ok || !ctxRes.ok) {
        throw new Error('Error al cargar datos del servidor');
      }

      roles = await rolesRes.json();
      permissions = await permsRes.json();
      contextTypes = await ctxRes.json();
    } catch (e) {
      errorMsg = e instanceof Error ? e.message : String(e);
    } finally {
      isLoading = false;
    }
  }

  async function loadContextObjects(typeKey: string) {
    contextObjects = [];
    selectedContextObject = null;
    contextObjectSearch = '';
    contextObjectsLoading = true;
    try {
      const res = await fetch(`/admin/assignment/context-types/${typeKey}/objects`);
      if (!res.ok) throw new Error('Error al cargar objetos');
      contextObjects = await res.json();
    } catch (e) {
      errorMsg = e instanceof Error ? e.message : String(e);
    } finally {
      contextObjectsLoading = false;
    }
  }

  async function handleSave() {
    isSaving = true;
    errorMsg = '';
    try {
      const base = `/admin/usuarios/${usuario.id_usuario}`;
      const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getXsrfToken(),
        Accept: 'application/json',
      };

      if (flow === 'role' && selectedRole) {
        const res = await fetch(`${base}/assign-role`, {
          method: 'POST',
          headers,
          body: JSON.stringify({
            role_id: selectedRole.id_rol,
            context_type: selectedContextType?.key ?? 'GLOBAL',
            context_object_id: selectedContextType?.key === 'GLOBAL' ? null : selectedContextObject?.id,
            start_date: startDate || null,
            end_date: endDate || null,
          }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
        successMsg = data.message || 'Rol asignado correctamente.';
      } else if (flow === 'permission' && selectedPermission) {
        const res = await fetch(`${base}/assign-permission`, {
          method: 'POST',
          headers,
          body: JSON.stringify({
            permission_id: selectedPermission.id_permiso,
            context_type: selectedContextType?.key ?? 'GLOBAL',
            context_object_id: selectedContextType?.key === 'GLOBAL' ? null : selectedContextObject?.id,
            start_date: startDate || null,
            end_date: endDate || null,
            allowed: permAllowed,
            can_delegate: permCanDelegate,
          }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
        successMsg = data.message || 'Permiso asignado correctamente.';
      }

      // Brief success flash then close
      setTimeout(() => onClose(), 1200);
    } catch (e) {
      errorMsg = e instanceof Error ? e.message : String(e);
    } finally {
      isSaving = false;
    }
  }
</script>

{#if isOpen}
  <!-- Backdrop -->
  <div
    class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4"
    onclick={(e) => e.target === e.currentTarget && onClose()}
    role="presentation"
  >
    <!-- Modal Container - much larger -->
    <div
      class="bg-white rounded-2xl w-full max-w-[960px] min-h-[540px] max-h-[92vh] flex flex-col overflow-hidden shadow-2xl"
      role="dialog"
      aria-modal="true"
    >
      <!-- ════════════════════════════════ -->
      <!-- HEADER                          -->
      <!-- ════════════════════════════════ -->
      <div class="px-8 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50 flex-shrink-0">
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1.5">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                {usuario?.username || 'Usuario'}
              </span>
              {#if flow}
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                  class:bg-purple-100={flow === 'role'}
                  class:text-purple-700={flow === 'role'}
                  class:bg-amber-100={flow === 'permission'}
                  class:text-amber-700={flow === 'permission'}
                >
                  {flow === 'role' ? '👔 Rol' : '🔐 Permiso Especial'}
                </span>
              {/if}
            </div>
            <p class="text-xs text-gray-500 font-semibold tracking-widest uppercase">Paso {currentStep} de {totalSteps}</p>
            <h2 class="text-xl font-bold text-gray-900 mt-0.5 leading-snug">{stepTitles.heading}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{stepTitles.subtitle}</p>
          </div>
          <button
            onclick={onClose}
            class="p-2 rounded-lg hover:bg-white/60 transition text-gray-400 hover:text-gray-600 border-none bg-transparent cursor-pointer"
            aria-label="Cerrar"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
            >
          </button>
        </div>

        <!-- Step Progress Bar -->
        <div class="flex gap-2 mt-4">
          {#each Array(totalSteps) as _, i}
            <div
              class="h-1.5 flex-1 rounded-full transition-all duration-500 ease-out"
              class:bg-blue-500={i + 1 <= currentStep}
              class:bg-gray-200={i + 1 > currentStep}
            ></div>
          {/each}
        </div>
      </div>

      <!-- ════════════════════════════════ -->
      <!-- CONTENT AREA (scrollable)       -->
      <!-- ════════════════════════════════ -->
      <div class="flex-1 overflow-hidden relative">
        <!-- Error Banner -->
        {#if errorMsg}
          <div class="mx-8 mt-4 bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 text-sm flex items-start gap-2">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              class="flex-shrink-0 mt-0.5"
              ><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" /></svg
            >
            <span class="flex-1">{errorMsg}</span>
            <button onclick={() => (errorMsg = '')} class="text-red-400 hover:text-red-600 border-none bg-transparent cursor-pointer text-base"
              >✕</button
            >
          </div>
        {/if}

        <!-- Success Banner -->
        {#if successMsg}
          <div class="mx-8 mt-4 bg-green-50 text-green-700 p-3 rounded-lg border border-green-200 text-sm flex items-start gap-2">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              class="flex-shrink-0 mt-0.5"><polyline points="20 6 9 17 4 12" /></svg
            >
            <span class="flex-1">{successMsg}</span>
          </div>
        {/if}

        <!-- Loading State -->
        {#if isLoading && currentStep === 1}
          <div class="flex items-center justify-center h-64 text-gray-400">
            <div class="flex flex-col items-center gap-3">
              <div class="w-8 h-8 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin"></div>
              <span class="text-sm">Cargando datos...</span>
            </div>
          </div>
        {:else}
          <!-- Animated content pane -->
          <div
            class="h-full overflow-y-auto p-8 transition-all duration-300 ease-in-out"
            class:translate-x-4={isAnimating && slideDirection === 'forward'}
            class:-translate-x-4={isAnimating && slideDirection === 'backward'}
            class:opacity-0={isAnimating}
          >
            <!-- ═════════════════════════════════════════ -->
            <!-- STEP 1: Choose Flow                      -->
            <!-- ═════════════════════════════════════════ -->
            {#if currentStep === 1}
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-[700px] mx-auto">
                <!-- Role Card -->
                <button
                  class="group relative flex flex-col items-center gap-4 p-8 rounded-xl border-2 transition-all duration-200 text-center cursor-pointer bg-white"
                  class:border-purple-400={flow === 'role'}
                  class:bg-purple-50={flow === 'role'}
                  class:shadow-lg={flow === 'role'}
                  class:shadow-purple-100={flow === 'role'}
                  class:ring-2={flow === 'role'}
                  class:ring-purple-200={flow === 'role'}
                  class:border-gray-200={flow !== 'role'}
                  class:hover:border-purple-300={flow !== 'role'}
                  class:hover:bg-purple-100={flow !== 'role'}
                  onclick={() => selectFlow('role')}
                  disabled={hideRoles}
                >
                  <div
                    class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl transition"
                    class:bg-purple-100={flow === 'role'}
                    class:bg-gray-100={flow !== 'role'}
                    class:group-hover:bg-purple-100={flow !== 'role'}
                  >
                    👔
                  </div>
                  <div>
                    <h3 class="text-lg font-bold text-gray-900">Asignar Rol</h3>
                    <p class="text-sm text-gray-500 mt-1">
                      Agrupa múltiples permisos relacionados.<br />Ideal para asignar funciones completas.
                    </p>
                  </div>
                  {#if hideRoles}
                    <div class="absolute inset-0 bg-white/60 rounded-xl flex items-center justify-center">
                      <span class="text-xs text-gray-400 font-medium">No disponible en este contexto</span>
                    </div>
                  {/if}
                  {#if flow === 'role'}
                    <div class="absolute top-3 right-3 w-6 h-6 bg-purple-500 rounded-full flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"
                        ><polyline points="20 6 9 17 4 12" /></svg
                      >
                    </div>
                  {/if}
                </button>

                <!-- Permission Card -->
                <button
                  class="group relative flex flex-col items-center gap-4 p-8 rounded-xl border-2 transition-all duration-200 text-center cursor-pointer bg-white"
                  class:border-amber-400={flow === 'permission'}
                  class:bg-amber-50={flow === 'permission'}
                  class:shadow-lg={flow === 'permission'}
                  class:shadow-amber-100={flow === 'permission'}
                  class:ring-2={flow === 'permission'}
                  class:ring-amber-200={flow === 'permission'}
                  class:border-gray-200={flow !== 'permission'}
                  class:hover:border-amber-300={flow !== 'permission'}
                  class:hover:bg-amber-100={flow !== 'permission'}
                  onclick={() => selectFlow('permission')}
                >
                  <div
                    class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl transition"
                    class:bg-amber-100={flow === 'permission'}
                    class:bg-gray-100={flow !== 'permission'}
                    class:group-hover:bg-amber-100={flow !== 'permission'}
                  >
                    🔐
                  </div>
                  <div>
                    <h3 class="text-lg font-bold text-gray-900">Permiso Especial</h3>
                    <p class="text-sm text-gray-500 mt-1">
                      Otorga o revoca un permiso individual.<br />Control granular de acceso.
                    </p>
                  </div>
                  {#if flow === 'permission'}
                    <div class="absolute top-3 right-3 w-6 h-6 bg-amber-500 rounded-full flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"
                        ><polyline points="20 6 9 17 4 12" /></svg
                      >
                    </div>
                  {/if}
                </button>
              </div>

              <!-- ═════════════════════════════════════════ -->
              <!-- STEP 2: Select Role / Permission         -->
              <!-- ═════════════════════════════════════════ -->
            {:else if currentStep === 2}
              {#if flow === 'role'}
                <!-- Search -->
                <div class="relative mb-4 max-w-md">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                    ><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg
                  >
                  <input
                    type="text"
                    bind:value={roleSearch}
                    placeholder="Buscar rol..."
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-200 focus:border-purple-400 bg-white"
                  />
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                  {#if filteredRoles.length === 0}
                    <div class="col-span-full text-center py-10 text-gray-400 text-sm">No se encontraron roles.</div>
                  {:else}
                    {#each filteredRoles as rol}
                      <button
                        class="p-5 rounded-xl border-2 transition-all text-center cursor-pointer bg-white"
                        class:border-purple-400={selectedRole?.id_rol === rol.id_rol}
                        class:bg-purple-50={selectedRole?.id_rol === rol.id_rol}
                        class:font-semibold={selectedRole?.id_rol === rol.id_rol}
                        class:text-purple-700={selectedRole?.id_rol === rol.id_rol}
                        class:ring-1={selectedRole?.id_rol === rol.id_rol}
                        class:ring-purple-300={selectedRole?.id_rol === rol.id_rol}
                        class:border-gray-200={selectedRole?.id_rol !== rol.id_rol}
                        class:hover:border-purple-300={selectedRole?.id_rol !== rol.id_rol}
                        class:hover:bg-purple-100={selectedRole?.id_rol !== rol.id_rol}
                        class:text-gray-700={selectedRole?.id_rol !== rol.id_rol}
                        onclick={() => (selectedRole = rol)}
                      >
                        <span class="text-2xl block mb-2">👔</span>
                        <span class="text-sm">{rol.nombre}</span>
                      </button>
                    {/each}
                  {/if}
                </div>
              {:else if flow === 'permission'}
                <!-- Search -->
                <div class="relative mb-4 max-w-md">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                    ><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg
                  >
                  <input
                    type="text"
                    bind:value={permSearch}
                    placeholder="Buscar permiso por nombre o slug..."
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-200 focus:border-amber-400 bg-white"
                  />
                </div>

                <div class="flex flex-col gap-5 max-h-[420px] overflow-y-auto pr-1">
                  {#each Object.entries(filteredPermissions) as [mod, perms]}
                    <div>
                      <h4 class="text-xs uppercase tracking-wider text-gray-500 font-semibold mb-2 border-b border-gray-100 pb-1.5">
                        {mod}
                      </h4>
                      <div class="flex flex-col gap-1">
                        {#each perms as perm}
                          <button
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg border transition-all text-left cursor-pointer text-sm bg-white"
                            class:border-amber-400={selectedPermission?.id_permiso === perm.id_permiso}
                            class:bg-amber-50={selectedPermission?.id_permiso === perm.id_permiso}
                            class:font-medium={selectedPermission?.id_permiso === perm.id_permiso}
                            class:text-amber-800={selectedPermission?.id_permiso === perm.id_permiso}
                            class:border-gray-100={selectedPermission?.id_permiso !== perm.id_permiso}
                            class:hover:border-amber-300={selectedPermission?.id_permiso !== perm.id_permiso}
                            class:hover:bg-amber-100={selectedPermission?.id_permiso !== perm.id_permiso}
                            onclick={() => (selectedPermission = perm)}
                          >
                            <span class="flex-shrink-0 text-base">
                              {selectedPermission?.id_permiso === perm.id_permiso ? '🟡' : '⚪'}
                            </span>
                            <div class="flex-1 min-w-0">
                              <div class="font-medium truncate">{perm.nombre}</div>
                              <div class="text-xs text-gray-400 font-mono">{perm.slug}</div>
                            </div>
                            {#if selectedPermission?.id_permiso === perm.id_permiso}
                              <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="text-amber-500 flex-shrink-0"><polyline points="20 6 9 17 4 12" /></svg
                              >
                            {/if}
                          </button>
                        {/each}
                      </div>
                    </div>
                  {/each}

                  {#if Object.keys(filteredPermissions).length === 0}
                    <div class="text-center py-10 text-gray-400 text-sm">No se encontraron permisos.</div>
                  {/if}
                </div>
              {/if}

              <!-- ═════════════════════════════════════════ -->
              <!-- STEP 3: Select Context                   -->
              <!-- ═════════════════════════════════════════ -->
            {:else if currentStep === 3}
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 h-full">
                <!-- Left: Context Type -->
                <div>
                  <h4 class="text-sm font-semibold text-gray-600 mb-3">Tipo de contexto</h4>
                  {#if flow === 'permission' && selectedPermission?.valid_context_types && selectedPermission.valid_context_types.length < contextTypes.length}
                    <p class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-2">
                      Solo se muestran los tipos válidos para
                      <span class="font-mono font-semibold">{selectedPermission.slug}</span>.
                    </p>
                  {/if}
                  <div class="flex flex-col gap-2">
                    {#each availableContextTypes as ctxType}
                      <button
                        class="flex items-center gap-3 p-3.5 rounded-lg border-2 transition-all text-left cursor-pointer bg-white"
                        class:border-blue-400={selectedContextType?.key === ctxType.key}
                        class:bg-blue-50={selectedContextType?.key === ctxType.key}
                        class:ring-1={selectedContextType?.key === ctxType.key}
                        class:ring-blue-200={selectedContextType?.key === ctxType.key}
                        class:border-gray-200={selectedContextType?.key !== ctxType.key}
                        class:hover:border-blue-300={selectedContextType?.key !== ctxType.key}
                        class:hover:bg-blue-100={selectedContextType?.key !== ctxType.key}
                        onclick={() => {
                          selectedContextType = ctxType;
                          if (ctxType.key === 'GLOBAL') {
                            selectedContextObject = null;
                          }
                        }}
                      >
                        <span class="text-xl flex-shrink-0">
                          {#if ctxType.key === 'GLOBAL'}🌐
                          {:else if ctxType.key === 'FACULTAD'}🏛️
                          {:else if ctxType.key === 'DEPARTAMENTO'}🏢
                          {:else if ctxType.key === 'CARRERA'}🎓
                          {:else if ctxType.key === 'CURSO'}📚
                          {:else if ctxType.key === 'ACTIVIDAD'}📋
                          {:else}📦{/if}
                        </span>
                        <div class="flex-1 min-w-0">
                          <div class="font-medium text-gray-900 text-sm">{ctxType.label}</div>
                          <div class="text-xs text-gray-400 truncate">{ctxType.description}</div>
                        </div>
                        {#if ctxType.count !== undefined}
                          <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full flex-shrink-0">{ctxType.count}</span>
                        {/if}
                        {#if selectedContextType?.key === ctxType.key}
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="text-blue-500 flex-shrink-0"><polyline points="20 6 9 17 4 12" /></svg
                          >
                        {/if}
                      </button>
                    {/each}
                  </div>
                </div>

                <!-- Right: Specific Object -->
                <div>
                  {#if selectedContextType && selectedContextType.key !== 'GLOBAL'}
                    <h4 class="text-sm font-semibold text-gray-600 mb-3">
                      Selecciona {selectedContextType.label}
                    </h4>

                    <div class="relative mb-3">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                        ><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg
                      >
                      <input
                        type="text"
                        bind:value={contextObjectSearch}
                        placeholder="Buscar {selectedContextType.label.toLowerCase()}..."
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 bg-white"
                      />
                    </div>

                    <div class="flex flex-col gap-1.5 max-h-[340px] overflow-y-auto pr-1">
                      {#if contextObjectsLoading}
                        <div class="text-center py-8 text-gray-400 text-sm">
                          <div class="w-6 h-6 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin mx-auto mb-2"></div>
                          Cargando...
                        </div>
                      {:else if filteredContextObjects.length === 0}
                        <div class="text-center py-8 text-gray-400 text-sm">No se encontraron resultados.</div>
                      {:else}
                        {#each filteredContextObjects as obj}
                          <button
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border text-left transition-all cursor-pointer text-sm bg-white"
                            class:border-blue-400={selectedContextObject?.id === obj.id}
                            class:bg-blue-50={selectedContextObject?.id === obj.id}
                            class:font-medium={selectedContextObject?.id === obj.id}
                            class:border-gray-200={selectedContextObject?.id !== obj.id}
                            class:hover:border-blue-300={selectedContextObject?.id !== obj.id}
                            class:hover:bg-blue-100={selectedContextObject?.id !== obj.id}
                            onclick={() => (selectedContextObject = obj)}
                          >
                            <span class="text-gray-400 text-xs font-mono w-8 text-right flex-shrink-0">#{obj.id}</span>
                            <span class="flex-1 truncate">{obj.label}</span>
                            {#if selectedContextObject?.id === obj.id}
                              <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="text-blue-500 flex-shrink-0"><polyline points="20 6 9 17 4 12" /></svg
                              >
                            {/if}
                          </button>
                        {/each}
                      {/if}
                    </div>
                  {:else if selectedContextType?.key === 'GLOBAL'}
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                      <span class="text-5xl mb-3">🌐</span>
                      <p class="text-sm font-medium text-gray-600">Contexto Global</p>
                      <p class="text-xs text-gray-400 mt-1">Se aplicará en todo el sistema sin restricción de objeto</p>
                    </div>
                  {:else}
                    <div class="flex items-center justify-center py-16 text-center text-gray-300">
                      <div>
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="32"
                          height="32"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="1"
                          class="mx-auto mb-2 opacity-40"><polyline points="15 18 9 12 15 6" /></svg
                        >
                        <p class="text-sm">Selecciona un tipo de contexto</p>
                      </div>
                    </div>
                  {/if}
                </div>
              </div>

              <!-- ═════════════════════════════════════════ -->
              <!-- STEP 4: Parameters & Confirm             -->
              <!-- ═════════════════════════════════════════ -->
            {:else if currentStep === 4}
              <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                <!-- Left: Parameters (3 cols) -->
                <div class="lg:col-span-3 flex flex-col gap-6">
                  <!-- Date Fields -->
                  <div>
                    <h4 class="text-sm font-semibold text-gray-600 mb-3">Vigencia</h4>
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label for="wizard-start-date" class="block text-xs font-medium text-gray-500 mb-1.5">Fecha inicio</label>
                        <input
                          id="wizard-start-date"
                          type="date"
                          bind:value={startDate}
                          min={today}
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 bg-white"
                        />
                      </div>
                      <div>
                        <label for="wizard-end-date" class="block text-xs font-medium text-gray-500 mb-1.5">Fecha fin</label>
                        <input
                          id="wizard-end-date"
                          type="date"
                          bind:value={endDate}
                          min={startDate || today}
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 bg-white"
                        />
                        <p class="text-xs text-gray-400 mt-1">Vacío = 1 año por defecto</p>
                      </div>
                    </div>
                  </div>

                  <!-- Permission-only parameters -->
                  {#if flow === 'permission'}
                    <div class="border-t border-gray-100 pt-5">
                      <h4 class="text-sm font-semibold text-gray-600 mb-4">Configuración del permiso</h4>

                      <!-- Allowed / Denied toggle -->
                      <div class="flex items-center gap-4 mb-5">
                        <span class="text-sm text-gray-600 w-28 flex-shrink-0">Acción:</span>
                        <div class="flex bg-gray-100 rounded-lg p-0.5 gap-0.5">
                          <button
                            class="px-5 py-2 rounded-md text-sm font-medium transition-all border-none cursor-pointer"
                            class:bg-green-500={permAllowed}
                            class:text-white={permAllowed}
                            class:shadow-sm={permAllowed}
                            class:bg-transparent={!permAllowed}
                            class:text-gray-500={!permAllowed}
                            onclick={() => (permAllowed = true)}>🟢 Permitir</button
                          >
                          <button
                            class="px-5 py-2 rounded-md text-sm font-medium transition-all border-none cursor-pointer"
                            class:bg-red-500={!permAllowed}
                            class:text-white={!permAllowed}
                            class:shadow-sm={!permAllowed}
                            class:bg-transparent={permAllowed}
                            class:text-gray-500={permAllowed}
                            onclick={() => (permAllowed = false)}>🔴 Denegar</button
                          >
                        </div>
                      </div>

                      <!-- Can Delegate toggle -->
                      <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-600 w-28 flex-shrink-0">Puede delegar:</span>
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                          <div class="relative inline-flex">
                            <input type="checkbox" bind:checked={permCanDelegate} class="sr-only peer" />
                            <div
                              class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-200 rounded-full peer peer-checked:bg-blue-500 transition-colors"
                            ></div>
                            <div
                              class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5 shadow-sm"
                            ></div>
                          </div>
                          <span class="text-sm text-gray-600">
                            {permCanDelegate ? 'Sí, puede re-delegar este permiso' : 'No puede delegar'}
                          </span>
                        </label>
                      </div>
                    </div>
                  {/if}
                </div>

                <!-- Right: Summary Card (2 cols) -->
                <div class="lg:col-span-2">
                  <div class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl border border-gray-200 p-5 sticky top-0">
                    <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        ><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line
                          x1="16"
                          y1="13"
                          x2="8"
                          y2="13"
                        /><line x1="16" y1="17" x2="8" y2="17" /></svg
                      >
                      Resumen de asignación
                    </h4>

                    <div class="flex flex-col gap-2.5 text-sm">
                      <!-- User -->
                      <div class="flex justify-between items-center">
                        <span class="text-gray-500">Usuario</span>
                        <span class="font-medium text-gray-900">{usuario?.username || 'N/A'}</span>
                      </div>

                      <!-- Type -->
                      <div class="flex justify-between items-center">
                        <span class="text-gray-500">Tipo</span>
                        <span
                          class="font-medium px-2 py-0.5 rounded text-xs"
                          class:bg-purple-100={flow === 'role'}
                          class:text-purple-700={flow === 'role'}
                          class:bg-amber-100={flow === 'permission'}
                          class:text-amber-700={flow === 'permission'}
                        >
                          {flow === 'role' ? '👔 Rol' : '🔐 Permiso'}
                        </span>
                      </div>

                      <!-- Item -->
                      <div class="flex justify-between items-center">
                        <span class="text-gray-500">{flow === 'role' ? 'Rol' : 'Permiso'}</span>
                        <span class="font-medium text-gray-900 text-right max-w-[160px] truncate">
                          {flow === 'role' ? selectedRole?.nombre : selectedPermission?.nombre}
                        </span>
                      </div>

                      <div class="border-t border-gray-200 my-1"></div>

                      <!-- Context -->
                      <div class="flex justify-between items-center">
                        <span class="text-gray-500">Contexto</span>
                        <span class="font-medium text-gray-900 text-right max-w-[160px] truncate">
                          {#if selectedContextType?.key === 'GLOBAL'}
                            🌐 Global
                          {:else}
                            {selectedContextType?.label}
                          {/if}
                        </span>
                      </div>

                      {#if selectedContextType?.key !== 'GLOBAL' && selectedContextObject}
                        <div class="flex justify-between items-center">
                          <span class="text-gray-500">Objeto</span>
                          <span class="font-medium text-gray-900 text-right max-w-[160px] truncate">
                            {selectedContextObject.label}
                          </span>
                        </div>
                      {/if}

                      <div class="border-t border-gray-200 my-1"></div>

                      <!-- Dates -->
                      <div class="flex justify-between items-center">
                        <span class="text-gray-500">Inicio</span>
                        <span class="font-medium text-gray-900">{startDate || today}</span>
                      </div>
                      <div class="flex justify-between items-center">
                        <span class="text-gray-500">Fin</span>
                        <span class:text-gray-900={!!endDate} class:text-gray-400={!endDate} class:font-medium={!!endDate} class:text-xs={!endDate}>
                          {endDate || '1 año (por defecto)'}
                        </span>
                      </div>

                      <!-- Permission specifics -->
                      {#if flow === 'permission'}
                        <div class="border-t border-gray-200 my-1"></div>
                        <div class="flex justify-between items-center">
                          <span class="text-gray-500">Acción</span>
                          <span class="font-semibold" class:text-green-600={permAllowed} class:text-red-600={!permAllowed}>
                            {permAllowed ? '🟢 Permitir' : '🔴 Denegar'}
                          </span>
                        </div>
                        <div class="flex justify-between items-center">
                          <span class="text-gray-500">Delegable</span>
                          <span class="font-medium text-gray-900">{permCanDelegate ? 'Sí' : 'No'}</span>
                        </div>
                      {/if}
                    </div>
                  </div>
                </div>
              </div>
            {/if}
          </div>
        {/if}
      </div>

      <!-- ════════════════════════════════ -->
      <!-- FOOTER NAV                      -->
      <!-- ════════════════════════════════ -->
      <div class="px-8 py-4 border-t border-gray-200 bg-white flex justify-between items-center flex-shrink-0">
        <div>
          {#if currentStep > 1}
            <button
              onclick={goBack}
              class="flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition cursor-pointer border-none"
              disabled={isSaving}
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                ><polyline points="15 18 9 12 15 6" /></svg
              >
              Atrás
            </button>
          {:else}
            <button
              onclick={onClose}
              class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition cursor-pointer border-none"
              disabled={isSaving}
            >
              Cancelar
            </button>
          {/if}
        </div>

        <div>
          {#if currentStep < totalSteps}
            <button
              onclick={goNext}
              class="flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-medium text-white transition cursor-pointer border-none disabled:opacity-50 disabled:cursor-not-allowed"
              class:bg-blue-500={canGoNext}
              class:hover:bg-blue-600={canGoNext}
              class:bg-gray-300={!canGoNext}
              disabled={!canGoNext || isSaving}
            >
              Siguiente
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                ><polyline points="9 18 15 12 9 6" /></svg
              >
            </button>
          {:else}
            <button
              onclick={handleSave}
              class="flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-green-500 hover:bg-green-600 transition cursor-pointer border-none disabled:opacity-60"
              disabled={isSaving || !!successMsg}
            >
              {#if isSaving}
                <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                Guardando...
              {:else if successMsg}
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  ><polyline points="20 6 9 17 4 12" /></svg
                >
                Guardado
              {:else}
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  ><polyline points="20 6 9 17 4 12" /></svg
                >
                Confirmar y Guardar
              {/if}
            </button>
          {/if}
        </div>
      </div>
    </div>
  </div>
{/if}
