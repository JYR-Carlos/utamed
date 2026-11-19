<script lang="ts">
  /**
   * Actividades del curso — /docente/cursos/{curso}/actividades y
   * /docente/cursos/{curso}/actividades/{actividad}/evaluacion.
   *
   * Revisión «Mi UTAmed»: gestionar actividades y la hoja de la actividad son
   * una sola pantalla. «Agenda» despliega la actividad dentro de su fila con
   * fechas, enunciado, rúbrica y la tabla de estudiantes; se abre una a la vez
   * y la URL dice cuál (la de la hoja de siempre), así que recargar, volver o
   * llegar desde la bandeja la deja abierta.
   *
   * La lista va primero; «Resumen» reemplaza al tablero por estado. Los
   * estados son los mismos en todas partes: la actividad es Planificada,
   * Activa o Cerrada; cada entrega es No entregó, Por revisar/calificar o
   * Revisado/Calificado, y la columna Entregas suma esos mismos estados.
   *
   * El docente de componente ve sólo lo suyo (`mis_componentes_ids`); donde no
   * hay permiso la acción no se dibuja.
   */
  import { page, router } from '@inertiajs/svelte';
  import CursoDocenteLayout from '@/layouts/CursoDocenteLayout.svelte';
  import {
    ChevronDown,
    Copy,
    Eye,
    EyeOff,
    LayoutGrid,
    List,
    ListChecks,
    MessageSquare,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
  } from 'lucide-svelte';
  import { aviso, avisoTono, btnPrimario, btnSecundario, campo, chico, chip, cuerpo, tarjeta, titulo, tono, type Tono } from '@/lib/inst';
  import {
    ActividadForm,
    createActividad,
    updateActividad,
    deleteActividad,
    toggleVisibilidadActividad,
  } from '@/modules/resources/actividad';
  import { subirEnunciadoActividad } from '@/modules/resources/actividad/services/actividadApi';
  import ActividadEliminarDialog from '@/components/docente/ActividadEliminarDialog.svelte';
  import CopiarActividadesModal from '@/components/docente/CopiarActividadesModal.svelte';
  import ActividadDetalle, { type ActividadAbierta, type GrupoData } from './Activities/components/ActividadDetalle.svelte';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';
  import type { Actividad } from '@/types/actividad';
  import type { Rubrica } from '@/types/rubrica';
  import type { InteraccionItem } from '@/types/agenda';
  import { ETIQUETA_ESTADO, estadoActividad, type EstadoActividad } from '@/utils/actividadEstado';
  import { BARRA_ENTREGA, ORDEN_ENTREGA, conteoEntregas, textosEntrega } from '@/lib/estadoEntrega';
  import { formatNota } from '@/utils/formatters';

  interface ComponenteCurso {
    id_componente: number;
    tipo_componente?: { tipo: string } | null;
    es_mio?: boolean;
  }

  interface UnidadCurso {
    id_unidad: number;
    nombre: string;
    num_unidad?: number;
  }

  interface ReglasEnunciado {
    extensiones_pdf: string[];
    extensiones_img: string[];
    max_mb_pdf: number;
    max_mb_imagen: number;
  }

  interface Props {
    curso: {
      id_curso: number;
      nombre: string;
      etiqueta?: string;
      asignatura_nombre?: string | null;
      es_titular_curso?: boolean;
      mis_componentes_ids?: number[];
      userPermissions?: Permission[];
    };
    actividades: Actividad[];
    componentes: ComponenteCurso[];
    unidades: UnidadCurso[];
    reglas_enunciado?: ReglasEnunciado;
    // ── Sólo con una actividad desplegada (…/actividades/{id}/evaluacion) ──
    actividad?: ActividadAbierta;
    grupos?: GrupoData[];
    rubrica?: Rubrica | null;
    rubrica_id?: number | null;
    tiene_evaluaciones?: boolean;
    puede_editar_rubrica?: boolean;
    interaccionesGrupo?: InteraccionItem[];
  }

  let {
    curso,
    actividades,
    componentes,
    unidades,
    reglas_enunciado,
    actividad,
    grupos = [],
    rubrica = null,
    rubrica_id = null,
    tiene_evaluaciones = false,
    puede_editar_rubrica = false,
    interaccionesGrupo = [],
  }: Props = $props();

  const REGLAS_DEFAULT: ReglasEnunciado = {
    extensiones_pdf: ['pdf'],
    extensiones_img: ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'svg', 'tiff'],
    max_mb_pdf: 100,
    max_mb_imagen: 50,
  };
  const reglas = $derived(reglas_enunciado ?? REGLAS_DEFAULT);
  const base = $derived(`/docente/cursos/${curso.id_curso}/actividades`);

  // ── Rol y permisos ────────────────────────────────────────────────────────
  const esTitular = $derived(curso.es_titular_curso ?? false);
  const misComponentesIds = $derived(curso.mis_componentes_ids ?? []);
  const canCreate = $derived(esTitular || hasPermission(curso.userPermissions ?? [], 'actividades:crear'));
  const canEdit = $derived(esTitular || hasPermission(curso.userPermissions ?? [], 'actividades:editar'));
  const canDelete = $derived(esTitular || hasPermission(curso.userPermissions ?? [], 'actividades:eliminar'));

  const actividadesDelRol = $derived(
    esTitular ? actividades : actividades.filter((a) => misComponentesIds.includes(a.id_componente ?? -1)),
  );
  const componentesDelRol = $derived(esTitular ? componentes : componentes.filter((c) => c.es_mio));
  const unidadesOrdenadas = $derived([...unidades].sort((a, b) => (a.num_unidad ?? 0) - (b.num_unidad ?? 0)));

  // ── Vista y filtros ───────────────────────────────────────────────────────
  let vista = $state<'lista' | 'resumen'>('lista');
  let busqueda = $state('');
  let filtroEstado = $state<EstadoActividad | 'todos'>('todos');
  let filtroTipo = $state<Actividad['tipo_actividad'] | 'todos'>('todos');
  let filtroUnidad = $state<number | 'todas'>('todas');
  let filtroVisibilidad = $state<'todas' | 'visibles' | 'ocultas'>('todas');

  const ahora = new Date();
  const estadoDe = (a: Actividad) => estadoActividad(a, ahora);

  const filtradas = $derived(
    actividadesDelRol.filter((a) => {
      const q = busqueda.trim().toLowerCase();
      if (q && !a.nombre.toLowerCase().includes(q)) return false;
      if (filtroEstado !== 'todos' && estadoDe(a) !== filtroEstado) return false;
      if (filtroTipo !== 'todos' && a.tipo_actividad !== filtroTipo) return false;
      if (filtroUnidad !== 'todas' && a.id_unidad !== filtroUnidad) return false;
      if (filtroVisibilidad !== 'todas' && a.visible !== (filtroVisibilidad === 'visibles')) return false;
      return true;
    }),
  );

  function limpiarFiltros() {
    busqueda = '';
    filtroEstado = 'todos';
    filtroTipo = 'todos';
    filtroUnidad = 'todas';
    filtroVisibilidad = 'todas';
  }

  const TONO_ESTADO: Record<EstadoActividad, Tono> = { planificada: 'off', activa: 'ok', cerrada: 'info' };
  const fmtCierre = new Intl.DateTimeFormat('es-CL', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' });
  const cierreDe = (a: Actividad) => {
    if (!a.fecha_limite) return 'Sin fecha';
    const [y, m, d] = a.fecha_limite.slice(0, 10).split('-').map(Number);
    return fmtCierre.format(Date.UTC(y, m - 1, d));
  };
  const unidadDe = (a: Actividad) => {
    const u = a.unidad ?? unidades.find((x) => x.id_unidad === a.id_unidad);
    return u ? `Unidad ${u.num_unidad ?? ''}`.trim() : null;
  };

  // ── Desplegar una actividad (una a la vez; la URL dice cuál) ───────────────
  const abiertaId = $derived(actividad?.id_actividad ?? null);

  function alternar(a: Actividad) {
    menuAbierto = null;
    const url = abiertaId === a.id_actividad ? base : `${base}/${a.id_actividad}/evaluacion`;
    router.visit(url, { preserveScroll: true, preserveState: true });
  }

  function agendaDesdeResumen(a: Actividad) {
    vista = 'lista';
    if (abiertaId !== a.id_actividad) router.visit(`${base}/${a.id_actividad}/evaluacion`, { preserveState: true });
  }

  // ── Menú «⋯» ──────────────────────────────────────────────────────────────
  let menuAbierto = $state<number | null>(null);

  // ── Formularios y diálogos ────────────────────────────────────────────────
  let showForm = $state(false);
  let showDelete = $state(false);
  let showCopiar = $state(false);
  let isLoading = $state(false);
  let editando = $state<Actividad | null>(null);
  let eliminando = $state<Actividad | null>(null);
  let copiarPreseleccion = $state<number[]>([]);
  let formErrors = $state<Record<string, string>>({});

  function abrirCrear() {
    editando = null;
    formErrors = {};
    showForm = true;
  }

  function abrirEditar(a: Actividad) {
    menuAbierto = null;
    editando = a;
    formErrors = {};
    showForm = true;
  }

  function handleSubmit(data: Partial<Actividad>, enunciado: File | null) {
    isLoading = true;
    formErrors = {};
    const idsAntes = new Set(actividades.map((a) => a.id_actividad));

    const terminar = () => {
      showForm = false;
      editando = null;
      isLoading = false;
    };
    const fallar = (errors: Record<string, string>) => {
      formErrors = errors;
      isLoading = false;
    };
    const subir = (idActividad: number | undefined) => {
      if (!enunciado || !idActividad) return terminar();
      subirEnunciadoActividad(curso.id_curso, idActividad, enunciado, {
        onSuccess: terminar,
        onError: (e) => fallar({ error: e.archivo ?? 'La actividad se guardó, pero el enunciado no se pudo subir.' }),
      });
    };

    if (editando) {
      const id = editando.id_actividad;
      updateActividad(curso.id_curso, id, data, { onSuccess: () => subir(id), onError: fallar });
    } else {
      createActividad(curso.id_curso, data, {
        // La actividad nueva es la única que no estaba antes de guardar.
        onSuccess: () => {
          const frescas = (($page.props as any).actividades ?? []) as Actividad[];
          subir(frescas.find((a) => !idsAntes.has(a.id_actividad))?.id_actividad);
        },
        onError: fallar,
      });
    }
  }

  function abrirEliminar(a: Actividad) {
    menuAbierto = null;
    eliminando = a;
    showDelete = true;
  }

  function handleDelete() {
    if (!eliminando) return;
    const id = eliminando.id_actividad;
    isLoading = true;
    deleteActividad(curso.id_curso, id, {
      onSuccess: () => {
        showDelete = false;
        eliminando = null;
        isLoading = false;
        if (abiertaId === id) router.visit(base, { preserveScroll: true, preserveState: true });
      },
      onError: () => (isLoading = false),
    });
  }

  function alternarVisibilidad(a: Actividad) {
    menuAbierto = null;
    toggleVisibilidadActividad(curso.id_curso, a.id_actividad);
  }

  function ocultarDesdeDialogo() {
    if (!eliminando) return;
    const a = eliminando;
    showDelete = false;
    eliminando = null;
    alternarVisibilidad(a);
  }

  function abrirCopiar(a?: Actividad) {
    menuAbierto = null;
    copiarPreseleccion = a ? [a.id_actividad] : [];
    showCopiar = true;
  }

  function rubricaDe(a: Actividad) {
    menuAbierto = null;
    router.visit(`${base}/${a.id_actividad}/evaluacion?rubrica=1`, { preserveScroll: true, preserveState: true });
  }

  // ── Avisos del servidor ───────────────────────────────────────────────────
  const flash = $derived((($page.props as any).flash ?? {}) as { success?: string; error?: string });
  const copiaOmitidas = $derived(
    ((($page.props as any).flash?.data?.copia_omitidas ?? []) as Array<{ nombre: string; curso: string; motivo: string }>),
  );

  $effect(() => {
    const errs = ($page.props as any).errors as Record<string, string> | undefined;
    if (showForm && errs && Object.keys(errs).length > 0) {
      formErrors = errs;
      isLoading = false;
    }
  });

  const GRID = 'lg:grid lg:grid-cols-[minmax(0,2.4fr)_minmax(96px,.8fr)_minmax(96px,.8fr)_minmax(200px,2fr)_minmax(96px,.8fr)_auto] lg:items-center lg:gap-x-4';
  const ITEM_MENU =
    'flex min-h-11 w-full items-center gap-2.5 border-0 bg-transparent px-3.5 text-left text-sm font-bold text-inst-ink hover:bg-inst-sutil lg:min-h-10';
  const BTN_SEG =
    'inline-flex min-h-11 items-center gap-1.5 border-0 border-r border-inst-linea px-3.5 text-[13px] font-bold last:border-r-0 lg:min-h-9';
</script>

<svelte:head>
  <title>Actividades · {curso.etiqueta ?? curso.nombre} | UTAmed</title>
</svelte:head>

<svelte:window
  onclick={(e) => {
    if (menuAbierto !== null && !(e.target as HTMLElement).closest('[data-menu-actividad]')) menuAbierto = null;
  }}
/>

{#snippet avance(a: Actividad)}
  {@const c = conteoEntregas(a)}
  {@const total = c.ok + c.pend + c.no}
  {@const t = textosEntrega(a.tipo_actividad === 'SUMATIVA')}
  {#if !a.visible || total === 0}
    <span class="text-[13px] text-inst-ink-3">{a.visible ? 'Sin estudiantes asignados' : 'Aún no abre'}</span>
  {:else}
    <span class="flex min-w-0 flex-col gap-1.5">
      <span class="flex h-2 overflow-hidden rounded-full bg-inst-sutil" aria-hidden="true">
        {#each ORDEN_ENTREGA as k (k)}
          {#if c[k]}<span class={BARRA_ENTREGA[k]} style="width: {(c[k] / total) * 100}%"></span>{/if}
        {/each}
      </span>
      <span class="flex flex-wrap gap-x-2.5 gap-y-0.5 text-[13px]">
        {#each ORDEN_ENTREGA as k (k)}
          <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
            <span aria-hidden="true" class="size-2 rounded-sm {BARRA_ENTREGA[k]}"></span>{t[k]} <strong>{c[k]}</strong>
          </span>
        {/each}
      </span>
    </span>
  {/if}
{/snippet}

{#snippet menu(a: Actividad)}
  {@const sumativa = a.tipo_actividad === 'SUMATIVA'}
  <span class="relative inline-flex" data-menu-actividad>
    <button
      type="button"
      class="{btnSecundario} {chico} px-0! size-11 lg:size-9"
      aria-haspopup="menu"
      aria-expanded={menuAbierto === a.id_actividad}
      aria-label="Más opciones de {a.nombre}"
      onclick={() => (menuAbierto = menuAbierto === a.id_actividad ? null : a.id_actividad)}
    >
      <MoreHorizontal size={18} aria-hidden="true" />
    </button>
    {#if menuAbierto === a.id_actividad}
      <div role="menu" aria-label="Opciones de {a.nombre}" class="absolute top-full right-0 z-30 mt-1 flex min-w-[230px] flex-col rounded-md border border-inst-linea bg-inst-tarjeta py-1 shadow-lg">
        {#if canEdit}
          <button type="button" role="menuitem" class={ITEM_MENU} onclick={() => abrirEditar(a)}><Pencil size={16} aria-hidden="true" />Editar actividad</button>
        {/if}
        {#if sumativa}
          <button type="button" role="menuitem" class={ITEM_MENU} onclick={() => rubricaDe(a)}>
            <ListChecks size={16} aria-hidden="true" />{a.tiene_rubrica ? 'Editar rúbrica' : 'Crear rúbrica'}
          </button>
        {/if}
        {#if esTitular}
          <button type="button" role="menuitem" class={ITEM_MENU} onclick={() => abrirCopiar(a)}><Copy size={16} aria-hidden="true" />Copiar a otro curso</button>
        {/if}
        {#if canEdit}
          <button type="button" role="menuitem" class={ITEM_MENU} onclick={() => alternarVisibilidad(a)}>
            {#if a.visible}<EyeOff size={16} aria-hidden="true" />Ocultar al estudiante{:else}<Eye size={16} aria-hidden="true" />Publicar al estudiante{/if}
          </button>
        {/if}
        {#if canDelete}
          <div class="my-1 border-t border-inst-linea" role="separator"></div>
          <button type="button" role="menuitem" class="{ITEM_MENU} text-inst-error" onclick={() => abrirEliminar(a)}><Trash2 size={16} aria-hidden="true" />Eliminar</button>
        {/if}
      </div>
    {/if}
  </span>
{/snippet}

{#snippet tipoChip(a: Actividad)}
  <span class="{chip} {a.tipo_actividad === 'SUMATIVA' ? tono.accion : tono.info}">{a.tipo_actividad === 'SUMATIVA' ? 'Sumativa' : 'Formativa'}</span>
{/snippet}

<CursoDocenteLayout idCurso={curso.id_curso} seccion="actividades" nombre={curso.etiqueta ?? curso.nombre}>
  <div class={cuerpo}>
    <div class="flex flex-wrap items-center gap-3">
      <h2 class="{titulo} m-0 mr-auto text-2xl leading-[30px]">Actividades</h2>
      <div class="inline-flex overflow-hidden rounded-md border border-inst-control" role="group" aria-label="Vista">
        <button type="button" aria-pressed={vista === 'lista'} class="{BTN_SEG} {vista === 'lista' ? 'bg-inst-accion text-inst-sobre-accion' : 'bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil'}" onclick={() => (vista = 'lista')}>
          <List size={16} aria-hidden="true" />Lista
        </button>
        <button type="button" aria-pressed={vista === 'resumen'} class="{BTN_SEG} {vista === 'resumen' ? 'bg-inst-accion text-inst-sobre-accion' : 'bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil'}" onclick={() => (vista = 'resumen')}>
          <LayoutGrid size={16} aria-hidden="true" />Resumen
        </button>
      </div>
      {#if esTitular}
        <button type="button" class={btnSecundario} onclick={() => abrirCopiar()}><Copy size={16} aria-hidden="true" />Copiar a otro curso</button>
      {/if}
      {#if canCreate}
        <button type="button" class={btnPrimario} onclick={abrirCrear}><Plus size={16} aria-hidden="true" />Nueva actividad</button>
      {/if}
    </div>

    {#if flash.error}
      <div class="{aviso} {avisoTono.error}" role="alert"><p class="m-0 text-[13px] font-bold">{flash.error}</p></div>
    {/if}
    {#if copiaOmitidas.length > 0}
      <div class="{aviso} {avisoTono.warn}" role="status">
        <div class="text-[13px]">
          <p class="m-0 font-bold">No se copiaron:</p>
          <ul class="m-0 mt-1 list-disc pl-5">
            {#each copiaOmitidas as o, i (i)}<li>{o.nombre} → {o.curso}: {o.motivo}</li>{/each}
          </ul>
        </div>
      </div>
    {/if}
    {#if flash.success}
      <div class="{aviso} {avisoTono.ok}" role="status"><p class="m-0 text-[13px] font-bold">{flash.success}</p></div>
    {/if}

    <div class="{tarjeta} flex flex-wrap items-end gap-3 px-4.5 py-3.5">
      <label class="relative min-w-[220px] flex-[1_1_260px]">
        <span class="sr-only">Buscar actividad</span>
        <Search size={16} aria-hidden="true" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-inst-ink-3" />
        <input type="search" bind:value={busqueda} placeholder="Buscar actividad" class="{campo} pl-9" />
      </label>
      <label class="flex flex-[0_1_150px] flex-col gap-1 text-[13px] font-bold">
        Estado
        <select bind:value={filtroEstado} class={campo}>
          <option value="todos">Todos</option>
          <option value="planificada">Planificada</option>
          <option value="activa">Activa</option>
          <option value="cerrada">Cerrada</option>
        </select>
      </label>
      <label class="flex flex-[0_1_150px] flex-col gap-1 text-[13px] font-bold">
        Tipo
        <select bind:value={filtroTipo} class={campo}>
          <option value="todos">Todos</option>
          <option value="SUMATIVA">Sumativa</option>
          <option value="FORMATIVA">Formativa</option>
        </select>
      </label>
      <label class="flex flex-[0_1_160px] flex-col gap-1 text-[13px] font-bold">
        Unidad
        <select bind:value={filtroUnidad} class={campo}>
          <option value="todas">Todas</option>
          {#each unidadesOrdenadas as u (u.id_unidad)}<option value={u.id_unidad}>Unidad {u.num_unidad ?? ''} · {u.nombre}</option>{/each}
        </select>
      </label>
      <label class="flex flex-[0_1_150px] flex-col gap-1 text-[13px] font-bold">
        Visibilidad
        <select bind:value={filtroVisibilidad} class={campo}>
          <option value="todas">Todas</option>
          <option value="visibles">Visibles</option>
          <option value="ocultas">Ocultas</option>
        </select>
      </label>
    </div>

    {#if actividadesDelRol.length === 0}
      <section class="{tarjeta} px-6 py-10 text-center">
        <p class="m-0 font-bold">Todavía no hay actividades en este curso</p>
        <p class="m-0 mt-1 text-inst-ink-2">{canCreate ? 'Crea la primera con «Nueva actividad».' : 'El titular aún no crea actividades.'}</p>
      </section>
    {:else if filtradas.length === 0}
      <section class="{tarjeta} flex flex-col items-center gap-3 px-6 py-10 text-center">
        <p class="m-0 font-bold">Ninguna actividad coincide con los filtros</p>
        <button type="button" class="{btnSecundario} {chico}" onclick={limpiarFiltros}>Limpiar filtros</button>
      </section>
    {:else if vista === 'lista'}
      <div class={tarjeta}>
        <div class="hidden border-b border-inst-linea bg-inst-sutil px-4.5 py-2.5 text-xs font-extrabold tracking-[0.06em] text-inst-ink-3 uppercase {GRID}" aria-hidden="true">
          <span>Actividad</span><span>Estado</span><span>Cierre</span><span>Entregas</span><span>Nota</span><span></span>
        </div>
        <ul class="m-0 list-none p-0">
          {#each filtradas as a (a.id_actividad)}
            {@const estado = estadoDe(a)}
            {@const abierta = abiertaId === a.id_actividad}
            <li class="border-t border-inst-linea first:border-t-0">
              <div class="flex flex-col gap-3 px-4.5 py-3.5 {GRID}">
                <span class="flex min-w-0 flex-col gap-1">
                  <span class="text-[15px] font-bold break-words">{a.nombre}</span>
                  <span class="flex flex-wrap items-center gap-1.5">
                    {@render tipoChip(a)}
                    <span class="text-[13px] text-inst-ink-2">{[a.es_grupal ? 'Grupal' : 'Individual', unidadDe(a)].filter(Boolean).join(' · ')}</span>
                  </span>
                </span>
                <span><span class="{chip} {tono[TONO_ESTADO[estado]]}">{ETIQUETA_ESTADO[estado]}</span></span>
                <span class="text-[13px]"><span class="lg:hidden">Cierra </span>{cierreDe(a)}</span>
                {@render avance(a)}
                <span class="flex flex-col text-[13px]">
                  {#if a.tipo_actividad === 'SUMATIVA'}
                    <span>Pond. <strong>{a.ponderacion ? `${a.ponderacion} %` : '—'}</strong></span>
                    <span>Prom. <strong>{a.promedio_nota != null ? formatNota(a.promedio_nota) : '—'}</strong></span>
                  {:else}
                    <span class="text-inst-ink-3">Sin nota</span>
                  {/if}
                </span>
                <span class="flex items-center justify-end gap-1.5">
                  <button
                    type="button"
                    class="{abierta ? btnPrimario : btnSecundario} {chico} whitespace-nowrap"
                    aria-expanded={abierta}
                    aria-controls="det-{a.id_actividad}"
                    onclick={() => alternar(a)}
                  >
                    <MessageSquare size={16} aria-hidden="true" />Agenda
                    {#if (a.mensajes_pendientes ?? 0) > 0}
                      <span class="rounded-full px-1.5 text-xs font-extrabold {abierta ? 'bg-inst-tarjeta text-inst-accion-t' : 'bg-inst-accion text-inst-sobre-accion'}" aria-label="{a.mensajes_pendientes} por responder">{a.mensajes_pendientes}</span>
                    {/if}
                    <ChevronDown size={16} aria-hidden="true" class="-ml-0.5 transition-transform {abierta ? 'rotate-180' : ''}" />
                  </button>
                  {@render menu(a)}
                </span>
              </div>
              {#if abierta && actividad}
                <div
                  id="det-{a.id_actividad}"
                  class="flex flex-col gap-3.5 border-t border-inst-linea bg-inst-sel px-4.5 pt-4 pb-5 shadow-[inset_4px_0_0_var(--color-inst-oro)]"
                >
                  {#key actividad.id_actividad}
                    <ActividadDetalle
                      {curso}
                      {actividad}
                      fila={a}
                      {grupos}
                      {rubrica}
                      {rubrica_id}
                      {tiene_evaluaciones}
                      {puede_editar_rubrica}
                      {interaccionesGrupo}
                      reglas_enunciado={reglas}
                      puedeEditar={canEdit}
                      onEditar={() => abrirEditar(a)}
                    />
                  {/key}
                </div>
              {/if}
            </li>
          {/each}
        </ul>
      </div>
    {:else}
      <div class="grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] items-start gap-4">
        {#each ['activa', 'planificada', 'cerrada'] as EstadoActividad[] as e (e)}
          {@const xs = filtradas.filter((a) => estadoDe(a) === e)}
          <section class="{tarjeta} bg-inst-sutil">
            <div class="flex items-center gap-2.5 rounded-t-lg border-b border-inst-linea bg-inst-tarjeta px-4.5 py-3">
              <span class="{chip} {tono[TONO_ESTADO[e]]}">{ETIQUETA_ESTADO[e]}</span>
              <span class="text-[13px] text-inst-ink-2">{xs.length} {xs.length === 1 ? 'actividad' : 'actividades'}</span>
            </div>
            <div class="flex flex-col gap-2.5 p-3">
              {#each xs as a (a.id_actividad)}
                <div class="{tarjeta} flex flex-col gap-2.5 border-l-4 px-3.5 py-3 {e === 'cerrada' ? 'border-l-inst-ok' : e === 'activa' ? 'border-l-inst-oro' : 'border-l-inst-linea'}">
                  <span class="flex items-center gap-1.5">
                    {@render tipoChip(a)}
                    <span class="mr-auto text-[13px] text-inst-ink-2">{unidadDe(a) ?? ''}</span>
                    {@render menu(a)}
                  </span>
                  <span class="font-bold">{a.nombre}</span>
                  <span class="text-[13px] text-inst-ink-2">
                    Cierra {cierreDe(a)}{#if a.tipo_actividad === 'SUMATIVA'} · ponderación {a.ponderacion ? `${a.ponderacion} %` : '—'} · promedio {a.promedio_nota != null ? formatNota(a.promedio_nota) : '—'}{/if}
                  </span>
                  {@render avance(a)}
                  <button type="button" class="{btnSecundario} {chico} self-start" onclick={() => agendaDesdeResumen(a)}>
                    <MessageSquare size={16} aria-hidden="true" />Agenda
                  </button>
                </div>
              {:else}
                <p class="m-0 px-1 py-2 text-[13px] text-inst-ink-2">Ninguna.</p>
              {/each}
            </div>
          </section>
        {/each}
      </div>
    {/if}
  </div>

  <ActividadForm
    bind:isOpen={showForm}
    {isLoading}
    editingActividad={editando}
    componentes={componentesDelRol}
    unidades={unidadesOrdenadas}
    errors={formErrors}
    reglasEnunciado={esTitular ? reglas : null}
    onClose={() => {
      showForm = false;
      editando = null;
      formErrors = {};
    }}
    onSubmit={handleSubmit}
  />

  <ActividadEliminarDialog
    bind:isOpen={showDelete}
    actividad={eliminando}
    {isLoading}
    puedeOcultar={canEdit}
    onConfirm={handleDelete}
    onOcultar={ocultarDesdeDialogo}
    onCancel={() => {
      showDelete = false;
      eliminando = null;
    }}
  />

  <CopiarActividadesModal
    bind:isOpen={showCopiar}
    idCurso={curso.id_curso}
    {actividades}
    preseleccion={copiarPreseleccion}
    onClose={() => (showCopiar = false)}
  />
</CursoDocenteLayout>
