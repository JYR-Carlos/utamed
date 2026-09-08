<script lang="ts">
  /**
   * Actividades del curso — /docente/cursos/{curso}/actividades.
   *
   * Centro operativo donde se planifica y se abre el semestre (lámina
   * «Actividades del curso (docente)»). Dos vistas sobre los mismos datos —
   * Lista densa y Kanban por estado— compartiendo una sola barra de filtros.
   *
   * La distinción que gobierna toda la pantalla:
   *
   *   • El **estado** (planificada / activa / cerrada) lo pone el sistema: se
   *     deriva de `visible` + `fecha_limite` (ver `utils/actividadEstado`). No
   *     hay columna que editar.
   *   • La **visibilidad al alumno** la pone el docente, se cambia muchas veces
   *     al día y por eso vive como interruptor dentro de la propia fila, con
   *     guardado optimista y micro-feedback en la fila — nunca un modal.
   *
   * Las dos caras de rol siguen el precedente de `CursoDetalle.svelte`: el
   * controlador sigue enviando todas las actividades del curso y el alcance del
   * docente de componente se aplica en frontend (`mis_componentes_ids`), con el
   * filtro de componente puesto y bloqueado. Donde no hay permiso no se
   * deshabilita nada: la acción sencillamente no se dibuja.
   *
   * Tablas implicadas: agenda.actividad, agenda.actividad_asignada_grupo
   * (grupos y notas), agenda.agenda (entregas), curso.componente, curso.unidad.
   */
  import { Link, page } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import {
    ArrowLeft,
    ChevronRight,
    ClipboardList,
    Columns3,
    EyeOff,
    FilterX,
    List,
    Lock,
    Plus,
    Search,
    X,
  } from 'lucide-svelte';
  import {
    ActividadForm,
    createActividad,
    updateActividad,
    deleteActividad,
    toggleVisibilidadActividad,
  } from '@/modules/resources/actividad';
  import ActividadesPorEstado from './components/ActividadesPorEstado.svelte';
  import ActividadesTabla from '@/components/docente/ActividadesTabla.svelte';
  import ActividadEliminarDialog from '@/components/docente/ActividadEliminarDialog.svelte';
  import EnunciadoModal from '@/components/docente/EnunciadoModal.svelte';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';
  import type { Actividad } from '@/types/actividad';
  import { formatDate } from '@/utils/formatters';
  import { estadoActividad, type EstadoActividad, type ToggleVisibilidad } from '@/utils/actividadEstado';

  interface ComponenteCurso {
    id_componente: number;
    tipo_componente?: { tipo: string } | null;
    /** true si ESTE docente dicta el componente (lo calcula el controlador). */
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
      cod_curso?: string;
      cod_asignatura?: string | null;
      asignatura_nombre?: string | null;
      letra_grupo?: string | null;
      agno_real?: number;
      semestre_real?: number;
      fecha_inicio?: string | null;
      fecha_fin?: string | null;
      total_estudiantes?: number;
      es_titular_curso?: boolean;
      mis_componentes_ids?: number[];
      userPermissions?: Permission[];
    };
    actividades: Actividad[];
    componentes: ComponenteCurso[];
    unidades: UnidadCurso[];
    reglas_enunciado?: ReglasEnunciado;
  }

  let { curso, actividades, componentes, unidades, reglas_enunciado }: Props = $props();

  /** Fallback sólo por si el backend deja de enviar la config de archivos. */
  const REGLAS_DEFAULT: ReglasEnunciado = {
    extensiones_pdf: ['pdf'],
    extensiones_img: ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'svg', 'tiff'],
    max_mb_pdf: 100,
    max_mb_imagen: 50,
  };
  const reglas = $derived(reglas_enunciado ?? REGLAS_DEFAULT);

  // ── Rol y permisos ────────────────────────────────────────────────────────

  const esTitular = $derived(curso.es_titular_curso ?? false);
  const misComponentesIds = $derived(curso.mis_componentes_ids ?? []);

  const canCreate = $derived(
    esTitular || hasPermission(curso.userPermissions ?? [], 'actividades:crear'),
  );
  const canEdit = $derived(
    esTitular || hasPermission(curso.userPermissions ?? [], 'actividades:editar'),
  );
  const canDelete = $derived(
    esTitular || hasPermission(curso.userPermissions ?? [], 'actividades:eliminar'),
  );

  /**
   * Alcance de los datos, no del diseño: el titular ve el curso entero; el
   * docente de componente, sólo lo que dicta. Mismo criterio que CursoDetalle.
   */
  const actividadesDelRol = $derived(
    esTitular
      ? actividades
      : actividades.filter((a) => misComponentesIds.includes(a.id_componente ?? -1)),
  );

  /** Componentes por los que se puede filtrar y sobre los que se puede crear. */
  const componentesDelRol = $derived(
    esTitular ? componentes : componentes.filter((c) => c.es_mio),
  );

  /** Con un solo componente el filtro no se abre: viene puesto y bloqueado. */
  const componenteBloqueado = $derived(!esTitular && componentesDelRol.length === 1);

  const unidadesOrdenadas = $derived(
    [...unidades].sort((a, b) => (a.num_unidad ?? 0) - (b.num_unidad ?? 0)),
  );

  // ── Vista y filtros ───────────────────────────────────────────────────────

  type Vista = 'lista' | 'kanban';
  type ClaveFiltro = 'busqueda' | 'componente' | 'unidad' | 'estado' | 'visibilidad';

  let vista = $state<Vista>('lista');
  let busqueda = $state('');
  let filtroComponente = $state<number | 'todos'>('todos');
  let filtroUnidad = $state<number | 'todas'>('todas');
  let filtroEstado = $state<EstadoActividad | 'todos'>('todos');
  let filtroVisibilidad = $state<'todas' | 'visibles' | 'ocultas'>('todas');

  const ahora = new Date();

  /**
   * Aplica la barra de filtros. `omitir` deja uno fuera para poder responder
   * «¿cuántos resultados saldrían si quito este filtro?» sin duplicar la lógica.
   */
  function aplicarFiltros(lista: Actividad[], omitir: ClaveFiltro | null = null): Actividad[] {
    const q = busqueda.trim().toLowerCase();

    return lista.filter((a) => {
      if (omitir !== 'busqueda' && q && !a.nombre.toLowerCase().includes(q)) return false;
      if (omitir !== 'componente' && filtroComponente !== 'todos' && a.id_componente !== filtroComponente)
        return false;
      if (omitir !== 'unidad' && filtroUnidad !== 'todas' && a.id_unidad !== filtroUnidad) return false;
      // En Kanban el estado ES la columna, así que su filtro queda sin uso.
      if (
        omitir !== 'estado' &&
        vista === 'lista' &&
        filtroEstado !== 'todos' &&
        estadoActividad(a, ahora) !== filtroEstado
      )
        return false;
      if (
        omitir !== 'visibilidad' &&
        filtroVisibilidad !== 'todas' &&
        a.visible !== (filtroVisibilidad === 'visibles')
      )
        return false;
      return true;
    });
  }

  const actividadesFiltradas = $derived(aplicarFiltros(actividadesDelRol));

  function limpiarFiltros() {
    busqueda = '';
    if (!componenteBloqueado) filtroComponente = 'todos';
    filtroUnidad = 'todas';
    filtroEstado = 'todos';
    filtroVisibilidad = 'todas';
  }

  /** Filtros activos, cada uno con su forma de quitarse (lámina d). */
  const chipsActivos = $derived.by(() => {
    const chips: Array<{ clave: ClaveFiltro; etiqueta: string; quitar: () => void }> = [];

    if (busqueda.trim()) {
      chips.push({ clave: 'busqueda', etiqueta: `«${busqueda.trim()}»`, quitar: () => (busqueda = '') });
    }
    if (filtroComponente !== 'todos' && !componenteBloqueado) {
      const c = componentesDelRol.find((x) => x.id_componente === filtroComponente);
      chips.push({
        clave: 'componente',
        etiqueta: c?.tipo_componente?.tipo ?? 'Componente',
        quitar: () => (filtroComponente = 'todos'),
      });
    }
    if (filtroUnidad !== 'todas') {
      const u = unidadesOrdenadas.find((x) => x.id_unidad === filtroUnidad);
      chips.push({
        clave: 'unidad',
        etiqueta: u ? `Unidad ${u.num_unidad ?? ''} ${u.nombre}`.trim() : 'Unidad',
        quitar: () => (filtroUnidad = 'todas'),
      });
    }
    if (filtroEstado !== 'todos' && vista === 'lista') {
      const rotulo = { planificada: 'Planificada', activa: 'Activa', cerrada: 'Cerrada' };
      chips.push({
        clave: 'estado',
        etiqueta: rotulo[filtroEstado],
        quitar: () => (filtroEstado = 'todos'),
      });
    }
    if (filtroVisibilidad !== 'todas') {
      chips.push({
        clave: 'visibilidad',
        etiqueta: filtroVisibilidad === 'visibles' ? 'Visible' : 'Oculta',
        quitar: () => (filtroVisibilidad = 'todas'),
      });
    }

    return chips;
  });

  /**
   * Qué filtro conviene soltar para volver a ver algo. Se calcula con los datos
   * reales: si quitar ninguno devuelve resultados, no se sugiere nada.
   */
  const sugerencia = $derived.by(() => {
    if (chipsActivos.length < 2) return null;
    let mejor: { etiqueta: string; total: number; quitar: () => void } | null = null;
    for (const chip of chipsActivos) {
      const total = aplicarFiltros(actividadesDelRol, chip.clave).length;
      if (total > 0 && (mejor === null || total > mejor.total)) {
        mejor = { etiqueta: chip.etiqueta, total, quitar: chip.quitar };
      }
    }
    return mejor;
  });

  // ── Conteos honestos ──────────────────────────────────────────────────────

  const conteoPorEstado = $derived.by(() => {
    const c: Record<EstadoActividad, number> = { planificada: 0, activa: 0, cerrada: 0 };
    for (const a of actividadesFiltradas) c[estadoActividad(a, ahora)]++;
    return c;
  });

  /**
   * Ocultas al alumno. Se calcula sobre las props, no sobre el estado optimista:
   * por eso el contador sólo se mueve cuando el servidor confirma el guardado.
   */
  const ocultasAlAlumno = $derived(actividadesDelRol.filter((a) => !a.visible).length);

  // ── Modales ───────────────────────────────────────────────────────────────

  let showForm = $state(false);
  let showDelete = $state(false);
  let showEnunciado = $state(false);
  let isLoading = $state(false);
  let editando = $state<Actividad | null>(null);
  let eliminando = $state<Actividad | null>(null);
  let conEnunciado = $state<Actividad | null>(null);
  let formErrors = $state<Record<string, string>>({});

  // ── Guardado optimista del interruptor de visibilidad (lámina e) ──────────

  let toggles = $state<Record<number, ToggleVisibilidad>>({});
  const temporizadores = new Map<number, ReturnType<typeof setTimeout>>();

  /**
   * El toggle ya se explica solo dentro de la fila; un banner de flash arriba
   * sería un segundo aviso para la misma acción. Bandera NO reactiva a
   * propósito: sólo la leen los $effect de flash, no la plantilla.
   */
  let saltarFlashDeToggle = false;

  function handleToggleVisible(act: Actividad) {
    const id = act.id_actividad;
    const deseado = !act.visible;

    clearTimeout(temporizadores.get(id));
    temporizadores.delete(id);

    toggles[id] = { fase: 'guardando', optimista: deseado };
    saltarFlashDeToggle = true;

    toggleVisibilidadActividad(curso.id_curso, id, {
      onSuccess: () => {
        // El controlador atrapa sus propias excepciones y responde 302 igual,
        // así que «éxito HTTP» no basta: se compara contra el dato que vuelve.
        const frescas = (($page.props as any).actividades ?? []) as Actividad[];
        const fresca = frescas.find((a) => a.id_actividad === id);
        const guardado = fresca ? fresca.visible === deseado : true;

        if (!guardado) {
          toggles[id] = { fase: 'error', optimista: act.visible };
          return;
        }

        toggles[id] = { fase: 'ok', optimista: deseado };
        temporizadores.set(
          id,
          setTimeout(() => {
            delete toggles[id];
            temporizadores.delete(id);
          }, 2000),
        );
      },
      onError: () => {
        toggles[id] = { fase: 'error', optimista: act.visible };
      },
    });
  }

  // ── Flash del servidor ────────────────────────────────────────────────────

  let flashError = $state<string | undefined>(undefined);
  let flashSuccess = $state<string | undefined>(undefined);

  $effect(() => {
    const pageErrors = ($page.props as any).errors as Record<string, string> | undefined;
    if (pageErrors && Object.keys(pageErrors).length > 0) {
      formErrors = pageErrors;
      isLoading = false;
    }
  });

  $effect(() => {
    const err = ($page.props as any).flash?.error as string | undefined;
    if (!err) return;
    flashError = err;
    const t = setTimeout(() => (flashError = undefined), 5000);
    return () => clearTimeout(t);
  });

  $effect(() => {
    const ok = ($page.props as any).flash?.success as string | undefined;
    if (!ok) return;
    if (saltarFlashDeToggle) {
      saltarFlashDeToggle = false;
      return;
    }
    flashSuccess = ok;
    const t = setTimeout(() => (flashSuccess = undefined), 4000);
    return () => clearTimeout(t);
  });

  // ── Handlers de CRUD ──────────────────────────────────────────────────────

  function abrirCrear() {
    editando = null;
    formErrors = {};
    showForm = true;
  }

  function abrirEditar(act: Actividad) {
    editando = act;
    formErrors = {};
    showForm = true;
  }

  function handleSubmit(data: Partial<Actividad>) {
    isLoading = true;
    formErrors = {};

    const done = () => {
      showForm = false;
      editando = null;
      isLoading = false;
      formErrors = {};
    };
    const fail = (errors: Record<string, string>) => {
      formErrors = errors;
      isLoading = false;
    };

    if (editando) {
      updateActividad(curso.id_curso, editando.id_actividad, data, { onSuccess: done, onError: fail });
    } else {
      createActividad(curso.id_curso, data, { onSuccess: done, onError: fail });
    }
  }

  function abrirEliminar(act: Actividad) {
    eliminando = act;
    showDelete = true;
  }

  function handleDelete() {
    if (!eliminando) return;
    isLoading = true;
    deleteActividad(curso.id_curso, eliminando.id_actividad, {
      onSuccess: () => {
        showDelete = false;
        eliminando = null;
        isLoading = false;
      },
      onError: () => (isLoading = false),
    });
  }

  /** Salida barata del diálogo destructivo: ocultar en vez de borrar. */
  function ocultarDesdeDialogo() {
    if (!eliminando) return;
    const act = eliminando;
    showDelete = false;
    eliminando = null;
    handleToggleVisible(act);
  }

  function abrirEnunciado(act: Actividad) {
    conEnunciado = act;
    showEnunciado = true;
  }

  // ── Lenguaje visual compartido (mismo vocabulario que CursoDetalle) ───────

  const CARD = 'bg-white border border-[#E5E7EB] rounded-xl shadow-[0_1px_3px_rgba(0,0,0,.08)]';
  const BTN_PRIMARY =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#002F6C] bg-[#002F6C] px-4 py-2.5 text-[13.5px] font-semibold text-white transition-colors hover:bg-[#1B4789]';
  const BTN_OUTLINE =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2 text-[13px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA]';
  const BTN_GHOST =
    'inline-flex items-center gap-[7px] rounded-lg border border-transparent px-3 py-2 text-[13px] font-medium text-[#002F6C] transition-colors hover:bg-[#F5F1EA]';
  const PILL_AZUL =
    'inline-flex items-center gap-1.5 rounded-full border border-[#C9D6E6] bg-[#E8EDF5] px-2.5 py-0.5 text-[12px] font-semibold text-[#002F6C]';
  const PILL_NEUTRA =
    'inline-flex items-center gap-1.5 rounded-full border border-[#E5E7EB] bg-[#F5F1EA] px-2.5 py-0.5 text-[12px] font-medium text-[#5A5E6E]';
  const SELECT_FILTRO =
    'h-[38px] rounded-lg border border-[#D6D9E0] bg-white px-2.5 text-[13px] font-medium text-[#1A1A24] outline-none transition-colors hover:bg-[#FCFBF9] focus:border-[#002F6C]';
  const SEG_ACTIVO =
    'inline-flex items-center gap-1.5 rounded-[7px] border border-[#D6D9E0] bg-white px-3 py-1.5 text-[13px] font-semibold text-[#1A1A24] shadow-[0_1px_2px_rgba(0,0,0,.05)]';
  const SEG_INACTIVO =
    'inline-flex items-center gap-1.5 rounded-[7px] border border-transparent px-3 py-1.5 text-[13px] font-medium text-[#5A5E6E] transition-colors hover:text-[#1A1A24]';
