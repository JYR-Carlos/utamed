<script lang="ts">
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import { Undo2, BookOpen, Edit2, Plus } from 'lucide-svelte';
  import { Link } from '@inertiajs/svelte';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import SyllabusTypeSelector from '@/components/custom/admin/SyllabusTypeSelector.svelte';
  import ProgramaDocument from '@/components/custom/common/ProgramaDocument.svelte';
  import type { Curso, Programa } from '@/types/admin.types';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions.types';

  interface Props {
    programa:
      | (Programa & {
          estado: string;
          secciones: Array<{
            nombre_seccion: string;
            numeral_romano?: string;
            contenidos_programa?: Array<{ texto_contenido: string | null }>;
            contenidos?: Array<{ texto_contenido: string | null }>;
            componentes?: any[];
            ponderacion_optativa?: any;
          }>;
          version: number;
          creado_por: string;
          fecha_creacion: string;
        })
      | null;
    curso: Curso & {
      asignatura?: { nombre: string };
      carrera?: { nombre: string };
      creditos_sct?: number;
      horas_teoricas?: number;
      horas_practicas?: number;
      horas_laboratorio?: number;
    };
    mode?: 'view' | 'edit' | 'create';
    userPermissions?: Permission[];
  }

  let { programa, curso, mode = 'view', userPermissions = [] }: Props = $props();

  let isSyllabusTypeOpen = $state(mode === 'create');
  let isSyllabusModalOpen = $state(false);
  let selectedSyllabusType = $state<'simplified' | 'combined' | 'complete' | null>(null);

  const breadcrumbs = $derived([
    { title: 'Dashboard', href: '/ayudante/dashboard' },
    { title: 'Mis Cursos', href: '/ayudante/cursos' },
    { title: curso?.nombre ?? 'Curso', href: `/ayudante/cursos/${curso?.id_curso ?? '#'}` },
    { title: 'Programa', href: `/ayudante/cursos/${curso?.id_curso ?? '#'}/programa` },
  ]);

  // Prepara el objeto curso para los modales con todos los campos necesarios
  const preparedCurso = $derived({
    ...curso,
    asignatura_nombre: curso?.asignatura?.nombre ?? curso?.asignatura_nombre ?? curso?.nombre,
    carrera_nombre: curso?.carrera?.nombre ?? curso?.carrera_nombre,
    id_asignacion_plan: curso?.id_asignacion_plan ?? 0,
    id_contexto: curso?.id_contexto ?? 0,
    has_programa: programa !== null,
  });

  // Datos normalizados para ProgramaDocument
  const secciones = $derived(programa?.secciones ?? []);
  const metadata = $derived(
    programa
      ? {
          version: programa.version,
          creado_por: programa.creado_por,
          fecha_creacion: programa.fecha_creacion,
        }
      : undefined,
  );

  const canEditPrograma = $derived(
    hasPermission(userPermissions, 'cursos/programas:modificar:modulo_1') || hasPermission(userPermissions, 'cursos/programas:*'),
  );
  const canCreatePrograma = $derived(hasPermission(userPermissions, 'cursos/programas:crear'));

  function openSyllabusTypeSelectorModal() {
    isSyllabusTypeOpen = true;
  }

  function openCompleteWizard() {
    // Salta el selector y abre directamente el wizard COMPLETO pre-poblado desde BASICO
    selectedSyllabusType = 'combined';
    isSyllabusTypeOpen = false;
    isSyllabusModalOpen = true;
  }

  function closeSyllabusTypeSelector() {
    isSyllabusTypeOpen = false;
  }

  function handleSyllabusTypeSelect(type: 'simplified' | 'combined' | 'complete') {
    selectedSyllabusType = type;
    isSyllabusTypeOpen = false;
    isSyllabusModalOpen = true;
  }

  function closeSyllabusModal() {
    isSyllabusModalOpen = false;
    selectedSyllabusType = null;
  }

  function handleSyllabusSuccess() {
    closeSyllabusModal();
    // Reload the page to show the updated programa
    window.location.reload();
  }
</script>

