<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import { Button } from '@/components/ui/button';
  import { Card } from '@/components/ui/card';
  import { Plus, Save, X, AlertCircle, Info } from 'lucide-svelte';
  import ProgramaStateBadges from '@/components/custom/admin/ProgramaStateBadges.svelte';
  import CompletenessProgressBar from '@/components/custom/admin/CompletenessProgressBar.svelte';

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
  }

  interface Props {
    programa: Programa;
    userRole: string;
    userId: number;
  }

  let { programa, userRole, userId }: Props = $props();

  let editedSyllabus: any = $state({ metadata: {}, secciones: {} });
  let isSaving = $state(false);
  let error = $state('');
  let success = $state('');

  const isAdmin = $derived(userRole === 'admin' || userRole === 'administrator' || userRole === 'Admin');

  const canEdit = $derived(
    (userId === programa.creado_por || isAdmin) &&
      (programa.estado === 'BORRADOR' || programa.estado === 'BASICO_COMPLETO' || programa.estado === 'COMPLETO'),
  );

  const requiredSecciones = $derived(
    programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO'
      ? ['I', 'II', 'VI', 'VII', 'VIII']
      : ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'],
  );

  function handleSave() {
    error = '';
    success = '';

    if (!editedSyllabus) {
      error = 'Error: No hay datos para guardar';
      return;
    }

    isSaving = true;

    router.put(
      `/admin/programas/${programa.id_programa}`,
      { data_syllabus: editedSyllabus },
      {
        onSuccess: () => {
          success = 'Programa guardado exitosamente';
          router.reload();
        },
        onError: (errors) => {
          error = Object.values(errors).flat().join('. ') || 'Error al guardar los cambios';
        },
        onFinish: () => {
          isSaving = false;
        },
      },
    );
  }

  function handleCancel() {
    if (confirm('¿Descartar cambios? Los cambios no guardados se perderán.')) {
      router.visit(`/admin/programas/${programa.id_programa}`);
    }
  }

  function handleAddSection() {
    // Find next missing section
    const allSecciones = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
    for (const sec of allSecciones) {
      if (!editedSyllabus?.secciones?.[sec]) {
        editedSyllabus.secciones = editedSyllabus.secciones || {};
        editedSyllabus.secciones[sec] = {
          contenido: {
            titulo: `Sección ${sec}`,
            contenido_texto: '',
          },
        };
        break;
      }
    }
  }
</script>

