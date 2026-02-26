<script lang="ts">
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Undo2, BookOpen, Edit2 } from 'lucide-svelte';
  import { Link } from '@inertiajs/svelte';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import type { Curso, Programa } from '@/types/admin.types';

  interface Props {
    programa:
      | (Programa & {
          estado: string;
          secciones: Array<{
            nombre_seccion: string;
            numeral_romano?: string;
            contenidos_programa?: Array<{ texto_contenido: string | null }>;
            contenidos?: Array<{ texto_contenido: string | null }>;
          }>;
          version: number;
          creado_por: string;
          fecha_creacion: string;
        })
      | null;
    curso: Curso & {
      asignatura?: { nombre: string };
      carrera?: { nombre: string };
    };
    mode?: 'view' | 'edit';
  }

  let { programa, curso, mode = 'view' }: Props = $props();

  let isSyllabusModalOpen = $state(false);

  let breadcrumbs = $derived.by(() => [
    { title: 'Dashboard', href: '/ayudante/dashboard' },
    { title: 'Mis Cursos', href: '/ayudante/cursos' },
    { title: curso?.nombre ?? 'Curso', href: `/ayudante/cursos/${curso?.id_curso ?? '#'}` },
    { title: 'Programa', href: `/ayudante/cursos/${curso?.id_curso ?? '#'}/programa` },
  ]);

  function getContenidos(seccion: any) {
    return seccion.contenidos || seccion.contenidos_programa || [];
  }

  function openSyllabusModal() {
    isSyllabusModalOpen = true;
  }

  function closeSyllabusModal() {
    isSyllabusModalOpen = false;
  }

  function handleSyllabusSuccess() {
    closeSyllabusModal();
  }

  function formatDate(dateString: string) {
    try {
      return new Date(dateString).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    } catch {
      return dateString;
    }
  }
</script>

<AyudanteLayout {breadcrumbs}>
  <div class="w-full bg-white min-h-screen">
    <div class="max-w-5xl mx-auto px-6 py-12">
      {#if programa === null}
        <!-- Mensaje cuando no hay programa disponible -->
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
      {:else}
        <!-- Documento de Programa en visualización -->
        <article class="prose prose-sm lg:prose-base max-w-none">
          <!-- Encabezado del Programa -->
          <div class="text-center mb-8 pb-6 border-b-2 border-slate-300">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">PROGRAMA DE ASIGNATURA</h1>
            <h2 class="text-2xl font-semibold text-slate-700 mb-4">{curso.asignatura?.nombre}</h2>
            <div class="text-sm text-slate-600 space-y-1">
              <p><strong>Código:</strong> {curso.cod_curso}</p>
              <p><strong>Carrera:</strong> {curso.carrera?.nombre}</p>
              <p>
                <strong>Estado:</strong>
                <span
                  class="font-semibold"
                  class:text-green-600={programa.estado === 'APROBADO'}
                  class:text-amber-600={programa.estado !== 'APROBADO'}
                >
                  {programa.estado}
                </span>
              </p>
            </div>
          </div>

          <!-- Secciones -->
          <div class="space-y-8">
            {#each programa.secciones as seccion}
              <section class="mb-8">
                <!-- Encabezado de sección -->
                <div class="mb-4">
                  <h3 class="text-xl font-bold text-slate-900 flex items-center gap-3">
                    <span class="text-slate-600">{seccion.numeral_romano}.</span>
                    <span>{seccion.nombre_seccion.toUpperCase()}</span>
                  </h3>
                  <div class="h-1 w-20 bg-blue-600 mt-2"></div>
                </div>

                <!-- Contenido -->
                {#each getContenidos(seccion) as contenido}
                  <div class="whitespace-pre-wrap text-slate-700 mb-6 leading-relaxed font-sans text-sm">
                    {contenido.texto_contenido}
                  </div>
                {/each}
              </section>
            {/each}
          </div>

          <!-- Pie de página -->
          <div class="mt-12 pt-6 border-t border-slate-300 text-xs text-slate-600 space-y-1">
            <p><strong>Versión:</strong> {programa.version}</p>
            <p><strong>Preparado por:</strong> {programa.creado_por || 'N/A'}</p>
            <p><strong>Fecha de Aprobación:</strong> {formatDate(programa.fecha_creacion)}</p>
          </div>
        </article>

        <!-- Botón editar (si aplica) -->
        {#if programa.estado !== 'APROBADO'}
          <div class="mt-8 flex justify-center pt-6 border-t border-slate-200">
            <button
              onclick={openSyllabusModal}
              class="flex items-center gap-2 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
            >
              <Edit2 size={18} />
              Editar Contenidos
            </button>
          </div>
        {/if}
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

  <!-- SyllabusModal para editar programa -->
  {#if isSyllabusModalOpen}
    <SyllabusModal bind:isOpen={isSyllabusModalOpen} {curso} onClose={closeSyllabusModal} onSuccess={handleSyllabusSuccess} />
  {/if}
</AyudanteLayout>
