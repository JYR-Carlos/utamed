<script lang="ts">
  /**
   * Tabla de la pestaña Estudiantes del curso (dirección institucional UTA).
   *
   * Columnas: estudiante, promedio (la nota del componente), asistencia con
   * su barra, lo pendiente y la ficha. «Pendiente» usa las mismas palabras
   * que los estados de las actividades: «Por revisar» si entregó y falta
   * evaluarlo, «No entregó» si una actividad cerró sin su entrega, «Al día»
   * si no hay nada.
   *
   * Muestra las primeras `inicial` filas; el pie dice cuántas se ven y abre
   * el resto.
   */
  import AvatarAlumno from '@/components/docente/AvatarAlumno.svelte';
  import { btnSecundario, chico, chip, tono } from '@/lib/inst';
  import { formatNota } from '@/utils/formatters';

  interface EstudianteItem {
    id_inscripcion_componente: number;
    nota_componente: number | null;
    estudiante: { nombre: string; username: string; url_foto?: string | null };
    asistencia?: { presentes: number; total: number };
    pendientes?: { por_revisar: number; no_entrego: number };
  }

  interface Props {
    estudiantes: EstudianteItem[];
    /** Mínimo de asistencia del componente (0–100); bajo él la barra va en rojo. */
    minAsistencia?: number | null;
    onDetalle?: (item: EstudianteItem) => void;
    /** Filas visibles antes de «Ver los N estudiantes»; null = todas. */
    inicial?: number | null;
  }

  let { estudiantes, minAsistencia = null, onDetalle, inicial = 8 }: Props = $props();

  let verTodos = $state(false);

  const ordenados = $derived(
    [...estudiantes].sort((a, b) =>
      (a.estudiante?.nombre ?? '').localeCompare(b.estudiante?.nombre ?? '', 'es', { sensitivity: 'base' }),
    ),
  );
  const recortada = $derived(!verTodos && inicial !== null && ordenados.length > inicial);
  const visibles = $derived(recortada ? ordenados.slice(0, inicial!) : ordenados);

  function porcentaje(item: EstudianteItem): number | null {
    const a = item.asistencia;
    if (!a || a.total === 0) return null;
    return Math.round((a.presentes / a.total) * 100);
  }

  function bajoMinimo(p: number | null): boolean {
    return p !== null && !!minAsistencia && minAsistencia > 0 && p < minAsistencia;
  }
</script>

<table class="tabla-inst">
  <thead>
    <tr>
      <th scope="col">Estudiante</th>
      <th scope="col" class="w-[110px]">Promedio</th>
      <th scope="col" class="w-[200px]">Asistencia{minAsistencia ? ` (mín. ${minAsistencia} %)` : ''}</th>
      <th scope="col" class="w-[220px]">Pendiente</th>
      {#if onDetalle}<th scope="col" class="w-[120px]"><span class="sr-only">Ficha</span></th>{/if}
    </tr>
  </thead>
  <tbody>
    {#each visibles as item (item.id_inscripcion_componente)}
      {@const p = porcentaje(item)}
      {@const pend = item.pendientes ?? { por_revisar: 0, no_entrego: 0 }}
      <tr>
        <td data-l="Estudiante">
          <span class="flex items-center gap-2.5">
            <AvatarAlumno
              src={item.estudiante.url_foto ?? null}
              nombre={item.estudiante.nombre}
              class="size-8 bg-inst-cabecera text-xs font-extrabold text-inst-ink-2"
            />
            <span class="font-bold">{item.estudiante.nombre}</span>
          </span>
        </td>
        <td data-l="Promedio">
          <span class="font-inst-titulo text-base font-bold">
            {item.nota_componente !== null ? formatNota(item.nota_componente) : '—'}
          </span>
        </td>
        <td data-l="Asistencia">
          {#if p === null}
            <span class="text-[13px] text-inst-ink-3">Sin sesiones</span>
          {:else}
            <span class="flex items-center gap-2.5">
              <span class="block h-2 w-24 overflow-hidden rounded-full bg-inst-off-s" aria-hidden="true">
                <span class="block h-full {bajoMinimo(p) ? 'bg-inst-error' : 'bg-inst-ok'}" style="width: {p}%"></span>
              </span>
              <span class="text-[13px] font-bold {bajoMinimo(p) ? 'text-inst-error' : ''}">{p} %</span>
            </span>
          {/if}
        </td>
        <td data-l="Pendiente">
          <span class="flex flex-wrap gap-1.5">
            {#if pend.por_revisar > 0}
              <span class="{chip} {tono.warn}">Por revisar · {pend.por_revisar}</span>
            {/if}
            {#if pend.no_entrego > 0}
              <span class="{chip} {tono.off}">No entregó · {pend.no_entrego}</span>
            {/if}
            {#if pend.por_revisar === 0 && pend.no_entrego === 0}
              <span class="{chip} {tono.ok}">Al día</span>
            {/if}
          </span>
        </td>
        {#if onDetalle}
          <td>
            <button type="button" class="{btnSecundario} {chico}" onclick={() => onDetalle(item)}>Ver ficha</button>
          </td>
        {/if}
      </tr>
    {/each}
  </tbody>
</table>

<div class="flex flex-wrap items-center gap-2 rounded-b-lg border-t border-inst-linea bg-inst-sutil px-4.5 py-3">
  <span class="text-[13px] text-inst-ink-2">Mostrando {visibles.length} de {ordenados.length} estudiantes</span>
  {#if recortada}
    <button
      type="button"
      class="ml-auto inline-flex min-h-11 cursor-pointer items-center rounded-md border border-inst-oro bg-inst-oro px-3 text-[13px] font-bold text-uta-marino transition-colors hover:brightness-95 lg:min-h-9"
      onclick={() => (verTodos = true)}
    >
      Ver los {ordenados.length} estudiantes
    </button>
  {/if}
</div>
