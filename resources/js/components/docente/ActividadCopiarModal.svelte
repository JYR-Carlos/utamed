<script lang="ts">
  import { onMount } from 'svelte';
  import { Copy, Loader2, AlertCircle } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { fetchCursosHermanos, type CursoHermano } from '@/modules/resources/actividad';

  interface Props {
    isOpen?: boolean;
    idCurso: number;
    actividad: Actividad | null;
    isLoading?: boolean;
    onClose?: () => void;
    onSubmit?: (data: {
      id_curso_destino: number;
      id_componente_destino: number;
      id_unidad_destino: number;
    }) => void;
  }

  let {
    isOpen = $bindable(false),
    idCurso,
    actividad = null,
    isLoading = false,
    onClose = () => {},
    onSubmit = () => {},
  }: Props = $props();

  let loadingHermanos = $state(false);
  let errorHermanos = $state<string | null>(null);
  let hermanos = $state<CursoHermano[]>([]);

  let idCursoDestino = $state<number | null>(null);
  let idComponenteDestino = $state<number | null>(null);
  let idUnidadDestino = $state<number | null>(null);

  const cursoSeleccionado = $derived(
    hermanos.find((c) => c.id_curso === idCursoDestino) ?? null
  );

  const componentesDisponibles = $derived(cursoSeleccionado?.componentes ?? []);
  const unidadesDisponibles = $derived(cursoSeleccionado?.unidades ?? []);

  // Cargar cursos hermanos al abrirse
  $effect(() => {
    if (isOpen && idCurso) {
      cargarHermanos();
    } else {
      hermanos = [];
      idCursoDestino = null;
      idComponenteDestino = null;
      idUnidadDestino = null;
      errorHermanos = null;
    }
  });

  async function cargarHermanos() {
    loadingHermanos = true;
    errorHermanos = null;
    try {
      hermanos = await fetchCursosHermanos(idCurso);
      if (hermanos.length > 0) {
        seleccionarCurso(hermanos[0].id_curso);
      }
    } catch (e: any) {
      errorHermanos = e?.message ?? 'Error al cargar los cursos hermanos.';
    } finally {
      loadingHermanos = false;
    }
  }

  function seleccionarCurso(cursoId: number) {
    idCursoDestino = cursoId;
    const destino = hermanos.find((c) => c.id_curso === cursoId);
    if (!destino) return;

    // Buscar componente homólogo por id_tipo_componente
    const tipoCompOrigen = actividad?.componente?.tipo_componente?.tipo;
    const homologoComp = destino.componentes.find(
      (c) => c.tipo === tipoCompOrigen
    ) ?? destino.componentes[0];
    idComponenteDestino = homologoComp?.id_componente ?? null;

    // Buscar unidad homóloga por num_unidad
    const numUnidadOrigen = actividad?.unidad?.num_unidad;
    const homologoUnidad = destino.unidades.find(
      (u) => u.num_unidad != null && u.num_unidad === numUnidadOrigen
    ) ?? destino.unidades[0];
    idUnidadDestino = homologoUnidad?.id_unidad ?? null;
  }

  function onCambiarCurso(e: Event) {
    const val = Number((e.target as HTMLSelectElement).value);
    seleccionarCurso(val);
  }

  function handleSubmit(e: SubmitEvent) {
    e.preventDefault();
    if (!idCursoDestino || !idComponenteDestino || !idUnidadDestino) return;
    onSubmit({
      id_curso_destino: idCursoDestino,
      id_componente_destino: idComponenteDestino,
      id_unidad_destino: idUnidadDestino,
    });
  }

  function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && !isLoading) onClose();
  }

  function onBackdrop(e: MouseEvent) {
    if (e.target === e.currentTarget && !isLoading) onClose();
  }

  const puedeCopiar = $derived(
    !isLoading &&
    !loadingHermanos &&
    idCursoDestino != null &&
    idComponenteDestino != null &&
    idUnidadDestino != null
  );
</script>

<svelte:window onkeydown={isOpen ? onKeydown : undefined} />

