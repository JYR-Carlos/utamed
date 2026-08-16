<script lang="ts">
  /**
   * PageHeader — cabecera común de las pantallas del panel.
   *
   * Existe para que la acción primaria esté siempre en el mismo sitio y con
   * el mismo aspecto. Antes cambiaba de módulo a módulo: dos botones
   * circulares que sólo mostraban su etiqueta al pasar el ratón en Usuarios,
   * un botón etiquetado en la cabecera en Facultades y Carreras, y un botón
   * dentro de la barra de herramientas en Cursos Ofertados.
   *
   * Una sola ranura de acción primaria: si hacen falta más, van en
   * `secondaryActions`, visualmente por detrás.
   */
  import type { Snippet } from 'svelte';

  interface Props {
    title: string;
    subtitle?: string;
    /** Aviso breve bajo el subtítulo (p. ej. consecuencias de editar). */
    note?: Snippet;
    /** Acciones secundarias: se pintan como botones neutros. */
    secondaryActions?: Snippet;
    /** Acción primaria. Una sola, y siempre etiquetada. */
    primaryAction?: Snippet;
  }

  let { title, subtitle, note, secondaryActions, primaryAction }: Props = $props();
</script>

<header class="page-header">
  <div class="min-w-0">
    <h1 class="page-title">{title}</h1>
    {#if subtitle}
      <p class="page-subtitle">{subtitle}</p>
    {/if}
    {#if note}
      <div class="mt-2">{@render note()}</div>
    {/if}
  </div>

  {#if secondaryActions || primaryAction}
    <div class="flex items-center gap-2 shrink-0">
      {@render secondaryActions?.()}
      {@render primaryAction?.()}
    </div>
  {/if}
</header>
