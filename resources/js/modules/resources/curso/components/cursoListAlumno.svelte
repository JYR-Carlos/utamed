<script lang="ts">
  /**
   * Componente: Lista de Cursos para Alumno (Simplificado)
   *
   * Vista ultra-limpia y directa para estudiantes:
   * - Solo lista (sin grid)
   * - Ver información básica del curso
   * - Botón para entrar al curso
   * - Botón para ver programa (opcional)
   * - Agrupación por semestre (si aplica)
   *
   * Props:
   * - cursosData: any[] - Array de cursos (flat o agrupados por semestre)
   * - groupBySemestre: boolean - Agrupar por semestre
   * - showSyllabusButton: boolean - Mostrar botón de programa
   * - onCourseClick: (curso: any) => void - Click en "Entrar"
   * - onSyllabusClick: (curso: any) => void - Click en "Ver Programa"
   */
  import { ArrowRight, BookOpenCheck } from 'lucide-svelte';

  interface Props {
    cursosData?: any[];
    groupBySemestre?: boolean;
    showSyllabusButton?: boolean;
    onCourseClick?: (curso: any) => void;
    onSyllabusClick?: (curso: any) => void;
  }

  let {
    cursosData = [],
    groupBySemestre = false,
    showSyllabusButton = true,
    onCourseClick = () => {},
    onSyllabusClick = () => {},
  }: Props = $props();

  // Agrupar cursos por semestre si es necesario
  const cursosAgrupados = $derived(
    groupBySemestre
      ? {
          semestre1: cursosData.filter((c) => (c.semestre_real ?? 1) === 1),
          semestre2: cursosData.filter((c) => (c.semestre_real ?? 1) === 2),
        }
      : null,
  );
</script>

<div class="space-y-6">
  {#if cursosAgrupados}
    <!-- Agrupado por semestre -->
    {#if cursosAgrupados.semestre1.length > 0 || cursosAgrupados.semestre2.length > 0}
      {#if cursosAgrupados.semestre1.length > 0}
        <div>
          <h2 class="text-lg font-semibold text-slate-900 mb-3">Primer Semestre</h2>
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full">
              <tbody>
                {#each cursosAgrupados.semestre1 as curso}
                  <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                      <div>
                        <p class="font-semibold text-slate-900">{curso.nombre}</p>
                        <p class="text-sm text-slate-500">{curso.cod_curso}</p>
                      </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">{curso.carrera_nombre || '—'}</td>
                    <td class="px-6 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          onclick={() => onCourseClick(curso)}
                          class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors"
                        >
                          Entrar
                          <ArrowRight class="w-4 h-4" />
                        </button>
                        {#if showSyllabusButton}
                          <button
                            onclick={() => onSyllabusClick(curso)}
                            class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                            title="Ver programa"
                          >
                            <BookOpenCheck class="w-4 h-4" />
                          </button>
                        {/if}
                      </div>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
      {/if}

      {#if cursosAgrupados.semestre2.length > 0}
        <div>
          <h2 class="text-lg font-semibold text-slate-900 mb-3">Segundo Semestre</h2>
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full">
              <tbody>
                {#each cursosAgrupados.semestre2 as curso}
                  <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                      <div>
                        <p class="font-semibold text-slate-900">{curso.nombre}</p>
                        <p class="text-sm text-slate-500">{curso.cod_curso}</p>
                      </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">{curso.carrera_nombre || '—'}</td>
                    <td class="px-6 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          onclick={() => onCourseClick(curso)}
                          class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors"
                        >
                          Entrar
                          <ArrowRight class="w-4 h-4" />
                        </button>
                        {#if showSyllabusButton}
                          <button
                            onclick={() => onSyllabusClick(curso)}
                            class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                            title="Ver programa"
                          >
                            <BookOpenCheck class="w-4 h-4" />
                          </button>
                        {/if}
                      </div>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
      {/if}
    {:else}
      <div class="text-center py-12 text-slate-500">
        <p>No tienes cursos inscritos</p>
      </div>
    {/if}
  {:else}
    <!-- Vista plana sin agrupación -->
    {#if cursosData.length === 0}
      <div class="text-center py-12 text-slate-500">
        <p>No tienes cursos inscritos</p>
      </div>
    {:else}
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full">
          <tbody>
            {#each cursosData as curso}
              <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                  <div>
                    <p class="font-semibold text-slate-900">{curso.nombre}</p>
                    <p class="text-sm text-slate-500">{curso.cod_curso}</p>
                  </div>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">{curso.carrera_nombre || '—'}</td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      onclick={() => onCourseClick(curso)}
                      class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors"
                    >
                      Entrar
                      <ArrowRight class="w-4 h-4" />
                    </button>
                    {#if showSyllabusButton}
                      <button
                        onclick={() => onSyllabusClick(curso)}
                        class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                        title="Ver programa"
                      >
                        <BookOpenCheck class="w-4 h-4" />
                      </button>
                    {/if}
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  {/if}
</div>
