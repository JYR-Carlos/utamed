<script lang="ts">
  import { Link } from '@inertiajs/svelte';
  import { Edit2, Trash2, ClipboardList, Plus } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';

  interface Props {
    actividades: Actividad[];
    idCurso: number;
    canCreate?: boolean;
    canEdit?: boolean;
    canDelete?: boolean;
    onEdit?: (actividad: Actividad) => void;
    onDelete?: (actividad: Actividad) => void;
    onCreateClick?: () => void;
  }

  let {
    actividades,
    idCurso,
    canCreate = true,
    canEdit = true,
    canDelete = true,
    onEdit = () => {},
    onDelete = () => {},
    onCreateClick = () => {},
  }: Props = $props();

  function formatDate(dateString: string) {
    if (!dateString) return '';
    const [year, month, day] = dateString.split(' ')[0].split('-');
    return new Date(parseInt(year), parseInt(month) - 1, parseInt(day)).toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }
</script>

{#if actividades.length === 0}
  <div class="text-center p-16 bg-white rounded-xl border border-gray-200">
    <div class="text-5xl mb-4">📋</div>
    <h3 class="text-xl text-gray-900 font-semibold mb-2">No hay actividades creadas</h3>
    <p class="text-gray-600 mb-6">Crea tu primera actividad para este curso</p>
    {#if canCreate}
      <button
        onclick={onCreateClick}
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-900 border border-gray-300 rounded-md font-medium hover:bg-gray-200 transition-all"
      >
        <Plus size={18} />
        Crear Actividad
      </button>
    {/if}
  </div>
{:else}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    {#each actividades as actividad}
      <div
        class="bg-white border border-gray-200 rounded-xl p-6 hover:border-blue-500 hover:shadow-lg transition-all flex flex-col"
      >
        <div class="flex justify-between items-start mb-4">
          <div class="flex items-center gap-3 flex-1">
            <h3 class="text-lg font-semibold text-gray-900">{actividad.nombre}</h3>
            {#if actividad.visible}
              <span
                class="inline-block text-xs font-medium px-3 py-1 bg-green-100 text-green-900 rounded-full"
                >Visible</span
              >
            {:else}
              <span
                class="inline-block text-xs font-medium px-3 py-1 bg-red-100 text-red-900 rounded-full"
                >Oculta</span
              >
            {/if}
          </div>
          <div class="flex gap-2">
            {#if canEdit}
              <button
                onclick={() => onEdit(actividad)}
                class="p-2 bg-transparent border border-gray-200 rounded-md text-gray-600 hover:bg-gray-100 hover:text-blue-500 hover:border-blue-500 transition-all"
                title="Editar"
              >
                <Edit2 size={18} />
              </button>
            {/if}
            {#if canDelete}
              <button
                onclick={() => onDelete(actividad)}
                class="p-2 bg-transparent border border-gray-200 rounded-md text-gray-600 hover:bg-gray-100 hover:text-red-600 hover:border-red-600 transition-all"
                title="Eliminar"
              >
                <Trash2 size={18} />
              </button>
            {/if}
          </div>
        </div>

        <div class="flex flex-col gap-3 mb-4 flex-1">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600 font-medium">Tipo:</span>
            <span class="text-gray-900 font-medium">{actividad.tipo_actividad}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600 font-medium">Entrega:</span>
            <span class="text-gray-900 font-medium capitalize">{actividad.tipo_entrega}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600 font-medium">Modalidad:</span>
            <span class="text-gray-900 font-medium">
              {#if actividad.es_grupal}
                👥 Grupal (máx. {actividad.max_integrantes} personas)
              {:else}
                👤 Individual
              {/if}
            </span>
          </div>
          <div
            class="flex justify-between text-sm bg-blue-50 px-3 py-2 rounded-md border-l-4 border-blue-500"
          >
            <span class="text-gray-600 font-medium">Fecha Límite:</span>
            <span class="text-blue-600 font-semibold">{formatDate(actividad.fecha_limite)}</span>
          </div>
        </div>

        <div class="flex gap-2">
          <Link
            href={`/docente/cursos/${idCurso}/actividades/${actividad.id_actividad}/evaluacion`}
            class="flex-1 inline-flex items-center justify-center gap-1 px-4 py-2.5 bg-blue-50 border border-blue-200 rounded-md font-semibold text-blue-700 hover:bg-blue-100 hover:border-blue-500 transition-all text-sm no-underline"
          >
            <ClipboardList size={16} />
            Evaluar
          </Link>
          <button
            onclick={() => onEdit(actividad)}
            class="flex-1 inline-flex items-center justify-center gap-1 px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-md font-medium text-gray-900 hover:bg-gray-200 hover:border-blue-500 hover:text-blue-600 transition-all text-sm"
          >
            <Edit2 size={16} />
            Editar
          </button>
        </div>
      </div>
    {/each}
  </div>
{/if}
