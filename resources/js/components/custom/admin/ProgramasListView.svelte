<script lang="ts">
  import { Button } from '@/components/ui/button';
  import { Input } from '@/components/ui/input';
  import { Plus, ChevronRight, Search } from 'lucide-svelte';
  import { router } from '@inertiajs/svelte';
  import ProgramaStateBadges from './ProgramaStateBadges.svelte';
  import ProgramaActionButtons from './ProgramaActionButtons.svelte';
  import SyllabusModal from './SyllabusModal.svelte';
  import SyllabusTypeSelector from './SyllabusTypeSelector.svelte';
  import CompletenessProgressBar from './CompletenessProgressBar.svelte';
  import { formatDate, formatUserName } from '@/utils/formatters';
  import type { Curso } from '@/types/admin.types';

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
      };
      secciones?: object;
    };
    completenessPercentage?: number;
    creator?: { id_usuario: number; nombre_completo: string };
    reviewer?: { id_usuario: number; nombre_completo: string };
  }

  interface Props {
    programas: Programa[];
    cursoId: number;
    cursoNombre: string;
    userRole: string;
    userId: number;
  }

  let { programas, cursoId, cursoNombre, userRole, userId }: Props = $props();

  let searchQuery = $state('');
  let selectedFilter = $state<string | null>(null);
  let showSyllabusTypeSelector = $state(false);
  let showSyllabusModal = $state(false);
  let selectedSyllabusType = $state<'simplified' | 'combined' | 'complete' | null>(null);
  let currentCurso = $state<Curso | null>(null);

  // Detectar si existe un programa activo (cualquier estado) para mostrar opción de "Continuar"
  const existingSyllabusType = $derived.by(() => {
    const existing = programas.find((p) => ['BORRADOR', 'BASICO_COMPLETO', 'COMPLETO', 'APROBADO'].includes(p.estado));
    if (existing) {
      return existing.data_syllabus?.metadata?.tipo_syllabus as 'COMPLETO' | 'BASICO' | null | undefined;
    }
    return null;
  });

  const filteredProgramas = $derived.by(() => {
    let filtered = programas;

    if (selectedFilter && selectedFilter !== 'all') {
      filtered = filtered.filter((p) => p.estado === selectedFilter);
    }

    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(
        (p) =>
          p.data_syllabus?.metadata?.asignatura?.toLowerCase().includes(query) || p.data_syllabus?.metadata?.curso?.toLowerCase().includes(query),
      );
    }

    return filtered;
  });

  const estadoCounts = $derived.by(() => ({
    all: programas.length,
    borrador: programas.filter((p) => p.estado === 'BORRADOR').length,
    basico: programas.filter((p) => p.estado === 'BASICO_COMPLETO').length,
    completo: programas.filter((p) => p.estado === 'COMPLETO').length,
    aprobado: programas.filter((p) => p.estado === 'APROBADO').length,
    publicado: programas.filter((p) => p.estado === 'PUBLICADO').length,
  }));

  const isAdmin = $derived(userRole === 'admin' || userRole === 'administrator' || userRole === 'Admin');

  function handleApprove(id: number) {
    router.reload({ only: ['programas'] });
  }

  function handleReject(id: number, razon: string) {
    router.reload({ only: ['programas'] });
  }

  function handleEdit(id: number) {
    router.visit(`/admin/programas/${id}/edit`);
  }

  function handleViewDetails(programa: Programa) {
    router.visit(`/admin/programas/${programa.id_programa}`);
  }

  function openSyllabusTypeSelector() {
    // Construir un objeto Curso mínimo con los datos disponibles
    currentCurso = {
      id_curso: cursoId,
      cod_curso: cursoId,
      nombre: cursoNombre,
      asignatura_nombre: cursoNombre,
      id_asignacion_plan: 0,
      id_contexto: 0,
      has_programa: existingSyllabusType !== null,
    } as Curso;
    showSyllabusTypeSelector = true;
    selectedSyllabusType = null;
  }

  function closeSyllabusTypeSelector() {
    showSyllabusTypeSelector = false;
    selectedSyllabusType = null;
  }

  function handleSyllabusTypeSelect(type: 'simplified' | 'combined' | 'complete') {
    selectedSyllabusType = type;
    showSyllabusTypeSelector = false;
    showSyllabusModal = true;
  }

  function openSyllabusModal() {
    showSyllabusTypeSelector = true;
  }

  function closeSyllabusModal() {
    showSyllabusModal = false;
    showSyllabusTypeSelector = false;
    selectedSyllabusType = null;
    currentCurso = null;
  }

  function handleSyllabusSuccess() {
    closeSyllabusModal();
    // Recargar los programas
    router.reload({ only: ['programas'] });
  }
</script>

