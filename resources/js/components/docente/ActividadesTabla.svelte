<script lang="ts">
  /**
   * ActividadesTabla — vista Lista densa de las actividades de un curso
   * (láminas «a», «e» y «f» de /docente/cursos/{curso}/actividades).
   *
   * Tres ideas de la lámina se sostienen aquí:
   *
   *  1. **Estado ≠ visibilidad.** El estado lo deriva el sistema
   *     (`utils/actividadEstado`) y se lee; la visibilidad la pone el docente y
   *     por eso vive como interruptor en la propia fila.
   *  2. **Micro-feedback optimista.** El interruptor se mueve al instante; la
   *     fila queda arena y con las acciones inertes hasta que el servidor
   *     confirma. Si falla, el interruptor vuelve a su valor real y el reintento
   *     vive en la fila — nunca un modal ni una recarga.
   *  3. **Ausencia, no deshabilitado.** Sin permiso de editar el interruptor se
   *     vuelve etiqueta (informa, no actúa); sin ninguna acción delegada la
   *     columna «Acciones» desaparece completa, cabecera incluida.
   *
   * La columna «Calificadas» NO cuenta entregas: cuenta grupos con nota puesta
   * sobre grupos asignados (`agenda.actividad_asignada_grupo`). Es el mismo
   * criterio del kanban del detalle de curso.
   */
  import { Link } from '@inertiajs/svelte';
  import {
    AlertCircle,
    Check,
    Eye,
    EyeOff,
    FileText,
    Loader2,
    MessageSquare,
    Trash2,
    Upload,
  } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { formatDate } from '@/utils/formatters';
  import {
    estadoActividad,
    etiquetaUnidad,
    nombreComponente,
    siglaComponente,
    ETIQUETA_ESTADO,
    PILL_ESTADO,
    PUNTO_ESTADO,
  } from '@/utils/actividadEstado';
  import type { ToggleVisibilidad } from '@/utils/actividadEstado';

  interface Props {
    actividades: Actividad[];
    idCurso: number;
    canEdit?: boolean;
    canDelete?: boolean;
    /** Estado del guardado inline, indexado por id_actividad. */
    toggles?: Record<number, ToggleVisibilidad>;
    onToggleVisible?: (a: Actividad) => void;
    onEdit?: (a: Actividad) => void;
    onDelete?: (a: Actividad) => void;
    onEnunciado?: (a: Actividad) => void;
  }

  let {
    actividades,
    idCurso,
    canEdit = false,
    canDelete = false,
    toggles = {},
    onToggleVisible = () => {},
    onEdit = () => {},
    onDelete = () => {},
    onEnunciado = () => {},
  }: Props = $props();

  /** Sin ninguna acción delegada la columna entera no se dibuja (lámina f, caso 3). */
  const hayAcciones = $derived(canEdit || canDelete);

  const ahora = new Date();

  /** Valor que debe pintar el interruptor: el optimista manda mientras se guarda. */
  function visibleEnPantalla(a: Actividad): boolean {
    const t = toggles[a.id_actividad];
    return t?.fase === 'guardando' ? t.optimista : a.visible;
  }

  const TAG_COMPONENTE =
    'font-mono text-[10.5px] font-bold tracking-[0.06em] rounded-[5px] px-1.5 py-0.5 border';
  const BTN_FILA_OUTLINE =
    'inline-flex items-center rounded-[7px] border border-[#D6D9E0] bg-white px-2.5 py-[5px] text-[12.5px] font-medium text-[#1A1A24] no-underline transition-colors hover:bg-[#F5F1EA] disabled:pointer-events-none';
  const BTN_FILA_GHOST =
    'inline-flex items-center rounded-[7px] border border-transparent px-2.5 py-[5px] text-[12.5px] font-medium text-[#002F6C] transition-colors hover:bg-[#F5F1EA] disabled:pointer-events-none';
  const BTN_FILA_ICONO =
    'inline-flex items-center justify-center rounded-[7px] border border-transparent p-[5px] text-[#5A5E6E] transition-colors hover:bg-[#F5F1EA] hover:text-[#002F6C] disabled:pointer-events-none';
  const TH =
    'px-2 py-[9px] text-left text-[11.5px] font-semibold uppercase tracking-[0.04em] text-[#5A5E6E]';
</script>

