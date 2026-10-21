<script lang="ts">
  /**
   * ActividadesTabla — vista Lista de las actividades de un curso
   * (/docente/cursos/{curso}/actividades).
   *
   * Diseño del mockup «Actividades · Lista» (auditoría UI docente 2026-10-19):
   *  - La actividad ocupa el ancho flexible y se lee completa en hasta tres
   *    líneas; la fecha va en una línea con su plazo relativo («mañana»,
   *    «en 2 días») y el avance se ve como barra + texto (hallazgo #4).
   *  - Una sola acción visible por fila («Calificar» si hay pendientes); Editar,
   *    enunciado, copiar y eliminar pasan al menú «⋯».
   *  - Formativas: el avance cuenta feedback, no notas (se completan sólo con
   *    feedback, decisión del 2026-10-19; hallazgo #11).
   *  - Bajo 768 px, tarjetas con botones de 44 px (hallazgo #2).
   *
   * Se mantienen las ideas de la lámina original:
   *  1. **Estado ≠ visibilidad.** El estado lo deriva el sistema
   *     (`utils/actividadEstado`) y se lee; la visibilidad la pone el docente y
   *     por eso vive como interruptor en la propia fila.
   *  2. **Micro-feedback optimista.** El interruptor se mueve al instante; la
   *     fila queda inerte hasta que el servidor confirma. Si falla, vuelve a su
   *     valor real y el reintento vive en la fila — nunca un modal ni una recarga.
   *  3. **Ausencia, no deshabilitado.** Sin permiso de editar el interruptor se
   *     vuelve etiqueta; sin ninguna acción delegada la columna «Acciones»
   *     desaparece completa, cabecera incluida.
   */
  import type { Snippet } from 'svelte';
  import { Link } from '@inertiajs/svelte';
  import { AlertCircle, Check, Ellipsis, Eye, EyeOff, Loader2 } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
  } from '@/components/ui/dropdown-menu';
  import { fechaCorta, plazoRelativo } from '@/utils/plazo';
  import {
    estadoActividad,
    etiquetaUnidad,
    nombreComponente,
    siglaComponente,
    ETIQUETA_ESTADO,
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
    /** «Copiar a otro curso»; sólo lo pasa la página cuando el docente es titular. */
    onCopiar?: (a: Actividad) => void;
    /** Pie de la tabla (totales y leyenda), dentro del mismo marco. */
    pie?: Snippet;
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
    onCopiar,
    pie,
  }: Props = $props();

  /** Sin ninguna acción delegada la columna entera no se dibuja (lámina f, caso 3). */
  const hayAcciones = $derived(canEdit || canDelete);

  const ahora = new Date();

  /** Valor que debe pintar el interruptor: el optimista manda mientras se guarda. */
  function visibleEnPantalla(a: Actividad): boolean {
    const t = toggles[a.id_actividad];
    return t?.fase === 'guardando' ? t.optimista : a.visible;
  }

  type Avance = { listas: number; pendientes: number; total: number; texto: string; urgente: boolean };

  /** Barra y texto de avance: sumativas cuentan notas; formativas, feedback. */
  function avanceDe(a: Actividad, estado: string): Avance {
    const sumativa = a.tipo_actividad === 'SUMATIVA';
    const total = a.total_grupos ?? 0;
    const listas = (sumativa ? a.calificados : a.con_feedback) ?? 0;
    const pendientes = (sumativa ? a.por_calificar : a.por_revisar) ?? 0;
    const partes: string[] = [];
    if ((a.grupos_con_entrega ?? 0) === 0 && listas === 0) {
      partes.push(total === 0 ? 'Sin grupos asignados' : 'Sin entregas aún');
    } else {
      partes.push(sumativa ? `${listas} calificadas` : `${listas} con feedback`);
      if (pendientes > 0) partes.push(sumativa ? `${pendientes} por calificar` : `${pendientes} por revisar`);
      if (sumativa && a.promedio_nota != null) partes.push(`prom. ${String(a.promedio_nota).replace('.', ',')}`);
    }
    const mensajes = a.mensajes_pendientes ?? 0;
    if (mensajes > 0) partes.push(`${mensajes} ${mensajes === 1 ? 'mensaje' : 'mensajes'} por responder`);
    return { listas, pendientes, total, texto: partes.join(' · '), urgente: estado === 'activa' && pendientes > 0 };
  }

  /** Acción principal: calificar/revisar si hay pendientes; si no, ver. */
  function accionDe(a: Actividad, av: Avance): { texto: string; primaria: boolean } {
    const sumativa = a.tipo_actividad === 'SUMATIVA';
    if (av.pendientes > 0) return { texto: sumativa ? 'Calificar' : 'Revisar', primaria: av.urgente };
    if (av.listas > 0) return { texto: sumativa ? 'Ver notas' : 'Ver revisión', primaria: false };
    return { texto: 'Ver', primaria: false };
  }

  /** «TAL · Unidad 1 · Sumativa 33 %»: el nombre largo de la unidad va en el title. */
  function subtitulo(a: Actividad): string {
    const num = a.unidad?.num_unidad;
    const unidad = num != null ? `Unidad ${num}` : (a.unidad?.nombre ?? null);
    const tipo =
      a.tipo_actividad === 'SUMATIVA'
        ? `Sumativa${Number(a.ponderacion) > 0 ? ` ${Number(a.ponderacion)} %` : ''}`
        : 'Formativa';
    return [siglaComponente(a), unidad, tipo].filter(Boolean).join(' · ');
  }

  const BTN_PRIMARIO =
    'inline-flex items-center justify-center rounded-lg bg-uta-blue px-4 text-sm font-semibold text-white no-underline transition-colors hover:bg-uta-blue-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uta-blue focus-visible:ring-offset-2';
  const BTN_SECUNDARIO =
    'inline-flex items-center justify-center rounded-lg border border-line-control bg-white px-4 text-sm font-semibold text-uta-blue no-underline transition-colors hover:bg-uta-blue-light focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uta-blue focus-visible:ring-offset-2';
  const BTN_MAS =
    'inline-flex shrink-0 items-center justify-center rounded-lg border border-line-control bg-white text-ink transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uta-blue';
  const TH = 'px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600';

  // Pastilla de estado del mockup: sin punto, 13 px. La planificada va con borde
  // punteado para no confundirse con la cerrada (ambas grises).
  const PILL_BASE = 'inline-flex items-center rounded-full px-2.5 py-1 text-[13px] font-semibold whitespace-nowrap';
  const PILL: Record<string, string> = {
    planificada: `${PILL_BASE} border border-dashed border-line-control bg-white text-slate-600`,
    activa: `${PILL_BASE} bg-emerald-50 text-emerald-700`,
    cerrada: `${PILL_BASE} border border-slate-200 bg-slate-100 text-slate-600`,
  };

  function urlEnunciado(a: Actividad): string {
    return `/docente/cursos/${idCurso}/actividades/${a.id_actividad}/enunciado/descargar`;
  }

  function titleSubtitulo(a: Actividad): string {
    return [nombreComponente(a), etiquetaUnidad(a)].filter(Boolean).join(' · ');
  }
