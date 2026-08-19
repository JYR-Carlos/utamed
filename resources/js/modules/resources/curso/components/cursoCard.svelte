<script lang="ts">
  /**
   * cursoCard — Tarjeta de un curso en la vista del estudiante (usada por
   * cursoListAlumno): imagen, datos básicos y botones "Entrar" y "Programa".
   */
  import { ArrowRight, BookOpenCheck, BookOpen } from 'lucide-svelte';

  /** Shape mínimo del payload de cursos del estudiante. */
  interface CursoResumen {
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    imagen_url?: string | null;
    carrera_nombre?: string | null;
  }

  interface Props {
    curso: CursoResumen;
    /** Oculta el botón "Programa" cuando el curso no tiene syllabus. */
    showSyllabusButton?: boolean;
    onCourseClick?: (curso: CursoResumen) => void;
    onSyllabusClick?: (curso: CursoResumen) => void;
  }

  let {
    curso,
    showSyllabusButton = true,
    onCourseClick = () => {},
    onSyllabusClick = () => {},
  }: Props = $props();
</script>

<div
  class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6 flex flex-col sm:flex-row gap-4 lg:items-center lg:justify-between hover:shadow-md transition-all"
>
  <!-- IMAGEN -->
  <div class="shrink-0 w-full sm:w-32 h-32 rounded-xl bg-indigo-100 flex items-center justify-center overflow-hidden">
    {#if curso.imagen_url}
      <img
        src={curso.imagen_url}
        alt={curso.nombre}
        class="w-full h-full object-cover"
      />
    {:else}
      <BookOpen class="w-12 h-12 text-indigo-600" />
    {/if}
  </div>

  <!-- INFO -->
  <div class="min-w-0 flex-1">
    <p class="font-semibold text-slate-900 text-base sm:text-lg wrap-break-word">
      {curso.nombre}
    </p>

    <p class="text-sm text-slate-500 mt-1">
      {curso.cod_curso}{curso.letra_grupo ? `-${curso.letra_grupo}` : ''}
    </p>

    <p class="text-sm text-slate-600 mt-2">
      {curso.carrera_nombre || '—'}
    </p>
  </div>

  <!-- BOTONES -->
  <div class="flex flex-col sm:flex-row gap-2 sm:items-center w-full sm:w-auto shrink-0">
    <button
      onclick={() => onCourseClick(curso)}
      class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-xl transition-colors border border-indigo-100"
    >
      Entrar
      <ArrowRight class="w-4 h-4" />
    </button>

    {#if showSyllabusButton}
      <button
        onclick={() => onSyllabusClick(curso)}
        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-3 text-sm text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-colors border border-slate-200"
      >
        <BookOpenCheck class="w-4 h-4" />
        Programa
      </button>
    {/if}
  </div>
</div>