<AyudanteLayout {breadcrumbs}>
  <div class="w-full bg-white min-h-screen">
    <div class="max-w-5xl mx-auto px-6 py-12">
      {#if programa === null}
        <!-- Mensaje cuando no hay programa disponible -->
        {#if mode === 'create'}
          <div class="mb-8 rounded-lg bg-blue-50 border-2 border-blue-200 p-8">
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0">
                <Plus class="text-blue-600" size={32} />
              </div>
              <div class="flex-1">
                <h2 class="text-lg font-semibold text-blue-900 mb-2">Crear nuevo programa</h2>
                <p class="text-blue-800 text-sm mb-4">
                  Inicia la creación del programa de cátedra. Completa todas las 9 secciones para generar el documento.
                </p>
                <button
                  onclick={openSyllabusTypeSelectorModal}
                  class="inline-flex items-center gap-2 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                >
                  <Plus size={18} />
                  Comenzar a crear
                </button>
              </div>
            </div>
          </div>
        {:else}
          <div class="mb-8 rounded-lg bg-amber-50 border-2 border-amber-200 p-8">
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0">
                <BookOpen class="text-amber-600" size={32} />
              </div>
              <div class="flex-1">
                <h2 class="text-lg font-semibold text-amber-900 mb-2">Programa aún no disponible</h2>
                <p class="text-amber-800 text-sm mb-3">El programa de cátedra para este curso aún no ha sido creado.</p>
                <p class="text-amber-700 text-xs">Por favor, intenta más tarde o contacta con el docente del curso.</p>
              </div>
            </div>
          </div>
        {/if}
      {:else}
        <!-- Encabezado de página -->
        <div class="text-center mb-8 pb-6 border-b-2 border-slate-300">
          <h1 class="text-3xl font-bold text-slate-900 mb-2">PROGRAMA DE ASIGNATURA</h1>
          <h2 class="text-2xl font-semibold text-slate-700 mb-4">{preparedCurso.asignatura_nombre}</h2>
          <div class="text-sm text-slate-600 space-y-1">
            <p><strong>Código:</strong> {curso.cod_curso}</p>
            <p><strong>Carrera:</strong> {preparedCurso.carrera_nombre}</p>
            <p>
              <strong>Estado:</strong>
              <span class="font-semibold" class:text-green-600={programa.estado === 'APROBADO'} class:text-amber-600={programa.estado !== 'APROBADO'}>
                {programa.estado}
              </span>
            </p>
          </div>
        </div>

        <!-- Secciones + metadatos renderizados por el componente compartido -->
        <ProgramaDocument {secciones} {metadata}>
          {#snippet actions()}
            {#if programa.estado === 'BASICO_COMPLETO' && canEditPrograma}
              <div class="mt-8 flex flex-col items-center gap-3 pt-6 border-t border-slate-200">
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 w-full max-w-xl text-center">
                  <p class="text-sm font-medium text-amber-900 mb-1">📝 Programa básico creado</p>
                  <p class="text-sm text-amber-700 mb-3">Puedes completarlo con las secciones adicionales del programa completo.</p>
                  <button
                    onclick={openCompleteWizard}
                    class="inline-flex items-center gap-2 px-6 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors font-medium"
                  >
                    Completar Syllabus
                  </button>
                </div>
                <button
                  onclick={openSyllabusTypeSelectorModal}
                  class="flex items-center gap-2 px-4 py-1.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                >
                  <Edit2 size={14} />
                  Editar sección básica
                </button>
              </div>
            {:else if programa.estado !== 'APROBADO' && canEditPrograma}
              <div class="mt-8 flex justify-center pt-6 border-t border-slate-200">
                <button
                  onclick={openSyllabusTypeSelectorModal}
                  class="flex items-center gap-2 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                >
                  <Edit2 size={18} />
                  Editar Contenidos
                </button>
              </div>
            {/if}
          {/snippet}
        </ProgramaDocument>
      {/if}

      <!-- Botón volver -->
      <div class="mt-12 flex justify-center pt-6 border-t border-slate-200">
        <Link
          href={`/ayudante/cursos/${curso.id_curso}`}
          class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium transition-colors"
        >
          <Undo2 size={18} />
          Volver al Curso
        </Link>
      </div>
    </div>
  </div>

  <!-- SyllabusTypeSelector para elegir tipo de programa -->
  {#if isSyllabusTypeOpen}
    <SyllabusTypeSelector
      bind:isOpen={isSyllabusTypeOpen}
      onClose={closeSyllabusTypeSelector}
      onSelect={handleSyllabusTypeSelect}
      existingSyllabusType={programa?.estado === 'BASICO_COMPLETO' ? 'BASICO' : programa ? 'COMPLETO' : null}
    />
  {/if}

  <!-- SyllabusModal para editar programa -->
  {#if isSyllabusModalOpen}
    <SyllabusModal
      bind:isOpen={isSyllabusModalOpen}
      curso={preparedCurso}
      syllabusType={selectedSyllabusType}
      onClose={closeSyllabusModal}
      onSuccess={handleSyllabusSuccess}
    />
  {/if}
</AyudanteLayout>