<div class="min-h-screen bg-gray-50 py-8">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-6">
      <button onclick={() => history.back()} class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4"> ← Volver </button>

      <div class="flex justify-between items-start mb-4">
        <div>
          <h1 class="text-4xl font-bold text-slate-900">
            {programa.data_syllabus?.metadata?.asignatura || 'Editar Programa'}
          </h1>
          <p class="text-slate-600 mt-1">
            v{programa.version_programa} • {programa.data_syllabus?.metadata?.curso}
          </p>
        </div>

        <div class="text-right">
          <ProgramaStateBadges
            estado={programa.estado}
            tipoSyllabus={programa.data_syllabus?.metadata?.tipo_syllabus}
            completenessPercentage={programa.completenessPercentage}
          />
        </div>
      </div>
    </div>

    {#if !canEdit}
      <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800">
          <strong>No tienes permiso para editar este programa.</strong>
          Solo el creador o un administrador puede hacer cambios.
        </p>
      </div>
    {/if}

    {#if error}
      <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-start gap-3">
        <AlertCircle class="text-red-600 flex-shrink-0 mt-1" size={20} />
        <div>
          <h3 class="font-semibold text-red-900">Error</h3>
          <p class="text-red-800">{error}</p>
        </div>
      </div>
    {/if}

    {#if success}
      <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-start gap-3">
        <AlertCircle class="text-green-600 flex-shrink-0 mt-1" size={20} />
        <div>
          <h3 class="font-semibold text-green-900">Éxito</h3>
          <p class="text-green-800">{success}</p>
        </div>
      </div>
    {/if}

    <!-- Program Info Card -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <Card class="p-4">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">Tipo de Syllabus</h3>
        <p class="text-lg text-slate-900">
          {programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO' ? 'Básico (5 secciones)' : 'Completo (9 secciones)'}
        </p>
      </Card>

      <Card class="p-4">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">Créditos</h3>
        <p class="text-lg text-slate-900">
          {programa.data_syllabus?.metadata?.creditos || 'No especificado'}
        </p>
      </Card>
    </div>

    <!-- Completeness Progress -->
    <Card class="p-4 mb-6">
      <CompletenessProgressBar percentage={programa.completenessPercentage || 0} tipo={programa.data_syllabus?.metadata?.tipo_syllabus || 'BASICO'} />
    </Card>

    <!-- Conversion Notice -->
    {#if programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO'}
      <div class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-6 flex items-start gap-3">
        <Info class="text-amber-600 flex-shrink-0 mt-0.5" size={20} />
        <div>
          <h4 class="font-semibold text-amber-900">Nota importante</h4>
          <p class="text-amber-800 text-sm mt-1">
            Si añade contenido a las secciones <strong>III, IV, V o IX</strong>, este programa se convertirá automáticamente a tipo
            <strong>COMPLETO</strong>.
          </p>
        </div>
      </div>
    {/if}

    <!-- Editor Section -->
    {#if canEdit && editedSyllabus}
      <div class="bg-white rounded-lg border-2 border-blue-500 p-6 mb-6">
        <h2 class="text-2xl font-bold text-slate-900 mb-4">Editar Contenido del Syllabus</h2>

        <div class="mb-4">
          <Button variant="outline" onclick={handleAddSection} disabled={isSaving || !canEdit}>
            <Plus size={18} class="mr-2" />
            Añadir Sección
          </Button>
        </div>

        {#if editedSyllabus?.secciones}
          <div class="space-y-6">
            {#each requiredSecciones as seccion}
              {@const content = editedSyllabus.secciones[seccion]}
              <div class="border-l-4 border-blue-500 pl-4 py-2">
                <h3 class="text-lg font-bold text-slate-900">
                  Sección {seccion}
                </h3>

                {#if content?.contenido}
                  <div class="mt-2 bg-slate-50 p-3 rounded max-h-40 overflow-y-auto">
                    <pre class="text-sm text-slate-700 whitespace-pre-wrap font-mono">
{JSON.stringify(content.contenido, null, 2)}
                    </pre>
                  </div>
                {:else}
                  <p class="text-slate-500 italic mt-2 text-sm">Sin contenido (opcional para tipo BASICO si no es obligatoria)</p>
                {/if}
              </div>
            {/each}
          </div>
        {/if}
      </div>
    {/if}

    <!-- Editor Component (if you have SyllabusEditor) -->
    <!-- Uncomment if SyllabusEditor is available -->
    {#if canEdit && editedSyllabus}
      <!-- 
      <SyllabusEditor
        bind:syllabus={editedSyllabus}
        requiredSecciones={requiredSecciones}
      />
      -->
    {/if}

    <!-- Action Buttons -->
    {#if canEdit}
      <div class="bg-white rounded-lg border p-6 sticky bottom-0 shadow-lg">
        <div class="flex justify-end gap-4">
          <Button variant="outline" onclick={handleCancel} disabled={isSaving}>
            <X size={18} class="mr-2" />
            Cancelar
          </Button>
          <Button variant="default" onclick={handleSave} disabled={isSaving} class="bg-blue-600 hover:bg-blue-700">
            {#if isSaving}
              <span class="inline-block mr-2">⏳</span>
              Guardando...
            {:else}
              <Save size={18} class="mr-2" />
              Guardar Cambios
            {/if}
          </Button>
        </div>
      </div>
    {/if}
  </div>
</div>