{#if isOpen && actividad}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    role="presentation"
    onclick={onBackdrop}
  >
    <div
      class="flex max-h-[90vh] w-full max-w-[520px] flex-col overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)]"
      role="dialog"
      aria-modal="true"
      aria-labelledby="copiar-actividad-titulo"
    >
      <!-- Cabecera -->
      <div class="flex items-start gap-3 border-b border-[#E5E7EB] bg-[#FCFBF9] px-5 py-3.5">
        <Copy size={18} class="mt-0.5 shrink-0 text-[#002F6C]" aria-hidden="true" />
        <div class="flex min-w-0 flex-col gap-0.5">
          <h2
            id="copiar-actividad-titulo"
            class="m-0 text-[15.5px] font-semibold break-words text-[#1A1A24]"
          >
            Copiar «{actividad.nombre}»
          </h2>
          <p class="m-0 text-[12.5px] text-[#5A5E6E]">
            Duplica esta actividad a otra sección de la misma asignatura.
          </p>
        </div>
      </div>

      <!-- Cuerpo -->
      <form onsubmit={handleSubmit} class="flex flex-col overflow-y-auto">
        <div class="flex flex-col gap-4 px-5 py-4">
          {#if loadingHermanos}
            <div class="flex items-center justify-center py-8 text-[#5A5E6E] gap-2">
              <Loader2 size={16} class="animate-spin text-[#002F6C]" />
              <span class="text-[13px]">Buscando cursos hermanos…</span>
            </div>
          {:else if errorHermanos}
            <div class="flex items-start gap-2 rounded-lg border border-[#FECACA] bg-[#FEF2F2] p-3 text-[13px] text-[#B91C1C]">
              <AlertCircle size={16} class="mt-0.5 shrink-0" />
              <span>{errorHermanos}</span>
            </div>
          {:else if hermanos.length === 0}
            <div class="rounded-lg border border-[#E5E7EB] bg-[#F5F1EA] p-4 text-center text-[13px] text-[#5A5E6E]">
              No existen otros cursos hermanos disponibles donde puedas crear actividades.
            </div>
          {:else}
            <!-- Selector de Curso Destino -->
            <div class="flex flex-col gap-1.5">
              <label for="curso-destino" class="text-[12.5px] font-semibold text-[#1A1A24]">
                Curso destino
              </label>
              <select
                id="curso-destino"
                value={idCursoDestino}
                onchange={onCambiarCurso}
                disabled={isLoading}
                class="h-[38px] rounded-lg border border-[#D6D9E0] bg-white px-3 text-[13px] text-[#1A1A24] outline-none focus:border-[#002F6C]"
              >
                {#each hermanos as h (h.id_curso)}
                  <option value={h.id_curso}>
                    {h.cod_curso} {h.letra_grupo ? `(Grupo ${h.letra_grupo})` : ''} — {h.agno_real ?? ''}-{h.semestre_real ?? ''}
                  </option>
                {/each}
              </select>
            </div>

            <!-- Selector de Componente Destino -->
            <div class="flex flex-col gap-1.5">
              <label for="componente-destino" class="text-[12.5px] font-semibold text-[#1A1A24]">
                Componente destino
              </label>
              <select
                id="componente-destino"
                bind:value={idComponenteDestino}
                disabled={isLoading || componentesDisponibles.length === 0}
                class="h-[38px] rounded-lg border border-[#D6D9E0] bg-white px-3 text-[13px] text-[#1A1A24] outline-none focus:border-[#002F6C]"
              >
                {#each componentesDisponibles as c (c.id_componente)}
                  <option value={c.id_componente}>
                    {c.tipo ?? `Componente #${c.id_componente}`}
                  </option>
                {/each}
              </select>
            </div>

            <!-- Selector de Unidad Destino -->
            <div class="flex flex-col gap-1.5">
              <label for="unidad-destino" class="text-[12.5px] font-semibold text-[#1A1A24]">
                Unidad destino
              </label>
              <select
                id="unidad-destino"
                bind:value={idUnidadDestino}
                disabled={isLoading || unidadesDisponibles.length === 0}
                class="h-[38px] rounded-lg border border-[#D6D9E0] bg-white px-3 text-[13px] text-[#1A1A24] outline-none focus:border-[#002F6C]"
              >
                {#each unidadesDisponibles as u (u.id_unidad)}
                  <option value={u.id_unidad}>
                    {u.num_unidad != null ? `Unidad ${u.num_unidad}: ` : ''}{u.nombre}
                  </option>
                {/each}
              </select>
            </div>

            <p class="m-0 text-[12px] text-[#5A5E6E]">
              La copia se creará como oculta y conservará el enunciado y la rúbrica si existen. Las entregas y notas no se transfieren.
            </p>
          {/if}
        </div>

        <!-- Pie -->
        <div class="flex items-center justify-end gap-2.5 border-t border-[#E5E7EB] bg-[#FCFBF9] px-5 py-3.5">
          <button
            type="button"
            class="rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2 text-[13.5px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA] disabled:opacity-50"
            disabled={isLoading}
            onclick={onClose}
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-lg border border-[#002F6C] bg-[#002F6C] px-3.5 py-2 text-[13.5px] font-semibold text-white transition-colors hover:bg-[#00224F] disabled:cursor-not-allowed disabled:opacity-45"
            disabled={!puedeCopiar}
          >
            {#if isLoading}
              <Loader2 size={14} class="animate-spin" aria-hidden="true" />
              Copiando…
            {:else}
              <Copy size={14} aria-hidden="true" />
              Copiar actividad
            {/if}
          </button>
        </div>
      </form>
    </div>
  </div>
{/if}