</script>

{#snippet fecha(a: Actividad, estado: string, urgente: boolean)}
  {#if a.fecha_limite}
    <span class="block whitespace-nowrap text-sm font-medium text-ink">
      {fechaCorta(a.fecha_limite)} {a.fecha_limite.slice(0, 4)}
    </span>
    {#if estado !== 'cerrada'}
      {@const rel = plazoRelativo(a.fecha_limite)}
      <span
        class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {urgente || rel.tono !== 'neutro'
          ? 'bg-amber-100 text-amber-800'
          : 'bg-slate-100 text-slate-600'}"
      >
        {rel.texto}
      </span>
    {/if}
  {:else}
    <span class="text-sm text-slate-600">Sin fecha</span>
  {/if}
{/snippet}

{#snippet barra(av: Avance)}
  <div class="h-1.5 w-full overflow-hidden rounded bg-slate-100" aria-hidden="true">
    <div class="flex h-full">
      {#if av.total > 0 && av.listas > 0}
        <div class="h-full bg-emerald-700" style="width: {Math.max(2, (av.listas / av.total) * 100)}%"></div>
      {/if}
      {#if av.total > 0 && av.pendientes > 0}
        <div class="h-full bg-yellow-400" style="width: {Math.max(2, (av.pendientes / av.total) * 100)}%"></div>
      {/if}
    </div>
  </div>
{/snippet}

<!-- `largo`: tarjeta móvil, el rótulo dice qué controla («Visible para estudiantes»)
     en vez del «Sí/No» de la columna. -->
{#snippet visibilidad(act: Actividad, largo = false)}
  {@const toggle = toggles[act.id_actividad]}
  {@const guardando = toggle?.fase === 'guardando'}
  {@const visible = visibleEnPantalla(act)}
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
            : 'justify-start'} {guardando ? 'bg-gray-300' : visible ? 'bg-emerald-700' : 'bg-line-control'}"
        >
          <span class="h-4 w-4 rounded-full bg-white shadow-[0_1px_2px_rgba(0,0,0,.2)]" aria-hidden="true"></span>
        </button>
        {#if guardando}
          <span class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-amber-800">
            <Loader2 size={12} class="animate-spin" aria-hidden="true" />
            Guardando…
          </span>
        {:else}
          {#if largo}
            <span class="text-[13px] font-semibold {visible ? 'text-ink' : 'text-amber-800'}"
              >{visible ? 'Visible para estudiantes' : 'Oculta a estudiantes'}</span
            >
          {:else}
            <span class="text-[13px] font-semibold {visible ? 'text-emerald-700' : 'text-amber-800'}">{visible ? 'Sí' : 'No'}</span>
          {/if}
        {/if}
      </span>
      {#if toggle?.fase === 'ok'}
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
          <Check size={12} aria-hidden="true" />
          Guardado
        </span>
      {:else if toggle?.fase === 'error'}
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-700">
          <AlertCircle size={12} aria-hidden="true" />
          No se pudo guardar ·
          <button type="button" class="underline underline-offset-2" onclick={() => onToggleVisible(act)}>Reintentar</button>
        </span>
      {/if}
    </div>
  {:else}
    <!-- Sin permiso de editar el interruptor se vuelve etiqueta. -->
    <span class="inline-flex items-center gap-1.5 text-[13px] font-semibold {act.visible ? 'text-emerald-700' : 'text-amber-800'}">
      {#if act.visible}
        <Eye size={14} aria-hidden="true" />Visible
      {:else}
        <EyeOff size={14} aria-hidden="true" />Oculta
      {/if}
    </span>
  {/if}
{/snippet}

{#snippet menuMas(act: Actividad, tam: string)}
  <DropdownMenu>
    <DropdownMenuTrigger class="{BTN_MAS} {tam}" aria-label="Más acciones para {act.nombre}">
      <Ellipsis size={18} />
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-56 rounded-xl p-1.5">
      {#if act.archivo_enunciado}
        <DropdownMenuItem
          onSelect={() => window.location.assign(urlEnunciado(act))}
          class="min-h-10 cursor-pointer rounded-lg text-sm"
          title={act.archivo_enunciado.nombre_original}
        >
          Descargar enunciado
        </DropdownMenuItem>
      {/if}
      {#if canEdit}
        <DropdownMenuItem onSelect={() => onEdit(act)} class="min-h-10 cursor-pointer rounded-lg text-sm">Editar</DropdownMenuItem>
        <DropdownMenuItem onSelect={() => onEnunciado(act)} class="min-h-10 cursor-pointer rounded-lg text-sm">
          {act.archivo_enunciado ? 'Reemplazar enunciado' : 'Adjuntar enunciado'}
        </DropdownMenuItem>
      {/if}
      {#if onCopiar}
        <DropdownMenuItem onSelect={() => onCopiar(act)} class="min-h-10 cursor-pointer rounded-lg text-sm">Copiar a otro curso</DropdownMenuItem>
      {/if}
      {#if canDelete}
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={() => onDelete(act)} class="min-h-10 cursor-pointer rounded-lg text-sm text-red-700">Eliminar</DropdownMenuItem>
      {/if}
    </DropdownMenuContent>
  </DropdownMenu>
{/snippet}

<!-- El enunciado se descarga desde «⋯». Sin columna de acciones no hay menú,
     así que sólo entonces queda como enlace bajo el nombre. Los mensajes por
     responder ya van en el texto del avance. -->
{#snippet extras(act: Actividad)}
  {#if !hayAcciones && act.archivo_enunciado}
    <a
      href={urlEnunciado(act)}
      class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-slate-600 underline underline-offset-2 hover:text-uta-blue"
      title={act.archivo_enunciado.nombre_original}
    >
      Descargar enunciado
    </a>
  {/if}
{/snippet}

{#snippet estadoPill(estado: string)}
  <span class={PILL[estado]}>{ETIQUETA_ESTADO[estado as keyof typeof ETIQUETA_ESTADO]}</span>
{/snippet}

<!-- Desktop / tablet: tabla -->
<div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
  <table class="w-full border-collapse text-sm">
    <thead class="bg-slate-50">
      <tr>
        <th class={TH}>Actividad</th>
        <th class="{TH} w-[160px]">Fecha límite</th>
        <th class="{TH} w-[250px]">Avance</th>
        <th class="{TH} w-[106px]">Estado</th>
        <th class="{TH} w-[100px]">Visible</th>
        {#if hayAcciones}
          <th class="{TH} w-[176px] text-right">Acciones</th>
        {/if}
      </tr>
    </thead>
    <tbody>
      {#each actividades as act (act.id_actividad)}
        {@const estado = estadoActividad(act, ahora)}
        {@const toggle = toggles[act.id_actividad]}
        {@const guardando = toggle?.fase === 'guardando'}
        {@const av = avanceDe(act, estado)}
        {@const accion = accionDe(act, av)}
        <tr
          class="border-t border-slate-200 align-middle {guardando
            ? 'bg-amber-50'
            : toggle?.fase === 'error'
              ? 'bg-red-50'
              : 'bg-white hover:bg-slate-50'}"
        >
          <td class="px-4 py-3.5">
            <Link
              href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
              class="block text-[15px] font-semibold leading-5 text-ink no-underline hover:text-uta-blue hover:underline"
            >
              {act.nombre}
            </Link>
            <span class="mt-1 block text-xs font-medium text-slate-600" title={titleSubtitulo(act)}>{subtitulo(act)}</span>
            {@render extras(act)}
          </td>
          <td class="px-4 py-3.5">{@render fecha(act, estado, av.urgente)}</td>
          <td class="px-4 py-3.5">
            <div class="flex flex-col gap-1.5">
              {@render barra(av)}
              <span class="text-xs {av.urgente ? 'font-semibold text-amber-800' : 'font-medium text-slate-600'}">{av.texto}</span>
            </div>
          </td>
          <td class="px-4 py-3.5">{@render estadoPill(estado)}</td>
          <td class="px-4 py-3.5">{@render visibilidad(act)}</td>
          {#if hayAcciones}
            <td class="px-4 py-3.5">
              <div class="flex items-center justify-end gap-2 {guardando ? 'pointer-events-none opacity-45' : ''}">
                <Link
                  href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
                  class="{accion.primaria ? BTN_PRIMARIO : BTN_SECUNDARIO} h-9 whitespace-nowrap"
                >
                  {accion.texto}
                </Link>
                {@render menuMas(act, 'size-9')}
              </div>
            </td>
          {/if}
        </tr>
      {/each}
    </tbody>
  </table>
  {#if pie}
    <div
      class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-200 bg-slate-50 px-4 py-3 text-[13px] font-medium text-slate-600"
    >
      {@render pie()}
    </div>
  {/if}
</div>

<!-- Móvil: tarjetas -->
<ul class="flex flex-col gap-3 md:hidden">
  {#each actividades as act (act.id_actividad)}
    {@const estado = estadoActividad(act, ahora)}
    {@const guardando = toggles[act.id_actividad]?.fase === 'guardando'}
    {@const av = avanceDe(act, estado)}
    {@const accion = accionDe(act, av)}
    <li
      class="flex flex-col gap-2.5 rounded-xl border bg-white px-4 py-3.5 {av.urgente ? 'border-yellow-400' : 'border-slate-200'}"
    >
      <Link
        href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
        class="text-base font-semibold leading-[21px] text-ink no-underline"
      >
        {act.nombre}
      </Link>
      <span class="-mt-1.5 text-xs font-medium text-slate-600" title={titleSubtitulo(act)}>{subtitulo(act)}</span>
      {@render extras(act)}
      <div class="flex flex-wrap items-center gap-2">
        {@render estadoPill(estado)}
        <span class="flex items-center gap-2 [&>span]:mt-0">{@render fecha(act, estado, av.urgente)}</span>
      </div>
      {@render barra(av)}
      <span class="text-[13px] {av.urgente ? 'font-semibold text-amber-800' : 'font-medium text-slate-600'}">{av.texto}</span>
      {@render visibilidad(act, true)}
      {#if hayAcciones}
        <div class="flex items-center gap-2 {guardando ? 'pointer-events-none opacity-45' : ''}">
          <Link
            href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
            class="{accion.primaria ? BTN_PRIMARIO : BTN_SECUNDARIO} h-11 flex-1"
          >
            {accion.texto}
          </Link>
          {@render menuMas(act, 'size-11')}
        </div>
      {/if}
    </li>
  {/each}
</ul>
