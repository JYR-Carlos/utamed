<script lang="ts">
	import { router } from '@inertiajs/svelte';

	interface Props {
		isOpen: boolean;
		onClose: () => void;
		usuario: any;
		availableRoles: any[];
		availablePermissions: Record<string, any[]>; // Grouped by module
	}

	let {
		isOpen = $bindable(),
		onClose,
		usuario,
		availableRoles = [],
		availablePermissions = {}
	}: Props = $props();

	let activeTab = $state('roles'); // 'roles' | 'permissions'
    let isLoading = $state(false);
	let selectedRoles = $state<number[]>([]);
	let specialPermissions = $state<Record<number, boolean | null>>({}); // id_permiso -> true (allow), false (deny), null (inherit)

    // Load initial data when modal opens or user changes
    $effect(() => {
        if (isOpen && usuario) {
            loadUserPermissions();
        }
    });

    async function loadUserPermissions() {
        isLoading = true;
        try {
            const res = await fetch(`/admin/usuarios/${usuario.id_usuario}/permissions`);
            const data = await res.json();
            selectedRoles = data.roles;
            
            // Map special permissions array to object key-value
            const specialMapping: Record<number, boolean | null> = {};
            data.special_permissions.forEach((sp: any) => {
                specialMapping[sp.id_permiso] = sp.esta_permitido;
            });
            specialPermissions = specialMapping;
        } catch (error) {
            console.error("Error loading permissions", error);
        } finally {
            isLoading = false;
        }
    }

	function handleSave() {
        isLoading = true;
        router.post(`/admin/usuarios/${usuario.id_usuario}/sync-permissions`, {
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
        const current = specialPermissions[id_permiso];
        if (current === undefined || current === null) {
            specialPermissions[id_permiso] = true; // Allow
        } else if (current === true) {
            specialPermissions[id_permiso] = false; // Deny
        } else {
            specialPermissions[id_permiso] = null; // Inherit (reset)
        }
    }
</script>

{#if isOpen}
	<div class="modal-backdrop" onclick={(e) => e.target === e.currentTarget && onClose()} role="presentation">
		<div class="modal-content" role="dialog" aria-modal="true">
			<div class="modal-header">
				<h2 class="modal-title">Permisos: {usuario.username}</h2>
				<button onclick={onClose} class="close-button">✕</button>
			</div>

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

			<div class="modal-body">
                {#if isLoading}
                    <div class="loading-state">Cargando...</div>
                {:else if activeTab === 'roles'}
                    <div class="roles-grid">
                        {#each availableRoles as rol}
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
                    </div>
                {:else}
                    <div class="permissions-list">
                        <div class="info-banner">
                            <small>
                                🟢 Permitir explícitamente | 🔴 Denegar explícitamente | ⚪ Heredar de Rol
                            </small>
                        </div>
                        {#each Object.entries(availablePermissions) as [modulo, perms]}
                            <div class="module-group">
                                <h3>{modulo || 'General'}</h3>
                                <div class="perms-grid">
                                    {#each perms as perm}
                                        {@const state = specialPermissions[perm.id_permiso]}
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
                                    {/each}
                                </div>
                            </div>
                        {/each}
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
	/* Reuse modal styles from FormModal or define new ones */
    .modal-backdrop { /* ... same as FormModal ... */
		position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem;
	}
    .modal-content {
		background: white; border-radius: 12px; max-width: 600px; width: 100%; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;
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
        color: #1f2937 !important; /* Force visible text */
    }
    .role-card:hover { background: #f0f7ff; border-color: #bfdbfe; }
    .role-card.selected { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8 !important; font-weight: 500; box-shadow: 0 0 0 1px #3b82f6; }	
    .hidden-checkbox { display: none; }

    /* Reviewing */
    .module-group h3 { margin-top: 1rem; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 0.2rem; }
    .perms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; }
    
    .perm-btn {
        display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border: 1px solid #eee; background: white; border-radius: 4px; cursor: pointer; text-align: left; font-size: 0.85rem; width: 100%;
        color: #1f2937 !important; /* Force visible text */
    }
    .perm-btn:hover { background: #fafafa; border-color: #ddd; }
    .perm-btn.allow { background: #ecfdf5; border-color: #10b981; color: #065f46 !important; } /* Green-ish text for allow */
    .perm-btn.deny { background: #fef2f2; border-color: #ef4444; color: #991b1b !important; } /* Red-ish text for deny */
    
    .status-indicator { font-size: 0.8rem; }
    .info-banner { background: #f8fafc; padding: 0.5rem; margin-bottom: 1rem; border-radius: 4px; text-align: center; color: #64748b; }

    .btn-cancel, .btn-submit { padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; border: none; }
    .btn-cancel { background: #f3f4f6; color: #374151; }
    .btn-submit { background: #3b82f6; color: white; }
    .btn-submit:disabled { opacity: 0.7; }
</style>
