<script lang="ts">
  import { router } from '@inertiajs/svelte';

  interface Props {
    isOpen: boolean;
    onClose: () => void;
    usuario: any;
    availableRoles: any[];
    availablePermissions: Record<string, any[]>; // Grouped by module
    loadPath?: string; // Optional custom load path
    savePath?: string; // Optional custom save path
    hideRoles?: boolean;
    isCourseContext?: boolean;
  }

  let {
    isOpen = $bindable(),
    onClose,
    usuario,
    availableRoles = [],
    availablePermissions = {},
    loadPath = '',
    savePath = '',
    hideRoles = false,
    isCourseContext = false,
  }: Props = $props();

  let activeTab = $state('roles'); // 'roles' | 'permissions'

  $effect(() => {
    if (hideRoles) {
      activeTab = 'permissions';
    }
  });

  let isLoading = $state(false);
  let loadError = $state(''); // Track error messages
  let selectedRoles = $state<number[]>([]);
  // id_permiso -> { allowed: boolean | null, can_delegate: boolean }
  let specialPermissions = $state<Record<number, any>>({});

  // Local copy of available permissions
  let currentPermissions = $state<Record<string, any[]>>({});

  // Global permission map for quick lookup (id -> permission object)
  let permissionMap = $state<Record<number, any>>({});

  // Local copy of available roles - updated from backend OR from prop
  let localAvailableRoles = $state<any[]>([]);

  // Confirmation dialog state
  let showConfirmation = $state(false);
  let pendingPermissions = $state<Record<number, any> | null>(null);
  let pendingRoles = $state<number[] | null>(null);

  // Initialize local roles from prop ONLY if loadPath is not provided
  $effect(() => {
    if (!loadPath && availableRoles && availableRoles.length > 0) {
      localAvailableRoles = availableRoles;
    }
  });

  // Synchronize local state with prop ONLY if we don't have data yet and no custom loadPath
  $effect(() => {
    if (!loadPath && availablePermissions && Object.keys(availablePermissions).length > 0 && Object.keys(currentPermissions).length === 0) {
      currentPermissions = availablePermissions;
    }
  });

  // Load initial data when modal opens or user changes
  $effect(() => {
    if (isOpen) {
      if (usuario?.id_usuario) {
        // Reset local states to avoid leakage from previous users/views
        selectedRoles = [];
        specialPermissions = {};
        currentPermissions = {}; // Reset will trigger the prop synchronization if available

        loadUserPermissions();
      }
    }
  });

  async function loadUserPermissions() {
    isLoading = true;
    loadError = '';
    try {
      const url = loadPath || `/admin/usuarios/${usuario.id_usuario}/permissions`;
      console.log('🔄 Loading permissions from:', url);

      const res = await fetch(url);
      console.log('📦 Response status:', res.status);

      if (!res.ok) {
        const errorText = await res.text();
        console.error('❌ HTTP Error Response:', res.status, errorText.substring(0, 500));
        const errorData = await res.json().catch(() => ({}));
        const errorMsg = errorData.error || errorData.message || `HTTP ${res.status}`;
        throw new Error(`Failed to load permissions: ${errorMsg}`);
      }

      const data = await res.json();
      console.log('✅ Loaded permissions data:', data);

      selectedRoles = data.roles || [];

      // Map special permissions array to object key-value
      const specialMapping: Record<number, any> = {};
      (data.special_permissions || []).forEach((sp: any) => {
        // Convert esta_permitido to proper boolean
        // Handle: 1/0 (int), true/false (bool), '1'/'0' (string), null (inherit)
        let allowed: boolean | null = null;

        if (sp.esta_permitido == null || sp.esta_permitido === '') {
          allowed = null; // Inherit
        } else if (sp.esta_permitido == true || sp.esta_permitido == 1 || sp.esta_permitido === '1') {
          allowed = true; // Allow (handles: 1, true, '1')
        } else if (sp.esta_permitido == false || sp.esta_permitido == 0 || sp.esta_permitido === '0') {
          allowed = false; // Deny (handles: 0, false, '0')
        }

        // Use can_delegate from backend, fallback to puede_delegar for backward compatibility
        const canDelegate = sp.can_delegate !== undefined ? sp.can_delegate : sp.puede_delegar || false;

        specialMapping[sp.id_permiso] = {
          allowed: allowed,
          can_delegate: !!canDelegate, // Ensure boolean
          source: sp.source || 'special', // 'special' o 'role'
        };
      });
      specialPermissions = specialMapping;
      console.log('📋 Mapped specialPermissions:', specialPermissions);

      // Debug: show the values that came from the API
      console.log('🔍 Raw esta_permitido values from API:');
      (data.special_permissions || []).forEach((sp: any) => {
        console.log(
          `  ID ${sp.id_permiso}: esta_permitido=${sp.esta_permitido} (type: ${typeof sp.esta_permitido}), mapped to: ${specialMapping[sp.id_permiso]?.allowed}`,
        );
      });

      // If backend provides a specific list of available permissions for this user/context, use it.
      if (data.available_permissions) {
        currentPermissions = data.available_permissions;
        console.log('📦 currentPermissions structure:', currentPermissions);
        console.log('📦 currentPermissions keys:', Object.keys(currentPermissions));

        // Build permissionMap from available_permissions for quick lookup
        permissionMap = {};
        for (const [module, perms] of Object.entries(data.available_permissions) as [string, any[]][]) {
          for (const perm of perms) {
            permissionMap[perm.id_permiso] = perm;
          }
        }
        console.log('🗺️ Built permissionMap:', permissionMap);

        // Check if permission 64 is in there
        for (const [module, perms] of Object.entries(currentPermissions)) {
          const has64 = perms.some((p) => p.id_permiso === 64);
          if (has64) {
            console.log('✓ Permission 64 found in module:', module);
          }
        }
      }

      // ✅ ALWAYS update available roles from backend if provided (this is the source of truth for custom loadPath)
      if (data.available_roles && Array.isArray(data.available_roles)) {
        console.log('Loaded available roles:', data.available_roles);
        localAvailableRoles = data.available_roles;
      } else {
        console.warn('No available_roles in response');
      }
    } catch (error) {
      const msg = error instanceof Error ? error.message : String(error);
      console.error('Error loading permissions:', msg);
      loadError = msg;
    } finally {
      isLoading = false;
    }
  }

  function handleSave() {
    // Build special_permissions object with ONLY the permissions that were actually modified
    // (exclude: permisos por rol, y permisos sin cambios)
    const changedPermissions: Record<number, any> = {};

    // Only include permissions that have been explicitly set (allowed: true|false)
    // NOT permissions with allowed: null (those are "inherit" state and shouldn't be sent)
    Object.entries(specialPermissions).forEach(([permIdStr, permState]: [string, any]) => {
      const permId = parseInt(permIdStr);
      const source = permState?.source ?? 'special';

      // Solo incluir si:
      // 1. NO viene de un rol (source !== 'role')
      // 2. Y tiene un estado explícito (allowed !== null)
      if (source !== 'role' && permState.allowed !== null) {
        changedPermissions[permId] = {
          allowed: permState.allowed,
          can_delegate: permState.can_delegate ?? false,
        };
      }
    });

    // Store pending data and show confirmation
    pendingPermissions = changedPermissions;
    pendingRoles = selectedRoles;
    showConfirmation = true;
  }

  function confirmSave() {
    if (!pendingPermissions || !pendingRoles) return;

    isLoading = true;
    const url = savePath || `/admin/usuarios/${usuario.id_usuario}/sync-permissions`;

    router.post(
      url,
      {
        roles: pendingRoles,
        special_permissions: pendingPermissions,
      },
      {
        onSuccess: () => {
          showConfirmation = false;
          pendingPermissions = null;
          pendingRoles = null;
          // Reload permissions after successful save to show updates
          loadUserPermissions().then(() => {
            onClose();
            isLoading = false;
          });
        },
        onError: () => {
          showConfirmation = false;
          isLoading = false;
        },
      },
    );
  }

  function cancelSave() {
    showConfirmation = false;
    pendingPermissions = null;
    pendingRoles = null;
  }

  // Helper to toggle tri-state permission
  function cyclePermission(id_permiso: number) {
    if (!specialPermissions[id_permiso]) {
      specialPermissions[id_permiso] = { allowed: null, can_delegate: false };
    }

    const current = specialPermissions[id_permiso].allowed;
    let nextAllowed: boolean | null = null;

    if (current === null) {
      nextAllowed = true; // Allow
    } else if (current === true) {
      nextAllowed = false; // Deny
    } else {
      nextAllowed = null; // Inherit (reset)
    }

    specialPermissions[id_permiso] = {
      ...specialPermissions[id_permiso],
      allowed: nextAllowed,
    };

    // Force Svelte 5 to detect the change by reassigning the parent object
    specialPermissions = { ...specialPermissions };
  }

  function toggleDelegation(id_permiso: number) {
    if (!specialPermissions[id_permiso]) {
      specialPermissions[id_permiso] = { allowed: null, can_delegate: false };
    }

    specialPermissions[id_permiso] = {
      ...specialPermissions[id_permiso],
      can_delegate: !specialPermissions[id_permiso].can_delegate,
    };

    // Force Svelte 5 to detect the change by reassigning the parent object
    specialPermissions = { ...specialPermissions };
  }

  // Safely get entries for availablePermissions and apply visual filter if needed
  let moduleEntries = $derived.by(() => {
    const allEntries = Object.entries(currentPermissions || {});
    console.log(
      '📋 All module entries BEFORE filter:',
      allEntries.map(([mod, perms]) => ({ module: mod, count: perms.length, has64: perms.some((p) => p.id_permiso === 64) })),
    );

    const filtered = allEntries.filter(([modulo]) => {
      // If in course context OR hideRoles is true, we strictly show only specific modules
      if (isCourseContext || hideRoles) {
        const normalized = (modulo || '')
          .trim()
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '');
        const matches = normalized.includes('docencia') || normalized.includes('ayudantia');
        console.log(`Module filter: "${modulo}" → normalized="${normalized}" → matches=${matches}`);
        return matches;
      }
      return true;
    });

    console.log(
      '📋 Module entries AFTER filter:',
      filtered.map(([mod, perms]) => ({ module: mod, count: perms.length, has64: perms.some((p) => p.id_permiso === 64) })),
    );
    return filtered;
  });
