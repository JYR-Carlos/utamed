<script lang="ts">
  import { formatFechaHora, formatNota } from '@/utils/formatters';
  /**
   * EstudiantesTabla — estudiantes asignados a una actividad INDIVIDUAL.
   *
   * Diseño del mockup «Hoja de evaluación» (auditoría UI docente 2026-10-19):
   *  - Resumen arriba (entregaron / por calificar / calificadas / sin entrega) y
   *    filtros por estado: lo pendiente se encuentra sin recorrer la lista.
   *  - Cada fila dice su estado con chip, ícono y texto (hallazgo #1): por
   *    calificar, fuera de plazo, calificada, sin entrega o no entregó. Antes
   *    «Sin calificar» y «Sin entrega» iban en gris claro (1.47:1–2.6:1).
   *  - La acción principal está en la fila (hallazgo #3): «Calificar» abre la
   *    rúbrica sobre la entrega vigente; Entregas, Agenda y holgura van al «⋯».
   *  - Bajo 768 px la tabla pasa a tarjetas con botón de 44 px (hallazgo #2).
   *  - Formativas: la entrega se completa sólo con feedback (decisión del
   *    2026-10-19): «Revisada» si lo tiene, «Por revisar» si no.
   *
   * Mismas reglas de antes, sin excepción:
   *  - Las décimas sólo se tocan si hay nota puesta (sin nota no hay qué ajustar).
   *  - La holgura personal sólo la ve y edita el titular.
   *  - «Entregas» sólo existe si la actividad pide archivo.
   *
   * Todo el estado vive en el padre (Index.svelte); lo local es el filtro, la
   * búsqueda y qué fila tiene abierto el editor de holgura.
   */
  import {
    Check,
    Clock3,
    Ellipsis,
    Minus,
    Plus,
    Search,
    TriangleAlert,
    X,
  } from 'lucide-svelte';
  import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
  } from '@/components/ui/dropdown-menu';

  type IntegranteData = {
    id_estudiante: number;
    nombre_completo: string;
    id_asignado_actividad?: number;
    nota_individual?: number | null;
    diferencia_decimas?: number;
  };

  type GrupoData = {
    grupo: number;
    etiqueta: string;
    /** Resumen de la entrega vigente del grupo; null si aún no entregó. */
    entrega?: { id_agenda?: number; fecha: string; fuera_de_plazo: boolean; cantidad: number } | null;
    nota: number | null;
    estado_actividad_asignada: string | null;
    nro_dias_adicionales_para_bloqueo_personal: number;
    /** Formativas: el docente ya le dejó feedback (entrega revisada). */
    con_feedback?: boolean;
    integrantes: IntegranteData[];
  };

  interface Props {
    grupos: GrupoData[];
    esTitular: boolean;
    traeArchivo: boolean;
    /** Sumativa: se cierra con nota. Formativa: se cierra con feedback. */
    esSumativa?: boolean;
    /** Hay rúbrica con la que calificar (sumativas). */
    puedeCalificar?: boolean;
    /** Estado del estudiante que se está guardando (id_asignado_actividad) o null. */
    savingDecimas: number | null;
    getEstadoColor: (estado: string) => string;
    formatDecimas: (d: number | undefined) => string;
    onAjustarDecimas: (grupoId: number, integrante: IntegranteData, delta: number) => void;
    onVerEntregas: (grupo: GrupoData) => void;
    onVerAgenda: (grupo: GrupoData) => void;
    /** Abre la rúbrica sobre la entrega vigente (o sin entrega si no la hay). */
    onCalificar?: (grupo: GrupoData, idAgenda: number | null) => void;
    /** Mensajes de cada grupo que el docente aún no ha visto (marcan «Agenda»). */
    noLeidosDe?: (grupoId: number) => number;
    onActualizarHolguraPersonal: (grupoId: number, dias: number) => void;
  }

  let {
    grupos,
    esTitular,
    traeArchivo,
    esSumativa = true,
    puedeCalificar = false,
    savingDecimas,
    formatDecimas,
    onAjustarDecimas,
    onVerEntregas,
    onVerAgenda,
    onCalificar,
    noLeidosDe = () => 0,
    onActualizarHolguraPersonal,
  }: Props = $props();

  // ─── Estado de cada estudiante ────────────────────────────────────────────
  type Estado = 'por_calificar' | 'fuera_de_plazo' | 'calificada' | 'sin_entrega' | 'no_entrego';

  function estadoDe(g: GrupoData): Estado {
    const lista = esSumativa ? g.nota !== null : !!g.con_feedback;
    if (lista) return 'calificada';
    if (!traeArchivo || g.entrega) {
      return g.entrega?.fuera_de_plazo ? 'fuera_de_plazo' : 'por_calificar';
    }
    return g.estado_actividad_asignada?.toUpperCase() === 'CERRADA' ? 'no_entrego' : 'sin_entrega';
  }

  const CHIP: Record<Estado, { texto: string; clase: string }> = $derived({
    por_calificar: {
      texto: esSumativa ? 'Por calificar' : 'Por revisar',
      clase: 'bg-amber-100 text-amber-800 border-transparent',
    },
    fuera_de_plazo: { texto: 'Fuera de plazo', clase: 'bg-amber-100 text-amber-800 border-yellow-400' },
    calificada: {
      texto: esSumativa ? 'Calificada' : 'Revisada',
      clase: 'bg-emerald-50 text-emerald-700 border-transparent',
    },
    sin_entrega: { texto: 'Sin entrega', clase: 'bg-slate-100 text-slate-600 border-line-control' },
    no_entrego: { texto: 'No entregó', clase: 'bg-red-100 text-red-700 border-transparent' },
  });

  // ─── Resumen, filtro y búsqueda ───────────────────────────────────────────
  type Filtro = 'todos' | 'pendientes' | 'calificadas' | 'sin_entrega';
  let filtro = $state<Filtro>('todos');
  let busqueda = $state('');

  const estados = $derived(new Map(grupos.map((g) => [g.grupo, estadoDe(g)])));
  const cuenta = $derived.by(() => {
    const c = { entregaron: 0, pendientes: 0, calificadas: 0, sin_entrega: 0 };
    for (const g of grupos) {
      const e = estados.get(g.grupo)!;
      if (g.entrega) c.entregaron++;
      if (e === 'por_calificar' || e === 'fuera_de_plazo') c.pendientes++;
      else if (e === 'calificada') c.calificadas++;
      else c.sin_entrega++;
    }
    return c;
  });
  const promedio = $derived.by(() => {
    const notas = grupos.map((g) => g.nota).filter((n): n is number => n !== null).map(Number);
    return notas.length ? notas.reduce((a, n) => a + n, 0) / notas.length : null;
  });

  /** Primero lo pendiente: por calificar, fuera de plazo, calificadas y al final sin entrega. */
  const PRIORIDAD: Record<Estado, number> = {
    por_calificar: 0,
    fuera_de_plazo: 1,
    calificada: 2,
    sin_entrega: 3,
    no_entrego: 3,
  };

  const gruposVisibles = $derived.by(() => {
    const q = busqueda.trim().toLowerCase();
    return [...grupos]
      .filter((g) => {
        const e = estados.get(g.grupo)!;
        if (filtro === 'pendientes' && !(e === 'por_calificar' || e === 'fuera_de_plazo')) return false;
        if (filtro === 'calificadas' && e !== 'calificada') return false;
        if (filtro === 'sin_entrega' && !(e === 'sin_entrega' || e === 'no_entrego')) return false;
        return !q || (g.integrantes[0]?.nombre_completo ?? '').toLowerCase().includes(q);
      })
      .sort((a, b) => {
        const d = PRIORIDAD[estados.get(a.grupo)!] - PRIORIDAD[estados.get(b.grupo)!];
        if (d !== 0) return d;
        return (a.integrantes[0]?.nombre_completo ?? '').localeCompare(
          b.integrantes[0]?.nombre_completo ?? '',
          'es',
          { sensitivity: 'base' },
        );
      });
  });

  const etiquetaPendientes = $derived(esSumativa ? 'Por calificar' : 'Por revisar');
  const etiquetaListas = $derived(esSumativa ? 'Calificadas' : 'Revisadas');

  // ─── Holgura personal ─────────────────────────────────────────────────────
  /** Grupo (estudiante) cuyo editor de holgura está abierto, o null. */
  let editandoHolgura = $state<number | null>(null);
  let holguraTemp = $state(0);

  function iniciarEdicionHolgura(grupo: GrupoData) {
    holguraTemp = grupo.nro_dias_adicionales_para_bloqueo_personal;
    editandoHolgura = grupo.grupo;
  }

  function guardarHolgura(grupoId: number) {
    onActualizarHolguraPersonal(grupoId, holguraTemp);
    editandoHolgura = null;
  }

  /** Segunda línea de la columna Entrega. */
  function detalleEntrega(g: GrupoData): string {
    const holgura = g.nro_dias_adicionales_para_bloqueo_personal;
    const partes: string[] = [];
    if (g.entrega) partes.push(g.entrega.cantidad === 1 ? '1 envío' : `${g.entrega.cantidad} envíos`);
    else partes.push(estadoDe(g) === 'no_entrego' ? 'Plazo cerrado' : 'Aún no envía');
    if (holgura > 0) partes.push(`Holgura personal +${holgura} día${holgura === 1 ? '' : 's'}`);
    return partes.join(' · ');
  }

  // ─── Acción principal de la fila ──────────────────────────────────────────
  type Accion = { texto: string; primaria: boolean; hacer: () => void };

  function accionDe(g: GrupoData): Accion {
    const e = estados.get(g.grupo)!;
    if (e === 'por_calificar' || e === 'fuera_de_plazo') {
      if (esSumativa && puedeCalificar && onCalificar) {
        return { texto: 'Calificar', primaria: true, hacer: () => onCalificar(g, g.entrega?.id_agenda ?? null) };
      }
      // Formativa: se completa con feedback, que se escribe en la agenda.
      return { texto: esSumativa ? 'Ver entregas' : 'Revisar', primaria: true, hacer: () => (esSumativa && traeArchivo ? onVerEntregas(g) : onVerAgenda(g)) };
    }
    if (e === 'calificada') {
      return { texto: 'Ver evaluación', primaria: false, hacer: () => (traeArchivo ? onVerEntregas(g) : onVerAgenda(g)) };
    }
    return { texto: 'Enviar mensaje', primaria: false, hacer: () => onVerAgenda(g) };
  }

  const BTN_PRIMARIO =
    'inline-flex items-center justify-center rounded-lg bg-uta-blue px-4 text-sm font-semibold text-white transition-colors hover:bg-uta-blue-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uta-blue focus-visible:ring-offset-2';
  const BTN_SECUNDARIO =
    'inline-flex items-center justify-center rounded-lg border border-line-control bg-white px-4 text-sm font-semibold text-uta-blue transition-colors hover:bg-uta-blue-light focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uta-blue focus-visible:ring-offset-2';
  const BTN_MAS =
    'relative inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-line-control bg-white text-ink transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uta-blue';
  const BTN_DECIMA =
    'size-6 flex items-center justify-center rounded border border-line-control text-slate-600 hover:bg-slate-100 transition disabled:opacity-30';
  const TH = 'px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600';
