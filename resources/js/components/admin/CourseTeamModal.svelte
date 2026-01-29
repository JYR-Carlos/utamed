<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import type { Curso } from '@/types/admin.types';

    interface Props {
        isOpen: boolean;
        onClose: () => void;
        curso: Curso;
    }

    let { isOpen = $bindable(), onClose, curso }: Props = $props();

    let teamMembers = $state<any[]>([]);
    let isLoading = $state(false);
    let searchTerm = $state('');
    let searchResults = $state<any[]>([]);
    let isSearching = $state(false);

    // Initial load
    $effect(() => {
        if (isOpen && curso) {
            loadTeamMembers();
        }
    });

    async function loadTeamMembers() {
        isLoading = true;
        try {
            const res = await fetch(`/admin/cursos/${curso.id_curso}/team`);
            const data = await res.json();
            teamMembers = data;
        } catch (error) {
            console.error("Error loading team:", error);
        } finally {
            isLoading = false;
        }
    }

    async function searchUsers() {
        if (searchTerm.length < 3) return;
        isSearching = true;
        try {
            // Search all users generically
            const res = await fetch(`/admin/usuarios?search=${searchTerm}&per_page=5`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            // Filter out existing members
            const currentIds = teamMembers.map(m => m.id_usuario);
            searchResults = data.data.filter((u: any) => !currentIds.includes(u.id_usuario));
        } catch (error) {
            console.error("Error searching users:", error);
        } finally {
            isSearching = false;
        }
    }

    async function addMember(user: any) {
        isLoading = true;
        try {
            // Hardcode 'Ayudante' role logic for now, or allow selection if multiple roles exist
            // Assuming 'Ayudante' is the primary delegable role.
            await router.post(`/admin/cursos/${curso.id_curso}/team`, {
                id_usuario: user.id_usuario,
                role_name: 'Ayudante' 
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    searchTerm = '';
                    searchResults = [];
                    loadTeamMembers();
                }
            });
        } catch (error) {
            console.error("Error adding member:", error);
        } finally {
            isLoading = false;
        }
    }

    function removeMember(member: any) {
        if (!confirm(`¿Quitar a ${member.nombre_completo} del equipo?`)) return;

        isLoading = true;
        router.delete(`/admin/cursos/${curso.id_curso}/team/${member.id_usuario}`, {
            preserveScroll: true,
            onSuccess: () => {
                loadTeamMembers();
            },
            onFinish: () => {
                isLoading = false;
            }
        });
    }
</script>

