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
    'flex items-center gap-2.5 px-3 py-2.5 text-[13px] text-ink border-t border-gray-200 first:border-t-0';
  const TAG_FILA = 'ml-auto font-mono text-[12px] text-ink-2';
</script>

<svelte:window onkeydown={isOpen ? onKeydown : undefined} />

{#if isOpen && actividad}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    role="presentation"
    onclick={onBackdrop}
  >
    <div
      class="flex max-h-[90vh] w-full max-w-[560px] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)]"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="eliminar-actividad-titulo"
    >
      <!-- Cabecera -->
      <div class="flex items-start gap-3 border-b border-red-200 bg-red-50 px-5 py-3.5">
        <AlertTriangle size={18} class="mt-px shrink-0 text-red-700" aria-hidden="true" />
        <div class="flex min-w-0 flex-col gap-0.5">
          <h2
            id="eliminar-actividad-titulo"
            class="m-0 text-[15.5px] font-semibold break-words text-red-900"
          >
            Eliminar «{actividad.nombre}»
          </h2>
          <p class="m-0 text-[12.5px] text-red-700">
            Esta acción no se puede deshacer{grupos > 0
              ? ` y afecta a ${grupos === 1 ? 'el grupo' : `los ${grupos} grupos`} de la actividad`
              : ''}.
          </p>
        </div>
      </div>

      <!-- Cuerpo -->
      <div class="flex flex-col gap-3.5 overflow-y-auto px-5 py-4">
        {#if grupos > 0 || entregas > 0 || notas > 0 || actividad.fecha_limite}
          <p class="m-0 text-[13px] text-ink">
            Al eliminar la actividad se borra también, de forma permanente:
          </p>
          <div class="flex flex-col overflow-hidden rounded-[10px] border border-gray-200">
            {#if grupos > 0}
              <div class={FILA}>
                <Users size={15} class="shrink-0 text-red-700" aria-hidden="true" />
                <span
                  >{grupos}
                  {grupos === 1 ? 'grupo de trabajo' : 'grupos de trabajo'} con su composición</span
                >
                <span class={TAG_FILA}>grupos</span>
              </div>
            {/if}
            {#if entregas > 0}
              <div class="{FILA} bg-stone-50">
                <FileUp size={15} class="shrink-0 text-red-700" aria-hidden="true" />
                <span
                  >{entregas}
                  {entregas === 1 ? 'entrega recibida' : 'entregas recibidas'} y sus archivos</span
                >
                <span class={TAG_FILA}>entregas</span>
              </div>
            {/if}
            {#if notas > 0}
              <div class={FILA}>
                <ClipboardCheck size={15} class="shrink-0 text-red-700" aria-hidden="true" />
                <span>{notas} {notas === 1 ? 'nota ya puesta' : 'notas ya puestas'}</span>
                <span class={TAG_FILA}>notas</span>
              </div>
            {/if}
            {#if actividad.fecha_limite}
              <div class="{FILA} bg-stone-50">
                <CalendarX size={15} class="shrink-0 text-red-700" aria-hidden="true" />
                <span>El hito del {formatDate(actividad.fecha_limite)} en la agenda del curso</span>
                <span class={TAG_FILA}>agenda</span>
              </div>
            {/if}
          </div>
        {:else}
          <p class="m-0 text-[13px] text-ink-2">
            La actividad no tiene todavía grupos, entregas ni notas: al eliminarla no se pierde
            trabajo de los alumnos.
          </p>
        {/if}

        {#if ofreceOcultar}
          <div
            class="flex items-start gap-2.5 rounded-[10px] border border-amber-200 bg-amber-50 px-3 py-2.5"
          >
            <EyeOff size={15} class="mt-px shrink-0 text-amber-700" aria-hidden="true" />
            <p class="m-0 text-[12.5px] text-pretty text-amber-900">
              Si sólo quieres que el alumno deje de verla,
              <button
                type="button"
                class="font-semibold text-amber-900 underline underline-offset-2"
                disabled={isLoading}
                onclick={onOcultar}>ocúltala</button
              >: conserva grupos, entregas y notas.
            </p>
          </div>
        {/if}

        {#if exigeFrase}
          <div class="flex flex-col gap-1.5">
            <label for="frase-eliminar" class="text-[12.5px] font-semibold text-ink">
              Escribe <span class="font-mono">ELIMINAR</span> para habilitar el botón
            </label>
            <input
              id="frase-eliminar"
              type="text"
              bind:value={escrito}
              autocomplete="off"
              disabled={isLoading}
              class="h-[38px] rounded-lg border border-line bg-white px-3 font-mono text-[13px] text-ink outline-none focus:border-navy"
            />
          </div>
        {/if}
      </div>

      <!-- Pie -->
      <div
        class="flex items-center gap-2.5 border-t border-gray-200 bg-stone-50 px-5 py-3.5"
      >
        <span class="mr-auto text-[12px] text-ink-2">
          {exigeFrase ? 'Hay trabajo de alumnos en juego.' : ''}
        </span>
        <button
          type="button"
          class="rounded-lg border border-line bg-white px-3.5 py-2 text-[13.5px] font-medium text-ink transition-colors hover:bg-sand disabled:opacity-50"
          disabled={isLoading}
          onclick={onCancel}
        >
          Cancelar
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-red-700 bg-red-700 px-3.5 py-2 text-[13.5px] font-semibold text-white transition-colors hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-45"
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
