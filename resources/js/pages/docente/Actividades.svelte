<script lang="ts">
    /**
     * Página de gestión de actividades/tareas en un curso.
     * 
     * Permite a docentes crear, leer, actualizar y eliminar actividades
     * (tareas, evaluaciones, trabajos) en sus cursos.
     * 
     * Características:
     * - CRUD de actividades con datos como nombre, tipo, fecha límite
     * - Soporte para actividades individuales o grupales
     * - Asignación a secciones específicas del curso
     * - Organización por unidades temáticas
     * - Configuración de tipo de entrega (online, presencial, etc.)
     * - Validación de acceso (solo docente responsable del curso)
     * 
     * Tabla relacionada:
     * - agenda.actividad: Información de actividades/tareas
     */
    import { router, Link } from '@inertiajs/svelte';
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import FormModal from '@/components/custom/admin/FormModal.svelte';
    import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
    import { Plus, Edit2, Trash2, ArrowLeft, Users } from 'lucide-svelte';

    /**
     * Interface para un objeto Actividad.
     */
    interface Actividad {
        id_actividad: number;
        nombre: string;
        fecha_limite: string;
        tipo_actividad: number;
        tipo_entrega: string;
        es_grupal: boolean;
        max_integrantes: number;
        visible: boolean;
        id_seccion: number;
        id_unidad: number;
    }

    interface Props {
        curso: any;
        actividades: Actividad[];
        secciones: any[];
        unidades: any[];
    }

    let { curso, actividades, secciones, unidades }: Props = $props();

    let showModal = $state(false);
    let showDeleteDialog = $state(false);
    let isLoading = $state(false);
    let editingActividad = $state<Actividad | null>(null);
    let deletingActividad = $state<Actividad | null>(null);

    let formData = $state<Partial<Actividad>>({
        nombre: '',
        fecha_limite: '',
        tipo_actividad: 1,
        tipo_entrega: 'online',
        es_grupal: false,
        max_integrantes: 1,
        visible: true,
        id_seccion: 0,
        id_unidad: 0
    });

    function openCreateModal() {
        editingActividad = null;
        formData = {
            nombre: '',
            fecha_limite: '',
            tipo_actividad: 1,
            tipo_entrega: 'online',
            es_grupal: false,
            max_integrantes: 1,
            visible: true,
            id_seccion: 0,
            id_unidad: 0
        };
        showModal = true;
    }

    function openEditModal(actividad: Actividad) {
        editingActividad = actividad;
        formData = { ...actividad };
        showModal = true;
    }

    function closeModal() {
        showModal = false;
        editingActividad = null;
    }

    function handleSubmit() {
        isLoading = true;

        if (editingActividad) {
            router.put(`/docente/cursos/${curso.id_curso}/actividades/${editingActividad.id_actividad}`, formData, {
                onSuccess: () => {
                    closeModal();
                    isLoading = false;
                },
                onError: () => {
                    isLoading = false;
                }
            });
        } else {
            router.post(`/docente/cursos/${curso.id_curso}/actividades`, formData, {
                onSuccess: () => {
                    closeModal();
                    isLoading = false;
                },
                onError: () => {
                    isLoading = false;
                }
            });
        }
    }

    function openDeleteDialog(actividad: Actividad) {
        deletingActividad = actividad;
        showDeleteDialog = true;
    }

    function closeDeleteDialog() {
        showDeleteDialog = false;
        deletingActividad = null;
    }

    function handleDelete() {
        if (!deletingActividad) return;

        isLoading = true;
        router.delete(`/docente/cursos/${curso.id_curso}/actividades/${deletingActividad.id_actividad}`, {
            onSuccess: () => {
                closeDeleteDialog();
                isLoading = false;
            },
            onError: () => {
                isLoading = false;
            }
        });
    }

    function formatDate(dateString: string) {
        return new Date(dateString).toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
</script>

<DocenteLayout>
    <div class="page-container">
        <!-- Header with back button -->
        <div class="page-header">
            <div class="header-top">
                <Link href="/docente/cursos" class="back-button">
                    <ArrowLeft size={20} />
                    Mis Cursos
                </Link>
            </div>
            <div class="header-content">
                <div>
                    <h1 class="page-title">Actividades del Curso</h1>
                    <p class="page-description">
                        {curso.cod_asignatura} - {curso.asignatura_nombre}
                    </p>
                </div>
                <div class="flex gap-3">
                    <Link href={`/docente/inscripciones?id_curso=${curso.id_curso}`} class="btn-secondary">
                        <Users size={20} class="mr-2"/>
                        Inscripciones
                    </Link>
                    <button onclick={openCreateModal} class="btn-primary">
                        <Plus size={20} />
                        Nueva Actividad
                    </button>
                </div>
            </div>
        </div>

        <!-- Activities List -->
        <div class="actividades-container">
            {#if actividades.length === 0}
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3>No hay actividades creadas</h3>
                    <p>Crea tu primera actividad para este curso</p>
                    <button onclick={openCreateModal} class="btn-secondary">
                        <Plus size={18} />
                        Crear Actividad
                    </button>
                </div>
            {:else}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {#each actividades as actividad}
                        <div class="activity-card">
                            <div class="activity-header">
                                <div class="activity-title-section">
                                    <h3 class="activity-name">{actividad.nombre}</h3>
                                    {#if actividad.visible}
                                        <span class="badge badge-visible">Visible</span>
                                    {:else}
                                        <span class="badge badge-hidden">Oculta</span>
                                    {/if}
                                </div>
                                <div class="activity-actions-inline">
                                    <button
                                        onclick={() => openEditModal(actividad)}
                                        class="btn-icon-small"
                                        title="Editar"
                                    >
                                        <Edit2 size={18} />
                                    </button>
                                    <button
                                        onclick={() => openDeleteDialog(actividad)}
                                        class="btn-icon-small btn-delete-small"
                                        title="Eliminar"
                                    >
                                        <Trash2 size={18} />
                                    </button>
                                </div>
                            </div>

                            <div class="activity-details">
                                <div class="detail-row">
                                    <span class="label">Tipo:</span>
                                    <span class="value">{actividad.tipo_actividad}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Entrega:</span>
                                    <span class="value capitalize">{actividad.tipo_entrega}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Modalidad:</span>
                                    <span class="value">
                                        {#if actividad.es_grupal}
                                            👥 Grupal (máx. {actividad.max_integrantes} personas)
                                        {:else}
                                            👤 Individual
                                        {/if}
                                    </span>
                                </div>
                                <div class="detail-row highlight">
                                    <span class="label">Fecha Límite:</span>
                                    <span class="value date">{formatDate(actividad.fecha_limite)}</span>
                                </div>
                            </div>

                            <div class="activity-footer">
                                <button onclick={() => openEditModal(actividad)} class="btn-edit-full">
                                    Editar
                                </button>
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <FormModal
        bind:isOpen={showModal}
        title={editingActividad ? 'Editar Actividad' : 'Nueva Actividad'}
        onClose={closeModal}
        onSubmit={handleSubmit}
        {isLoading}
    >
        <div class="form-group">
            <label for="nombre" class="form-label">Nombre de la Actividad *</label>
            <input
                id="nombre"
                type="text"
                bind:value={formData.nombre}
                class="form-input"
                placeholder="Ej: Tarea 1, Evaluación Parcial"
                required
            />
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="fecha_limite" class="form-label">Fecha Límite *</label>
                <input
                    id="fecha_limite"
                    type="date"
                    bind:value={formData.fecha_limite}
                    class="form-input"
                    required
                />
            </div>

            <div class="form-group">
                <label for="tipo_entrega" class="form-label">Tipo de Entrega *</label>
                <select
                    id="tipo_entrega"
                    bind:value={formData.tipo_entrega}
                    class="form-input"
                    required
                >
                    <option value="online">En línea</option>
                    <option value="presencial">Presencial</option>
                    <option value="hibrido">Híbrido</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="id_seccion" class="form-label">Sección *</label>
                <select
                    id="id_seccion"
                    bind:value={formData.id_seccion}
                    class="form-input"
                    required
                >
                    <option value={0}>Seleccione una sección</option>
                    {#each secciones as seccion}
                        <option value={seccion.id_seccion}>
                            {seccion.numero_seccion}
                        </option>
                    {/each}
                </select>
            </div>

            <div class="form-group">
                <label for="id_unidad" class="form-label">Unidad *</label>
                <select
                    id="id_unidad"
                    bind:value={formData.id_unidad}
                    class="form-input"
                    required
                >
                    <option value={0}>Seleccione una unidad</option>
                    {#each unidades as unidad}
                        <option value={unidad.id_unidad}>
                            {unidad.nombre}
                        </option>
                    {/each}
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input
                    type="checkbox"
                    bind:checked={formData.es_grupal}
                    class="checkbox-input"
                />
                <span>Es una actividad grupal</span>
            </label>
        </div>

        {#if formData.es_grupal}
            <div class="form-group">
                <label for="max_integrantes" class="form-label">Máximo de Integrantes</label>
                <input
                    id="max_integrantes"
                    type="number"
                    bind:value={formData.max_integrantes}
                    class="form-input"
                    min="2"
                    max="100"
                />
            </div>
        {/if}

        <div class="form-group">
            <label class="checkbox-label">
                <input
                    type="checkbox"
                    bind:checked={formData.visible}
                    class="checkbox-input"
                />
                <span>Visible para estudiantes</span>
            </label>
        </div>
    </FormModal>

    <!-- Delete Confirmation -->
    <DeleteConfirmation
        bind:isOpen={showDeleteDialog}
        title="¿Eliminar Actividad?"
        message="Esta acción no se puede deshacer. Los datos de entrega asociados podrían verse afectados."
        onConfirm={handleDelete}
        onCancel={closeDeleteDialog}
        {isLoading}
    />
</DocenteLayout>

<style>
    .page-container {
        padding: 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .header-top {
        margin-bottom: 1rem;
    }

    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #3b82f6;
        font-weight: 500;
        text-decoration: none;
        padding: 0.5rem 0;
        transition: color 0.2s;
    }

    .back-button:hover {
        color: #2563eb;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }

    .page-description {
        color: #6b7280;
        font-size: 1rem;
        margin-top: 0.5rem;
    }

    .btn-primary {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        white-space: nowrap;
    }

    .btn-primary:hover {
        background: #2563eb;
    }

    .actividades-container {
        display: flex;
        flex-direction: column;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }

    .empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .empty-state h3 {
        color: #111827;
        font-size: 1.25rem;
        margin: 0 0 0.5rem 0;
    }

    .empty-state p {
        color: #6b7280;
        margin-bottom: 1.5rem;
    }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: #f3f4f6;
        color: #111827;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-secondary:hover {
        background: #e5e7eb;
    }

    .activity-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
    }

    .activity-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
    }

    .activity-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .activity-title-section {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }

    .activity-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: #111827;
        margin: 0;
    }

    .badge {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
    }

    .badge-visible {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-hidden {
        background: #fee2e2;
        color: #991b1b;
    }

    .activity-actions-inline {
        display: flex;
        gap: 0.5rem;
    }

    .btn-icon-small {
        padding: 0.5rem;
        background: transparent;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-icon-small:hover {
        background: #f3f4f6;
        color: #3b82f6;
        border-color: #3b82f6;
    }

    .btn-delete-small:hover {
        color: #dc2626;
        border-color: #dc2626;
    }

    .activity-details {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1rem;
        flex: 1;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        font-size: 0.875rem;
    }

    .detail-row.highlight {
        background: #f0f9ff;
        padding: 0.75rem;
        border-radius: 6px;
        border-left: 3px solid #3b82f6;
    }

    .label {
        color: #6b7280;
        font-weight: 500;
    }

    .value {
        color: #111827;
        font-weight: 500;
    }

    .value.date {
        color: #3b82f6;
        font-weight: 600;
    }

    .capitalize {
        text-transform: capitalize;
    }

    .activity-footer {
        display: flex;
        gap: 0.5rem;
    }

    .btn-edit-full {
        flex: 1;
        padding: 0.625rem;
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-weight: 500;
        color: #111827;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-edit-full:hover {
        background: #e5e7eb;
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .form-label {
        display: block;
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
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

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #374151;
        font-size: 0.875rem;
        cursor: pointer;
    }

    .checkbox-input {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>
