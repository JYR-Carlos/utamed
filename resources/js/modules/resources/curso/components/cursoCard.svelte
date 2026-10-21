<script lang="ts">
  /**
   * cursoCard — Tarjeta de un curso en la vista del estudiante (usada por
   * cursoListAlumno): imagen, datos básicos y botones "Entrar" y "Programa".
   */
  import { ArrowRight, BookOpenCheck, BookOpen } from 'lucide-svelte';
  import type { CursoResumen } from '../types/curso.types';

  interface Props {
    curso: CursoResumen;
    /** Oculta el botón "Programa" cuando el curso no tiene syllabus. */
    showSyllabusButton?: boolean;
    isPeriodoActual?: boolean;
    onCourseClick?: (curso: CursoResumen) => void;
    onSyllabusClick?: (curso: CursoResumen) => void;
  }

  let {
    curso,
    showSyllabusButton = true,
    isPeriodoActual = true,
    onCourseClick = () => {},
    onSyllabusClick = () => {},
  }: Props = $props();
</script>

<div
  class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-full hover:shadow-md transition-all group"
>
  <!-- IMAGEN / BANNER -->
  <div class="w-full h-40 bg-gradient-to-br from-indigo-50 to-slate-100 flex items-center justify-center overflow-hidden border-b border-slate-100 shrink-0">
    {#if curso.imagen_url}
      <img
        src={curso.imagen_url}
        alt={curso.nombre}
        class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
      />
    {:else}
      <BookOpen class="w-10 h-10 text-indigo-400" />
    {/if}

    {#if curso.letra_grupo}
      <span class="absolute top-2.5 right-2.5 px-2 py-0.5 text-xs font-semibold rounded-md bg-white/90 text-slate-700 shadow-xs backdrop-blur-xs">
        
      </span>
    {/if}
  </div>

  <!-- INFO Y ACCIONES -->
  <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between gap-4">
    <div class="min-w-0">
    
      <h2 class="text-sm  text-indigo-600 truncate mb-1 font-semibold" title={curso.nombre}>
        {curso.nombre}
      </h2>
      <span class='text-sm font-medium'>
        Grupo {curso.letra_grupo}
      </span>

      
    </div>

    <!-- BOTONES -->
    <div class="pt-3 border-t border-slate-100 flex items-center gap-2 mt-auto">
      <button
        type="button"
        onclick={() => onCourseClick(curso)}
        class="cursor-pointer flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-xl transition-colors border border-indigo-100"
      >
        Entrar
        <ArrowRight class="w-4 h-4" />
      </button>

      {#if showSyllabusButton}
        <button
          type="button"
          onclick={() => onSyllabusClick(curso)}
          class="cursor-pointer flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-colors border border-slate-200"
        >
          <BookOpenCheck class="w-4 h-4" />
          Programa
        </button>
      {/if}
    </div>
  </div>
</div>
