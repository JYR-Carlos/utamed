<script lang="ts">
  /**
   * CopiarActividadesModal — «Copiar a otro curso».
   *
   * Lleva actividades de este curso a los otros grupos de la misma asignatura
   * y período donde el docente también es titular (CopiaActividadesService).
   * Las copias llegan ocultas; el enunciado se comparte y la rúbrica se copia.
   *
   * Al abrirse pide al servidor qué se puede copiar a cada curso: si el destino
   * no tiene el componente o la unidad de una actividad, esa actividad no se
   * puede copiar ahí y se dice por qué; si ya existe una con el mismo nombre,
   * se avisa (no se impide: puede ser a propósito).
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import { router } from '@inertiajs/svelte';
  import { AlertTriangle, Info } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { formatDate } from '@/utils/formatters';

  interface Compat {
    copiable: boolean;
    motivo: string | null;
    nombre_repetido: boolean;
  }

  interface Destino {
    id_curso: number;
    etiqueta: string;
    cod_curso: string;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    actividades: Record<number, Compat>;
  }

  interface Props {
    isOpen?: boolean;
    idCurso: number;
    actividades: Actividad[];
    /** Actividades marcadas al abrir (la fila desde la que se abrió). */
    preseleccion?: number[];
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    idCurso,
    actividades,
    preseleccion = [],
    onClose = () => {},
  }: Props = $props();

  let cargando = $state(false);
  let enviando = $state(false);
  let error = $state('');
  let destinos = $state<Destino[]>([]);
  let destinosSel = $state<number[]>([]);
  let actividadesSel = $state<number[]>([]);
  let fechas = $state<Record<number, string>>({});
  let copiarRubrica = $state(true);
  let copiarEnunciado = $state(true);

  // Al abrir: se piden las opciones y se arranca con la fila elegida y, si hay
  // un solo curso hermano (el caso de los grupos A y B), ya marcado.
  $effect(() => {
    if (!isOpen) return;
    error = '';
    enviando = false;
    actividadesSel = [...preseleccion];
    fechas = Object.fromEntries(actividades.map((a) => [a.id_actividad, (a.fecha_limite ?? '').slice(0, 10)]));
    cargarOpciones();
  });

  async function cargarOpciones() {
    cargando = true;
    try {
      const res = await fetch(`/docente/cursos/${idCurso}/actividades/copiar/opciones`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(String(res.status));
      destinos = ((await res.json()).destinos ?? []) as Destino[];
      destinosSel = destinos.length === 1 ? [destinos[0].id_curso] : [];
    } catch {
      error = 'No se pudieron cargar los cursos de destino.';
      destinos = [];
    } finally {
      cargando = false;
    }
  }

  const destinosElegidos = $derived(destinos.filter((d) => destinosSel.includes(d.id_curso)));

  /** Avisos de una actividad frente a los cursos elegidos. */
  function avisos(a: Actividad) {
    const bloqueos: string[] = [];
    const repetidos: string[] = [];
    const fueraDePeriodo: string[] = [];
    const fecha = fechas[a.id_actividad];
    for (const d of destinosElegidos) {
      const c = d.actividades[a.id_actividad];
      if (c && !c.copiable) bloqueos.push(`${d.etiqueta}: ${c.motivo}`);
      if (c?.copiable && c.nombre_repetido) repetidos.push(d.etiqueta);
      if (fecha && d.fecha_inicio && d.fecha_fin && (fecha < d.fecha_inicio || fecha > d.fecha_fin)) {
        fueraDePeriodo.push(`${d.etiqueta} (${formatDate(d.fecha_inicio)} a ${formatDate(d.fecha_fin)})`);
      }
    }
    // Se puede elegir si cabe en al menos uno de los cursos elegidos; en los
    // demás se omite y el servidor lo informa.
    const copiable = destinosElegidos.length === 0 || bloqueos.length < destinosElegidos.length;
    return { bloqueos, repetidos, fueraDePeriodo, copiable };
  }

  function alternar(lista: number[], id: number): number[] {
    return lista.includes(id) ? lista.filter((x) => x !== id) : [...lista, id];
  }

  const totalElegidas = $derived(actividadesSel.length);
  const etiquetaEnviar = $derived(
    `Copiar ${totalElegidas} ${totalElegidas === 1 ? 'actividad' : 'actividades'}` +
      (destinosSel.length > 1 ? ` a ${destinosSel.length} cursos` : ''),
  );

  function enviar() {
    if (totalElegidas === 0 || destinosSel.length === 0) return;
    enviando = true;
    error = '';
    router.post(
      `/docente/cursos/${idCurso}/actividades/copiar`,
      {
        actividades: actividadesSel,
        destinos: destinosSel,
        copiar_rubrica: copiarRubrica,
        copiar_enunciado: copiarEnunciado,
        fechas: Object.fromEntries(actividadesSel.map((id) => [id, fechas[id] || null])),
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          enviando = false;
          onClose();
        },
        onError: (errores) => {
          enviando = false;
          error = Object.values(errores)[0] ?? 'No se pudo copiar.';
        },
      },
    );
  }
</script>

<FormModal
  bind:isOpen
  title="Copiar a otro curso"
  {onClose}
  onSubmit={enviar}
  submitLabel={etiquetaEnviar}
  isLoading={enviando}
  submitDisabled={cargando || totalElegidas === 0 || destinosSel.length === 0}
