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
        for (const [module, perms] of Object.entries(data.available_permissions)) {
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
  <div class="modal-backdrop" onclick={(e) => e.target === e.currentTarget && onClose()} role="presentation">
    <div class="modal-content" role="dialog" aria-modal="true">
      <div class="modal-header">
        <h2 class="modal-title">Permisos: {usuario?.username || usuario?.usuario?.username || 'Cargando...'}</h2>
        <button onclick={onClose} class="close-button">✕</button>
      </div>

      {#if !hideRoles}
        <div class="tabs">
          <button class="tab-btn" class:active={activeTab === 'roles'} onclick={() => (activeTab = 'roles')}> Roles </button>
          <button class="tab-btn" class:active={activeTab === 'permissions'} onclick={() => (activeTab = 'permissions')}>
            Permisos Especiales
          </button>
        </div>
      {/if}

      <div class="modal-body">
        {#if loadError}
          <div class="error-banner" style="background: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
            ❌ {loadError}
          </div>
        {/if}
        {#if isLoading}
          <div class="loading-state">Cargando...</div>
        {:else if activeTab === 'roles'}
          <div class="roles-grid">
            {#if !localAvailableRoles || localAvailableRoles.length === 0}
              <div class="empty-state">No hay roles disponibles.</div>
            {:else}
              {#each localAvailableRoles as rol}
                <label class="role-card" class:selected={selectedRoles.includes(rol.id_rol)}>
                  <input type="checkbox" bind:group={selectedRoles} value={rol.id_rol} class="hidden-checkbox" />
                  <div class="role-name">{rol.nombre}</div>
                </label>
              {/each}
            {/if}
          </div>
        {:else}
          <div class="permissions-list">
            <div class="info-banner">
              <small>
                🟢 Permitir | 🔴 Denegar | ⚪ Heredar | <strong>Delegar</strong> (Solo Admin)
              </small>
            </div>

            {#if moduleEntries.length === 0}
              <div class="empty-state">No hay permisos disponibles.</div>
            {:else}
              {#each moduleEntries as [modulo, perms]}
                <div class="module-group">
                  <h3>{modulo || 'General'}</h3>
                  <div class="perms-grid">
                    {#each perms as perm}
                      {@const state = specialPermissions[perm.id_permiso]?.allowed ?? null}
                      {@const canDelegate = specialPermissions[perm.id_permiso]?.can_delegate ?? false}
                      {@const source = specialPermissions[perm.id_permiso]?.source ?? 'special'}
                      {@const isFromRole = source === 'role'}
                      {#if perm.id_permiso === 64}
                        <div style="position: fixed; top: 10px; right: 10px; background: yellow; padding: 10px; font-weight: bold; z-index: 10000;">
                          🎯 RENDERING PERMISSION 64!
                        </div>
                      {/if}
                      <div class="perm-row" class:from-role={isFromRole}>
                        <button
                          class="perm-btn"
                          class:allow={state === true}
                          class:deny={state === false}
                          class:disabled={isFromRole}
                          onclick={() => !isFromRole && cyclePermission(perm.id_permiso)}
                          title={isFromRole ? 'Permiso heredado del rol (solo lectura)' : perm.descripcion}
                          disabled={isFromRole}
                        >
                          <span class="status-indicator">
                            {#if state === true}🟢
                            {:else if state === false}🔴
                            {:else}⚪{/if}
                          </span>
                          <div style="flex: 1; display: flex; flex-direction: column; gap: 0.2rem; min-width: 0;">
                            <div style="font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                              {perm.nombre}
                            </div>
                            <div class="perm-slug">{perm.slug}</div>
                          </div>
                          {#if isFromRole}
                            <span class="role-badge">👔 ROL</span>
                          {/if}
                        </button>

                        {#if !hideRoles}
                          <button
                            class="delegate-btn"
                            class:active={canDelegate}
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

      <div class="modal-footer">
        <button onclick={onClose} class="btn-cancel" disabled={isLoading}>Cancelar</button>
        <button onclick={handleSave} class="btn-submit" disabled={isLoading}>
          {#if isLoading}Guardando...{:else}Guardar Cambios{/if}
        </button>
      </div>
    </div>
  </div>

  {#if showConfirmation}
    <div class="confirm-backdrop" onclick={(e) => e.target === e.currentTarget && cancelSave()} role="presentation">
      <div class="confirm-dialog" role="alertdialog" aria-modal="true">
        <div class="confirm-header">
          <h3 class="confirm-title">⚠️ Confirmar Cambios de Permisos</h3>
          <button onclick={cancelSave} class="close-button">✕</button>
        </div>

        <div class="confirm-body">
          <div class="confirm-info">
            <p><strong>Usuario:</strong> {usuario?.username || usuario?.usuario?.username || 'Desconocido'}</p>
            <p><strong>Contexto:</strong> Se aplicarán los permisos al contexto específico de este curso.</p>
          </div>

          <div class="confirm-changes">
            <h4>Resumen de cambios:</h4>

            {#if pendingRoles && pendingRoles.length > 0}
              <div class="change-section">
                <strong>👔 Roles ({pendingRoles.length}):</strong>
                <ul class="change-list">
                  {#each pendingRoles as roleId (roleId)}
                    {@const foundRole = localAvailableRoles.find((r: any) => r.id_rol === roleId || r.id === roleId)}
                    <li>{foundRole?.nombre ?? foundRole?.name ?? `Rol #${roleId}`}</li>
                  {/each}
                </ul>
              </div>
            {/if}

            {#if pendingPermissions && Object.keys(pendingPermissions).length > 0}
              <div class="change-section">
                <strong>🔐 Permisos especiales ({Object.keys(pendingPermissions).length}):</strong>
                <ul class="change-list">
                  {#each Object.entries(pendingPermissions) as [permIdStr, permState]}
                    {@const permId = parseInt(permIdStr)}
                    {@const perm = permissionMap[permId] || getPermissionFromAllPerms(permId)}
                    <li>
                      {perm?.nombre || perm?.name || `Permiso #${permId}`}
                      <span class="perm-state">
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
              <p class="no-changes">No hay cambios para guardar.</p>
            {/if}
          </div>

          <div class="confirm-warning">
            ℹ️ <em>Estos cambios se aplicarán <strong>solo en el contexto de este curso</strong> y no afectarán otros cursos.</em>
          </div>
        </div>

        <div class="confirm-footer">
          <button onclick={cancelSave} class="btn-cancel" disabled={isLoading}>Cancelar</button>
          <button onclick={confirmSave} class="btn-confirm" disabled={isLoading}>
            {#if isLoading}Guardando...{:else}Confirmar y Guardar{/if}
          </button>
        </div>
      </div>
    </div>
  {/if}
{/if}

<style>
  .modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 1rem;
  }
  .modal-content {
    background: white;
    border-radius: 12px;
    max-width: 650px;
    width: 100%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  .modal-header {
    padding: 1rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .modal-title {
    font-size: 1.1rem;
    font-weight: bold;
    margin: 0;
  }
  .close-button {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #666;
  }

  .tabs {
    display: flex;
    border-bottom: 1px solid #eee;
  }
  .tab-btn {
    flex: 1;
    padding: 0.75rem;
    background: #f9f9f9;
    border: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    color: #555;
  }
  .tab-btn.active {
    background: white;
    border-bottom-color: #3b82f6;
    color: #3b82f6;
    font-weight: 600;
  }

  .modal-body {
    padding: 1rem;
    overflow-y: auto;
    background: #fdfdfd;
  }
  .modal-footer {
    padding: 1rem;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    background: white;
  }

  /* Roles */
  .roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.5rem;
  }
  .role-card {
    border: 1px solid #ddd;
    padding: 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
    color: #1f2937 !important;
  }
  .role-card:hover {
    background: #f0f7ff;
    border-color: #bfdbfe;
  }
  .role-card.selected {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8 !important;
    font-weight: 500;
    box-shadow: 0 0 0 1px #3b82f6;
  }
  .hidden-checkbox {
    display: none;
  }

  /* Permissions */
  .module-group h3 {
    margin-top: 1rem;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    color: #555;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #eee;
    padding-bottom: 0.25rem;
  }
  .perms-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .perm-row {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 0.5rem;
    background: #fafafa;
    border: 1px solid #eee;
    border-radius: 6px;
    transition: all 0.15s;
  }
  .perm-row:hover {
    background: #f5f5f5;
  }
  .perm-row.from-role {
    opacity: 0.7;
    background: #f0f7ff;
  }

  .perm-btn {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    font-size: 0.85rem;
    color: #1f2937 !important;
    border-radius: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .perm-btn:hover {
    background: rgba(0, 0, 0, 0.05);
  }
  .perm-btn.allow {
    background: #ecfdf5;
    color: #065f46 !important;
  }
  .perm-btn.deny {
    background: #fef2f2;
    color: #991b1b !important;
  }
  .perm-btn.disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }

  .status-indicator {
    flex-shrink: 0;
    font-size: 1rem;
    min-width: 1.25rem;
  }

  .perm-slug {
    font-size: 0.75rem;
    color: #999;
    flex-shrink: 0;
  }

  .role-badge {
    padding: 0.25rem 0.5rem;
    background: #e0e7ff;
    color: #4f46e5;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 3px;
    flex-shrink: 0;
  }

  .delegate-btn {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid #ddd;
    background: #fafafa;
    border-radius: 4px;
    cursor: pointer;
    color: #9ca3af;
    transition: all 0.2s;
  }
  .delegate-btn:hover {
    background: #f0f0f0;
    border-color: #bbb;
    color: #6b7280;
  }
  .delegate-btn.active {
    background: #fef9c3;
    border-color: #facc15;
    color: #854d0e;
  }

  .status-indicator {
    font-size: 0.8rem;
  }
  .info-banner {
    background: #f8fafc;
    padding: 0.5rem;
    margin-bottom: 1rem;
    border-radius: 4px;
    text-align: center;
    color: #64748b;
    font-size: 0.8rem;
  }

  .btn-cancel,
  .btn-submit {
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    border: none;
  }
  .btn-cancel {
    background: #f3f4f6;
    color: #374151;
  }
  .btn-submit {
    background: #3b82f6;
    color: white;
  }
  .btn-submit:disabled {
    opacity: 0.7;
  }

  .loading-state {
    text-align: center;
    color: #6b7280;
    padding: 2rem;
  }
  .empty-state {
    text-align: center;
    color: #94a3b8;
    padding: 1.5rem;
    font-size: 0.875rem;
    border: 1px dashed #e2e8f0;
    border-radius: 8px;
    grid-column: 1 / -1;
  }

  /* Confirmation Dialog */
  .confirm-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 60;
    padding: 1rem;
  }
  .confirm-dialog {
    background: white;
    border-radius: 12px;
    max-width: 450px;
    width: 100%;
    box-shadow:
      0 20px 25px -5px rgba(0, 0, 0, 0.1),
      0 10px 10px -5px rgba(0, 0, 0, 0.04);
  }
  .confirm-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .confirm-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
  }
  .confirm-body {
    padding: 1.25rem;
    max-height: 60vh;
    overflow-y: auto;
  }
  .confirm-footer {
    padding: 1rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: #f9fafb;
  }

  .confirm-info {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
  }
  .confirm-info p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
    color: #1e40af;
  }
  .confirm-info strong {
    color: #1d4ed8;
  }

  .confirm-changes {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
  }
  .confirm-changes h4 {
    margin: 0 0 1rem 0;
    font-size: 0.95rem;
    color: #166534;
    font-weight: 600;
  }

  .change-section {
    margin-bottom: 1rem;
  }
  .change-section > strong {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    color: #166534;
  }
  .change-list {
    margin: 0;
    padding-left: 1.5rem;
    list-style: none;
  }
  .change-list li {
    font-size: 0.85rem;
    color: #166534;
    margin: 0.35rem 0;
    padding-left: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.5rem;
  }
  .perm-state {
    font-size: 0.75rem;
    color: #15803d;
    font-weight: 500;
    background: rgba(34, 197, 94, 0.1);
    padding: 0.15rem 0.4rem;
    border-radius: 2px;
    white-space: nowrap;
  }

  .no-changes {
    font-size: 0.85rem;
    color: #999;
    font-style: italic;
    padding: 0.75rem;
    text-align: center;
  }

  .confirm-warning {
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.85rem;
    color: #92400e;
    line-height: 1.4;
  }

  .btn-confirm {
    padding: 0.625rem 1.25rem;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
  }
  .btn-confirm:hover:not(:disabled) {
    background: #059669;
  }
  .btn-confirm:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
</style>
