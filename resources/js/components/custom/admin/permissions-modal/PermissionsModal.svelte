<script lang="ts">
  import {
    getContextTypes,
    getPermissions,
    getRoles,
  } from '@/actions/App/Http/Controllers/Admin/AssignmentWizardController';
  import { getUserPermissions } from '@/actions/App/Http/Controllers/Admin/UsuarioController';
  import type {
    ContextTypeResponse,
    PermissionGroupResponse,
    RolResponse,
    UsuarioData,
    GetUserPermissionsResponse,
    UserRoleAssignment,
    UserSpecialPermissionAssignment,
    PermissionDetail,
  } from '@/types';
  import { ContextType } from '@/types/permissions';
  import {
    getLabel,
    getDescription,
    compareContextTypes,
    isRoot,
    isGlobalContext,
  } from '@/utils/contextType.utils';
  import { SvelteSet } from 'svelte/reactivity';

  // ─── Types ────────────────────────────────────────────────────────────
  interface Props {
    isOpen: boolean;
    onClose: () => void;
    usuario: UsuarioData;
    hideRoles?: boolean;
  }

  interface ContextObject {
    id: number;
    label: string;
    context_id: number;
  }

  interface PermissionItem {
    id_permiso: number;
    slug: string;
    nombre: string;
    descripcion?: string;
    /** Tipos de contexto válidos para asignar este permiso (enum instances) */
    valid_context_types?: ContextType[];
  }

  interface RoleDetailPerm {
    id_permiso: number;
    slug: string;
    nombre: string;
    descripcion?: string;
    puede_delegar_permisos: boolean;
  }

  /**
   * ContextType transformado con instancia del enum para type-safety
   */
  interface ContextTypeWithEnum extends ContextTypeResponse {
    type: ContextType;
  }

  type FlowType = 'role' | 'permission' | null;

  // ─── Props ────────────────────────────────────────────────────────────
  let { isOpen = $bindable(), onClose, usuario, hideRoles = false }: Props = $props();

  // ─── Wizard State ─────────────────────────────────────────────────────
  let currentStep = $state(1);
  let flow = $state<FlowType>(null);
  let slideDirection = $state<'forward' | 'backward'>('forward');
  let isAnimating = $state(false);

  // Step 2 selections
  let selectedRole = $state<RolResponse | null>(null);
  let selectedPermission = $state<PermissionDetail | null>(null);

  // Step 3 selections (context)
  let selectedContextType = $state<ContextTypeWithEnum | null>(null);
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
  let roles = $state<RolResponse[]>([]);
  let permissions = $state<PermissionGroupResponse>({});
  let contextTypes = $state<ContextTypeWithEnum[]>([]);
  let contextObjects = $state<ContextObject[]>([]);
  let contextObjectsLoading = $state(false);

  // ─── Current user assignments ──────────────────────────────────────────
  let userCurrentRoleAssignments = $state<UserRoleAssignment[]>([]);
  let userCurrentSpecialPermissions = $state<UserSpecialPermissionAssignment[]>([]);

  // ─── Role detail panel ────────────────────────────────────────────────
  let roleDetailOpen = $state(false);
  let roleDetailTarget = $state<UserRoleAssignment | null>(null);
  let roleDetailPerms = $state<RoleDetailPerm[]>([]);
  let roleDetailRoleName = $state('');
  let roleDetailLoading = $state(false);

  // ─── Search filters ───────────────────────────────────────────────────
  let roleSearch = $state('');
  let permSearch = $state('');
  let contextObjectSearch = $state('');

  // ─── Tooltip positioning ──────────────────────────────────────────────
  // FIX: el tooltip se desincroniza, no funciona sobre el texto
  let tooltipX = $state(0);
  let tooltipY = $state(0);
  let showTooltip = $state(false);
  let currentDuplicateAssignment = $state<UserRoleAssignment | null>(null);

  function handleMouseMove(e: MouseEvent) {
    tooltipX = e.clientX + 20;
    tooltipY = e.clientY - 20;
  }

  function showDuplicateTooltip(obj: ContextObject) {
    if (!isContextObjectDuplicate(obj)) return;
    const assignment = getDuplicateAssignment(obj);
    if (assignment) {
      currentDuplicateAssignment = assignment;
      showTooltip = true;
    }
  }

  function hideTooltip() {
    showTooltip = false;
    currentDuplicateAssignment = null;
  }

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
          heading:
            flow === 'role' ? 'Selecciona el rol a asignar' : 'Selecciona el permiso a asignar',
          subtitle:
            flow === 'role'
              ? 'Un rol agrupa varios permisos relacionados'
              : 'Un permiso especial otorga acceso granular a una acción específica',
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
        return (
          selectedContextType !== null &&
          (selectedContextType.type === ContextType.GLOBAL || selectedContextObject !== null)
        );
      case 4:
        return true;
      default:
        return false;
    }
  });

  /** Unique role names currently assigned (for the step-2 banner) */
  let userCurrentRoles = $derived.by(() => {
    const seen = new SvelteSet<number>();
    return userCurrentRoleAssignments.filter((a) => {
      if (seen.has(a.id_rol)) return false;
      seen.add(a.id_rol);
      return true;
    });
  });

  /**
   * True when the currently selected role is already assigned in the
   * context the user just chose — would trigger a 23P01 exclusion conflict.
   */
  let isDuplicateAssignment = $derived.by(() => {
    if (flow !== 'role' || !selectedRole || !selectedContextType) return false;

    // Resolve the context_id that will be used for the INSERT
    let resolvedContextId: number | null = null;
    if (selectedContextType.type === ContextType.GLOBAL) {
      resolvedContextId = selectedContextType.context_id ?? null;
    } else if (selectedContextObject) {
      resolvedContextId = selectedContextObject.context_id;
    } else {
      return false; // context object not yet chosen
    }

    return userCurrentRoleAssignments.some(
      (a) => a.id_rol === selectedRole!.id_rol && a.id_contexto === resolvedContextId,
    );
  });

  /**
   * Verifica si un contexto específico causaría conflicto de solapamiento
   */
  function isContextObjectDuplicate(obj: ContextObject): boolean {
    if (flow !== 'role' || !selectedRole || !selectedContextType) return false;
    return userCurrentRoleAssignments.some(
      (a) => a.id_rol === selectedRole!.id_rol && a.id_contexto === obj.context_id,
    );
  }

  /**
   * Obtiene la asignación duplicada para un contexto específico
   */
  function getDuplicateAssignment(obj: ContextObject): UserRoleAssignment | null {
    if (flow !== 'role' || !selectedRole || !selectedContextType) return null;
    return (
      userCurrentRoleAssignments.find(
        (a) => a.id_rol === selectedRole!.id_rol && a.id_contexto === obj.context_id,
      ) || null
    );
  }

  /**
   * Verifica si un permiso especial ya está asignado al usuario
   */
  function isPermissionAlreadyAssigned(perm: PermissionItem): boolean {
    return userCurrentSpecialPermissions.some((a) => a.id_permiso === perm.id_permiso);
  }

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
      const filtered = perms.filter(
        (p) => p.nombre.toLowerCase().includes(q) || p.slug.toLowerCase().includes(q),
      );
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
   * Cuando el flujo es 'role' y hay un rol seleccionado con valid_assignment_context_types,
   * filtra los tipos de contexto disponibles para mostrar solo los compatibles.
   *
   * Ordenados jerárquicamente usando compareContextTypes para consistencia.
   */
  let availableContextTypes = $derived.by(() => {
    let filtered = contextTypes;

    // Para permisos: filtrar por valid_context_types
    if (flow === 'permission' && selectedPermission?.valid_context_types?.length) {
      filtered = contextTypes.filter((ct) =>
        selectedPermission?.valid_context_types!.includes(ct.type),
      );
    }

    // Para roles: filtrar por valid_assignment_context_types
    else if (flow === 'role' && selectedRole?.valid_assignment_context_types?.length) {
      filtered = contextTypes.filter((ct) =>
        selectedRole?.valid_assignment_context_types!.includes(ct.type),
      );
    }

    // Ordenar jerárquicamente
    return [...filtered].sort((a, b) => compareContextTypes(a.type, b.type));
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
  let _lastCtxValue = '';
  $effect(() => {
    const value = selectedContextType?.value ?? '';
    if (
      value &&
      selectedContextType &&
      !isGlobalContext(selectedContextType.type) &&
      value !== _lastCtxValue
    ) {
      _lastCtxValue = value;
      loadContextObjects(value);
    }
  });

  // Cuando el permiso o rol cambia, resetear selectedContextType si ya no es válido
  $effect(() => {
    if (
      flow === 'permission' &&
      selectedContextType &&
      selectedPermission?.valid_context_types?.length
    ) {
      if (!selectedPermission.valid_context_types.includes(selectedContextType.type)) {
        selectedContextType = null;
        selectedContextObject = null;
        contextObjects = [];
        _lastCtxValue = '';
      }
    }
    if (
      flow === 'role' &&
      selectedContextType &&
      selectedRole?.valid_assignment_context_types?.length
    ) {
      if (!selectedRole.valid_assignment_context_types.includes(selectedContextType.type)) {
        selectedContextType = null;
        selectedContextObject = null;
        contextObjects = [];
        _lastCtxValue = '';
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
    _lastCtxValue = '';
    userCurrentRoleAssignments = [];
    userCurrentSpecialPermissions = [];
    roleDetailOpen = false;
    roleDetailTarget = null;
    roleDetailPerms = [];
    roleDetailRoleName = '';
    roleDetailLoading = false;
  }

  function goNext() {
    if (!canGoNext || isAnimating) return;
    slideDirection = 'forward';
    isAnimating = true;
    setTimeout(() => {
      currentStep = Math.min(currentStep + 1, totalSteps);
      // Set default dates when entering step 4
      if (currentStep === 4 && !startDate) {
        startDate = today;

        // Calculate endDate as 1 year after startDate
        const start = new Date(today);
        start.setFullYear(start.getFullYear() + 1);
        endDate = start.toISOString().split('T')[0]; // Format YYYY-MM-DD
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
        _lastCtxValue = '';
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

  async function apiFetch<T>(url: string): Promise<T> {
    // $inspect(url);
    console.log('Fetching:', url);
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`Error ${response.status}: Falló la petición a ${url}`);
    }
    return (await response.json()) as T;
  }

  async function loadInitialData() {
    isLoading = true;
    errorMsg = '';
    try {
      const [rolesRes, permsRes, ctxRes, userPermsRes] = await Promise.all([
        apiFetch<RolResponse[]>(getRoles().url),
        apiFetch<PermissionGroupResponse>(getPermissions().url),
        apiFetch<ContextTypeResponse[]>(getContextTypes().url),
        apiFetch<GetUserPermissionsResponse>(getUserPermissions(usuario.id_usuario).url),
      ]);

      // Cargar datos disponibles para asignar (desde los endpoints globales)
      // Transformar roles para convertir valid_assignment_context_types a enum instances
      roles = rolesRes.map((rol) => ({
        ...rol,
        valid_assignment_context_types: (rol.valid_assignment_context_types || []).map(
          (value) => ContextType[value.toUpperCase() as keyof typeof ContextType],
        ),
      }));
      // Transformar permissions para convertir valid_context_types a enum instances
      // permsRes es PermissionGroupResponse ({[module: string]: PermissionDetail[]})
      permissions = Object.fromEntries(
        Object.entries(permsRes).map(([key, perms]) => [
          key,
          (perms || []).map((p) => ({
            ...p,
            valid_context_types: (p.valid_context_types || []).map(
              (value: string) => ContextType[value.toUpperCase() as keyof typeof ContextType],
            ),
          })),
        ]),
      ) as PermissionGroupResponse;
      // Transformar contextTypes para incluir instancia del enum
      // Agregar fallbacks usando utils en caso de que backend no devuelva etiquetas completas
      contextTypes = ctxRes.map((ct) => {
        const type = ContextType[ct.value.toUpperCase() as keyof typeof ContextType];
        return {
          ...ct,
          type,
          label: ct.label || getLabel(type),
          description: ct.description || getDescription(type),
          count: ct.count,
        };
      });

      console.log(contextTypes);

      // Cargar asignaciones ACTUALES del usuario (solo del endpoint específico del usuario)
      userCurrentRoleAssignments = Array.isArray(userPermsRes.roles) ? userPermsRes.roles : [];
      // $inspect(userCurrentRoleAssignments);
      userCurrentSpecialPermissions = Array.isArray(userPermsRes.special_permissions)
        ? userPermsRes.special_permissions
        : [];
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

  async function openRoleDetail(asignacion: UserRoleAssignment) {
    roleDetailTarget = asignacion;
    roleDetailOpen = true;
    roleDetailLoading = true;
    roleDetailPerms = [];
    roleDetailRoleName = asignacion.nombre;
    try {
      const res = await fetch(`/admin/assignment/roles/${asignacion.id_rol}/detail`);
      if (!res.ok) throw new Error('Error al cargar detalles del rol');
      const data = await res.json();
      roleDetailPerms = data.permisos ?? [];
      roleDetailRoleName = data.nombre ?? asignacion.nombre;
    } finally {
      roleDetailLoading = false;
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
            context_type: selectedContextType?.value ?? 'global',
            context_object_id:
              selectedContextType && !isGlobalContext(selectedContextType.type)
                ? selectedContextObject?.id
                : null,
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
            context_type: selectedContextType?.value ?? 'global',
            context_object_id:
              selectedContextType && !isGlobalContext(selectedContextType.type)
                ? selectedContextObject?.id
                : null,
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

      // Reload data to show updated assignments, then close
      await loadInitialData();
      setTimeout(() => onClose(), 1200);
    } catch (e) {
      errorMsg = e instanceof Error ? e.message : String(e);
    } finally {
      isSaving = false;
    }
  }

  let isRevoking = $state(false);

  async function revokeRole(asignacion: UserRoleAssignment) {
    if (
      !confirm(
        `¿Revocar el rol "${asignacion.nombre}"${asignacion.contexto_display ? ` en ${asignacion.contexto_display}` : ''}?`,
      )
    )
      return;
    isRevoking = true;
    errorMsg = '';
    try {
      const res = await fetch(`/admin/usuarios/${usuario.id_usuario}/roles/${asignacion.id_ura}`, {
        method: 'DELETE',
        headers: {
          'X-XSRF-TOKEN': getXsrfToken(),
          Accept: 'application/json',
        },
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
      userCurrentRoleAssignments = userCurrentRoleAssignments.filter(
        (r) => r.id_ura !== asignacion.id_ura,
      );
      successMsg = data.message || 'Rol revocado.';
      // Recargar después de revocar
      await loadInitialData();
    } catch (e) {
      errorMsg = e instanceof Error ? e.message : String(e);
    } finally {
      isRevoking = false;
    }
  }

  async function revokePermission(perm: UserSpecialPermissionAssignment) {
    if (!perm.id_upe) return;
    if (
      !confirm(
        `¿Revocar el permiso "${perm.nombre ?? perm.slug}"${perm.contexto_display ? ` en ${perm.contexto_display}` : ''}?`,
      )
    )
      return;
    isRevoking = true;
    errorMsg = '';
    try {
      const res = await fetch(`/admin/usuarios/${usuario.id_usuario}/permissions/${perm.id_upe}`, {
        method: 'DELETE',
        headers: {
          'X-XSRF-TOKEN': getXsrfToken(),
          Accept: 'application/json',
        },
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
      userCurrentSpecialPermissions = userCurrentSpecialPermissions.filter(
        (p) => p.id_upe !== perm.id_upe,
      );
      successMsg = data.message || 'Permiso revocado.';
      // Recargar después de revocar
      await loadInitialData();
    } catch (e) {
      errorMsg = e instanceof Error ? e.message : String(e);
    } finally {
      isRevoking = false;
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
      <div
        class="px-8 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50 flex-shrink-0"
      >
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1.5">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700"
              >
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
            <p class="text-xs text-gray-500 font-semibold tracking-widest uppercase">
              Paso {currentStep} de {totalSteps}
            </p>
            <h2 class="text-xl font-bold text-gray-900 mt-0.5 leading-snug">
              {stepTitles.heading}
            </h2>
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
              stroke-linejoin="round"
              ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
            >
          </button>
        </div>

        <!-- Step Progress Bar -->
        <div class="flex gap-2 mt-4">
          {#each Array(totalSteps), i (i)}
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
          <div
            class="mx-8 mt-4 bg-red-50 text-red-700 p-3 rounded-lg border border-red-200 text-sm flex items-start gap-2"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              class="flex-shrink-0 mt-0.5"
              ><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line
                x1="9"
                y1="9"
                x2="15"
                y2="15"
              /></svg
            >
            <span class="flex-1">{errorMsg}</span>
            <button
              onclick={() => (errorMsg = '')}
              class="text-red-400 hover:text-red-600 border-none bg-transparent cursor-pointer text-base"
              >✕</button
            >
          </div>
        {/if}

        <!-- Success Banner -->
        {#if successMsg}
          <div
            class="mx-8 mt-4 bg-green-50 text-green-700 p-3 rounded-lg border border-green-200 text-sm flex items-start gap-2"
          >
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
              <div
                class="w-8 h-8 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin"
              ></div>
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
                      Agrupa múltiples permisos relacionados.<br />Ideal para asignar funciones
                      completas.
                    </p>
                  </div>
                  {#if hideRoles}
                    <div
                      class="absolute inset-0 bg-white/60 rounded-xl flex items-center justify-center"
                    >
                      <span class="text-xs text-gray-400 font-medium"
                        >No disponible en este contexto</span
                      >
                    </div>
                  {/if}
                  {#if flow === 'role'}
                    <div
                      class="absolute top-3 right-3 w-6 h-6 bg-purple-500 rounded-full flex items-center justify-center"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="white"
                        stroke-width="3"><polyline points="20 6 9 17 4 12" /></svg
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
                    <div
                      class="absolute top-3 right-3 w-6 h-6 bg-amber-500 rounded-full flex items-center justify-center"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="white"
                        stroke-width="3"><polyline points="20 6 9 17 4 12" /></svg
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
                <!-- Current roles banner -->
                <div class="mb-5 p-3.5 rounded-xl border border-purple-200 bg-purple-50">
                  <p class="text-xs font-semibold text-purple-600 uppercase tracking-wider mb-2">
                    Roles asignados actualmente
                  </p>
                  {#if userCurrentRoles.length === 0}
                    <span class="text-sm text-gray-400 italic">Sin rol asignado</span>
                  {:else}
                    <div class="flex flex-wrap gap-2">
                      {#each userCurrentRoleAssignments as asignacion}
                        <span
                          class="inline-flex items-center gap-1.5 pl-3 pr-1 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800 border border-purple-300"
                        >
                          <button
                            onclick={() => openRoleDetail(asignacion)}
                            title="Ver permisos de este rol"
                            class="inline-flex items-center gap-1.5 bg-transparent border-none cursor-pointer text-inherit font-inherit p-0"
                          >
                            <span>👔</span>
                            {asignacion.nombre}
                            {#if asignacion.contexto_display || asignacion.curso_nombre}
                              <span class="text-purple-500 text-xs">
                                ({asignacion.contexto_display}{asignacion.curso_nombre
                                  ? ` - ${asignacion.curso_nombre}`
                                  : ''})
                              </span>
                            {/if}
                          </button>
                          <button
                            onclick={() => revokeRole(asignacion)}
                            disabled={isRevoking}
                            title="Revocar este rol"
                            class="ml-1 w-5 h-5 rounded-full flex items-center justify-center bg-purple-200 hover:bg-red-200 hover:text-red-700 text-purple-600 border-none cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                          >
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              width="10"
                              height="10"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="currentColor"
                              stroke-width="3"
                              ><line x1="18" y1="6" x2="6" y2="18" /><line
                                x1="6"
                                y1="6"
                                x2="18"
                                y2="18"
                              /></svg
                            >
                          </button>
                        </span>
                      {/each}
                    </div>
                  {/if}
                </div>

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
                    ><circle cx="11" cy="11" r="8" /><line
                      x1="21"
                      y1="21"
                      x2="16.65"
                      y2="16.65"
                    /></svg
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
                    <div class="col-span-full text-center py-10 text-gray-400 text-sm">
                      No se encontraron roles.
                    </div>
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
                <!-- Current special permissions banner -->
                <div class="mb-5 p-3.5 rounded-xl border border-amber-200 bg-amber-50">
                  <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-2">
                    Permisos especiales asignados actualmente
                  </p>
                  {#if userCurrentSpecialPermissions.length === 0}
                    <span class="text-sm text-gray-400 italic"
                      >Sin permisos especiales asignados</span
                    >
                  {:else}
                    <div class="flex flex-wrap gap-2">
                      {#each userCurrentSpecialPermissions as perm}
                        <span
                          class="inline-flex items-center gap-1.5 pl-3 pr-1 py-1 rounded-full text-sm font-medium border"
                          class:bg-green-100={perm.esta_permitido}
                          class:text-green-800={perm.esta_permitido}
                          class:border-green-300={perm.esta_permitido}
                          class:bg-red-100={!perm.esta_permitido}
                          class:text-red-800={!perm.esta_permitido}
                          class:border-red-300={!perm.esta_permitido}
                          title={perm.contexto_display || perm.curso_nombre
                            ? `Contexto: ${perm.contexto_display}${perm.curso_nombre ? ` - ${perm.curso_nombre}` : ''}`
                            : 'Contexto global'}
                        >
                          <span>{perm.esta_permitido ? '✅' : '🚫'}</span>
                          <span>{perm.nombre ?? perm.slug}</span>
                          {#if perm.contexto_display || perm.curso_nombre}
                            <span class="opacity-60 text-xs">
                              ({perm.contexto_display}{perm.curso_nombre
                                ? ` - ${perm.curso_nombre}`
                                : ''})
                            </span>
                          {/if}
                          <button
                            onclick={() => revokePermission(perm)}
                            disabled={isRevoking || !perm.id_upe}
                            title="Revocar este permiso"
                            class="ml-1 w-5 h-5 rounded-full flex items-center justify-center border-none cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            class:bg-green-200={perm.esta_permitido}
                            class:hover:bg-red-200={perm.esta_permitido}
                            class:hover:text-red-700={perm.esta_permitido}
                            class:text-green-600={perm.esta_permitido}
                            class:bg-red-200={!perm.esta_permitido}
                            class:hover:bg-red-300={!perm.esta_permitido}
                            class:text-red-600={!perm.esta_permitido}
                          >
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              width="10"
                              height="10"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="currentColor"
                              stroke-width="3"
                              ><line x1="18" y1="6" x2="6" y2="18" /><line
                                x1="6"
                                y1="6"
                                x2="18"
                                y2="18"
                              /></svg
                            >
                          </button>
                        </span>
                      {/each}
                    </div>
                  {/if}
                </div>

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
                    ><circle cx="11" cy="11" r="8" /><line
                      x1="21"
                      y1="21"
                      x2="16.65"
                      y2="16.65"
                    /></svg
                  >
                  <input
                    type="text"
                    bind:value={permSearch}
                    placeholder="Buscar permiso por nombre o slug..."
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-200 focus:border-amber-400 bg-white"
                  />
                </div>

                <div class="flex flex-col gap-5 max-h-[420px] overflow-y-auto pr-1">
                  {#each Object.entries(filteredPermissions) as [mod, perms] (mod)}
                    <div>
                      <h4
                        class="text-xs uppercase tracking-wider text-gray-500 font-semibold mb-2 border-b border-gray-100 pb-1.5"
                      >
                        {mod}
                      </h4>
                      <div class="flex flex-col gap-1">
                        {#each perms as perm (perm.id_permiso)}
                          {@const alreadyAssigned = isPermissionAlreadyAssigned(perm)}
                          <button
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg border transition-all text-left cursor-pointer text-sm bg-white"
                            class:border-amber-400={selectedPermission?.id_permiso ===
                              perm.id_permiso}
                            class:bg-amber-50={selectedPermission?.id_permiso === perm.id_permiso}
                            class:font-medium={selectedPermission?.id_permiso === perm.id_permiso}
                            class:text-amber-800={selectedPermission?.id_permiso ===
                              perm.id_permiso}
                            class:border-gray-100={selectedPermission?.id_permiso !==
                              perm.id_permiso}
                            class:hover:border-amber-300={selectedPermission?.id_permiso !==
                              perm.id_permiso}
                            class:hover:bg-amber-100={selectedPermission?.id_permiso !==
                              perm.id_permiso}
                            onclick={() => (selectedPermission = perm)}
                          >
                            <span class="flex-shrink-0 text-base">
                              {selectedPermission?.id_permiso === perm.id_permiso ? '🟡' : '⚪'}
                            </span>
                            <div class="flex-1 min-w-0">
                              <div class="font-medium truncate">
                                {perm.nombre}
                              </div>
                              <div class="text-xs text-gray-400 font-mono">
                                {perm.slug}
                              </div>
                            </div>
                            {#if alreadyAssigned}
                              <span
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-100 text-green-700 text-[11px] font-semibold flex-shrink-0 border border-green-200"
                              >
                                <svg
                                  xmlns="http://www.w3.org/2000/svg"
                                  width="12"
                                  height="12"
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  stroke="currentColor"
                                  stroke-width="3"
                                  ><polyline points="20 6 9 17 4 12"></polyline></svg
                                >
                                Asignado
                              </span>
                            {:else if selectedPermission?.id_permiso === perm.id_permiso}
                              <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="text-amber-500 flex-shrink-0"
                                ><polyline points="20 6 9 17 4 12" /></svg
                              >
                            {/if}
                          </button>
                        {/each}
                      </div>
                    </div>
                  {/each}

                  {#if Object.keys(filteredPermissions).length === 0}
                    <div class="text-center py-10 text-gray-400 text-sm">
                      No se encontraron permisos.
                    </div>
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
                    <p
                      class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-2"
                    >
                      Solo se muestran los tipos válidos para
                      <span class="font-mono font-semibold">{selectedPermission.slug}</span>.
                    </p>
                  {/if}
                  {#if flow === 'role' && selectedRole?.valid_assignment_context_types && selectedRole.valid_assignment_context_types.length < contextTypes.length}
                    <p
                      class="text-xs text-purple-600 bg-purple-50 border border-purple-200 rounded-lg px-3 py-2 mb-2"
                    >
                      Solo se muestran los contextos donde el rol
                      <span class="font-mono font-semibold">{selectedRole.nombre}</span> puede ser asignado.
                    </p>
                  {/if}
                  <div class="flex flex-col gap-2">
                    {#each availableContextTypes as ctxType (ctxType.value)}
                      <button
                        class="flex items-center gap-3 p-3.5 rounded-lg border-2 transition-all text-left cursor-pointer bg-white"
                        class:border-blue-400={selectedContextType?.type === ctxType.type}
                        class:bg-blue-50={selectedContextType?.type === ctxType.type}
                        class:ring-1={selectedContextType?.type === ctxType.type}
                        class:ring-blue-200={selectedContextType?.type === ctxType.type}
                        class:border-gray-200={selectedContextType?.type !== ctxType.type}
                        class:hover:border-blue-300={selectedContextType?.type !== ctxType.type}
                        class:hover:bg-blue-100={selectedContextType?.type !== ctxType.type}
                        onclick={() => {
                          selectedContextType = ctxType;
                          if (ctxType.type === ContextType.GLOBAL) {
                            selectedContextObject = null;
                          }
                        }}
                      >
                        <span class="text-xl flex-shrink-0">
                          {#if ctxType.type === ContextType.GLOBAL}🌐
                          {:else if ctxType.type === ContextType.FACULTAD}🏛️
                          {:else if ctxType.type === ContextType.DEPARTAMENTO}🏢
                          {:else if ctxType.type === ContextType.CARRERA}🎓
                          {:else if ctxType.type === ContextType.CURSO}📚
                          {:else if ctxType.type === ContextType.ACTIVIDAD}📋
                          {:else}📦{/if}
                        </span>
                        <div class="flex-1 min-w-0">
                          <div class="font-medium text-gray-900 text-sm">
                            {ctxType.label}
                          </div>
                          <div class="text-xs text-gray-400 truncate">
                            {ctxType.description}
                          </div>
                        </div>
                        {#if ctxType.count !== undefined}
                          <span
                            class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full flex-shrink-0"
                            >{ctxType.count}</span
                          >
                        {/if}
                        {#if selectedContextType?.type === ctxType.type}
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="text-blue-500 flex-shrink-0"
                            ><polyline points="20 6 9 17 4 12" /></svg
                          >
                        {/if}
                      </button>
                    {/each}
                  </div>
                </div>

                <!-- Right: Specific Object -->
                <div>
                  {#if selectedContextType && !isGlobalContext(selectedContextType.type)}
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
                        ><circle cx="11" cy="11" r="8" /><line
                          x1="21"
                          y1="21"
                          x2="16.65"
                          y2="16.65"
                        /></svg
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
                          <div
                            class="w-6 h-6 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin mx-auto mb-2"
                          ></div>
                          Cargando...
                        </div>
                      {:else if filteredContextObjects.length === 0}
                        <div class="text-center py-8 text-gray-400 text-sm">
                          No se encontraron resultados.
                        </div>
                      {:else}
                        {#each filteredContextObjects as obj (obj.id)}
                          {@const hasDuplicate = isContextObjectDuplicate(obj)}
                          <div class="relative group">
                            <button
                              class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border text-left transition-all text-sm bg-white w-full"
                              class:cursor-pointer={!hasDuplicate}
                              class:cursor-not-allowed={hasDuplicate}
                              class:border-blue-400={selectedContextObject?.id === obj.id &&
                                !hasDuplicate}
                              class:bg-blue-50={selectedContextObject?.id === obj.id &&
                                !hasDuplicate}
                              class:ring-1={selectedContextObject?.id === obj.id}
                              class:ring-blue-200={selectedContextObject?.id === obj.id &&
                                !hasDuplicate}
                              class:ring-orange-200={selectedContextObject?.id === obj.id &&
                                hasDuplicate}
                              class:font-medium={(selectedContextObject?.id === obj.id &&
                                !hasDuplicate) ||
                                hasDuplicate}
                              class:border-orange-400={hasDuplicate &&
                                selectedContextObject?.id !== obj.id}
                              class:bg-orange-50={hasDuplicate &&
                                selectedContextObject?.id !== obj.id}
                              class:hover:border-orange-300={hasDuplicate &&
                                selectedContextObject?.id !== obj.id}
                              class:hover:bg-orange-100={hasDuplicate &&
                                selectedContextObject?.id !== obj.id}
                              class:border-orange-500={hasDuplicate &&
                                selectedContextObject?.id === obj.id}
                              class:bg-orange-100={hasDuplicate &&
                                selectedContextObject?.id === obj.id}
                              class:hover:border-orange-600={hasDuplicate &&
                                selectedContextObject?.id === obj.id}
                              class:hover:bg-orange-200={hasDuplicate &&
                                selectedContextObject?.id === obj.id}
                              class:border-gray-200={selectedContextObject?.id !== obj.id &&
                                !hasDuplicate}
                              class:hover:border-blue-300={selectedContextObject?.id !== obj.id &&
                                !hasDuplicate}
                              class:hover:bg-blue-100={selectedContextObject?.id !== obj.id &&
                                !hasDuplicate}
                              disabled={hasDuplicate}
                              onmousemove={hasDuplicate ? handleMouseMove : undefined}
                              onmouseenter={hasDuplicate
                                ? () => showDuplicateTooltip(obj)
                                : undefined}
                              onmouseleave={hasDuplicate ? hideTooltip : undefined}
                              onclick={() => (selectedContextObject = obj)}
                            >
                              <span
                                class="text-gray-400 text-xs font-mono w-8 text-right flex-shrink-0"
                                >#{obj.id}</span
                              >
                              <span class="flex-1 truncate">{obj.label}</span>
                              {#if hasDuplicate}
                                <svg
                                  xmlns="http://www.w3.org/2000/svg"
                                  fill="none"
                                  viewBox="0 0 24 24"
                                  stroke-width="1.5"
                                  stroke="currentColor"
                                  class="size-5 flex-shrink-0 text-orange-600"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                  />
                                </svg>
                              {:else if selectedContextObject?.id === obj.id}
                                <svg
                                  xmlns="http://www.w3.org/2000/svg"
                                  width="14"
                                  height="14"
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  stroke="currentColor"
                                  stroke-width="2"
                                  class="text-blue-500 flex-shrink-0"
                                  ><polyline points="20 6 9 17 4 12" /></svg
                                >
                              {/if}
                            </button>
                          </div>
                        {/each}
                      {/if}
                    </div>
                  {:else if selectedContextType?.type === ContextType.GLOBAL}
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                      <span class="text-5xl mb-3">🌐</span>
                      <p class="text-sm font-medium text-gray-600">Contexto Global</p>
                      <p class="text-xs text-gray-400 mt-1">
                        Se aplicará en todo el sistema sin restricción de objeto
                      </p>
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
              <!-- Duplicate role+context warning -->
              {#if isDuplicateAssignment}
                <div
                  class="mb-5 bg-orange-50 border border-orange-300 text-orange-800 rounded-xl p-4 flex items-start gap-3"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="flex-shrink-0 mt-0.5 text-orange-500"
                    ><path
                      d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                    /><line x1="12" y1="9" x2="12" y2="13" /><line
                      x1="12"
                      y1="17"
                      x2="12.01"
                      y2="17"
                    /></svg
                  >
                  <div>
                    <p class="text-sm font-semibold">
                      El usuario ya tiene este rol en ese contexto
                    </p>
                    <p class="text-xs mt-0.5">
                      Existe una asignación vigente de <strong>{selectedRole?.nombre}</strong> en el contexto
                      seleccionado. Intentar guardar fallará por restricción de solapamiento. Revoca o
                      espera a que expire la asignación actual antes de crear una nueva.
                    </p>
                  </div>
                </div>
              {/if}

              <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                <!-- Left: Parameters (3 cols) -->
                <div class="lg:col-span-3 flex flex-col gap-6">
                  <!-- Date Fields -->
                  <div>
                    <h4 class="text-sm font-semibold text-gray-600 mb-3">Vigencia</h4>
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label
                          for="wizard-start-date"
                          class="block text-xs font-medium text-gray-500 mb-1.5">Fecha inicio</label
                        >
                        <input
                          id="wizard-start-date"
                          type="date"
                          bind:value={startDate}
                          min={today}
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 bg-white"
                        />
                      </div>
                      <div>
                        <label
                          for="wizard-end-date"
                          class="block text-xs font-medium text-gray-500 mb-1.5">Fecha fin</label
                        >
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
                      <h4 class="text-sm font-semibold text-gray-600 mb-4">
                        Configuración del permiso
                      </h4>

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
                            <input
                              type="checkbox"
                              bind:checked={permCanDelegate}
                              class="sr-only peer"
                            />
                            <div
                              class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-200 rounded-full peer peer-checked:bg-blue-500 transition-colors"
                            ></div>
                            <div
                              class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5 shadow-sm"
                            ></div>
                          </div>
                          <span class="text-sm text-gray-600">
                            {permCanDelegate
                              ? 'Sí, puede re-delegar este permiso'
                              : 'No puede delegar'}
                          </span>
                        </label>
                      </div>
                    </div>
                  {/if}
                </div>

                <!-- Right: Summary Card (2 cols) -->
                <div class="lg:col-span-2">
                  <div
                    class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl border border-gray-200 p-5 sticky top-0"
                  >
                    <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        ><path
                          d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                        /><polyline points="14 2 14 8 20 8" /><line
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
                          {#if selectedContextType?.type === ContextType.GLOBAL}
                            🌐 Global
                          {:else}
                            {selectedContextType?.label}
                          {/if}
                        </span>
                      </div>

                      {#if selectedContextType && !isGlobalContext(selectedContextType.type) && selectedContextObject}
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
                        <span
                          class:text-gray-900={!!endDate}
                          class:text-gray-400={!endDate}
                          class:font-medium={!!endDate}
                          class:text-xs={!endDate}
                        >
                          {endDate || '1 año (por defecto)'}
                        </span>
                      </div>

                      <!-- Permission specifics -->
                      {#if flow === 'permission'}
                        <div class="border-t border-gray-200 my-1"></div>
                        <div class="flex justify-between items-center">
                          <span class="text-gray-500">Acción</span>
                          <span
                            class="font-semibold"
                            class:text-green-600={permAllowed}
                            class:text-red-600={!permAllowed}
                          >
                            {permAllowed ? '🟢 Permitir' : '🔴 Denegar'}
                          </span>
                        </div>
                        <div class="flex justify-between items-center">
                          <span class="text-gray-500">Delegable</span>
                          <span class="font-medium text-gray-900"
                            >{permCanDelegate ? 'Sí' : 'No'}</span
                          >
                        </div>
                      {/if}
                    </div>
                  </div>
                </div>
              </div>
            {/if}
          </div>
        {/if}

        <!-- ════════════════════════════════════════ -->
        <!-- ROLE DETAIL OVERLAY PANEL               -->
        <!-- Opens when a role chip is clicked        -->
        <!-- ════════════════════════════════════════ -->
        {#if roleDetailOpen}
          <div class="absolute inset-0 bg-white z-10 flex flex-col overflow-hidden">
            <!-- Panel header -->
            <div
              class="px-8 py-4 border-b border-gray-200 bg-purple-50 flex items-center gap-3 flex-shrink-0"
            >
              <span class="text-2xl">👔</span>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-purple-600 uppercase tracking-wider">
                  Detalle de rol
                </p>
                <h3 class="text-lg font-bold text-gray-900 truncate">
                  {roleDetailRoleName}
                </h3>
              </div>
              <button
                onclick={() => (roleDetailOpen = false)}
                class="p-2 rounded-lg hover:bg-purple-100 transition text-gray-400 hover:text-gray-600 border-none bg-transparent cursor-pointer flex-shrink-0"
                aria-label="Cerrar detalle"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
                >
              </button>
            </div>

            <!-- Panel body (scrollable) -->
            <div class="flex-1 overflow-y-auto px-8 py-5 flex flex-col gap-6">
              <!-- Contexts where this role is assigned to the user -->
              <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                  Contextos asignados al usuario
                </p>
                <div class="flex flex-wrap gap-2">
                  {#each userCurrentRoleAssignments.filter((a) => a.id_rol === roleDetailTarget?.id_rol) as asig (asig.id_ura)}
                    {@const isGlobal =
                      asig.id_contexto ===
                      contextTypes.find((ct) => ct.type === ContextType.GLOBAL)?.context_id}
                    <span
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border {isGlobal
                        ? 'bg-blue-50 text-blue-800 border-blue-200'
                        : 'bg-gray-50 text-gray-700 border-gray-200'}"
                    >
                      {isGlobal ? '🌐' : '🏷️'}
                      {asig.contexto_display ??
                        (isGlobal ? 'Global' : 'Contexto #' + asig.id_contexto)}
                    </span>
                  {/each}
                </div>
              </div>

              <!-- Permissions of this role -->
              <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                  Permisos incluidos en este rol
                  {#if !roleDetailLoading}
                    <span
                      class="ml-1.5 px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-bold"
                      >{roleDetailPerms.length}</span
                    >
                  {/if}
                </p>

                {#if roleDetailLoading}
                  <div class="flex items-center gap-2 py-6 text-gray-400 text-sm">
                    <div
                      class="w-5 h-5 border-2 border-gray-300 border-t-purple-500 rounded-full animate-spin"
                    ></div>
                    Cargando permisos…
                  </div>
                {:else if roleDetailPerms.length === 0}
                  <p class="text-sm text-gray-400 italic py-4">
                    Este rol no tiene permisos asignados o es el SuperAdmin (permisos implícitos).
                  </p>
                {:else}
                  <div class="flex flex-col gap-1.5">
                    {#each roleDetailPerms as perm (perm.id_permiso)}
                      <div
                        class="flex items-start gap-3 px-4 py-3 rounded-lg border border-gray-100 bg-gray-50 hover:bg-gray-100 transition-colors"
                      >
                        <span class="text-base flex-shrink-0 mt-0.5"
                          >{perm.slug === '*' ? '⚡' : '🔐'}</span
                        >
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-800">{perm.nombre}</span>
                            {#if perm.puede_delegar_permisos}
                              <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 border border-amber-200"
                                >delegable</span
                              >
                            {/if}
                          </div>
                          <div class="text-xs font-mono text-purple-600 mt-0.5">
                            {perm.slug}
                          </div>
                          {#if perm.descripcion}
                            <div class="text-xs text-gray-500 mt-1">
                              {perm.descripcion}
                            </div>
                          {/if}
                        </div>
                      </div>
                    {/each}
                  </div>
                {/if}
              </div>
            </div>

            <!-- Panel footer -->
            <div class="px-8 py-3 border-t border-gray-200 bg-gray-50 flex-shrink-0">
              <button
                onclick={() => (roleDetailOpen = false)}
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 transition cursor-pointer"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"><polyline points="15 18 9 12 15 6" /></svg
                >
                Volver a selección de rol
              </button>
            </div>
          </div>
        {/if}
      </div>

      <!-- ════════════════════════════════ -->
      <!-- FOOTER NAV                      -->
      <!-- ════════════════════════════════ -->
      <div
        class="px-8 py-4 border-t border-gray-200 bg-white flex justify-between items-center flex-shrink-0"
      >
        <div>
          {#if currentStep > 1}
            <button
              onclick={goBack}
              class="flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition cursor-pointer border-none"
              disabled={isSaving}
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"><polyline points="15 18 9 12 15 6" /></svg
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
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"><polyline points="9 18 15 12 9 6" /></svg
              >
            </button>
          {:else}
            <button
              onclick={handleSave}
              class="flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-green-500 hover:bg-green-600 transition cursor-pointer border-none disabled:opacity-60"
              disabled={isSaving || !!successMsg}
            >
              {#if isSaving}
                <div
                  class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
                ></div>
                Guardando...
              {:else if successMsg}
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"><polyline points="20 6 9 17 4 12" /></svg
                >
                Guardado
              {:else}
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"><polyline points="20 6 9 17 4 12" /></svg
                >
                Confirmar y Guardar
              {/if}
            </button>
          {/if}
        </div>
      </div>
    </div>
  </div>

  <!-- Floating tooltip anchored to mouse cursor (bottom-left corner) -->
  {#if showTooltip && currentDuplicateAssignment}
    <div
      class="fixed bg-orange-900 text-orange-50 text-xs rounded-lg px-3 py-2 shadow-lg z-9999 pointer-events-none max-w-xs"
      style="left: {tooltipX}px; top: {tooltipY}px; transform: translateY(-100%); font-size: 12px;"
      role="tooltip"
    >
      <p class="font-semibold mb-1">Conflicto detectado</p>
      <p class="mb-2">
        Existe una asignación vigente de <strong>{selectedRole?.nombre}</strong> en este contexto.
      </p>
      <div class="text-orange-100 space-y-1 mb-2">
        <p>
          <strong>Vigencia:</strong>
          {currentDuplicateAssignment.fecha_inicio_planificada} a {currentDuplicateAssignment.fecha_fin_planificada}
        </p>
        <p>
          <strong>Asignado por:</strong>
          {currentDuplicateAssignment.creado_por_nombre}
        </p>
      </div>
      <p class="text-orange-100">
        Revoca o espera a que expire la asignación actual antes de crear una nueva.
      </p>
    </div>
  {/if}
{/if}
