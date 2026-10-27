<script lang="ts">
  /**
   * Ventana de la guía dm sobre el Dialog de bits-ui (foco atrapado, Escape,
   * clic fuera, scroll bloqueado). Cabecera noche con el color del curso y
   * tres formatos:
   *
   * - `grande`: agenda y enunciado. Centrada y alta; en el móvil, a pantalla
   *   completa.
   * - `centro`: entrega. Centrada al ancho de un formulario; en el móvil sube
   *   desde abajo.
   * - `lateral`: rúbrica. Panel a la derecha; en el móvil, a pantalla completa.
   *
   * Va a un portal fuera de la página, así que el color del curso se le pasa
   * (`estilo`, de estiloCurso) y lleva su propio `vista-alumno`.
   */
  import { Dialog } from 'bits-ui';
  import { X } from 'lucide-svelte';
  import type { Snippet } from 'svelte';
  import Reticula from './Reticula.svelte';
  import { etiqueta as claseEtiqueta, tituloCond } from '@/lib/dm';

  interface Props {
    open: boolean;
    variante?: 'grande' | 'centro' | 'lateral';
    /** Rótulo amarillo sobre el título. */
    etiqueta: string;
    titulo: string;
    subtitulo?: string;
    /** Variables --curso* (estiloCurso). */
    estilo?: string;
    /** «Cerrar agenda», «Cerrar rúbrica»… */
    rotuloCerrar?: string;
    /** Mientras algo no se puede interrumpir (una subida): sin cerrar. */
    bloqueada?: boolean;
    children: Snippet;
  }

  let {
    open = $bindable(),
    variante = 'grande',
    etiqueta,
    titulo,
    subtitulo = '',
    estilo = '',
    rotuloCerrar = 'Cerrar',
    bloqueada = false,
    children,
  }: Props = $props();

  const POSICION = {
    grande:
      'inset-0 lg:inset-x-0 lg:top-10 lg:bottom-10 lg:mx-auto lg:w-[min(880px,calc(100vw-48px))] lg:rounded-2xl lg:shadow-[0_24px_64px_rgb(10_12_40/0.35)]',
    centro:
      'inset-x-0 bottom-0 max-h-[92svh] rounded-t-2xl shadow-[0_-12px_40px_rgb(10_12_40/0.3)] md:inset-x-auto md:top-1/2 md:bottom-auto md:left-1/2 md:max-h-[calc(100svh-48px)] md:w-140 md:-translate-1/2 md:rounded-2xl md:shadow-[0_24px_64px_rgb(10_12_40/0.35)]',
    lateral: 'inset-0 lg:left-auto lg:w-[min(760px,100vw)] lg:shadow-[-24px_0_64px_rgb(10_12_40/0.3)]',
  } as const;

  const TITULO = {
    grande: 'text-[26px] leading-7 lg:text-[32px] lg:leading-8',
    centro: 'text-[26px] leading-7',
    lateral: 'text-[26px] leading-7 lg:text-[30px] lg:leading-[30px]',
  } as const;
</script>

<Dialog.Root bind:open>
  <Dialog.Portal>
    <Dialog.Overlay class="fixed inset-0 z-50 bg-dm-velo" />
    <Dialog.Content
      style={estilo}
      escapeKeydownBehavior={bloqueada ? 'ignore' : 'close'}
      interactOutsideBehavior={bloqueada ? 'ignore' : 'close'}
      class="vista-alumno fixed z-50 flex flex-col overflow-hidden bg-dm-elevada font-dm text-sm text-dm-ink outline-none {POSICION[variante]}"
    >
      <header class="sobre-noche relative shrink-0 overflow-hidden border-b-6 border-(--curso) bg-dm-noche">
        <Reticula class="left-[45%] lg:left-1/2" />
        <div class="relative flex items-start gap-2 py-3 pr-1.5 pl-4 lg:gap-4 lg:py-5 lg:pr-2.5 lg:pl-7 {variante === 'centro' ? 'md:py-4.5 md:pl-6' : ''}">
          <div class="flex min-w-0 flex-1 flex-col gap-1 pt-1 lg:gap-1.5 lg:pt-0">
            <span class="{claseEtiqueta} text-dm-amarillo">{etiqueta}</span>
            <Dialog.Title class="{tituloCond} text-dm-noche-texto {TITULO[variante]}">{titulo}</Dialog.Title>
            {#if subtitulo}
              <span class="text-[13px] leading-[18px] text-dm-noche-texto-2 lg:text-sm lg:leading-5">{subtitulo}</span>
            {/if}
          </div>
          {#if !bloqueada}
            <Dialog.Close
              class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-[10px] text-dm-noche-texto hover:bg-dm-noche-activo"
              aria-label={rotuloCerrar}
            >
              <X size={22} aria-hidden="true" />
            </Dialog.Close>
          {/if}
        </div>
      </header>
      {@render children()}
    </Dialog.Content>
  </Dialog.Portal>
</Dialog.Root>
