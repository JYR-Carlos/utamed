<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router } from '@inertiajs/svelte';
    import DataTable from '@/components/admin/DataTable.svelte';
    import FormModal from '@/components/admin/FormModal.svelte';
    import DeleteConfirmation from '@/components/admin/DeleteConfirmation.svelte';
    import type { Departamento, Facultad, PaginatedResponse, DepartamentoFormData } from '@/types/admin.types';

    interface Props {
        departamentos: PaginatedResponse<Departamento>;
        facultades: Facultad[];
        filters: { search?: string; id_facultad?: number };
    }

    let { departamentos, facultades, filters }: Props = $props();

    let showModal = $state(false);
    let showDeleteDialog = $state(false);
    let isLoading = $state(false);
    let editingDepartamento = $state<Departamento | null>(null);
    let deletingDepartamento = $state<Departamento | null>(null);

    let formData = $state<DepartamentoFormData>({
        nombre: '',
        id_facultad: 0,
    });

    const columns = [
        { key: 'id_departamento', label: 'ID' },
        { key: 'nombre', label: 'Nombre' },
        { key: 'facultad.nombre', label: 'Facultad' },
    ];

    function openCreateModal() {
        editingDepartamento = null;
        formData = { nombre: '', id_facultad: 0 };
        showModal = true;
    }

    function openEditModal(departamento: Departamento) {
        editingDepartamento = departamento;
        formData = {
            nombre: departamento.nombre,
            id_facultad: departamento.id_facultad,
        };
        showModal = true;
    }

    function closeModal() {
        showModal = false;
        editingDepartamento = null;
    }

    function handleSubmit() {
        isLoading = true;

        if (editingDepartamento) {
            router.put(`/admin/departamentos/${editingDepartamento.id_departamento}`, formData, {
                onSuccess: () => {
                    closeModal();
                    isLoading = false;
                },
                onError: () => {
                    isLoading = false;
                },
            });
        } else {
            router.post('/admin/departamentos', formData, {
                onSuccess: () => {
                    closeModal();
                    isLoading = false;
                },
                onError: () => {
                    isLoading = false;
                },
            });
        }
    }

    function openDeleteDialog(departamento: Departamento) {
        deletingDepartamento = departamento;
        showDeleteDialog = true;
    }

    function closeDeleteDialog() {
        showDeleteDialog = false;
        deletingDepartamento = null;
    }

    function handleDelete() {
        if (!deletingDepartamento) return;

        isLoading = true;
        router.delete(`/admin/departamentos/${deletingDepartamento.id_departamento}`, {
            onSuccess: () => {
                closeDeleteDialog();
                isLoading = false;
            },
            onError: () => {
                isLoading = false;
            },
        });
    }
</script>

<AdminLayout>
    <div class="page-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Departamentos</h1>
            <p class="page-description">Gestión de departamentos por facultad</p>
        </div>
        <button onclick={openCreateModal} class="btn-primary">
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
            >
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Nuevo Departamento
        </button>
    </div>

    <DataTable data={departamentos} {columns} onEdit={openEditModal} onDelete={openDeleteDialog} />
</div>

<FormModal
    bind:isOpen={showModal}
    title={editingDepartamento ? 'Editar Departamento' : 'Nuevo Departamento'}
    onClose={closeModal}
    onSubmit={handleSubmit}
    {isLoading}
>
    <div class="form-group">
        <label for="nombre" class="form-label">Nombre</label>
        <input id="nombre" type="text" bind:value={formData.nombre} class="form-input" placeholder="Ej: Departamento de Ciencias Básicas" required />
    </div>

    <div class="form-group">
        <label for="facultad" class="form-label">Facultad</label>
        <select id="facultad" bind:value={formData.id_facultad} class="form-input" required>
            <option value={0}>Seleccione una facultad</option>
            {#each facultades as facultad}
                <option value={facultad.id_facultad}>{facultad.nombre}</option>
            {/each}
        </select>
    </div>
</FormModal>

<DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Departamento?"
    message="Esta acción no se puede deshacer. Si el departamento tiene carreras asociadas, no podrá ser eliminado."
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
    {isLoading}
/>

<style>
    .page-container {
        padding: 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 0.25rem 0;
    }

    .page-description {
        color: #6b7280;
        font-size: 0.875rem;
        margin: 0;
    }

    .btn-primary {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(59, 130, 246, 0.4);
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .form-input {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.875rem;
        color: #111827;
        background-color: white;
        transition: all 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
</style>
</AdminLayout>