<div class="overflow-x-auto rounded-xl border border-[#E5E7EB]">
  <table class="w-full min-w-[1040px] border-collapse text-[13.5px]">
    <thead>
      <tr class="bg-[#F5F1EA]">
        <th class="{TH} pl-4">Nombre</th>
        <th class="{TH} w-[78px]">Comp.</th>
        <th class="{TH} w-[150px]">Unidad</th>
        <th class="{TH} w-[96px]">Tipo</th>
        <th class="{TH} w-[112px]">Fecha límite</th>
        <th class="{TH} w-[112px]">Calificadas</th>
        <th class="{TH} w-[126px]">Estado</th>
        <th class="{TH} w-[168px]">Visible</th>
        {#if hayAcciones}
          <th class="{TH} w-[212px] pr-4 text-right">Acciones</th>
        {/if}
      </tr>
    </thead>
    <tbody>
      {#each actividades as act, i (act.id_actividad)}
        {@const estado = estadoActividad(act, ahora)}
        {@const toggle = toggles[act.id_actividad]}
        {@const guardando = toggle?.fase === 'guardando'}
        {@const visible = visibleEnPantalla(act)}
        {@const unidad = etiquetaUnidad(act)}
        <tr
          class="border-t border-[#E5E7EB] {guardando
            ? 'bg-[#FFFBEB]'
            : toggle?.fase === 'error'
              ? 'bg-[#FEF2F2]'
              : i % 2 === 1
                ? 'bg-[#FCFBF9]'
                : 'bg-white'}"
        >
          <!-- Nombre + lo que cuelga de la actividad -->
          <td class="py-2.5 pl-4 pr-2 align-top">
            <Link
              href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
              class="font-medium text-[#002F6C] no-underline hover:underline"
            >
              {act.nombre}
            </Link>
            {#if act.archivo_enunciado || (act.mensajes_pendientes ?? 0) > 0}
              <div class="mt-1 flex flex-wrap items-center gap-2.5 text-[11.5px]">
                {#if act.archivo_enunciado}
                  <a
                    href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/enunciado/descargar"
                    class="inline-flex items-center gap-1 text-[#5A5E6E] no-underline hover:text-[#002F6C]"
                    title={act.archivo_enunciado.nombre_original}
                  >
                    <FileText size={12} aria-hidden="true" />
                    Enunciado
                  </a>
                {/if}
                {#if (act.mensajes_pendientes ?? 0) > 0}
                  <Link
                    href="/docente/mensajes?actividad_id={act.id_actividad}"
                    class="inline-flex items-center gap-1 font-semibold text-[#B45309] no-underline hover:underline"
                  >
                    <MessageSquare size={12} aria-hidden="true" />
                    {act.mensajes_pendientes} por responder
                  </Link>
                {/if}
              </div>
            {/if}
          </td>

          <!-- Componente -->
          <td class="px-2 py-2.5 align-top">
            <span
              class="{TAG_COMPONENTE} {act.componente
                ? 'border-[#C9D6E6] bg-white text-[#002F6C]'
                : 'border-[#E5E7EB] bg-[#F5F1EA] text-[#5A5E6E]'}"
              title={nombreComponente(act)}
            >
              {siglaComponente(act)}
            </span>
          </td>

          <!-- Unidad -->
          <td class="px-2 py-2.5 align-top text-[12.5px] text-[#5A5E6E]">
            {unidad ?? '—'}
          </td>

          <!-- Tipo -->
          <td
            class="px-2 py-2.5 align-top text-[12.5px] {act.tipo_actividad === 'SUMATIVA'
              ? 'font-semibold text-[#1A1A24]'
              : 'text-[#5A5E6E]'}"
          >
            {act.tipo_actividad === 'SUMATIVA' ? 'Sumativa' : 'Formativa'}
          </td>

          <!-- Fecha límite -->
          <td class="px-2 py-2.5 align-top font-mono text-[12.5px] tabular-nums text-[#1A1A24]">
            {act.fecha_limite ? formatDate(act.fecha_limite) : '—'}
          </td>

          <!-- Calificadas: grupos con nota / grupos asignados -->
          <td class="px-2 py-2.5 align-top font-mono text-[12.5px] tabular-nums">
            {#if (act.total_grupos ?? 0) > 0}
              <span class={(act.calificados ?? 0) === 0 ? 'text-[#98A0AE]' : 'text-[#1A1A24]'}>
                {act.calificados ?? 0}/{act.total_grupos}
              </span>
            {:else}
              <span class="text-[#98A0AE]" title="La actividad todavía no tiene grupos asignados"
                >Sin grupos</span
              >
            {/if}
          </td>

          <!-- Estado (lo pone el sistema) -->
          <td class="px-2 py-2.5 align-top">
            <span class={PILL_ESTADO[estado]}>
              <span
                class="h-1.5 w-1.5 rounded-full {PUNTO_ESTADO[estado]}"
                aria-hidden="true"
              ></span>
              {ETIQUETA_ESTADO[estado]}
            </span>
          </td>

          <!-- Visibilidad (la pone el docente) -->
          <td class="px-2 py-2.5 align-top">
            {#if canEdit}
              <div class="flex flex-col gap-1">
                <span class="flex items-center gap-2 {guardando ? 'opacity-70' : ''}">
                  <button
                    type="button"
                    role="switch"
                    aria-checked={visible}
                    aria-label={visible
                      ? `«${act.nombre}» es visible para los alumnos — clic para ocultar`
                      : `«${act.nombre}» está oculta a los alumnos — clic para mostrar`}
                    disabled={guardando}
                    onclick={() => onToggleVisible(act)}
                    class="flex h-5 w-9 shrink-0 items-center rounded-full p-0.5 transition-colors duration-150 disabled:cursor-progress {visible
                      ? 'justify-end'
                      : 'justify-start'} {guardando
                      ? 'bg-[#B7BCC6]'
                      : visible
                        ? 'bg-[#059669]'
                        : 'bg-[#E0E3E9]'}"
                  >
                    <span
                      class="h-4 w-4 rounded-full bg-white shadow-[0_1px_2px_rgba(0,0,0,.2)]"
                      aria-hidden="true"
                    ></span>
                  </button>
                  {#if guardando}
                    <span class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-[#B45309]">
                      <Loader2 size={12} class="animate-spin" aria-hidden="true" />
                      Guardando…
                    </span>
                  {:else}
                    <span
                      class="text-[11.5px] font-semibold {visible
                        ? 'text-[#047857]'
                        : 'text-[#B45309]'}">{visible ? 'Visible' : 'Oculta'}</span
                    >
                  {/if}
                </span>

                {#if toggle?.fase === 'ok'}
                  <span class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-[#047857]">
                    <Check size={12} aria-hidden="true" />
                    Guardado
                  </span>
                {:else if toggle?.fase === 'error'}
                  <span class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-[#B91C1C]">
                    <AlertCircle size={12} aria-hidden="true" />
                    No se pudo guardar ·
                    <button
                      type="button"
                      class="underline underline-offset-2"
                      onclick={() => onToggleVisible(act)}>Reintentar</button
                    >
                  </span>
                {/if}
              </div>
            {:else}
              <!-- Sin permiso de editar el interruptor se vuelve etiqueta. -->
              <span
                class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold {act.visible
                  ? 'text-[#047857]'
                  : 'text-[#B45309]'}"
              >
                {#if act.visible}
                  <Eye size={13} aria-hidden="true" />Visible
                {:else}
                  <EyeOff size={13} aria-hidden="true" />Oculta
                {/if}
              </span>
            {/if}
          </td>

          <!-- Acciones -->
          {#if hayAcciones}
            <td class="py-2.5 pl-2 pr-4 align-top">
              <div class="flex justify-end gap-1 {guardando ? 'pointer-events-none opacity-45' : ''}">
                <Link
                  href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
                  class={BTN_FILA_OUTLINE}
                >
                  Evaluar
                </Link>
                {#if canEdit}
                  <button type="button" class={BTN_FILA_GHOST} onclick={() => onEdit(act)}>
                    Editar
                  </button>
                  <button
                    type="button"
                    class={BTN_FILA_ICONO}
                    onclick={() => onEnunciado(act)}
                    title={act.archivo_enunciado ? 'Reemplazar enunciado' : 'Adjuntar enunciado'}
                    aria-label={act.archivo_enunciado
                      ? `Reemplazar el enunciado de ${act.nombre}`
                      : `Adjuntar un enunciado a ${act.nombre}`}
                  >
                    <Upload size={15} aria-hidden="true" />
                  </button>
                {/if}
                {#if canDelete}
                  <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-[7px] border border-transparent p-[5px] text-[#B91C1C] transition-colors hover:bg-[#FEF2F2]"
                    onclick={() => onDelete(act)}
                    aria-label="Eliminar {act.nombre}"
                    title="Eliminar"
                  >
                    <Trash2 size={15} aria-hidden="true" />
                  </button>
                {/if}
              </div>
            </td>
          {/if}
        </tr>
      {/each}
    </tbody>
  </table>
</div>