<div class="space-y-6">
  <!-- Header Section -->
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-3xl font-bold text-slate-900">Programas de {cursoNombre}</h1>
      <p class="text-slate-600 mt-1">
        {filteredProgramas.length} de {programas.length} programas
      </p>
    </div>
    {#if isAdmin}
      <Button variant="default" size="lg" onclick={openSyllabusModal}>
        <Plus size={20} class="mr-2" />
        Nuevo Programa
      </Button>
    {/if}
  </div>

  <!-- Filters and Search -->
  <div class="space-y-4">
    <div class="flex gap-2">
      <div class="flex-1 relative">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
        <Input type="text" placeholder="Buscar por asignatura o código..." bind:value={searchQuery} class="pl-10" />
      </div>
    </div>

    <!-- Estado Filters -->
    <div class="flex flex-wrap gap-2">
      <button
        class={`px-4 py-2 rounded-lg font-medium text-sm transition ${
          selectedFilter === null ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'
        }`}
        onclick={() => (selectedFilter = null)}
      >
        Todos ({estadoCounts.all})
      </button>

      <button
        class={`px-4 py-2 rounded-lg font-medium text-sm transition ${
          selectedFilter === 'BASICO_COMPLETO' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-800 hover:bg-blue-200'
        }`}
        onclick={() => (selectedFilter = 'BASICO_COMPLETO')}
      >
        Básico Completo ({estadoCounts.basico})
      </button>

      <button
        class={`px-4 py-2 rounded-lg font-medium text-sm transition ${
          selectedFilter === 'COMPLETO' ? 'bg-purple-600 text-white' : 'bg-purple-100 text-purple-800 hover:bg-purple-200'
        }`}
        onclick={() => (selectedFilter = 'COMPLETO')}
      >
        Completo ({estadoCounts.completo})
      </button>

      <button
        class={`px-4 py-2 rounded-lg font-medium text-sm transition ${
          selectedFilter === 'APROBADO' ? 'bg-green-600 text-white' : 'bg-green-100 text-green-800 hover:bg-green-200'
        }`}
        onclick={() => (selectedFilter = 'APROBADO')}
      >
        Aprobado ({estadoCounts.aprobado})
      </button>

      <button
        class={`px-4 py-2 rounded-lg font-medium text-sm transition ${
          selectedFilter === 'PUBLICADO' ? 'bg-teal-600 text-white' : 'bg-teal-100 text-teal-800 hover:bg-teal-200'
        }`}
        onclick={() => (selectedFilter = 'PUBLICADO')}
      >
        Publicado ({estadoCounts.publicado})
      </button>
    </div>
  </div>

  <!-- Programas List -->
  <div class="space-y-3">
    {#if filteredProgramas.length === 0}
      <div class="text-center py-12 bg-slate-50 rounded-lg border-2 border-dashed border-slate-300">
        <p class="text-slate-600 text-lg">
          {searchQuery ? 'No se encontraron programas que coincidan' : 'No hay programas disponibles'}
        </p>
      </div>
    {:else}
      {#each filteredProgramas as programa (programa.id_programa)}
        <div class="border border-gray-200 rounded-lg bg-white hover:shadow-md transition-all overflow-hidden">
          <div class="p-4 md:p-6">
            <!-- Main Row -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
              <div class="flex-1">
                <div class="flex items-start gap-3">
                  <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-900">
                      {programa.data_syllabus?.metadata?.asignatura || 'Sin asignatura'}
                    </h3>
                    <p class="text-sm text-slate-600">
                      Código: <code class="bg-gray-100 px-2 py-1 rounded">{programa.data_syllabus?.metadata?.curso || 'N/A'}</code>
                    </p>
                  </div>
                </div>
              </div>

              <ProgramaStateBadges
                estado={programa.estado}
                tipoSyllabus={programa.data_syllabus?.metadata?.tipo_syllabus}
                completenessPercentage={programa.completenessPercentage}
              />
            </div>

            <!-- Completeness Bar -->
            {#if programa.data_syllabus?.metadata?.tipo_syllabus}
              <div class="mb-4">
                <CompletenessProgressBar percentage={programa.completenessPercentage || 0} tipo={programa.data_syllabus.metadata.tipo_syllabus} />
              </div>
            {/if}

            <!-- Info Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4 pb-4 border-b border-gray-200">
              <div>
                <p class="text-gray-600">Creado por</p>
                <p class="font-medium text-slate-900">
                  {programa.creator?.nombre_completo || 'Desconocido'}
                </p>
              </div>
              <div>
                <p class="text-gray-600">Fecha de creación</p>
                <p class="font-medium text-slate-900">
                  {formatDate(programa.fecha_creacion)}
                </p>
              </div>
              <div>
                <p class="text-gray-600">
                  {programa.revisado_por ? 'Revisado por' : 'Estado'}
                </p>
                <p class="font-medium text-slate-900">
                  {programa.revisado_por && programa.reviewer
                    ? programa.reviewer.nombre_completo
                    : programa.estado === 'APROBADO'
                      ? 'Pendiente aprobación'
                      : 'Sin revisar'}
                </p>
              </div>
            </div>

            <!-- Actions Row -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <ProgramaActionButtons {programa} {userRole} {userId} onApprove={handleApprove} onReject={handleReject} onEdit={handleEdit} />

              <button
                onclick={() => handleViewDetails(programa)}
                class="flex items-center justify-center sm:justify-between gap-2 px-4 py-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition"
              >
                <span>Ver detalles</span>
                <ChevronRight size={18} />
              </button>
            </div>
          </div>
        </div>
      {/each}
    {/if}
  </div>

  <!-- SyllabusTypeSelector para elegir tipo de programa -->
  {#if showSyllabusTypeSelector && currentCurso}
    <SyllabusTypeSelector
      bind:isOpen={showSyllabusTypeSelector}
      onClose={closeSyllabusTypeSelector}
      onSelect={handleSyllabusTypeSelect}
      {existingSyllabusType}
    />
  {/if}

  <!-- Syllabus Modal -->
  {#if showSyllabusModal && currentCurso}
    <SyllabusModal
      bind:isOpen={showSyllabusModal}
      curso={currentCurso}
      syllabusType={selectedSyllabusType}
      onClose={closeSyllabusModal}
      onSuccess={handleSyllabusSuccess}
    />
  {/if}
</div>
