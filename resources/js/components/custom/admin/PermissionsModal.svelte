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
		isCourseContext = false
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

    // Local copy of available roles - updated from backend OR from prop
    let localAvailableRoles = $state<any[]>([]);

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
                specialMapping[sp.id_permiso] = {
                    allowed: sp.esta_permitido,
                    can_delegate: sp.puede_delegar || false
                };
            });
            specialPermissions = specialMapping;

            // If backend provides a specific list of available permissions for this user/context, use it.
            if (data.available_permissions) {
                currentPermissions = data.available_permissions;
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
            console.error("Error loading permissions:", msg);
            loadError = msg;
        } finally {
            isLoading = false;
        }
    }

	function handleSave() {
        isLoading = true;
        const url = savePath || `/admin/usuarios/${usuario.id_usuario}/sync-permissions`;
        
        // DEBUG: Log what we're sending
        console.log('📤 Sending to backend:', {
            roles: selectedRoles,
            special_permissions: specialPermissions,
            delegable_perms: Object.entries(specialPermissions)
                .filter(([_, p]: [any, any]) => p.can_delegate === true)
                .map(([id, _]: any) => id)
        });
        
        router.post(url, {
            roles: selectedRoles,
            special_permissions: specialPermissions
        }, {
            onSuccess: () => {
                onClose();
                isLoading = false;
            },
            onError: () => {
                isLoading = false;
            }
        });
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
            allowed: nextAllowed
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
            can_delegate: !specialPermissions[id_permiso].can_delegate
        };
        
        // Force Svelte 5 to detect the change by reassigning the parent object
        specialPermissions = { ...specialPermissions };
    }

    // Safely get entries for availablePermissions and apply visual filter if needed
    let moduleEntries = $derived(
        Object.entries(currentPermissions || {})
            .filter(([modulo]) => {
                // If in course context OR hideRoles is true, we strictly show only specific modules
                if (isCourseContext || hideRoles) {
                    const normalized = (modulo || '').trim().toLowerCase()
                        .normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                    return normalized.includes('docencia') || normalized.includes('ayudantia');
                }
                return true;
            })
    );
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
                    <button 
                        class="tab-btn" 
                        class:active={activeTab === 'roles'} 
                        onclick={() => activeTab = 'roles'}
                    >
                        Roles
                    </button>
                    <button 
                        class="tab-btn" 
                        class:active={activeTab === 'permissions'} 
                        onclick={() => activeTab = 'permissions'}
                    >
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
                                    <input 
                                        type="checkbox" 
                                        bind:group={selectedRoles} 
                                        value={rol.id_rol} 
                                        class="hidden-checkbox"
                                    />
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
                                            <div class="perm-row">
                                                <button 
                                                    class="perm-btn" 
                                                    class:allow={state === true}
                                                    class:deny={state === false}
                                                    onclick={() => cyclePermission(perm.id_permiso)}
                                                    title={perm.descripcion}
                                                >
                                                    <span class="status-indicator">
                                                        {#if state === true}🟢
                                                        {:else if state === false}🔴
                                                        {:else}⚪{/if}
                                                    </span>
                                                    <span class="perm-slug">{perm.slug}</span>
                                                </button>
                                                
                                                {#if !hideRoles}
                                                    <button 
                                                        class="delegate-btn" 
                                                        class:active={canDelegate}
                                                        onclick={() => toggleDelegation(perm.id_permiso)}
                                                        title="¿Puede delegar este permiso?"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
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
{/if}

<style>
    .modal-backdrop {
		position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem;
	}
    .modal-content {
		background: white; border-radius: 12px; max-width: 650px; width: 100%; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;
	}
    .modal-header { padding: 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 1.1rem; font-weight: bold; margin: 0; }
    .close-button { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #666; }
    
    .tabs { display: flex; border-bottom: 1px solid #eee; }
    .tab-btn { flex: 1; padding: 0.75rem; background: #f9f9f9; border: none; cursor: pointer; border-bottom: 2px solid transparent; color: #555; }
    .tab-btn.active { background: white; border-bottom-color: #3b82f6; color: #3b82f6; font-weight: 600; }

    .modal-body { padding: 1rem; overflow-y: auto; background: #fdfdfd; }
    .modal-footer { padding: 1rem; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 0.5rem; background: white; }

    /* Roles */
    .roles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.5rem; }
    .role-card {
        border: 1px solid #ddd; padding: 0.75rem; border-radius: 6px; cursor: pointer; text-align: center; transition: all 0.2s;
        color: #1f2937 !important;
    }
    .role-card:hover { background: #f0f7ff; border-color: #bfdbfe; }
    .role-card.selected { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8 !important; font-weight: 500; box-shadow: 0 0 0 1px #3b82f6; }	
    .hidden-checkbox { display: none; }

    /* Permissions */
    .module-group h3 { margin-top: 1rem; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 0.2rem; }
    .perms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 0.5rem; }
    
    .perm-row { display: flex; align-items: stretch; gap: 2px; }

    .perm-btn {
        flex: 1; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border: 1px solid #eee; background: white; border-radius: 4px 0 0 4px; cursor: pointer; text-align: left; font-size: 0.85rem;
        color: #1f2937 !important;
    }
    .perm-btn:hover { background: #fafafa; border-color: #ddd; }
    .perm-btn.allow { background: #ecfdf5; border-color: #10b981; color: #065f46 !important; }
    .perm-btn.deny { background: #fef2f2; border-color: #ef4444; color: #991b1b !important; }
    
    .delegate-btn {
        display: flex; align-items: center; justify-content: center; padding: 0.5rem; border: 1px solid #eee; background: #fff; border-radius: 0 4px 4px 0; cursor: pointer; color: #9ca3af; transition: all 0.2s;
    }
    .delegate-btn:hover { background: #f9fafb; border-color: #ddd; color: #6b7280; }
    .delegate-btn.active { background: #fef9c3; border-color: #facc15; color: #854d0e; }

    .status-indicator { font-size: 0.8rem; }
    .info-banner { background: #f8fafc; padding: 0.5rem; margin-bottom: 1rem; border-radius: 4px; text-align: center; color: #64748b; font-size: 0.8rem; }

    .btn-cancel, .btn-submit { padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; border: none; }
    .btn-cancel { background: #f3f4f6; color: #374151; }
    .btn-submit { background: #3b82f6; color: white; }
    .btn-submit:disabled { opacity: 0.7; }

    .loading-state { text-align: center; color: #6b7280; padding: 2rem; }
    .empty-state { text-align: center; color: #94a3b8; padding: 1.5rem; font-size: 0.875rem; border: 1px dashed #e2e8f0; border-radius: 8px; grid-column: 1 / -1; }
</style>
