<script lang="ts">
  /**
   * Leyenda de una actividad al pasar el mouse (o al enfocar su enlace) en el
   * calendario y en «Tu semana»: una tarjeta con la franja del color del
   * curso, el código y la asignatura, el nombre, el plazo, el estado y el tipo.
   * Reemplaza al `title` del navegador, que no se podía leer de un vistazo.
   *
   * Va en un portal (no la recorta la celda del mes) y lleva su propio
   * `vista-alumno`, como ModalAlumno. En pantallas táctiles no aparece: ahí
   * el detalle del día ya dice todo.
   *
   * El disparador lo dibuja quien la usa, con los atributos que recibe:
   * `{#snippet children(props)}<a {...props} …>{/snippet}`.
   */
  import { Tooltip } from 'bits-ui';
  import type { Clock } from 'lucide-svelte';
  import type { Snippet } from 'svelte';
  import TipoActividad from '@/components/student/dm/TipoActividad.svelte';
  import { pildora } from '@/lib/dm';

  interface Props {
    /** «DM301». */
    codigo?: string;
    /** Nombre de la asignatura (en la vista del semestre). */
    asignatura?: string;
    /** Variables del color del curso (`estiloCurso`). */
    estilo?: string;
    nombre: string;
    /** «Act. 02 · vence el viernes 12 de diciembre». */
    detalle?: string;
    estado?: { etiqueta: string; clase: string; icono: typeof Clock } | null;
    tipo?: { esSumativa: boolean; esGrupal: boolean } | null;
    /** Las marcas del mes no entran al orden de tabulación: el día ya es el botón. */
    enfocable?: boolean;
    children: Snippet<[Record<string | symbol, unknown>]>;
  }

  let { codigo = '', asignatura = '', estilo = '', nombre, detalle = '', estado = null, tipo = null, enfocable = true, children }: Props =
    $props();

  const IconoEstado = $derived(estado?.icono);
</script>

<Tooltip.Provider delayDuration={250} disableHoverableContent>
  <Tooltip.Root>
    <Tooltip.Trigger>
      {#snippet child({ props })}
        {@render children(enfocable ? props : { ...props, tabindex: undefined })}
      {/snippet}
    </Tooltip.Trigger>
    <Tooltip.Portal>
      <Tooltip.Content
        side="top"
        sideOffset={8}
        collisionPadding={12}
        style={estilo}
        class="vista-alumno z-50 w-72 max-w-[calc(100vw-24px)] rounded-xl border border-dm-linea bg-dm-elevada font-dm text-dm-ink shadow-[0_14px_32px_-10px_rgb(22_25_73/0.35)] animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 motion-reduce:animate-none"
      >
        <span aria-hidden="true" class="block h-1 rounded-t-[11px] bg-(--curso,var(--color-dm-control))"></span>
        <div class="flex flex-col gap-1 px-3.5 pt-2.5 pb-3">
          {#if codigo || asignatura}
            <p class="m-0 flex min-w-0 items-baseline gap-1.5 text-[13px] leading-[18px] text-dm-ink-2">
              {#if codigo}<span class="shrink-0 font-semibold tracking-wide text-(--curso-t,var(--color-dm-ink))">{codigo}</span>{/if}
              {#if codigo && asignatura}<span aria-hidden="true">·</span>{/if}
              {#if asignatura}<span class="truncate">{asignatura}</span>{/if}
            </p>
          {/if}
          <p class="m-0 text-[15px] leading-5 font-medium">{nombre}</p>
          {#if detalle}<p class="m-0 text-[13px] leading-[18px] text-dm-ink-2">{detalle}</p>{/if}
          {#if estado || tipo}
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-dm-linea pt-2">
              {#if estado && IconoEstado}
                <span class="{pildora} {estado.clase}"><IconoEstado size={14} aria-hidden="true" />{estado.etiqueta}</span>
              {/if}
              {#if tipo}<TipoActividad esSumativa={tipo.esSumativa} esGrupal={tipo.esGrupal} />{/if}
            </div>
          {/if}
        </div>
        <Tooltip.Arrow>
          {#snippet child({ props })}
            <span {...props}>
              <!-- Dos lados con borde y el relleno tapa el borde de la tarjeta: la punta sale de ella. -->
              <svg width="16" height="8" viewBox="0 0 16 8" aria-hidden="true" class="-mt-px block overflow-visible">
                <path d="M0 0 L8 8 L16 0" class="fill-dm-elevada stroke-dm-linea" stroke-width="1" />
              </svg>
            </span>
          {/snippet}
        </Tooltip.Arrow>
      </Tooltip.Content>
    </Tooltip.Portal>
  </Tooltip.Root>
</Tooltip.Provider>
