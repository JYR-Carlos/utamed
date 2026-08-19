<script lang="ts">
  /**
   * Componente: Slide-over de Gestión de Curso
   *
   * Panel lateral derecho (40% pantalla) que muestra detalles del curso
   * con Progressive Disclosure en tres pestañas:
   *   1. Secciones — sub-tabla de componentes + acción "+ Añadir Sección"
   *   2. Equipo Docente — info del docente titular + botón "Gestionar equipo"
   *   3. Configuración — datos de período, créditos y acceso al programa/syllabus
   *
   * Se activa haciendo clic en cualquier fila de la tabla principal.
   * La tabla de fondo queda levemente oscurecida (backdrop) con blur mínimo.
   */
  import { X, Plus, Edit2, Trash2, Users, BookOpen, ChevronRight, Calendar, Copy, UserPlus } from 'lucide-svelte';
  import { router } from '@inertiajs/svelte';
  import type { Curso, Componente } from '../types/curso.types';

  let isInscribiendo = $state(false);

  function handleInscribirAutomatica(cursoObj: Curso) {
    if (!cursoObj) return;
    isInscribiendo = true;
    router.post(`/admin/cursos/${cursoObj.id_curso}/inscripcion-automatica`, {}, {
      preserveScroll: true,
      onSuccess: (pageObj: any) => {
        isInscribiendo = false;
        const flashErr = pageObj?.props?.flash?.error;
        const flashSucc = pageObj?.props?.flash?.success;
        if (flashErr) {
          alert(`Error de Inscripción Intranet:\n\n${flashErr}`);
        } else if (flashSucc) {
          alert(`Inscripción Intranet:\n\n${flashSucc}`);
        }
      },
      onError: (err: any) => {
        isInscribiendo = false;
        const msg = typeof err === 'string' ? err : (err?.error || 'Error al conectar con la Intranet');
        alert(`Error al procesar inscripción automática:\n\n${msg}`);
      },
      onFinish: () => {
        isInscribiendo = false;
      }
    });
  }



  interface Props {
    isOpen?: boolean;
    curso?: Curso | null;
    onClose?: () => void;
    onEdit?: (curso: Curso) => void;
    onDelete?: (curso: Curso) => void;
    onTeam?: (curso: Curso) => void;
    onSyllabus?: (curso: Curso) => void;
    onCopy?: (curso: Curso) => void;
    onAddComponente?: (curso: Curso) => void;
    onEditComponente?: (curso: Curso, comp: Componente) => void;
    onDeleteComponente?: (curso: Curso, comp: Componente) => void;
  }

  let {
    isOpen = $bindable(false),
    curso = null,
    onClose = () => {},
    onEdit = () => {},
    onDelete = () => {},
    onTeam = () => {},
    onSyllabus = () => {},
    onCopy = () => {},
    onAddComponente = () => {},
    onEditComponente = () => {},
    onDeleteComponente = () => {},
  }: Props = $props();

  type Tab = 'secciones' | 'equipo' | 'configuracion';
  let activeTab = $state<Tab>('secciones');

  // ─── Estado del editor de fechas límite ────────────────────────────────
  let fechaBasico = $state('');
  let fechaSyllabus = $state('');
  let savingFechas = $state(false);
  let fechasError = $state('');
  let fechasSuccess = $state(false);
  let editingFechaBasico = $state(false);
  let editingFechaSyllabus = $state(false);

  const hasPrograma = $derived(
    Boolean(curso?.has_programa || curso?.id_programa || curso?.programa_estado),
  );

  const programaEstado = $derived(curso?.programa_estado ?? null);

  const basicoYaCreado = $derived.by(() => {
    return (
      programaEstado === 'BASICO_COMPLETO' ||
      programaEstado === 'COMPLETO' ||
      programaEstado === 'APROBADO' ||
      programaEstado === 'PUBLICADO' ||
      programaEstado === 'ENVIADO'
    );
  });

  const completoYaCreado = $derived.by(() => {
    return (
      programaEstado === 'COMPLETO' ||
      programaEstado === 'APROBADO' ||
      programaEstado === 'PUBLICADO' ||
      programaEstado === 'ENVIADO'
    );
  });

  function isoToInput(iso: string | null | undefined): string {
    if (!iso) return '';
    return iso.slice(0, 10);
  }

  function diasRestantes(dateStr: string): number {
    const [y, m, d] = dateStr.split('-').map(Number);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(y, m - 1, d);
    return Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
  }

  function formatFechaLarga(dateStr: string): string {
    const [y, m, d] = dateStr.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-CL', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  }

  // Reinicia la pestaña activa y los campos de fecha cada vez que se abre el panel
  $effect(() => {
    if (isOpen && curso) {
      activeTab = 'secciones';
      fechaBasico = isoToInput(curso.fecha_limite_entrega_basico);
      fechaSyllabus = isoToInput(curso.fecha_limite_entrega_syllabus);
      fechasError = '';
      fechasSuccess = false;
      editingFechaBasico = false;
      editingFechaSyllabus = false;
    }
  });

  function guardarFechas() {
    if (!curso) return;
    savingFechas = true;
    fechasError = '';
    fechasSuccess = false;
    router.put(
      `/admin/cursos/${curso.id_curso}/programa/fechas`,
      {
        fecha_limite_entrega_basico: fechaBasico || null,
        fecha_limite_entrega_syllabus: fechaSyllabus || null,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          savingFechas = false;
          fechasSuccess = true;
          editingFechaBasico = false;
          editingFechaSyllabus = false;
        },
        onError: (errors) => {
          savingFechas = false;
          fechasError = Object.values(errors).flat().join('. ');
        },
      },
    );
  }

  function handleClose() {
    isOpen = false;
    onClose();
  }

  function handleBackdropKey(e: KeyboardEvent) {
    if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') handleClose();
  }

  function getDocenteName(comp: Componente): string {
    const d = comp.docentes?.[0] as any;
    if (!d) return 'Sin asignar';
    return d.nombre_completo || `${d.nombre1 ?? ''} ${d.apellido1 ?? ''}`.trim() || 'Sin nombre';
  }

  function getPeriodo(c: Curso): string {
    const ap = c.asignacionPlan;
    if (ap?.semestre_planificado && ap?.agno_planificado) {
      return `Año ${ap.agno_planificado} · Semestre ${ap.semestre_planificado}`;
    }
    if (c.numero_semestre) return `Semestre ${c.numero_semestre}`;
    return '—';
  }

  const TABS: { id: Tab; label: string }[] = [
    { id: 'secciones', label: 'Componentes' },
    { id: 'equipo', label: 'Equipo Docente' },
    { id: 'configuracion', label: 'Configuración' },
  ];
