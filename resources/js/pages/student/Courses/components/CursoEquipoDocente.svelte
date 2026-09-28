<script lang="ts">
  /**
   * Equipo docente del curso, visto por el alumno. Era un bloque dentro de
   * «Sobre el curso»; desde T26 es la última sección de la ficha.
   */
  import { Mail } from 'lucide-svelte';
  import type { DocenteAlumno } from '@/types/syllabus.types';
  import { initials } from '@/utils/formatters';

  interface Props {
    docentes?: DocenteAlumno[];
  }

  let { docentes = [] }: Props = $props();

  // Titular primero: es el interlocutor por defecto del alumno.
  const equipo = $derived(
    [...docentes].sort((a, b) => Number(b.es_titular) - Number(a.es_titular)),
  );
</script>

{#if equipo.length === 0}
  <div class="rounded-3xl border border-dashed border-gray-200 p-6 text-center">
    <p class="text-sm text-gray-600">
      Todavía no hay docentes asignados a tus componentes de este curso.
    </p>
  </div>
{:else}
  <ul class="grid gap-3 sm:grid-cols-2">
    {#each equipo as docente (docente.nombre + (docente.componente ?? ''))}
      <li class="flex items-start gap-3 p-4 rounded-2xl border border-gray-200 bg-white">
        <span
          class="w-10 h-10 shrink-0 rounded-full bg-uta-blue text-white flex items-center justify-center text-xs font-bold"
          aria-hidden="true"
        >
          {initials(docente.nombre)}
        </span>
        <div class="min-w-0">
          <p class="font-semibold text-gray-900 leading-tight">{docente.nombre}</p>
          <p class="text-xs text-gray-500 mt-0.5">
            {docente.es_titular ? 'Titular' : 'Docente'}{docente.componente
              ? ` · ${docente.componente}`
              : ''}
          </p>
          {#if docente.email}
            <a
              href={`mailto:${docente.email}`}
              class="inline-flex items-center gap-1.5 mt-1.5 text-xs font-medium text-uta-blue hover:underline break-all"
            >
              <Mail class="w-3 h-3 shrink-0" />
              {docente.email}
            </a>
          {/if}
        </div>
      </li>
    {/each}
  </ul>
{/if}
