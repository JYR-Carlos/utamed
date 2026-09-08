<script lang="ts">
  /**
   * ActividadEliminarDialog — confirmación de borrado de una actividad
   * (lámina «g» de /docente/cursos/{curso}/actividades).
   *
   * Nada de «¿estás seguro?»: se enumera lo que se va con la actividad, con la
   * cifra real de cada cosa. Las tres reglas de la lámina:
   *
   *  • La lista se arma con lo que **existe**: una actividad sin entregas no
   *    muestra la línea de entregas, y el botón dice sólo «Eliminar actividad».
   *  • La fricción de escribir ELIMINAR aparece **sólo** cuando hay entregas o
   *    notas que perder.
   *  • «Ocultar» vive dentro del diálogo porque es el error más frecuente: se
   *    borra buscando que el alumno deje de verla.
   *
   * De dónde sale cada cifra:
   *  - grupos   → COUNT(*) de agenda.actividad_asignada_grupo  (`total_grupos`)
   *  - notas    → COUNT(nota) de la misma tabla                (`calificados`)
   *  - entregas → agenda.agenda con tipo_mensaje 'Entrega de archivo' (`total_entregas`)
   *  - agenda   → la propia `fecha_limite` de la actividad
   */
  import {
    AlertTriangle,
    CalendarX,
    ClipboardCheck,
    EyeOff,
    FileUp,
    Loader2,
    Users,
  } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { formatDate } from '@/utils/formatters';

  interface Props {
    isOpen?: boolean;
    actividad?: Actividad | null;
    isLoading?: boolean;
    /** Sólo se ofrece ocultar si el docente puede editar y la actividad se ve. */
    puedeOcultar?: boolean;
    onConfirm?: () => void;
    onOcultar?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    actividad = null,
    isLoading = false,
    puedeOcultar = false,
    onConfirm = () => {},
    onOcultar = () => {},
    onCancel = () => {},
  }: Props = $props();

  const grupos = $derived(actividad?.total_grupos ?? 0);
  const entregas = $derived(actividad?.total_entregas ?? 0);
  const notas = $derived(actividad?.calificados ?? 0);

  /** Sólo se pide escribir ELIMINAR si hay trabajo del alumno o notas que perder. */
  const exigeFrase = $derived(entregas > 0 || notas > 0);

  let escrito = $state('');

  // Al cerrarse se limpia para que reabrir no herede lo ya escrito.
  $effect(() => {
    if (!isOpen) escrito = '';
  });

  const puedeConfirmar = $derived(!isLoading && (!exigeFrase || escrito.trim() === 'ELIMINAR'));

  const etiquetaBoton = $derived(
    entregas > 0
      ? `Eliminar actividad y ${entregas} ${entregas === 1 ? 'entrega' : 'entregas'}`
      : 'Eliminar actividad',
  );

  const ofreceOcultar = $derived(puedeOcultar && (actividad?.visible ?? false));

  function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && !isLoading) onCancel();
  }

  function onBackdrop(event: MouseEvent) {
    if (event.target === event.currentTarget && !isLoading) onCancel();
  }

  const FILA =
    'flex items-center gap-2.5 px-3 py-2.5 text-[13px] text-[#1A1A24] border-t border-[#E5E7EB] first:border-t-0';
  const TAG_FILA = 'ml-auto font-mono text-[12px] text-[#5A5E6E]';
</script>

<svelte:window onkeydown={isOpen ? onKeydown : undefined} />

