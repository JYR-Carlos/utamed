<script lang="ts">
  /**
   * Recursos de la bibliografía, uno por fila: ícono según el tipo (libro,
   * archivo subido, enlace), la referencia y, a la derecha, la acción para
   * abrirlo. El archivo usa el color del curso (variables --curso*).
   */
  import { BookOpen, ExternalLink, FileText, Link2 } from 'lucide-svelte';
  import type { Recurso } from '@/lib/programaAlumno';

  interface Props {
    recursos: Recurso[];
    /** Margen lateral de las filas: el de la tarjeta que las contiene. */
    class?: string;
  }

  let { recursos, class: clase = 'px-4 lg:px-6' }: Props = $props();

  const ICONO = {
    libro: { icono: BookOpen, clases: 'bg-dm-sutil text-dm-ink-2', nombre: 'Libro o texto' },
    archivo: { icono: FileText, clases: 'bg-(--curso-s) text-(--curso-t)', nombre: 'Archivo' },
    enlace: { icono: Link2, clases: 'bg-dm-accion-suave text-dm-accion-texto', nombre: 'Enlace' },
  } as const;

  const ACCION =
    'inline-flex min-h-11 shrink-0 items-center gap-1.5 text-sm font-semibold text-dm-enlace no-underline hover:text-dm-enlace-hover hover:underline sm:min-h-9';
</script>

<ul class="m-0 list-none p-0">
  {#each recursos as recurso, i (i)}
    {@const tipo = ICONO[recurso.tipo]}
    {@const Icono = tipo.icono}
    <li class="flex flex-wrap items-start gap-x-3.5 gap-y-1 border-b border-dm-linea py-3.5 last:border-b-0 {clase}">
      <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {tipo.clases}">
        <Icono size={18} aria-hidden="true" />
        <span class="sr-only">{tipo.nombre}:</span>
      </span>
      <div class="flex min-w-0 flex-1 basis-60 flex-col gap-1">
        <span class="text-[15px] leading-[22px] text-pretty">{recurso.texto}</span>
        {#if recurso.cita}
          <span class="text-[13px] leading-[18px] text-pretty text-dm-ink-2">{recurso.cita}</span>
        {/if}
        {#if recurso.direccion}
          <span class="truncate text-[13px] leading-[18px] text-dm-ink-3">{recurso.direccion}</span>
        {/if}
      </div>
      {#if recurso.tipo === 'archivo'}
        <a href={recurso.href} target="_blank" rel="noopener noreferrer" class="{ACCION} max-sm:ml-12.5">
          <FileText size={16} aria-hidden="true" />Ver archivo<span class="sr-only"> (se abre en otra pestaña)</span>
        </a>
      {:else if recurso.tipo === 'enlace'}
        <a href={recurso.href} target="_blank" rel="noopener noreferrer" class="{ACCION} max-sm:ml-12.5">
          Abrir enlace<ExternalLink size={16} aria-hidden="true" /><span class="sr-only"> (se abre en otra pestaña)</span>
        </a>
      {/if}
    </li>
  {/each}
</ul>