</script>

<DocenteLayout>
  <div class="min-h-screen bg-white pb-16">
    <div class="mx-auto flex max-w-[1440px] flex-col gap-4 px-6 py-6 sm:px-10">
      <!-- ── Ruta ── -->
      <nav class="flex flex-wrap items-center gap-1.5 text-[13px] text-[#5A5E6E]" aria-label="Ruta de navegación">
        <Link
          href="/docente/cursos"
          class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 font-medium text-[#1A1A24] no-underline transition-colors hover:bg-[#F5F1EA] hover:text-[#002F6C]"
        >
          <ArrowLeft size={15} aria-hidden="true" />
          Mis Cursos
        </Link>
        <ChevronRight size={13} class="text-[#98A0AE]" aria-hidden="true" />
        <Link
          href="/docente/cursos/{curso.id_curso}"
          class="rounded-lg px-2 py-1.5 font-medium text-[#1A1A24] no-underline transition-colors hover:bg-[#F5F1EA] hover:text-[#002F6C]"
        >
          {curso.cod_curso ?? 'Curso'}
        </Link>
        <ChevronRight size={13} class="text-[#98A0AE]" aria-hidden="true" />
        <span class="px-1 font-medium text-[#1A1A24]" aria-current="page">Actividades</span>
      </nav>

      {#if flashError}
        <p
          class="m-0 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-[13px] font-medium text-[#B91C1C]"
          role="alert"
        >
          {flashError}
        </p>
      {/if}
      {#if flashSuccess}
        <p
          class="m-0 rounded-lg border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-3 text-[13px] font-medium text-[#047857]"
          role="status"
        >
          {flashSuccess}
        </p>
      {/if}

      <section class="{CARD} flex flex-col gap-4 p-5">
        <!-- ── Cabecera del curso ── -->
        <div class="flex flex-wrap items-end gap-6">
          <div class="flex min-w-0 flex-col gap-1.5">
            {#if curso.cod_asignatura}
              <span class="font-mono text-[12px] text-[#5A5E6E]">{curso.cod_asignatura}</span>
            {/if}
            <div class="flex flex-wrap items-center gap-2.5">
              <h1 class="m-0 text-[22px] font-semibold tracking-[-0.01em] text-[#1A1A24]">
                {curso.asignatura_nombre ?? curso.cod_curso ?? 'Actividades del curso'}
              </h1>
              {#if curso.letra_grupo}
                <span class={PILL_AZUL}>Grupo {curso.letra_grupo}</span>
              {/if}
              {#if curso.agno_real && curso.semestre_real}
                <span class={PILL_NEUTRA}>{curso.agno_real}-{curso.semestre_real}</span>
              {/if}
            </div>
            <p class="m-0 text-[12.5px] text-[#5A5E6E]">
              {#if curso.fecha_inicio && curso.fecha_fin}
                {formatDate(curso.fecha_inicio)} a {formatDate(curso.fecha_fin)} ·
              {/if}
              {curso.total_estudiantes ?? 0}
              {curso.total_estudiantes === 1 ? 'estudiante' : 'estudiantes'} · actúas como
              <strong class="font-semibold text-[#1A1A24]"
                >{esTitular ? 'docente titular' : 'docente de componente'}</strong
              >
            </p>
          </div>

          <div class="ml-auto flex flex-none flex-wrap items-center gap-2.5">
            <!-- Conmutador de vista -->
            <div
              class="flex items-center gap-0.5 rounded-[9px] border border-[#E5E7EB] bg-[#F5F1EA] p-[3px]"
              role="group"
              aria-label="Vista de las actividades"
            >
              <button
                type="button"
                class={vista === 'lista' ? SEG_ACTIVO : SEG_INACTIVO}
                aria-pressed={vista === 'lista'}
                onclick={() => (vista = 'lista')}
              >
                <List size={15} class={vista === 'lista' ? 'text-[#002F6C]' : ''} aria-hidden="true" />
                Lista
              </button>
              <button
                type="button"
                class={vista === 'kanban' ? SEG_ACTIVO : SEG_INACTIVO}
                aria-pressed={vista === 'kanban'}
                onclick={() => (vista = 'kanban')}
              >
                <Columns3
                  size={15}
                  class={vista === 'kanban' ? 'text-[#002F6C]' : ''}
                  aria-hidden="true"
                />
                Kanban
              </button>
            </div>

            {#if canCreate}
              <button type="button" class={BTN_PRIMARY} onclick={abrirCrear}>
                <Plus size={16} aria-hidden="true" />
                Nueva actividad
              </button>
            {/if}
          </div>
        </div>

        <!-- ── Barra de filtros, común a las dos vistas ── -->
        {#if actividadesDelRol.length > 0}
          <div class="flex flex-wrap items-center gap-2 border-t border-[#E5E7EB] pt-3.5">
            <label
              class="inline-flex h-[38px] w-[280px] items-center gap-2 rounded-lg border border-[#D6D9E0] bg-white px-3 focus-within:border-[#002F6C]"
            >
              <Search size={15} class="shrink-0 text-[#98A0AE]" aria-hidden="true" />
              <span class="sr-only">Buscar actividades por nombre</span>
              <input
                type="search"
                bind:value={busqueda}
                placeholder="Buscar por nombre"
                class="min-w-0 flex-1 bg-transparent text-[13.5px] text-[#1A1A24] outline-none placeholder:text-[#98A0AE]"
              />
            </label>

            {#if componenteBloqueado}
              <!-- El filtro viene puesto: sin `actividades:ver` en el resto de
                   componentes, no hay nada más que abrir. -->
              <span
                class="inline-flex h-[38px] items-center gap-2 rounded-lg border border-[#C9D6E6] bg-[#E8EDF5] px-3 text-[13px] text-[#002F6C]"
                title="Sólo dictas este componente"
              >
                <span>Componente</span>
                <strong class="font-semibold"
                  >{componentesDelRol[0]?.tipo_componente?.tipo ?? '—'}</strong
                >
                <Lock size={13} aria-hidden="true" />
              </span>
            {:else if componentesDelRol.length > 1}
              <label class="inline-flex items-center">
                <span class="sr-only">Filtrar por componente</span>
                <select bind:value={filtroComponente} class={SELECT_FILTRO}>
                  <option value="todos">Componente: todos</option>
                  {#each componentesDelRol as c (c.id_componente)}
                    <option value={c.id_componente}>{c.tipo_componente?.tipo ?? 'Componente'}</option>
                  {/each}
                </select>
              </label>
            {/if}

            {#if unidadesOrdenadas.length > 0}
              <label class="inline-flex items-center">
                <span class="sr-only">Filtrar por unidad</span>
                <select bind:value={filtroUnidad} class={SELECT_FILTRO}>
                  <option value="todas">Unidad: todas</option>
                  {#each unidadesOrdenadas as u (u.id_unidad)}
                    <option value={u.id_unidad}
                      >{u.num_unidad != null ? `${u.num_unidad} · ` : ''}{u.nombre}</option
                    >
                  {/each}
                </select>
              </label>
            {/if}

            {#if vista === 'lista'}
              <label class="inline-flex items-center">
                <span class="sr-only">Filtrar por estado</span>
                <select bind:value={filtroEstado} class={SELECT_FILTRO}>
                  <option value="todos">Estado: todos</option>
                  <option value="planificada">Planificada</option>
                  <option value="activa">Activa</option>
                  <option value="cerrada">Cerrada</option>
                </select>
              </label>
            {:else}
              <span
                class="inline-flex h-[38px] items-center gap-2 rounded-lg border border-[#D6D9E0] bg-[#F5F1EA] px-3 text-[13px] text-[#98A0AE]"
              >
                <span>Estado</span>
                <strong class="font-semibold">Todos</strong>
                <Lock size={13} aria-hidden="true" />
              </span>
            {/if}

            <label class="inline-flex items-center">
              <span class="sr-only">Filtrar por visibilidad</span>
              <select bind:value={filtroVisibilidad} class={SELECT_FILTRO}>
                <option value="todas">Visibilidad: todas</option>
                <option value="visibles">Visible</option>
                <option value="ocultas">Oculta</option>
              </select>
            </label>

            {#if vista === 'kanban'}
              <span class="text-[11.5px] text-pretty text-[#5A5E6E]">
                En Kanban el estado es la columna, así que su filtro queda sin uso.
              </span>
            {/if}

            {#if ocultasAlAlumno > 0}
              <span
                class="ml-auto inline-flex items-center gap-[7px] rounded-full border border-[#FDE68A] bg-[#FFFBEB] px-3 py-1.5 text-[12px] font-semibold text-[#B45309]"
              >
                <EyeOff size={14} aria-hidden="true" />
                {ocultasAlAlumno}
                {ocultasAlAlumno === 1 ? 'oculta' : 'ocultas'} al alumno
              </span>
            {/if}
          </div>
        {/if}

        <!-- ── Cuerpo ── -->
        {#if actividadesDelRol.length === 0}
          <!-- Vacío «curso por construir»: ofrece la acción. -->
          <div
            class="flex flex-col items-center gap-3.5 rounded-xl border border-dashed border-[#D6D9E0] bg-[#FCFBF9] px-6 py-9 text-center"
          >
            <ClipboardList size={30} class="text-[#98A0AE]" aria-hidden="true" />
            <div class="flex max-w-[440px] flex-col gap-1.5">
              <p class="m-0 text-[15px] font-semibold text-[#1A1A24]">
                {esTitular
                  ? 'El curso todavía no tiene actividades'
                  : 'No hay actividades en los componentes que dictas'}
              </p>
              <p class="m-0 text-[13px] text-pretty text-[#5A5E6E]">
                {#if esTitular}
                  Cada actividad se ancla a una unidad del syllabus y a un componente. Al crearla
                  queda planificada y oculta: el alumno la verá cuando tú la hagas visible.
                {:else}
                  El titular del curso puede tener actividades en otros componentes; aquí sólo se
                  listan las de los tuyos.
                {/if}
              </p>
            </div>
            {#if canCreate}
              <button type="button" class={BTN_PRIMARY} onclick={abrirCrear}>
                <Plus size={16} aria-hidden="true" />
                Crear primera actividad
              </button>
            {/if}
          </div>
        {:else if actividadesFiltradas.length === 0}
          <!-- Vacío «consulta demasiado estrecha»: ofrece deshacerla, no crear. -->
          <div class="flex flex-col gap-3">
            {#if chipsActivos.length > 0}
              <div class="flex flex-wrap items-center gap-2">
                {#each chipsActivos as chip (chip.clave)}
                  <button
                    type="button"
                    class="inline-flex h-9 items-center gap-[7px] rounded-lg border border-[#002F6C] bg-[#E8EDF5] px-2.5 text-[12.5px] font-semibold text-[#002F6C] transition-colors hover:bg-[#DCE6F1]"
                    onclick={chip.quitar}
                  >
                    {chip.etiqueta}
                    <X size={13} aria-hidden="true" />
                  </button>
                {/each}
              </div>
            {/if}
            <div
              class="flex flex-col items-center gap-3 rounded-xl border border-[#E5E7EB] bg-white px-6 py-9 text-center"
            >
              <FilterX size={26} class="text-[#98A0AE]" aria-hidden="true" />
              <div class="flex max-w-[420px] flex-col gap-1.5">
                <p class="m-0 text-[14.5px] font-semibold text-[#1A1A24]">
                  Ninguna de las {actividadesDelRol.length}
                  {actividadesDelRol.length === 1 ? 'actividad cumple' : 'actividades cumple'}
                  {chipsActivos.length === 1 ? 'el filtro' : `los ${chipsActivos.length} filtros`}
                </p>
                {#if sugerencia}
                  <p class="m-0 text-[13px] text-pretty text-[#5A5E6E]">
                    Quita <strong class="font-semibold text-[#1A1A24]">{sugerencia.etiqueta}</strong>
                    para ver {sugerencia.total}
                    {sugerencia.total === 1 ? 'resultado' : 'resultados'}.
                  </p>
                {/if}
              </div>
              <div class="flex flex-wrap justify-center gap-2">
                {#if sugerencia}
                  <button type="button" class={BTN_OUTLINE} onclick={sugerencia.quitar}>
                    Quitar «{sugerencia.etiqueta}»
                  </button>
                {/if}
                <button type="button" class={BTN_GHOST} onclick={limpiarFiltros}>
                  Limpiar todos los filtros
                </button>
              </div>
            </div>
          </div>
        {:else if vista === 'lista'}
          <ActividadesTabla
            actividades={actividadesFiltradas}
            idCurso={curso.id_curso}
            {canEdit}
            {canDelete}
            {toggles}
            onToggleVisible={handleToggleVisible}
            onEdit={abrirEditar}
            onDelete={abrirEliminar}
            onEnunciado={abrirEnunciado}
          />

          <!-- Pie: qué se está viendo y cómo se reparte. Sin paginación: el
               controlador entrega el curso completo en una sola carga. -->
          <div
            class="flex flex-wrap items-center gap-4 rounded-lg border border-[#E5E7EB] bg-[#FCFBF9] px-4 py-2.5 text-[12px] text-[#5A5E6E]"
          >
            <span class="mr-auto">
              {actividadesFiltradas.length === actividadesDelRol.length
                ? `${actividadesDelRol.length} ${actividadesDelRol.length === 1 ? 'actividad' : 'actividades'}`
                : `${actividadesFiltradas.length} de ${actividadesDelRol.length} actividades`}
              · orden por fecha límite ascendente
            </span>
            <span class="font-mono tabular-nums">
              {conteoPorEstado.planificada} planificadas · {conteoPorEstado.activa} activas · {conteoPorEstado.cerrada}
              cerradas
            </span>
          </div>
        {:else}
          <ActividadesPorEstado actividades={actividadesFiltradas} idCurso={curso.id_curso} />
        {/if}
      </section>
    </div>
  </div>

  <ActividadForm
    bind:isOpen={showForm}
    {isLoading}
    editingActividad={editando}
    componentes={componentesDelRol}
    unidades={unidadesOrdenadas}
    errors={formErrors}
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

  <EnunciadoModal
    bind:isOpen={showEnunciado}
    idCurso={curso.id_curso}
    actividad={conEnunciado}
    {reglas}
    onClose={() => {
      showEnunciado = false;
      conEnunciado = null;
    }}
  />
</DocenteLayout>
