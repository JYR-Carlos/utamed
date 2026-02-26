<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import type { Curso } from '@/types/admin.types';
  import PermissionsModal from './PermissionsModal.svelte';

  interface Props {
    isOpen: boolean;
    onClose: () => void;
    curso: Curso;
    urlPrefix?: string; // 'admin' or 'docente'
  }

  let { isOpen = $bindable(), onClose, curso, urlPrefix = 'admin' }: Props = $props();

  let teamMembers = $state<any[]>([]);
  let isLoading = $state(false);
  let searchTerm = $state('');
  let searchResults = $state<any[]>([]);
  let isSearching = $state(false);

  // Permissions Modal State
  let showPermissionsModal = $state(false);
  let selectedUserForPermissions = $state<any>(null);
  let availableRoles = $state<any[]>([]);
  let availablePermissions = $state<Record<string, any[]>>({});

  // Initial load
  $effect(() => {
    if (isOpen && curso) {
      loadTeamMembers();
    }
  });

  async function loadTeamMembers() {
    isLoading = true;
    try {
      const res = await fetch(`/${urlPrefix}/cursos/${curso.id_curso}/team`);
      const data = await res.json();
      console.log('Team members loaded:', data);
      teamMembers = data;
    } catch (error) {
      console.error('Error loading team:', error);
    } finally {
      isLoading = false;
    }
  }

  async function searchUsers() {
    if (searchTerm.length < 3) return;
    isSearching = true;
    searchResults = [];

    try {
      const url = `/${urlPrefix}/cursos/${curso.id_curso}/team/search-assistants?search=${encodeURIComponent(searchTerm)}`;
      console.log('Fetching:', url);

      const res = await fetch(url, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      console.log('Response status:', res.status);

      if (!res.ok) {
        const text = await res.text();
        console.error('HTTP Error:', res.status, text.substring(0, 200));
        return;
      }

      const text = await res.text();
      console.log('Response text:', text.substring(0, 300));

      const data = JSON.parse(text);
      console.log('Search results:', data);
      searchResults = Array.isArray(data) ? data : [];
    } catch (error) {
      console.error('Error searching assistants:', error);
    } finally {
      isSearching = false;
    }
  }

  async function addMember(user: any) {
    isLoading = true;
    try {
      await router.post(
        `/${urlPrefix}/cursos/${curso.id_curso}/team`,
        {
          id_usuario: user.id_usuario,
          role_name: 'ayudante',
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            searchTerm = '';
            searchResults = [];
            loadTeamMembers();
          },
        },
      );
    } catch (error) {
      console.error('Error adding member:', error);
    } finally {
      isLoading = false;
    }
  }

  function removeMember(member: any) {
    if (!confirm(`¿Quitar a ${member.nombre_completo} del equipo?`)) return;

    isLoading = true;
    router.delete(`/${urlPrefix}/cursos/${curso.id_curso}/team/${member.id_usuario}`, {
      preserveScroll: true,
      onSuccess: () => {
        loadTeamMembers();
      },
      onFinish: () => {
        isLoading = false;
      },
    });
  }

  function openPermissions(member: any) {
    console.log('Opening permissions for member:', member);
    if (!member.id_usuario) {
      console.error('❌ Member missing id_usuario:', member);
      alert('Error: El miembro no tiene ID de usuario');
      return;
    }
    selectedUserForPermissions = member;
    showPermissionsModal = true;
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
              oninput={() => (searchResults = [])}
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
                    <span class="member-name">{member.nombre}</span>
                    <span class="member-role badge">{member.role_name}</span>
                  </div>
                  <div class="member-actions">
                    <button onclick={() => openPermissions(member)} class="btn-permissions" title="Gestionar Permisos">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        ><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg
                      >
                    </button>
                    <button onclick={() => removeMember(member)} class="btn-remove" title="Quitar"> ✕ </button>
                  </div>
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

{#if selectedUserForPermissions}
  <PermissionsModal
    bind:isOpen={showPermissionsModal}
    onClose={() => {
      showPermissionsModal = false;
      selectedUserForPermissions = null;
      loadTeamMembers();
    }}
    usuario={{
      id_usuario: selectedUserForPermissions.id_usuario,
      username: selectedUserForPermissions.nombre_completo,
    }}
    {availableRoles}
    {availablePermissions}
    hideRoles={urlPrefix === 'docente'}
    isCourseContext={true}
    loadPath={`/${urlPrefix}/cursos/${curso.id_curso}/team/${selectedUserForPermissions.id_usuario}/permissions`}
    savePath={`/${urlPrefix}/cursos/${curso.id_curso}/team/${selectedUserForPermissions.id_usuario}/sync-permissions`}
  />
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
    max-width: 500px;
    width: 100%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.1),
      0 2px 4px -1px rgba(0, 0, 0, 0.06);
  }
  .modal-header {
    padding: 1.25rem;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
  }
  .modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
  }
  .modal-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0.25rem 0 0 0;
  }
  .close-button {
    background: none;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    color: #9ca3af;
    cursor: pointer;
    padding: 0.25rem;
  }
  .close-button:hover {
    color: #111827;
  }

  .modal-body {
    padding: 1.25rem;
    overflow-y: auto;
  }
  .divider {
    height: 1px;
    background: #e5e7eb;
    margin: 1.5rem 0;
  }

  h3 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 1rem 0;
  }

  /* Search */
  .search-box {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
  }
  .search-input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    color: #1f2937 !important;
  }
  .btn-search {
    padding: 0.5rem 1rem;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    color: #374151;
  }
  .btn-search:hover:not(:disabled) {
    background: #e5e7eb;
  }

  .search-results {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 0.5rem;
  }
  .result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: #f9fafb;
    border-radius: 4px;
  }
  .user-info {
    display: flex;
    flex-direction: column;
  }
  .user-name {
    font-weight: 500;
    font-size: 0.875rem;
    color: #111827;
  }
  .user-rut {
    font-size: 0.75rem;
    color: #6b7280;
  }
  .btn-add {
    padding: 0.25rem 0.75rem;
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
  }
  .btn-add:hover {
    background: #d1fae5;
  }

  /* Team List */
  .team-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }
  .team-member {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  }
  .member-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  .member-name {
    font-weight: 500;
    color: #111827;
  }
  .badge {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 9999px;
    width: fit-content;
  }

  .member-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .btn-permissions {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    color: #4b5563;
    cursor: pointer;
    border-radius: 6px;
  }
  .btn-permissions:hover {
    background: #e5e7eb;
    color: #111827;
  }

  .btn-remove {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    border-radius: 6px;
    font-size: 1rem;
  }
  .btn-remove:hover {
    background: #fee2e2;
    color: #dc2626;
  }

  .empty-state {
    text-align: center;
    color: #6b7280;
    font-size: 0.875rem;
    padding: 1rem;
    border: 1px dashed #d1d5db;
    border-radius: 6px;
  }
  .loading-state {
    text-align: center;
    color: #6b7280;
    padding: 1rem;
  }

  .modal-footer {
    padding: 1rem;
    border-top: 1px solid #f3f4f6;
    display: flex;
    justify-content: flex-end;
  }
  .btn-close {
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
  }
  .btn-close:hover {
    background: #f9fafb;
    border-color: #9ca3af;
  }
</style>