{#if isOpen}
    <div class="modal-backdrop" onclick={(e) => e.target === e.currentTarget && onClose()} role="presentation">
        <div class="modal-content" role="dialog" aria-modal="true">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title">Equipo Docente</h2>
                    <p class="modal-subtitle">{curso.cod_curso} - {curso.nombre || 'Sin nombre'}</p>
                </div>
                <button onclick={onClose} class="close-button">✕</button>
            </div>

            <div class="modal-body">
                <!-- Add Member Section -->
                <div class="add-member-section">
                    <h3>Agregar Ayudante</h3>
                    <div class="search-box">
                        <input 
                            type="text" 
                            bind:value={searchTerm} 
                            placeholder="Buscar usuario por nombre o RUT..." 
                            class="search-input"
                            oninput={() => searchResults = []}
                            onkeydown={(e) => e.key === 'Enter' && searchUsers()}
                        />
                        <button onclick={searchUsers} class="btn-search" disabled={isSearching || searchTerm.length < 3}>
                            {isSearching ? '...' : 'Buscar'}
                        </button>
                    </div>

                    {#if searchResults.length > 0}
                        <div class="search-results">
                            {#each searchResults as result}
                                <div class="result-item">
                                    <div class="user-info">
                                        <span class="user-name">{result.nombre || result.nombre1} {result.apellido || result.apellido1}</span>
                                        <span class="user-rut">{result.rut}</span>
                                    </div>
                                    <button onclick={() => addMember(result)} class="btn-add">Agregar</button>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>

                <div class="divider"></div>

                <!-- Current Team -->
                <div class="current-team-section">
                    <h3>Miembros del Equipo</h3>
                    {#if isLoading}
                        <div class="loading-state">Cargando...</div>
                    {:else if teamMembers.length === 0}
                        <div class="empty-state">No hay ayudantes asignados.</div>
                    {:else}
                        <div class="team-list">
                            {#each teamMembers as member}
                                <div class="team-member">
                                    <div class="member-info">
                                        <span class="member-name">{member.nombre_completo}</span>
                                        <span class="member-role badge">{member.role_name}</span>
                                    </div>
                                    <button onclick={() => removeMember(member)} class="btn-remove" title="Quitar">
                                        ✕
                                    </button>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>

            <div class="modal-footer">
                <button onclick={onClose} class="btn-close">Cerrar</button>
            </div>
        </div>
    </div>
{/if}

<style>
    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem;
    }
    .modal-content {
        background: white; border-radius: 12px; max-width: 500px; width: 100%; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .modal-header { padding: 1.25rem; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: flex-start; }
    .modal-title { font-size: 1.25rem; font-weight: 600; color: #111827; margin: 0; }
    .modal-subtitle { font-size: 0.875rem; color: #6b7280; margin: 0.25rem 0 0 0; }
    .close-button { background: none; border: none; font-size: 1.5rem; line-height: 1; color: #9ca3af; cursor: pointer; padding: 0.25rem; }
    .close-button:hover { color: #111827; }

    .modal-body { padding: 1.25rem; overflow-y: auto; }
    .divider { height: 1px; background: #e5e7eb; margin: 1.5rem 0; }

    h3 { font-size: 0.875rem; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 1rem 0; }

    /* Search */
    .search-box { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
    .search-input { 
        flex: 1; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; 
        color: #1f2937 !important; /* Force text visibility */
    }
    .btn-search { padding: 0.5rem 1rem; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px; font-weight: 500; cursor: pointer; color: #374151; }
    .btn-search:hover:not(:disabled) { background: #e5e7eb; }

    .search-results { display: flex; flex-direction: column; gap: 0.5rem; max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: 0.5rem; }
    .result-item { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: #f9fafb; border-radius: 4px; }
    .user-info { display: flex; flex-direction: column; }
    .user-name { font-weight: 500; font-size: 0.875rem; color: #111827; }
    .user-rut { font-size: 0.75rem; color: #6b7280; }
    .btn-add { padding: 0.25rem 0.75rem; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; cursor: pointer; }
    .btn-add:hover { background: #d1fae5; }

    /* Team List */
    .team-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .team-member { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .member-info { display: flex; flex-direction: column; gap: 0.25rem; }
    .member-name { font-weight: 500; color: #111827; }
    .badge { display: inline-block; padding: 0.125rem 0.5rem; background: #eff6ff; color: #1d4ed8; font-size: 0.75rem; font-weight: 500; border-radius: 9999px; width: fit-content; }
    
    .btn-remove { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; background: none; border: none; color: #9ca3af; cursor: pointer; border-radius: 50%; font-size: 0.875rem; }
    .btn-remove:hover { background: #fee2e2; color: #dc2626; }

    .empty-state { text-align: center; color: #6b7280; font-size: 0.875rem; padding: 1rem; border: 1px dashed #d1d5db; border-radius: 6px; }
    .loading-state { text-align: center; color: #6b7280; padding: 1rem; }

    .modal-footer { padding: 1rem; border-top: 1px solid #f3f4f6; display: flex; justify-content: flex-end; }
    .btn-close { padding: 0.5rem 1rem; background: white; border: 1px solid #d1d5db; border-radius: 6px; font-weight: 500; color: #374151; cursor: pointer; }
    .btn-close:hover { background: #f9fafb; border-color: #9ca3af; }
</style>
