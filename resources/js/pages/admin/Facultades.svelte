<script lang="ts">
    /**
     * Página de administración de facultades.
     * 
     * Gestión CRUD de facultades en la jerarquía académica.
     * Las facultades contienen departamentos que a su vez contienen carreras.
     * 
     * Características:
     * - Tabla con búsqueda por nombre
     * - Formulario modal para crear/editar facultades
     * - Eliminación con confirmación
     * 
     * Tabla relacionada:
     * - administrativo.facultad: Información de facultades
     */
    import { router } from '@inertiajs/svelte';
    import DataTable from '@/components/custom/admin/DataTable.svelte';
    import FormModal from '@/components/custom/admin/FormModal.svelte';
    import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import type { Facultad, PaginatedResponse, FacultadFormData } from '@/types/admin.types';

    /**
     * Props recibidas del servidor.
     */
    interface Props {
        /** Facultades paginadas */
        facultades: PaginatedResponse<Facultad>;
        /** Filtros de búsqueda */
        filters: { search?: string };
    }

    let { facultades, filters }: Props = $props();

    let showModal = $state(false);
    let showDeleteDialog = $state(false);
    let isLoading = $state(false);
    let editingFacultad = $state<Facultad | null>(null);
    let deletingFacultad = $state<Facultad | null>(null);

    let formData = $state<FacultadFormData>({
        nombre: '',
    });

    const columns = [
        { key: 'id_facultad', label: 'ID' },
        { key: 'nombre', label: 'Nombre' },
    ];

    function openCreateModal() {
        editingFacultad = null;
        formData = { nombre: '' };
        showModal = true;
    }

    function openEditModal(facultad: Facultad) {
        editingFacultad = facultad;
        formData = { nombre: facultad.nombre };
        showModal = true;
    }

    function closeModal() {
        showModal = false;
        editingFacultad = null;
        formData = { nombre: '' };
    }

    function handleSubmit() {
        isLoading = true;

        if (editingFacultad) {
            router.put(`/admin/facultades/${editingFacultad.id_facultad}`, formData, {
                onSuccess: () => {
                    closeModal();
                    isLoading = false;
                },
                onError: () => {
                    isLoading = false;
                },
            });
        } else {
            router.post('/admin/facultades', formData, {
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

    function openDeleteDialog(facultad: Facultad) {
        deletingFacultad = facultad;
        showDeleteDialog = true;
    }

    function closeDeleteDialog() {
        showDeleteDialog = false;
        deletingFacultad = null;
    }

    function handleDelete() {
        if (!deletingFacultad) return;

        isLoading = true;
        router.delete(`/admin/facultades/${deletingFacultad.id_facultad}`, {
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
                <h1 class="page-title">Facultades</h1>
                <p class="page-description">Gestión de facultades de la universidad</p>
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
                Nueva Facultad
            </button>
        </div>

        <DataTable data={facultades} {columns} onEdit={openEditModal} onDelete={openDeleteDialog} />
    </div>

    <FormModal
        bind:isOpen={showModal}
        title={editingFacultad ? 'Editar Facultad' : 'Nueva Facultad'}
        onClose={closeModal}
        onSubmit={handleSubmit}
        {isLoading}
    >
        <div class="form-group">
            <label for="nombre" class="form-label">Nombre</label>
            <input id="nombre" type="text" bind:value={formData.nombre} class="form-input" placeholder="Ej: Facultad de Medicina" required />
        </div>
    </FormModal>

    <DeleteConfirmation
        bind:isOpen={showDeleteDialog}
        title="¿Eliminar Facultad?"
        message="Esta acción no se puede deshacer. Si la facultad tiene departamentos asociados, no podrá ser eliminada."
        onConfirm={handleDelete}
        onCancel={closeDeleteDialog}
        {isLoading}
    />
</AdminLayout>


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
