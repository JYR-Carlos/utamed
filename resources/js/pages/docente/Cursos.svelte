<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import CourseTeamModal from '@/components/custom/admin/CourseTeamModal.svelte';

    interface Props {
        cursos: any[];
        availableRoles: any[];
        availablePermissions: Record<string, any[]>;
    }

    let { cursos, availableRoles, availablePermissions }: Props = $props();

    let showTeamModal = $state(false);
    let managingTeamCurso = $state<any>(null);

    function openTeamModal(curso: any) {
        managingTeamCurso = curso;
        showTeamModal = true;
    }

    function closeTeamModal() {
        showTeamModal = false;
        managingTeamCurso = null;
    }
</script>

<AdminLayout>
    <div class="page-container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Mis Cursos</h1>
                <p class="page-description">Gestiona el equipo y ayudantes de tus asignaturas asignadas.</p>
            </div>
        </div>

        {#if cursos.length === 0}
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>No tienes cursos asignados</h3>
                <p>Contacta con el administrador si crees que esto es un error.</p>
            </div>
        {:else}
            <div class="courses-grid">
                {#each cursos as curso}
                    <div class="course-card">
                        <div class="course-info">
                            <span class="course-code">{curso.cod_asignatura} - {curso.cod_curso}</span>
                            <h2 class="course-name">{curso.asignatura_nombre}</h2>
                            <div class="course-meta">
                                <span>📅 Inicio: {curso.fecha_inicio || 'No definida'}</span>
                                <span>🎯 Semestre: {curso.numero_semestre || 'N/A'}</span>
                            </div>
                        </div>
                        <div class="course-actions">
                            <button onclick={() => openTeamModal(curso)} class="btn-manage">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                Gestionar Equipo
                            </button>
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </div>

    {#if managingTeamCurso}
        <CourseTeamModal 
            bind:isOpen={showTeamModal}
            onClose={closeTeamModal}
            curso={managingTeamCurso}
            {availableRoles}
            {availablePermissions}
            urlPrefix="docente"
        />
    {/if}
</AdminLayout>

<style>
    .page-container {
        padding: 2rem;
        max-width: 1000px;
        margin: 0 auto;
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 0.5rem 0;
    }

    .page-description {
        color: #6b7280;
        font-size: 1rem;
    }

    .courses-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .course-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .course-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .course-code {
        font-size: 0.75rem;
        font-weight: 600;
        color: #3b82f6;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .course-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: #111827;
        margin: 0.5rem 0 1rem 0;
    }

    .course-meta {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: #6b7280;
    }

    .course-actions {
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #f3f4f6;
    }

    .btn-manage {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.625rem;
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-manage:hover {
        background: #e5e7eb;
        border-color: #9ca3af;
        color: #111827;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 12px;
        border: 1px dashed #d1d5db;
        color: #6b7280;
    }

    .empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .empty-state h3 {
        color: #111827;
        margin-bottom: 0.5rem;
    }
</style>