</script>

{#snippet chip(g: GrupoData)}
  {@const e = estados.get(g.grupo)!}
  <span
    class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border py-[3px] pl-2 pr-2.5 text-xs font-semibold {CHIP[e].clase}"
  >
    {#if e === 'por_calificar'}
      <Clock3 size={12} strokeWidth={2.5} aria-hidden="true" />
    {:else if e === 'fuera_de_plazo'}
      <TriangleAlert size={12} strokeWidth={2.5} aria-hidden="true" />
    {:else if e === 'calificada'}
      <Check size={12} strokeWidth={2.5} aria-hidden="true" />
    {:else if e === 'no_entrego'}
      <X size={12} strokeWidth={2.5} aria-hidden="true" />
    {:else}
      <Minus size={12} strokeWidth={2.5} aria-hidden="true" />
    {/if}
    {CHIP[e].texto}
  </span>
{/snippet}

{#snippet nota(g: GrupoData)}
  {@const integrante = g.integrantes[0]}
  {@const decimas = integrante?.diferencia_decimas ?? 0}
  {#if g.nota === null}
    <span class="text-lg font-bold text-slate-500" aria-hidden="true">—</span>
    <span class="sr-only">Sin nota</span>
  {:else}
    {@const final = integrante?.nota_individual ?? Number(g.nota)}
    <div class="flex flex-col gap-1">
      <span class="text-lg font-bold leading-6 tabular-nums {final >= 4 ? 'text-emerald-700' : 'text-red-700'}">
        {formatNota(final)}
      </span>
      {#if esTitular && integrante}
        <!-- Décimas: sólo con nota puesta; la nota de arriba ya es la final. -->
        <div class="flex items-center gap-1" aria-label="Ajuste de décimas">
          <button
            onclick={() => onAjustarDecimas(g.grupo, integrante, -0.1)}
            disabled={savingDecimas === integrante.id_asignado_actividad || decimas <= -9.9}
            class={BTN_DECIMA}
            title="Restar una décima"
            aria-label="Restar una décima"
          >
            <Minus class="size-3" />
          </button>
          <span class="w-10 text-center font-mono text-xs font-semibold {decimas === 0 ? 'text-slate-600' : decimas > 0 ? 'text-emerald-700' : 'text-red-700'}">
            {formatDecimas(decimas)}
          </span>
          <button
            onclick={() => onAjustarDecimas(g.grupo, integrante, 0.1)}
            disabled={savingDecimas === integrante.id_asignado_actividad || decimas >= 9.9}
            class={BTN_DECIMA}
            title="Sumar una décima"
            aria-label="Sumar una décima"
          >
            <Plus class="size-3" />
          </button>
        </div>
      {:else if decimas !== 0}
        <span class="font-mono text-xs text-slate-600">{formatDecimas(decimas)} décimas</span>
      {/if}
    </div>
  {/if}
{/snippet}

{#snippet holgura(g: GrupoData)}
  {#if editandoHolgura === g.grupo}
    <div class="mt-1.5 flex items-center gap-1.5">
      <input
        type="number"
        min="0"
        bind:value={holguraTemp}
        aria-label="Días de holgura personal"
        class="h-9 w-16 rounded-lg border border-line-control bg-white px-2 text-sm focus:border-uta-blue focus:outline-none focus:ring-2 focus:ring-uta-blue"
      />
      <span class="text-xs text-slate-600">días</span>
      <button onclick={() => guardarHolgura(g.grupo)} class="{BTN_PRIMARIO} h-9 px-3" title="Guardar holgura">
        Guardar
      </button>
      <button onclick={() => (editandoHolgura = null)} class="{BTN_SECUNDARIO} h-9 px-3">Cancelar</button>
    </div>
  {/if}
{/snippet}

{#snippet menuMas(g: GrupoData)}
  {@const nuevos = noLeidosDe(g.grupo)}
  <DropdownMenu>
    <DropdownMenuTrigger class={BTN_MAS} aria-label="Más acciones para {g.integrantes[0]?.nombre_completo ?? 'el estudiante'}">
      <Ellipsis size={18} />
      {#if nuevos > 0}
        <span
          class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-uta-blue px-1 text-center text-[11px] font-bold leading-5 text-white"
          title="Mensajes nuevos"
        >{nuevos}</span>
      {/if}
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-56 rounded-xl p-1.5">
      {#if traeArchivo}
        <DropdownMenuItem onSelect={() => onVerEntregas(g)} class="min-h-10 cursor-pointer rounded-lg text-sm">
          Ver entregas{g.entrega ? ` (${g.entrega.cantidad})` : ''}
        </DropdownMenuItem>
      {/if}
      <DropdownMenuItem onSelect={() => onVerAgenda(g)} class="min-h-10 cursor-pointer rounded-lg text-sm">
        Agenda{nuevos > 0 ? ` · ${nuevos} ${nuevos === 1 ? 'mensaje nuevo' : 'mensajes nuevos'}` : ''}
      </DropdownMenuItem>
      {#if esTitular}
        <DropdownMenuItem onSelect={() => iniciarEdicionHolgura(g)} class="min-h-10 cursor-pointer rounded-lg text-sm">
          Holgura personal{g.nro_dias_adicionales_para_bloqueo_personal > 0
            ? ` (+${g.nro_dias_adicionales_para_bloqueo_personal} días)`
            : ''}
        </DropdownMenuItem>
      {/if}
    </DropdownMenuContent>
  </DropdownMenu>
{/snippet}

<div class="flex flex-col gap-4">
  <!-- Resumen -->
  <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white px-4 py-3.5">
      <span class="text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600">Entregaron</span>
      <span class="text-[28px] font-bold leading-[34px] tabular-nums text-ink">{cuenta.entregaron}</span>
      <span class="text-[13px] text-slate-600">de {grupos.length} estudiantes</span>
    </div>
    <div
      class="flex flex-col gap-1 rounded-xl border px-4 py-3.5 {cuenta.pendientes > 0
        ? 'border-yellow-400 bg-amber-100'
        : 'border-slate-200 bg-white'}"
    >
      <span class="text-[11px] font-bold uppercase tracking-[0.06em] {cuenta.pendientes > 0 ? 'text-amber-800' : 'text-slate-600'}">{etiquetaPendientes}</span>
      <span class="text-[28px] font-bold leading-[34px] tabular-nums {cuenta.pendientes > 0 ? 'text-amber-800' : 'text-ink'}">{cuenta.pendientes}</span>
      <span class="text-[13px] {cuenta.pendientes > 0 ? 'text-amber-800' : 'text-slate-600'}">
        {esSumativa ? 'entregas vigentes sin nota' : 'entregas sin feedback'}
      </span>
    </div>
    <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white px-4 py-3.5">
      <span class="text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600">{etiquetaListas}</span>
      <span class="text-[28px] font-bold leading-[34px] tabular-nums text-ink">{cuenta.calificadas}</span>
      <span class="text-[13px] text-slate-600">
        {promedio !== null ? `promedio ${formatNota(promedio)}` : esSumativa ? 'aún sin notas' : 'con feedback'}
      </span>
    </div>
    <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white px-4 py-3.5">
      <span class="text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600">Sin entrega</span>
      <span class="text-[28px] font-bold leading-[34px] tabular-nums text-ink">{cuenta.sin_entrega}</span>
      <span class="text-[13px] text-slate-600">{traeArchivo ? 'no han enviado archivo' : 'actividad presencial'}</span>
    </div>
  </div>

  <!-- Filtros -->
  <div class="flex flex-wrap items-center gap-3">
    <div class="flex flex-wrap gap-2 rounded-[10px] md:gap-1 md:bg-slate-100 md:p-1" role="group" aria-label="Filtrar por estado">
      {#each [['todos', 'Todos', grupos.length], ['pendientes', etiquetaPendientes, cuenta.pendientes], ['calificadas', etiquetaListas, cuenta.calificadas], ['sin_entrega', 'Sin entrega', cuenta.sin_entrega]] as [valor, texto, n] (valor)}
        {@const activo = filtro === valor}
        <button
          type="button"
          aria-pressed={activo}
          onclick={() => (filtro = valor as Filtro)}
          class="inline-flex h-11 items-center gap-2 rounded-full border px-3.5 text-sm transition-colors md:h-9 md:rounded-lg {activo
            ? 'border-line-control bg-white font-semibold text-uta-blue md:border-line-control'
            : 'border-line-control bg-white font-medium text-slate-600 hover:text-ink md:border-transparent md:bg-transparent'}"
        >
          {texto}
          <span class="text-xs font-semibold">{n}</span>
        </button>
      {/each}
    </div>
    <label class="relative w-full md:ml-auto md:w-[280px]">
      <span class="sr-only">Buscar por nombre</span>
      <Search size={16} class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-2" />
      <input
        type="text"
        bind:value={busqueda}
        placeholder="Buscar por nombre"
        class="h-11 w-full rounded-lg border border-line-control bg-white pl-9 pr-3 text-sm focus:border-uta-blue focus:outline-none focus:ring-2 focus:ring-uta-blue md:h-10"
      />
    </label>
  </div>

  {#if grupos.length === 0}
    <div class="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center text-sm font-medium text-slate-600">
      No hay estudiantes asignados a esta actividad
    </div>
  {:else}
    <!-- Desktop / tablet: tabla -->
    <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
      <table class="w-full border-collapse text-sm">
        <caption class="sr-only">Estudiantes asignados a la actividad</caption>
        <thead class="bg-slate-50">
          <tr>
            <th class="{TH} w-12">#</th>
            <th class={TH}>Estudiante</th>
            {#if traeArchivo}
              <th class="{TH} w-[200px]">Entrega</th>
            {/if}
            <th class="{TH} w-[160px]">Estado</th>
            <th class="{TH} w-[130px]">Nota</th>
            <th class="{TH} w-[230px] text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          {#each gruposVisibles as g, i (g.grupo)}
            {@const accion = accionDe(g)}
            <tr class="border-t border-slate-200 align-middle transition-colors hover:bg-slate-50">
              <td class="px-4 py-3 font-mono text-xs tabular-nums text-muted-foreground">{String(i + 1).padStart(2, '0')}</td>
              <td class="px-4 py-3">
                <span class="block font-semibold text-ink">{g.integrantes[0]?.nombre_completo ?? 'Estudiante'}</span>
                {#if !traeArchivo && g.nro_dias_adicionales_para_bloqueo_personal > 0}
                  <span class="block text-xs text-muted-foreground">{detalleEntrega(g)}</span>
                {/if}
                {@render holgura(g)}
              </td>
              {#if traeArchivo}
                <td class="px-4 py-3">
                  <span class="block text-[13px] font-medium {g.entrega ? 'text-ink' : 'text-slate-600'}">
                    {g.entrega ? formatFechaHora(g.entrega.fecha) : 'Sin envío'}
                  </span>
                  <span class="block text-xs text-muted-foreground">{detalleEntrega(g)}</span>
                </td>
              {/if}
              <td class="px-4 py-3">{@render chip(g)}</td>
              <td class="px-4 py-3">{@render nota(g)}</td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <button onclick={accion.hacer} class="{accion.primaria ? BTN_PRIMARIO : BTN_SECUNDARIO} h-10 whitespace-nowrap">
                    {accion.texto}
                  </button>
                  {@render menuMas(g)}
                </div>
              </td>
            </tr>
          {:else}
            <tr class="border-t border-slate-200">
              <td colspan={traeArchivo ? 6 : 5} class="px-4 py-8 text-center text-sm text-slate-600">
                Ningún estudiante coincide con el filtro.
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
      <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-[13px] text-slate-600">
        Mostrando {gruposVisibles.length} de {grupos.length} estudiantes · ordenados por prioridad ({etiquetaPendientes.toLowerCase()}, fuera de plazo, {etiquetaListas.toLowerCase()}, sin entrega)
      </div>
    </div>

    <!-- Móvil: tarjetas -->
    <ul class="flex flex-col gap-3 md:hidden" aria-label="Estudiantes asignados a la actividad">
      {#each gruposVisibles as g (g.grupo)}
        {@const accion = accionDe(g)}
        <li class="flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-3.5">
          <div class="flex items-start gap-2">
            <span class="flex-1 text-[15px] font-semibold leading-5 text-ink">{g.integrantes[0]?.nombre_completo ?? 'Estudiante'}</span>
            {@render chip(g)}
          </div>
          <span class="text-[13px] text-slate-600">
            {#if traeArchivo}{g.entrega ? formatFechaHora(g.entrega.fecha) : 'Sin envío'} · {/if}{detalleEntrega(g)}
          </span>
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-muted-foreground">Nota</span>
            {@render nota(g)}
          </div>
          {@render holgura(g)}
          <div class="flex items-center gap-2">
            <button onclick={accion.hacer} class="{accion.primaria ? BTN_PRIMARIO : BTN_SECUNDARIO} h-11 flex-1">
              {accion.texto}
            </button>
            <span class="[&>button]:size-11">{@render menuMas(g)}</span>
          </div>
        </li>
      {:else}
        <li class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-600">
          Ningún estudiante coincide con el filtro.
        </li>
      {/each}
    </ul>
  {/if}
</div>