{#if isOpen && actividad}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    role="presentation"
    onclick={onBackdrop}
  >
    <div
      class="flex max-h-[90vh] w-full max-w-[560px] flex-col overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)]"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="eliminar-actividad-titulo"
    >
      <!-- Cabecera -->
      <div class="flex items-start gap-3 border-b border-[#FECACA] bg-[#FEF2F2] px-5 py-3.5">
        <AlertTriangle size={18} class="mt-px shrink-0 text-[#B91C1C]" aria-hidden="true" />
        <div class="flex min-w-0 flex-col gap-0.5">
          <h2
            id="eliminar-actividad-titulo"
            class="m-0 text-[15.5px] font-semibold break-words text-[#7F1D1D]"
          >
            Eliminar «{actividad.nombre}»
          </h2>
          <p class="m-0 text-[12.5px] text-[#B91C1C]">
            Esta acción no se puede deshacer{grupos > 0
              ? ` y afecta a ${grupos === 1 ? 'el grupo' : `los ${grupos} grupos`} de la actividad`
              : ''}.
          </p>
        </div>
      </div>

      <!-- Cuerpo -->
      <div class="flex flex-col gap-3.5 overflow-y-auto px-5 py-4">
        {#if grupos > 0 || entregas > 0 || notas > 0 || actividad.fecha_limite}
          <p class="m-0 text-[13px] text-[#1A1A24]">
            Al eliminar la actividad se borra también, de forma permanente:
          </p>
          <div class="flex flex-col overflow-hidden rounded-[10px] border border-[#E5E7EB]">
            {#if grupos > 0}
              <div class={FILA}>
                <Users size={15} class="shrink-0 text-[#B91C1C]" aria-hidden="true" />
                <span
                  >{grupos}
                  {grupos === 1 ? 'grupo de trabajo' : 'grupos de trabajo'} con su composición</span
                >
                <span class={TAG_FILA}>grupos</span>
              </div>
            {/if}
            {#if entregas > 0}
              <div class="{FILA} bg-[#FCFBF9]">
                <FileUp size={15} class="shrink-0 text-[#B91C1C]" aria-hidden="true" />
                <span
                  >{entregas}
                  {entregas === 1 ? 'entrega recibida' : 'entregas recibidas'} y sus archivos</span
                >
                <span class={TAG_FILA}>entregas</span>
              </div>
            {/if}
            {#if notas > 0}
              <div class={FILA}>
                <ClipboardCheck size={15} class="shrink-0 text-[#B91C1C]" aria-hidden="true" />
                <span>{notas} {notas === 1 ? 'nota ya puesta' : 'notas ya puestas'}</span>
                <span class={TAG_FILA}>notas</span>
              </div>
            {/if}
            {#if actividad.fecha_limite}
              <div class="{FILA} bg-[#FCFBF9]">
                <CalendarX size={15} class="shrink-0 text-[#B91C1C]" aria-hidden="true" />
                <span>El hito del {formatDate(actividad.fecha_limite)} en la agenda del curso</span>
                <span class={TAG_FILA}>agenda</span>
              </div>
            {/if}
          </div>
        {:else}
          <p class="m-0 text-[13px] text-[#5A5E6E]">
            La actividad no tiene todavía grupos, entregas ni notas: al eliminarla no se pierde
            trabajo de los alumnos.
          </p>
        {/if}

        {#if ofreceOcultar}
          <div
            class="flex items-start gap-2.5 rounded-[10px] border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2.5"
          >
            <EyeOff size={15} class="mt-px shrink-0 text-[#B45309]" aria-hidden="true" />
            <p class="m-0 text-[12.5px] text-pretty text-[#78350F]">
              Si sólo quieres que el alumno deje de verla,
              <button
                type="button"
                class="font-semibold text-[#78350F] underline underline-offset-2"
                disabled={isLoading}
                onclick={onOcultar}>ocúltala</button
              >: conserva grupos, entregas y notas.
            </p>
          </div>
        {/if}

        {#if exigeFrase}
          <div class="flex flex-col gap-1.5">
            <label for="frase-eliminar" class="text-[12.5px] font-semibold text-[#1A1A24]">
              Escribe <span class="font-mono">ELIMINAR</span> para habilitar el botón
            </label>
            <input
              id="frase-eliminar"
              type="text"
              bind:value={escrito}
              autocomplete="off"
              disabled={isLoading}
              class="h-[38px] rounded-lg border border-[#D6D9E0] bg-white px-3 font-mono text-[13px] text-[#1A1A24] outline-none focus:border-[#002F6C]"
            />
          </div>
        {/if}
      </div>

      <!-- Pie -->
      <div
        class="flex items-center gap-2.5 border-t border-[#E5E7EB] bg-[#FCFBF9] px-5 py-3.5"
      >
        <span class="mr-auto text-[12px] text-[#5A5E6E]">
          {exigeFrase ? 'Hay trabajo de alumnos en juego.' : ''}
        </span>
        <button
          type="button"
          class="rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2 text-[13.5px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA] disabled:opacity-50"
          disabled={isLoading}
          onclick={onCancel}
        >
          Cancelar
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-[#B91C1C] bg-[#B91C1C] px-3.5 py-2 text-[13.5px] font-semibold text-white transition-colors hover:bg-[#991B1B] disabled:cursor-not-allowed disabled:opacity-45"
          disabled={!puedeConfirmar}
          onclick={onConfirm}
        >
          {#if isLoading}
            <Loader2 size={14} class="animate-spin" aria-hidden="true" />
            Eliminando…
          {:else}
            {etiquetaBoton}
          {/if}
        </button>
      </div>
    </div>
  </div>
{/if}