</script>

{#if isOpen && curso}
  <!-- Backdrop -->
  <!-- svelte-ignore a11y_interactive_supports_focus -->
  <div
    class="fixed inset-0 z-40 bg-gray-900/25 backdrop-blur-[1px]"
    role="button"
    aria-label="Cerrar panel"
    onclick={handleClose}
    onkeydown={handleBackdropKey}
  ></div>

  <!-- Panel lateral -->
  <div
    class="fixed top-0 right-0 z-50 h-full w-full max-w-[560px] bg-white shadow-2xl flex flex-col border-l border-gray-200 slideover-panel"
    role="dialog"
    aria-modal="true"
    aria-labelledby="slideover-title"
  >
    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="flex items-start gap-4 px-6 pt-5 pb-4 border-b border-gray-100 flex-shrink-0">
      <div class="flex-1 min-w-0">
        <p class="text-[11px] font-semibold text-blue-600 uppercase tracking-widest mb-1.5">
          Gestión de Curso
        </p>
        <h2 id="slideover-title" class="text-base font-bold text-gray-900 leading-snug">
          {curso.asignatura_nombre || 'Curso sin nombre'}
        </h2>
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-2">
          <span class="font-mono text-[11px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded-md">
            {curso.cod_curso}
          </span>
          {#if curso.carrera_nombre}
            <span class="flex items-center gap-0.5 text-xs text-gray-500">
              <ChevronRight size={11} class="text-gray-300 flex-shrink-0" />
              {curso.carrera_nombre}
            </span>
          {/if}
          <span class="flex items-center gap-0.5 text-xs text-gray-400">
            <ChevronRight size={11} class="text-gray-300 flex-shrink-0" />
            {getPeriodo(curso)}
          </span>
        </div>
      </div>
      <button
        onclick={handleClose}
        class="mt-0.5 p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition flex-shrink-0"
        aria-label="Cerrar panel"
      >
        <X size={17} />
      </button>
    </div>

    <!-- ── Tabs ────────────────────────────────────────────────────────────── -->
    <div class="flex border-b border-gray-100 px-6 flex-shrink-0" role="tablist">
      {#each TABS as tab}
        <button
          role="tab"
          aria-selected={activeTab === tab.id}
          onclick={() => (activeTab = tab.id)}
          class="relative py-3 px-1 mr-7 text-sm font-medium transition-colors outline-none
            {activeTab === tab.id ? 'text-blue-600' : 'text-gray-500 hover:text-gray-800'}"
        >
          {tab.label}
          {#if activeTab === tab.id}
            <span class="absolute bottom-0 left-0 right-0 h-[2px] bg-blue-600 rounded-t-full"
            ></span>
          {/if}
        </button>
      {/each}
    </div>

    <!-- ── Contenido (scrollable) ──────────────────────────────────────────── -->
    <div class="flex-1 overflow-y-auto" role="tabpanel">
      <!-- Tab 1: Componentes.
           Esta pestaña los llamaba «secciones» mientras el modal que abre se
           titula «Crear componente» y el asistente pregunta «¿qué componentes
           tendrá este curso?»: son la misma cosa (cátedra, laboratorio,
           taller) y ahora se llaman igual en todas partes. -->
      {#if activeTab === 'secciones'}
        <div class="p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">
              Componentes del curso
              {#if (curso.componentes?.length ?? 0) > 0}
                <span
                  class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600"
                >
                  {curso.componentes!.length}
                </span>
              {/if}
            </h3>
            <button onclick={() => onAddComponente(curso)} class="btn btn-primary btn-sm">
              <Plus size={12} />
              Añadir componente
            </button>
          </div>

          {#if !curso.componentes || curso.componentes.length === 0}
            <div
              class="flex flex-col items-center justify-center py-14 text-center bg-gray-50 rounded-xl border-2 border-dashed border-gray-200"
            >
              <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                <Plus size={18} class="text-gray-400" />
              </div>
              <p class="text-sm font-medium text-gray-600">Sin componentes</p>
              <p class="text-xs text-gray-400 mt-1">
                Añade al menos un componente (cátedra, laboratorio o taller) para poder asignar
                docentes y actividades.
              </p>
            </div>
          {:else}
            <div class="rounded-xl border border-gray-200 overflow-hidden">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th
                      class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                      >Tipo</th
                    >
                    <th
                      class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                      >Docente</th
                    >
                    <th
                      class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                      >Acta</th
                    >
                    <th
                      class="px-4 py-2.5 text-right text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                      >Acciones</th
                    >
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  {#each curso.componentes as comp (comp.id_componente)}
                    <tr class="hover:bg-gray-50/80 transition-colors">
                      <td class="px-4 py-3 font-medium text-gray-800">
                        {comp.tipo_componente?.tipo ?? '—'}
                      </td>
                      <td class="px-4 py-3 text-gray-500 text-xs">
                        {getDocenteName(comp)}
                      </td>
                      <td class="px-4 py-3 text-center">
                        {#if comp.genera_acta}
                          <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-200"
                            >Sí</span
                          >
                        {:else}
                          <span class="text-gray-300 text-xs">—</span>
                        {/if}
                      </td>
                      <td class="px-4 py-3 text-right">
                        <!-- Iconos de 16 px en un objetivo de 36 px: los de
                             13 px con relleno 1.5 daban un área de pulsación
                             de unos 26 px, por debajo del mínimo. -->
                        <div class="flex items-center justify-end gap-0.5">
                          <button
                            onclick={() => onEditComponente(curso, comp)}
                            class="btn-icon"
                            aria-label="Editar componente"
                            title="Editar componente"
                          >
                            <Edit2 size={16} />
                          </button>
                          <button
                            onclick={() => onDeleteComponente(curso, comp)}
                            class="btn-icon btn-icon-danger"
                            aria-label="Eliminar componente"
                            title="Eliminar componente"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </div>

        <!-- Tab 2: Equipo Docente -->
      {:else if activeTab === 'equipo'}
        <div class="p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Equipo docente</h3>
            <button onclick={() => onTeam(curso)} class="btn btn-neutral btn-sm">
              <Users size={14} />
              Ver equipo completo
            </button>
          </div>

          {#if curso.docente_nombre}
            <!-- Docente titular card -->
            <div
              class="flex items-center gap-3.5 p-4 rounded-xl border border-gray-200 bg-gray-50/60"
            >
              <div
                class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 text-sm font-bold text-blue-600"
              >
                {(curso.docente_nombre || '?').charAt(0).toUpperCase()}
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 truncate">
                  {curso.docente_nombre}
                </p>
                {#if curso.docente_email}
                  <p class="text-xs text-gray-500 truncate">{curso.docente_email}</p>
                {/if}
                {#if curso.docente_cargo}
                  <p class="text-xs text-gray-400 mt-0.5">{curso.docente_cargo}</p>
                {/if}
              </div>
              <span
                class="flex-shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100"
              >
                Titular
              </span>
            </div>

            <!-- La pestaña sólo tiene sitio para el titular; el aviso dice qué
                 falta y el botón de arriba es dónde verlo, en vez de
                 mandar a buscar un control por su nombre. -->
            <p class="text-xs text-gray-400 text-center py-2">
              Aquí se muestra el docente titular. El resto del equipo está en «Ver equipo
              completo».
            </p>
          {:else}
            <div
              class="flex flex-col items-center justify-center py-14 text-center bg-gray-50 rounded-xl border-2 border-dashed border-gray-200"
            >
              <Users size={28} class="text-gray-300 mb-2" />
              <p class="text-sm font-medium text-gray-600">Sin docente asignado</p>
              <p class="text-xs text-gray-400 mt-1">
                Usa "Gestionar equipo" para asignar docentes.
              </p>
            </div>
          {/if}
        </div>

        <!-- Tab 3: Configuración / Syllabus -->
      {:else if activeTab === 'configuracion'}
        <div class="p-6 space-y-5">
          <h3 class="text-sm font-semibold text-gray-800">Configuración del Curso</h3>

          <!-- Programa card -->
          <div class="p-4 rounded-xl border border-gray-200 space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900">Programa Académico</p>
                <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                  {#if hasPrograma}
                    Programa en estado
                    <span class="font-medium capitalize">
                      {curso.programa_estado?.toLowerCase().replace('_', ' ') ?? 'disponible'}.
                    </span>
                  {:else}
                    No hay programa generado para este curso.
                  {/if}
                </p>
              </div>
              <button
                onclick={() => onSyllabus(curso)}
                class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 transition"
              >
                <BookOpen size={13} />
                {hasPrograma ? 'Ver programa' : 'Crear programa'}
              </button>
            </div>
          </div>

          <!-- Fechas límite de entrega -->
          <div class="space-y-3">
            <p
              class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-1.5"
            >
              <Calendar size={12} />
              Fechas límite de entrega
            </p>
            {#if !completoYaCreado}
              <div class="p-4 rounded-xl border border-gray-200 space-y-4">
                <!-- Fecha básico -->
                {#if !basicoYaCreado}
                  <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                      <span class="text-xs font-medium text-gray-700"
                        >Básico (programa sin contenidos)</span
                      >
                      {#if fechaBasico && !editingFechaBasico}
                        <button
                          onclick={() => (editingFechaBasico = true)}
                          class="text-xs text-blue-600 hover:underline">Cambiar</button
                        >
                      {/if}
                    </div>
                    {#if fechaBasico && !editingFechaBasico}
                      {@const dias = diasRestantes(fechaBasico)}
                      <div
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200"
                      >
                        <p class="flex-1 text-xs text-gray-500">
                          Vence el {formatFechaLarga(fechaBasico)}
                        </p>
                        <span
                          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                          {dias < 0
                            ? 'bg-red-100 text-red-700'
                            : dias <= 7
                              ? 'bg-amber-100 text-amber-700'
                              : 'bg-green-100 text-green-700'}"
                        >
                          {dias < 0
                            ? `Vencida hace ${Math.abs(dias)}d`
                            : dias === 0
                              ? 'Hoy'
                              : `${dias} días`}
                        </span>
                      </div>
                    {:else}
                      <input
                        id="fecha-basico"
                        type="date"
                        bind:value={fechaBasico}
                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      />
                    {/if}
                  </div>
                {/if}

                <!-- Fecha syllabus -->
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-700">Syllabus completo</span>
                    {#if fechaSyllabus && !editingFechaSyllabus}
                      <button
                        onclick={() => (editingFechaSyllabus = true)}
                        class="text-xs text-blue-600 hover:underline">Cambiar</button
                      >
                    {/if}
                  </div>
                  {#if fechaSyllabus && !editingFechaSyllabus}
                    {@const dias = diasRestantes(fechaSyllabus)}
                    <div
                      class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200"
                    >
                      <p class="flex-1 text-xs text-gray-500">
                        Vence el {formatFechaLarga(fechaSyllabus)}
                      </p>
                      <span
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                        {dias < 0
                          ? 'bg-red-100 text-red-700'
                          : dias <= 7
                            ? 'bg-amber-100 text-amber-700'
                            : 'bg-green-100 text-green-700'}"
                      >
                        {dias < 0
                          ? `Vencida hace ${Math.abs(dias)}d`
                          : dias === 0
                            ? 'Hoy'
                            : `${dias} días`}
                      </span>
                    </div>
                  {:else}
                    <input
                      id="fecha-syllabus"
                      type="date"
                      bind:value={fechaSyllabus}
                      class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                  {/if}
                </div>

                {#if fechasError}
                  <p class="text-xs text-red-600">{fechasError}</p>
                {/if}
                {#if fechasSuccess}
                  <p class="text-xs text-green-600">Fechas guardadas correctamente.</p>
                {/if}
                {#if (!basicoYaCreado && (!fechaBasico || editingFechaBasico)) || !fechaSyllabus || editingFechaSyllabus}
                  <button
                    onclick={guardarFechas}
                    disabled={savingFechas}
                    class="w-full flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60 transition"
                  >
                    {savingFechas ? 'Guardando…' : 'Guardar fechas'}
                  </button>
                {/if}
              </div>
            {/if}
          </div>

          <!-- Metadata grid -->
          <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
              Datos del período
            </p>
            <div class="grid grid-cols-2 gap-3">
              {#if curso.numero_semestre}
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                  <p class="text-[11px] text-gray-400 mb-1">Semestre en malla</p>
                  <p class="text-sm font-bold text-gray-900">{curso.numero_semestre}°</p>
                </div>
              {/if}
              {#if curso.asignacionPlan?.agno_planificado}
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                  <p class="text-[11px] text-gray-400 mb-1">Año académico</p>
                  <p class="text-sm font-bold text-gray-900">
                    {curso.asignacionPlan.agno_planificado}
                  </p>
                </div>
              {/if}
              {#if curso.asignacionPlan?.semestre_planificado}
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                  <p class="text-[11px] text-gray-400 mb-1">Semestre planificado</p>
                  <p class="text-sm font-bold text-gray-900">
                    {curso.asignacionPlan.semestre_planificado}°
                  </p>
                </div>
              {/if}
              {#if curso.creditos_sct}
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                  <p class="text-[11px] text-gray-400 mb-1">Créditos SCT</p>
                  <p class="text-sm font-bold text-gray-900">{curso.creditos_sct}</p>
                </div>
              {/if}
              {#if curso.es_colegiado !== undefined}
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                  <p class="text-[11px] text-gray-400 mb-1">Colegiado</p>
                  <p class="text-sm font-bold text-gray-900">{curso.es_colegiado ? 'Sí' : 'No'}</p>
                </div>
              {/if}
            </div>
          </div>
        </div>
      {/if}
    </div>

    <!-- ── Footer ──────────────────────────────────────────────────────────── -->
    <div
      class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex-shrink-0"
    >
      <button onclick={handleClose} class="btn btn-ghost"> Cerrar </button>
      <!-- Cuatro acciones con cuatro tratamientos distintos (texto, contorno
           verde, contorno violeta, sólido azul) no dejaban ver cuál es la
           principal. Ahora sólo «Editar curso» va destacada. -->
      <div class="flex items-center gap-2">
        <button
          onclick={() => handleInscribirAutomatica(curso!)}
          disabled={isInscribiendo}
          class="btn btn-neutral"
          title="Trae la nómina de estudiantes que la Intranet tiene inscritos en este curso"
        >
          <UserPlus size={14} />
          {isInscribiendo ? 'Inscribiendo…' : 'Inscribir desde Intranet'}
        </button>
        <button
          onclick={() => onCopy(curso)}
          class="btn btn-neutral"
          title="Copiar este curso en un nuevo período"
        >
          <Copy size={14} />
          Copiar
        </button>

        <button onclick={() => onEdit(curso)} class="btn btn-primary">
          <Edit2 size={14} />
          Editar curso
        </button>
      </div>
    </div>
  </div>
{/if}

<style>
  @keyframes slide-in {
    from {
      transform: translateX(100%);
      opacity: 0.8;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }

  .slideover-panel {
    animation: slide-in 0.22s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  }
</style>
