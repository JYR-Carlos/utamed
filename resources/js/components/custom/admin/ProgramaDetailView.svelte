<script lang="ts">
  import { Button } from '@/components/ui/button';
  import { ArrowLeft, Edit2, Save, X } from 'lucide-svelte';
  import { router } from '@inertiajs/svelte';
  import ProgramaStateBadges from './ProgramaStateBadges.svelte';
  import CompletenessProgressBar from './CompletenessProgressBar.svelte';
  // import SyllabusEditor from '../SyllabusEditor.svelte';

  interface Programa {
    id_programa: number;
    version_programa: number;
    estado: string;
    creado_por: number;
    revisado_por?: number;
    fecha_creacion: string;
    data_syllabus: {
      metadata?: {
        tipo_syllabus: string;
        curso?: string;
        asignatura?: string;
        creditos?: number;
      };
      secciones?: Record<string, any>;
    };
    completenessPercentage?: number;
    creator?: { id_usuario: number; nombre_completo: string };
    reviewer?: { id_usuario: number; nombre_completo: string };
  }

  interface Props {
    programa: Programa;
    userRole: string;
    userId: number;
  }

  let { programa, userRole, userId }: Props = $props();

  let isEditing = $state(false);
  let editedSyllabus = $state<typeof programa.data_syllabus | null>(null);
  let isSaving = $state(false);

  const isAdmin = $derived(userRole === 'admin' || userRole === 'administrator' || userRole === 'Admin');

  const canEdit = $derived((userId === programa.creado_por || isAdmin) && (programa.estado === 'BASICO_COMPLETO' || programa.estado === 'COMPLETO'));

  const requiredSecciones = $derived(
    programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO'
      ? ['I', 'II', 'VI', 'VII', 'VIII']
      : ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'],
  );

  function startEditing() {
    editedSyllabus = JSON.parse(JSON.stringify(programa.data_syllabus));
    isEditing = true;
  }

  function cancelEditing() {
    isEditing = false;
    editedSyllabus = null;
  }

  async function handleSave() {
    if (!editedSyllabus) return;

    isSaving = true;
    try {
      const response = await fetch(`/admin/programas/${programa.id_programa}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          data_syllabus: editedSyllabus,
        }),
      });

      if (response.ok) {
        isEditing = false;
        editedSyllabus = null;
        router.reload();
      } else {
        alert('Error al guardar los cambios');
      }
    } catch (error) {
      console.error('Error saving:', error);
      alert('Error al guardar los cambios');
    } finally {
      isSaving = false;
    }
  }
</script>

<div class="space-y-6">
  <!-- Header with Back Button -->
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-4">
      <button onclick={() => history.back()} class="p-2 hover:bg-slate-100 rounded-lg transition" title="Volver">
        <ArrowLeft size={24} class="text-slate-600" />
      </button>
      <div>
        <h1 class="text-3xl font-bold text-slate-900">
          {programa.data_syllabus?.metadata?.asignatura || 'Programa'}
        </h1>
        <p class="text-slate-600">
          v{programa.version_programa} • Código: {programa.data_syllabus?.metadata?.curso || 'N/A'}
        </p>
      </div>
    </div>

    {#if canEdit && !isEditing}
      <Button variant="default" onclick={startEditing}>
        <Edit2 size={18} class="mr-2" />
        Editar
      </Button>
    {/if}
  </div>

  <!-- Status Section -->
  <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-200">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Estado Actual</h2>
        <ProgramaStateBadges
          estado={programa.estado}
          tipoSyllabus={programa.data_syllabus?.metadata?.tipo_syllabus}
          completenessPercentage={programa.completenessPercentage}
        />
      </div>

      <div>
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Información</h2>
        <div class="space-y-1 text-sm">
          <p>
            <strong>Tipo:</strong>
            {programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO' ? 'Básico (5 secciones)' : 'Completo (9 secciones)'}
          </p>
          <p><strong>Créditos:</strong> {programa.data_syllabus?.metadata?.creditos || 'No especificado'}</p>
          <p><strong>Versión:</strong> {programa.version_programa}</p>
        </div>
      </div>
    </div>

    <!-- Completeness Bar -->
    {#if programa.data_syllabus?.metadata?.tipo_syllabus}
      <div class="mt-4 pt-4 border-t border-blue-200">
        <CompletenessProgressBar percentage={programa.completenessPercentage || 0} tipo={programa.data_syllabus.metadata.tipo_syllabus} />
      </div>
    {/if}
  </div>

  <!-- Programa Metadata -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded-lg border border-gray-200">
      <h3 class="text-sm font-semibold text-slate-700 mb-2">Creado por</h3>
      <p class="text-slate-900">{programa.creator?.nombre_completo || 'Sistema'}</p>
      <p class="text-xs text-slate-500 mt-1">
        {new Date(programa.fecha_creacion).toLocaleDateString('es-ES')}
      </p>
    </div>

    <div class="bg-white p-4 rounded-lg border border-gray-200">
      <h3 class="text-sm font-semibold text-slate-700 mb-2">Revisado por</h3>
      <p class="text-slate-900">
        {programa.revisado_por && programa.reviewer ? programa.reviewer.nombre_completo : '—'}
      </p>
      {#if !programa.revisado_por}
        <p class="text-xs text-orange-600 mt-1">Pendiente aprobación</p>
      {/if}
    </div>

    <div class="bg-white p-4 rounded-lg border border-gray-200">
      <h3 class="text-sm font-semibold text-slate-700 mb-2">Secciones</h3>
      <p class="text-slate-900">{requiredSecciones.join(', ')}</p>
    </div>
  </div>

  <!-- Syllabus Editor / Viewer -->
  {#if isEditing && editedSyllabus}
    <div class="bg-white p-6 rounded-lg border border-2 border-blue-500">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-slate-900">Editar Syllabus</h2>
        <div class="flex gap-2">
          <Button variant="outline" onclick={cancelEditing} disabled={isSaving}>
            <X size={18} class="mr-2" />
            Cancelar
          </Button>
          <Button variant="default" onclick={handleSave} disabled={isSaving}>
            <Save size={18} class="mr-2" />
            {isSaving ? 'Guardando...' : 'Guardar Cambios'}
          </Button>
        </div>
      </div>

      <!-- <SyllabusEditor bind:syllabus={editedSyllabus} {requiredSecciones} /> -->
      <!-- Note: Implement SyllabusEditor or use inline section editors -->
    </div>
  {:else}
    <div class="bg-white p-6 rounded-lg border border-gray-200">
      <h2 class="text-xl font-bold text-slate-900 mb-4">Contenido del Syllabus</h2>

      {#if programa.data_syllabus?.secciones}
        <div class="space-y-6">
          {#each requiredSecciones as seccion}
            {@const content = programa.data_syllabus.secciones?.[seccion]}
            <div class="border-l-4 border-blue-500 pl-4 py-2">
              <h3 class="text-lg font-bold text-slate-900">
                Sección {seccion}
              </h3>

              {#if content?.contenido}
                <div class="mt-2 text-slate-700 whitespace-pre-wrap">
                  {JSON.stringify(content.contenido, null, 2)}
                </div>
              {:else}
                <p class="text-slate-500 italic mt-2">Sin contenido</p>
              {/if}
            </div>
          {/each}
        </div>
      {:else}
        <p class="text-slate-500 italic">Sin contenido disponible</p>
      {/if}
    </div>
  {/if}

  <!-- Conversion Notice (for BASICO) -->
  {#if programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO' && isEditing}
    <div class="bg-amber-50 border border-amber-300 rounded-lg p-4">
      <p class="text-sm text-amber-900">
        <strong>ℹ️ Nota:</strong> Si añade las secciones III, IV, V o IX a este programa Básico, se convertirá automáticamente a tipo COMPLETO.
      </p>
    </div>
  {/if}
</div>