</script>

{#if isOpen}
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    onclick={(e) => e.target === e.currentTarget && onClose()}
    role="presentation"
  >
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden" role="dialog" aria-modal="true">
      <div class="p-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="text-lg font-bold m-0">Permisos: {usuario?.username || usuario?.usuario?.username || 'Cargando...'}</h2>
        <button onclick={onClose} class="bg-none border-none text-xl cursor-pointer text-gray-600 hover:text-gray-800">✕</button>
      </div>

      {#if !hideRoles}
        <div class="flex border-b border-gray-200">
          <button
            class="flex-1 py-3 px-4 bg-gray-100 border-none cursor-pointer border-b-2 border-transparent text-gray-700 transition-all hover:bg-gray-50"
            class:bg-white={activeTab === 'roles'}
            class:border-b-blue-500={activeTab === 'roles'}
            class:text-blue-600={activeTab === 'roles'}
            class:font-semibold={activeTab === 'roles'}
            onclick={() => (activeTab = 'roles')}
          >
            Roles
          </button>
          <button
            class="flex-1 py-3 px-4 bg-gray-100 border-none cursor-pointer border-b-2 border-transparent text-gray-700 transition-all hover:bg-gray-50"
            class:bg-white={activeTab === 'permissions'}
            class:border-b-blue-500={activeTab === 'permissions'}
            class:text-blue-600={activeTab === 'permissions'}
            class:font-semibold={activeTab === 'permissions'}
            onclick={() => (activeTab = 'permissions')}
          >
            Permisos Especiales
          </button>
        </div>
      {/if}

      <div class="p-4 overflow-y-auto bg-gray-50 flex-1">
        {#if loadError}
          <div class="bg-red-100 text-red-700 p-2.5 rounded mb-2.5">
            ❌ {loadError}
          </div>
        {/if}
        {#if isLoading}
          <div class="text-center text-gray-600 py-8">Cargando...</div>
        {:else if activeTab === 'roles'}
          <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));">
            {#if !localAvailableRoles || localAvailableRoles.length === 0}
              <div class="text-center text-gray-400 py-6 px-4 border-2 border-dashed border-gray-200 rounded-lg col-span-full text-sm">
                No hay roles disponibles.
              </div>
            {:else}
              {#each localAvailableRoles as rol}
                <label
                  class="border border-gray-300 p-3 rounded cursor-pointer text-center transition-all hover:bg-blue-50 hover:border-blue-300"
                  class:bg-blue-50={selectedRoles.includes(rol.id_rol)}
                  class:border-blue-500={selectedRoles.includes(rol.id_rol)}
                  class:text-blue-700={selectedRoles.includes(rol.id_rol)}
                  class:font-medium={selectedRoles.includes(rol.id_rol)}
                  class:shadow-sm={selectedRoles.includes(rol.id_rol)}
                >
                  <input type="checkbox" bind:group={selectedRoles} value={rol.id_rol} class="hidden" />
                  <div>{rol.nombre}</div>
                </label>
              {/each}
            {/if}
          </div>
        {:else}
          <div>
            <div class="bg-slate-100 p-2 mb-4 rounded text-center text-gray-700 text-xs">
              <small>
                🞪 Permitir | 🔴 Denegar | ⚪ Heredar | <strong>Delegar</strong> (Solo Admin)
              </small>
            </div>

            {#if moduleEntries.length === 0}
              <div class="text-center text-gray-400 py-6 px-4 border-2 border-dashed border-gray-200 rounded-lg text-sm">
                No hay permisos disponibles.
              </div>
            {:else}
              {#each moduleEntries as [modulo, perms]}
                <div class="mt-4">
                  <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide border-b-2 border-gray-200 pb-1 mb-3">{modulo || 'General'}</h3>
                  <div class="flex flex-col gap-3">
                    {#each perms as perm}
                      {@const state = specialPermissions[perm.id_permiso]?.allowed ?? null}
                      {@const canDelegate = specialPermissions[perm.id_permiso]?.can_delegate ?? false}
                      {@const source = specialPermissions[perm.id_permiso]?.source ?? 'special'}
                      {@const isFromRole = source === 'role'}
                      {#if perm.id_permiso === 64}
                        <div class="fixed top-2.5 right-2.5 bg-yellow-300 p-2.5 font-bold z-50" style="white-space: nowrap;">
                          🎯 RENDERING PERMISSION 64!
                        </div>
                      {/if}
                      <div
                        class="flex gap-1 p-2 bg-gray-50 border border-gray-200 rounded transition-all hover:bg-gray-100"
                        class:opacity-70={isFromRole}
                        class:bg-blue-50={isFromRole}
                      >
                        <button
                          class="flex-1 flex items-center gap-2 p-2 px-3 border-none bg-transparent cursor-pointer text-left text-xs text-gray-900 rounded hover:bg-black/5"
                          class:bg-green-50={state === true}
                          class:text-green-900={state === true}
                          class:bg-red-50={state === false}
                          class:text-red-900={state === false}
                          class:opacity-60={isFromRole}
                          class:cursor-not-allowed={isFromRole}
                          onclick={() => !isFromRole && cyclePermission(perm.id_permiso)}
                          title={isFromRole ? 'Permiso heredado del rol (solo lectura)' : perm.descripcion}
                          disabled={isFromRole}
                        >
                          <span class="flex-shrink-0 text-base min-w-5">
                            {#if state === true}🟢
                            {:else if state === false}🔴
                            {:else}⚪{/if}
                          </span>
                          <div class="flex-1 flex flex-col gap-0.5 min-w-0">
                            <div class="font-medium overflow-hidden text-ellipsis whitespace-nowrap">
                              {perm.nombre}
                            </div>
                            <div class="text-xs text-gray-500">{perm.slug}</div>
                          </div>
                          {#if isFromRole}
                            <span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-xs font-bold rounded flex-shrink-0">👔 ROL</span>
                          {/if}
                        </button>

                        {#if !hideRoles}
                          <button
                            class="flex-shrink-0 flex items-center justify-center w-8 h-8 p-0 border border-gray-300 bg-gray-50 rounded cursor-pointer text-gray-400 transition-all hover:bg-gray-100 hover:border-gray-400 hover:text-gray-600"
                            class:bg-yellow-100={canDelegate}
                            class:border-yellow-400={canDelegate}
                            class:text-yellow-900={canDelegate}
                            onclick={() => toggleDelegation(perm.id_permiso)}
                            title="¿Puede delegar este permiso?"
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
                              ><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline
                                points="16 11 18 13 22 9"
                              ></polyline></svg
                            >
                          </button>
                        {/if}
                      </div>
                    {/each}
                  </div>
                </div>
              {/each}
            {/if}
          </div>
        {/if}
      </div>

      <div class="p-4 border-t border-gray-200 flex justify-end gap-2 bg-white">
        <button
          onclick={onClose}
          class="px-4 py-2 bg-gray-100 text-gray-700 rounded cursor-pointer border-none hover:bg-gray-200 disabled:opacity-70"
          disabled={isLoading}>Cancelar</button
        >
        <button
          onclick={handleSave}
          class="px-4 py-2 bg-blue-500 text-white rounded cursor-pointer border-none hover:bg-blue-600 disabled:opacity-70"
          disabled={isLoading}
        >
          {#if isLoading}Guardando...{:else}Guardar Cambios{/if}
        </button>
      </div>
    </div>
  </div>

  {#if showConfirmation}
    <div
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-60 p-4"
      onclick={(e) => e.target === e.currentTarget && cancelSave()}
      role="presentation"
    >
      <div class="bg-white rounded-xl max-w-sm w-full shadow-2xl" role="alertdialog" aria-modal="true">
        <div class="p-5 border-b border-gray-200 flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900 m-0">⚠️ Confirmar Cambios de Permisos</h3>
          <button onclick={cancelSave} class="bg-none border-none text-xl cursor-pointer text-gray-600 hover:text-gray-800">✕</button>
        </div>

        <div class="p-5 max-h-60 overflow-y-auto">
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <p class="m-2 text-sm text-blue-900"><strong>Usuario:</strong> {usuario?.username || usuario?.usuario?.username || 'Desconocido'}</p>
            <p class="m-2 text-sm text-blue-900"><strong>Contexto:</strong> Se aplicarán los permisos al contexto específico de este curso.</p>
          </div>

          <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
            <h4 class="m-0 mb-4 text-sm font-semibold text-green-900">Resumen de cambios:</h4>

            {#if pendingRoles && pendingRoles.length > 0}
              <div class="mb-4">
                <strong class="block mb-2 text-sm text-green-900">👔 Roles ({pendingRoles.length}):</strong>
                <ul class="m-0 pl-6 list-none">
                  {#each pendingRoles as roleId (roleId)}
                    {@const foundRole = localAvailableRoles.find((r: any) => r.id_rol === roleId || r.id === roleId)}
                    <li class="text-sm text-green-900 mb-1.5">{foundRole?.nombre ?? foundRole?.name ?? `Rol #${roleId}`}</li>
                  {/each}
                </ul>
              </div>
            {/if}

            {#if pendingPermissions && Object.keys(pendingPermissions).length > 0}
              <div class="mb-4">
                <strong class="block mb-2 text-sm text-green-900">🔐 Permisos especiales ({Object.keys(pendingPermissions).length}):</strong>
                <ul class="m-0 pl-6 list-none">
                  {#each Object.entries(pendingPermissions) as [permIdStr, permState]}
                    {@const permId = parseInt(permIdStr)}
                    {@const perm = permissionMap[permId]}
                    <li class="text-sm text-green-900 mb-1.5 flex justify-between items-baseline gap-2">
                      <span>{perm?.nombre || perm?.name || `Permiso #${permId}`}</span>
                      <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded whitespace-nowrap">
                        {#if permState.allowed === true}
                          ✓ permitido
                        {:else if permState.allowed === false}
                          ✗ denegado
                        {/if}
                        {#if permState.can_delegate}
                          · delegable
                        {/if}
                      </span>
                    </li>
                  {/each}
                </ul>
              </div>
            {/if}

            {#if (!pendingRoles || pendingRoles.length === 0) && (!pendingPermissions || Object.keys(pendingPermissions).length === 0)}
              <p class="text-sm text-gray-600 italic text-center py-3">No hay cambios para guardar.</p>
            {/if}
          </div>

          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-900">
            ℹ️ <em>Estos cambios se aplicarán <strong>solo en el contexto de este curso</strong> y no afectarán otros cursos.</em>
          </div>
        </div>

        <div class="p-4 border-t border-gray-200 flex justify-end gap-3 bg-gray-50">
          <button
            onclick={cancelSave}
            class="px-4 py-2 bg-white text-gray-700 rounded border border-gray-300 cursor-pointer hover:bg-gray-100 disabled:opacity-70"
            disabled={isLoading}>Cancelar</button
          >
          <button
            onclick={confirmSave}
            class="px-4 py-2 bg-green-500 text-white rounded cursor-pointer border-none hover:bg-green-600 font-medium disabled:opacity-70"
            disabled={isLoading}
          >
            {#if isLoading}Guardando...{:else}Confirmar y Guardar{/if}
          </button>
        </div>
      </div>
    </div>
  {/if}
{/if}