>
  <div class="flex flex-col gap-5">
    {#if cargando}
      <p class="m-0 text-[13px] text-[#5A5E6E]">Buscando tus otros grupos de esta asignatura…</p>
    {:else if destinos.length === 0}
      <p class="m-0 flex items-start gap-2 rounded-lg bg-[#F5F1EA] px-3.5 py-3 text-[13px] text-[#3B3F4C]">
        <Info size={16} class="mt-px shrink-0 text-[#002F6C]" aria-hidden="true" />
        No tienes otros grupos de esta asignatura en el mismo año y semestre donde seas titular.
      </p>
    {:else}
      <!-- Destinos -->
      <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
        <legend class="mb-2 text-[13px] font-semibold text-[#3B3F4C]">Cursos de destino</legend>
        {#each destinos as d (d.id_curso)}
          <label
            class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border px-3.5 py-2.5 {destinosSel.includes(
              d.id_curso,
            )
              ? 'border-[#002F6C] bg-[#F6F9FC]'
              : 'border-[#E5E7EB]'}"
          >
            <input
              type="checkbox"
              class="h-[18px] w-[18px] accent-[#002F6C]"
              checked={destinosSel.includes(d.id_curso)}
              onchange={() => (destinosSel = alternar(destinosSel, d.id_curso))}
            />
            <span class="text-[14px] font-semibold text-[#1A1A24]">{d.etiqueta}</span>
            <span class="ml-auto font-mono text-[11px] text-[#5A5E6E]">{d.cod_curso}</span>
          </label>
        {/each}
      </fieldset>

      <!-- Actividades -->
      <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
        <legend class="mb-2 flex w-full items-center text-[13px] font-semibold text-[#3B3F4C]">
          Actividades
          <button
            type="button"
            class="ml-auto text-[12px] font-medium text-[#002F6C] hover:underline"
            onclick={() =>
              (actividadesSel =
                actividadesSel.length === actividades.length ? [] : actividades.map((a) => a.id_actividad))}
          >
            {actividadesSel.length === actividades.length ? 'Quitar todas' : 'Elegir todas'}
          </button>
        </legend>
        <ul class="m-0 flex max-h-[42vh] list-none flex-col gap-2 overflow-y-auto p-0">
          {#each actividades as a (a.id_actividad)}
            {@const av = avisos(a)}
            {@const elegida = actividadesSel.includes(a.id_actividad)}
            <li class="rounded-lg border px-3.5 py-2.5 {elegida ? 'border-[#002F6C]' : 'border-[#E5E7EB]'}">
              <div class="flex flex-wrap items-center gap-3">
                <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 {av.copiable ? '' : 'opacity-60'}">
                  <input
                    type="checkbox"
                    class="h-[18px] w-[18px] shrink-0 accent-[#002F6C]"
                    checked={elegida}
                    disabled={!av.copiable}
                    onchange={() => (actividadesSel = alternar(actividadesSel, a.id_actividad))}
                  />
                  <span class="min-w-0 text-[13.5px] font-medium text-[#1A1A24]">{a.nombre}</span>
                </label>
                {#if elegida}
                  <label class="flex items-center gap-2 text-[12px] text-[#5A5E6E]">
                    Fecha límite
                    <input
                      type="date"
                      class="h-9 rounded-md border border-[#D6D9E0] px-2 text-[13px] text-[#1A1A24]"
                      bind:value={fechas[a.id_actividad]}
                    />
                  </label>
                {/if}
              </div>
              {#each av.bloqueos as b (b)}
                <p class="m-0 mt-1.5 text-[12px] text-[#8A1538]">No se copia a {b}</p>
              {/each}
              {#if av.repetidos.length > 0}
                <p class="m-0 mt-1.5 flex items-center gap-1.5 text-[12px] text-[#92400E]">
                  <AlertTriangle size={13} aria-hidden="true" />
                  Ya existe una actividad con este nombre en {av.repetidos.join(', ')}: se creará otra.
                </p>
              {/if}
              {#if elegida && av.fueraDePeriodo.length > 0}
                <p class="m-0 mt-1.5 flex items-center gap-1.5 text-[12px] text-[#92400E]">
                  <AlertTriangle size={13} aria-hidden="true" />
                  La fecha queda fuera del período de {av.fueraDePeriodo.join(', ')}. Revisa el año.
                </p>
              {/if}
            </li>
          {/each}
        </ul>
      </fieldset>

      <!-- Qué se copia -->
      <fieldset class="m-0 flex flex-col gap-1.5 border-0 p-0">
        <legend class="mb-2 text-[13px] font-semibold text-[#3B3F4C]">Qué se copia</legend>
        <label class="flex min-h-[32px] items-center gap-2.5 text-[13.5px] text-[#1A1A24]">
          <input type="checkbox" class="h-[18px] w-[18px] accent-[#002F6C]" checked disabled />
          Configuración: tipo, ponderación, exigencia, entrega y si es grupal
        </label>
        <label class="flex min-h-[32px] items-center gap-2.5 text-[13.5px] text-[#1A1A24]">
          <input type="checkbox" class="h-[18px] w-[18px] accent-[#002F6C]" bind:checked={copiarRubrica} />
          Rúbrica (copia independiente en cada curso)
        </label>
        <label class="flex min-h-[32px] items-center gap-2.5 text-[13.5px] text-[#1A1A24]">
          <input type="checkbox" class="h-[18px] w-[18px] accent-[#002F6C]" bind:checked={copiarEnunciado} />
          Enunciado (el mismo archivo; reemplazarlo en un curso no cambia el otro)
        </label>
        <p class="m-0 mt-1 text-[12px] text-[#5A5E6E]">
          Las copias llegan ocultas (Planificada). No se copian entregas, notas, grupos ni mensajes.
        </p>
      </fieldset>
    {/if}

    {#if error}
      <p class="m-0 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3.5 py-2.5 text-[13px] text-[#B91C1C]" role="alert">
        {error}
      </p>
    {/if}
  </div>
</FormModal>
