<script lang="ts">
  /**
   * Gestión de Estudiantes — Vista de Docente Titular.
   *
   * Muestra la lista completa de estudiantes inscritos en los
   * componentes del curso donde el usuario es Docente Titular.
   * Cada fila expone accesos directos a:
   *  - Detalle del estudiante (evaluaciones, mensajes, asistencia)
   *  - Actividades del curso
   *
   * Authorization:
   *  - Renderiza contenido completo solo si `curso.es_titular_curso === true`
   *    o si el usuario posee el permiso wildcard `cursos:*`.
   *  - El backend protege la ruta a nivel de middleware (is_docente) y policy.
   *
   * Props (Inertia via HandleInertiaRequests):
   *  - curso       : Datos del curso + flag es_titular_curso + userPermissions
   *  - componentes : Lista de componentes del curso
   *  - estudiantes : Lista de inscripciones estudiante–componente
   *  - actividades : Actividades visibles del curso (para el modal de detalle)
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import {
    ArrowLeft,
    Search,
    Users,
    GraduationCap,
    ClipboardList,
    BookOpenCheck,
    ChevronDown,
    Crown,
    ShieldOff,
    X,
    MessageSquare,
  } from 'lucide-svelte';
  import EstudianteDetalleModal from './components/EstudianteDetalleModal.svelte';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';
  import type { Actividad } from '@/types/actividad';
  import { initials } from '@/utils/formatters';

  // ── Types ─────────────────────────────────────────────────────────────────

  interface Estudiante {
    id_inscripcion_componente: number;
    id_componente: number;
    tipo_componente: string;
    nota_componente: number | null;
    estudiante: {
      id_estudiante: number;
      nombre: string;
      username: string;
      email?: string;
    };
  }

  interface Componente {
    id_componente: number;
    tipo_componente: string;
    total_estudiantes?: number;
  }

  interface Curso {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    es_titular_curso: boolean;
    asignatura: {
      nombre: string;
      cod_asignatura: string;
    };
    userPermissions?: Permission[];
  }

  interface Props {
    curso: Curso;
    componentes: Componente[];
    estudiantes: Estudiante[];
    actividades: Actividad[];
  }

  // ── Props ─────────────────────────────────────────────────────────────────

  let { curso, componentes, estudiantes, actividades }: Props = $props();

  // ── Authorization ─────────────────────────────────────────────────────────

  const isTitular = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'cursos:*'),
  );

  // ── Search & filter state ─────────────────────────────────────────────────

  let query = $state('');
  let selectedComponente = $state<number | 'all'>('all');

  const estudiantesFiltrados = $derived(
    estudiantes.filter((e) => {
      const q = query.toLowerCase();
      const matchesSearch =
        !q ||
        e.estudiante.nombre.toLowerCase().includes(q) ||
        e.estudiante.username.toLowerCase().includes(q);
      const matchesComponente =
        selectedComponente === 'all' || e.id_componente === selectedComponente;
      return matchesSearch && matchesComponente;
    }),
  );

  // ── Aggregate stats ───────────────────────────────────────────────────────

  const stats = $derived({
    total: estudiantes.length,
    aprobados: estudiantes.filter((e) => (e.nota_componente ?? 0) >= 4.0).length,
    reprobados: estudiantes.filter((e) => e.nota_componente !== null && e.nota_componente < 4.0)
      .length,
    sinNota: estudiantes.filter((e) => e.nota_componente === null).length,
  });

  // ── Modal state ───────────────────────────────────────────────────────────

  let modalAbierto = $state(false);
  let estudianteSeleccionado = $state<Estudiante | null>(null);

  function abrirModal(e: Estudiante) {
    estudianteSeleccionado = e;
    modalAbierto = true;
  }

  function cerrarModal() {
    modalAbierto = false;
    estudianteSeleccionado = null;
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  function formatNota(nota: number | null): string {
    if (nota === null || nota === undefined) return '—';
    return nota.toFixed(1);
  }

  function notaColorClass(nota: number | null): string {
    if (nota === null) return 'text-slate-400';
    if (nota >= 4.0) return 'text-emerald-700';
    if (nota >= 3.0) return 'text-amber-600';
    return 'text-red-600';
  }
</script>

<DocenteLayout>
  <!-- ── Access guard ─────────────────────────────────────────────────────── -->
  {#if !isTitular}
    <div class="flex flex-col items-center justify-center gap-4 h-64 text-center px-6">
      <ShieldOff size={40} class="text-red-400" />
      <div>
        <h2 class="text-lg font-bold text-slate-800">Acceso restringido</h2>
        <p class="text-sm text-slate-500 mt-1">
          Esta vista es exclusiva del Docente Titular del curso.
        </p>
      </div>
      <Link
        href="/docente/cursos/{curso.id_curso}"
        class="text-indigo-600 text-sm font-medium hover:underline"
      >
        Volver al curso
      </Link>
    </div>
  {:else}
    <!-- ── Page header ───────────────────────────────────────────────────── -->
    <div class="bg-white border-b border-slate-200 px-6 py-5">
      <nav class="flex items-center gap-1.5 text-xs text-slate-500 mb-3" aria-label="Navegación">
        <Link href="/docente/cursos" class="hover:text-indigo-600 transition-colors">
          Mis Cursos
        </Link>
        <span class="text-slate-300" aria-hidden="true">/</span>
        <Link
          href="/docente/cursos/{curso.id_curso}"
          class="hover:text-indigo-600 transition-colors"
        >
          {curso.cod_curso}
        </Link>
        <span class="text-slate-300" aria-hidden="true">/</span>
        <span class="text-slate-700 font-medium" aria-current="page">Estudiantes</span>
      </nav>

      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
              Gestión de Estudiantes
            </h1>
            <span
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold border"
              style="background:#FFF8EC; color:#D97706; border-color:#FDE68A;"
            >
              <Crown size={10} />
              Titular
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            {curso.asignatura.cod_asignatura} · {curso.asignatura.nombre}
          </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <Link
            href="/docente/cursos/{curso.id_curso}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors no-underline"
          >
            <ArrowLeft size={14} />
            Volver al curso
          </Link>
          <Link
            href="/docente/cursos/{curso.id_curso}/mensajes"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 border border-indigo-200 bg-indigo-50 px-3 py-1.5 rounded-lg hover:bg-indigo-100 transition-colors no-underline"
          >
            <MessageSquare size={14} />
            Mensajes de actividades
          </Link>
        </div>
      </div>
    </div>

    <!-- ── Stats bar ─────────────────────────────────────────────────────── -->
    <div class="px-6 py-3.5 bg-slate-50 border-b border-slate-200">
      <div class="flex flex-wrap gap-6 text-sm">
        <span class="flex items-center gap-1.5 font-medium text-slate-700">
          <Users size={14} class="text-indigo-500" />
          <strong class="text-slate-900">{stats.total}</strong>
          estudiante{stats.total !== 1 ? 's' : ''}
        </span>
        <span class="flex items-center gap-1.5 text-slate-600">
          <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
          <strong class="text-emerald-700">{stats.aprobados}</strong> aprobados
        </span>
        <span class="flex items-center gap-1.5 text-slate-600">
          <span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>
          <strong class="text-red-600">{stats.reprobados}</strong> reprobados
        </span>
        <span class="flex items-center gap-1.5 text-slate-600">
          <span class="w-2 h-2 rounded-full bg-slate-300 inline-block"></span>
          <strong class="text-slate-500">{stats.sinNota}</strong> sin calificar
        </span>
      </div>
    </div>

    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <div class="px-6 py-3.5 flex flex-wrap gap-3 items-center bg-white border-b border-slate-100">
      <!-- Search input -->
      <div class="relative flex-1 min-w-[220px] max-w-sm">
        <Search
          size={13}
          class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
        />
        <input
          type="text"
          bind:value={query}
          placeholder="Buscar por nombre o username…"
          class="w-full pl-8 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder:text-slate-400"
        />
        {#if query}
          <button
            onclick={() => (query = '')}
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700"
            aria-label="Limpiar búsqueda"
          >
            <X size={13} />
          </button>
        {/if}
      </div>

      <!-- Component filter -->
      {#if componentes.length > 1}
        <div class="relative">
          <select
            bind:value={selectedComponente}
            class="appearance-none pl-3 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
          >
            <option value="all">Todos los componentes</option>
            {#each componentes as c (c.id_componente)}
              <option value={c.id_componente}>{c.tipo_componente}</option>
            {/each}
          </select>
          <ChevronDown
            size={13}
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
          />
        </div>
      {/if}

      <span class="text-xs text-slate-400 ml-auto shrink-0">
        {estudiantesFiltrados.length} de {estudiantes.length} estudiante{estudiantes.length !== 1
          ? 's'
          : ''}
      </span>
    </div>

    <!-- ── Student table ──────────────────────────────────────────────────── -->
    <div class="px-6 py-5">
      {#if estudiantesFiltrados.length === 0}
        <div class="flex flex-col items-center gap-3 py-16 text-center text-slate-400">
          <GraduationCap size={40} class="opacity-40" />
          <p class="text-sm">
            {query
              ? 'No se encontraron estudiantes con ese criterio.'
              : 'No hay estudiantes inscritos en este componente.'}
          </p>
          {#if query}
            <button
              onclick={() => (query = '')}
              class="text-xs text-indigo-600 font-medium hover:underline"
            >
              Limpiar búsqueda
            </button>
          {/if}
        </div>
      {:else}
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-left">
                <th
                  scope="col"
                  class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wider"
                >
                  Estudiante
                </th>
                <th
                  scope="col"
                  class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wider hidden sm:table-cell"
                >
                  Componente
                </th>
                <th
                  scope="col"
                  class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wider text-center"
                >
                  Nota
                </th>
                <th
                  scope="col"
                  class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wider text-right"
                >
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each estudiantesFiltrados as inscripcion (inscripcion.id_inscripcion_componente)}
                {@const est = inscripcion.estudiante}
                <tr class="hover:bg-indigo-50/30 transition-colors">
                  <!-- Avatar + name -->
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div
                        class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0 select-none"
                        aria-hidden="true"
                      >
                        {initials(est.nombre)}
                      </div>
                      <div class="min-w-0">
                        <p class="font-semibold text-slate-800 leading-tight truncate">
                          {est.nombre}
                        </p>
                        <p class="text-xs text-slate-400">{est.username}</p>
                      </div>
                    </div>
                  </td>

                  <!-- Component badge -->
                  <td class="px-4 py-3 hidden sm:table-cell">
                    <span
                      class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600"
                    >
                      {inscripcion.tipo_componente}
                    </span>
                  </td>

                  <!-- Grade -->
                  <td class="px-4 py-3 text-center">
                    <span class="text-sm font-bold {notaColorClass(inscripcion.nota_componente)}">
                      {formatNota(inscripcion.nota_componente)}
                    </span>
                  </td>

                  <!-- Actions -->
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-1.5">
                      <!-- Student detail modal -->
                      <button
                        onclick={() => abrirModal(inscripcion)}
                        title="Evaluaciones, mensajes y asistencia"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-md border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors"
                      >
                        <BookOpenCheck size={13} />
                        <span class="hidden sm:inline">Detalle</span>
                      </button>

                      <!-- Link to course activities -->
                      <Link
                        href="/docente/cursos/{curso.id_curso}/actividades"
                        title="Actividades del curso"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition-colors no-underline"
                      >
                        <ClipboardList size={13} />
                        <span class="hidden sm:inline">Actividades</span>
                      </Link>

                      <!-- Hilos de agenda del estudiante (por actividad/grupo).
                           El canal directo sin actividad vive en Mensajería. -->
                      <Link
                        href="/docente/cursos/{curso.id_curso}/mensajes?est={est.id_estudiante}"
                        title="Mensajes de actividades de este estudiante"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-md border border-indigo-200 bg-white text-indigo-600 hover:bg-indigo-50 transition-colors no-underline"
                      >
                        <MessageSquare size={13} />
                        <span class="hidden sm:inline">Mensaje</span>
                      </Link>
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </div>
  {/if}
</DocenteLayout>

<!-- ── Slide-over modal ──────────────────────────────────────────────────── -->
{#if modalAbierto && estudianteSeleccionado !== null}
  <EstudianteDetalleModal
    abierto={modalAbierto}
    estudiante={estudianteSeleccionado}
    {actividades}
    idCurso={curso.id_curso}
    onCerrar={cerrarModal}
  />
{/if}
