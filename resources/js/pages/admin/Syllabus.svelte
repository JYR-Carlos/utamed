<script lang="ts">
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import type { BreadcrumbItem } from '@/types';
  import { BookOpen, Calendar, Clock, CheckCircle2, AlertCircle, FileText, Edit, X, Search, ChevronDown } from 'lucide-svelte';
  import * as Card from '@/components/ui/card';
  import * as Dialog from '@/components/ui/dialog';
  import { Button } from '@/components/ui/button';
  import { Input } from '@/components/ui/input';
  import { Label } from '@/components/ui/label';

  // ─── Props ───────────────────────────────────────────────────────────────
  interface CursoSyllabus {
    id_curso: number;
    cod_curso: string;
    nombre: string;
    agno_real: number | null;
    semestre_real: string | null;
    asignatura: string;
    carrera: string;
    fecha_limite_entrega_basico: string | null;
    fecha_limite_entrega_syllabus: string | null;
    programa: {
      id_programa: number;
      estado: string;
      tipo_syllabus: string | null;
      version_programa: number;
      completud: number;
    } | null;
  }

  interface Pagination {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
  }

  interface Props {
    cursos: CursoSyllabus[];
    pagination: Pagination;
    filters: { q?: string; semestre?: string; agno?: string };
    semestres: string[];
    agnos: number[];
  }

  let { cursos, pagination, filters, semestres, agnos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Syllabus', href: '/admin/syllabus' },
  ];

  // ─── Filtros ─────────────────────────────────────────────────────────────
  let searchQ = $state(filters.q ?? '');
  let filtroSemestre = $state(filters.semestre ?? '');
  let filtroAgno = $state(filters.agno ?? '');

  function aplicarFiltros() {
    const params: Record<string, string> = {};
    if (searchQ) params.q = searchQ;
    if (filtroSemestre) params.semestre = filtroSemestre;
    if (filtroAgno) params.agno = filtroAgno;
    router.get('/admin/syllabus', params, { preserveState: true });
  }

  function limpiarFiltros() {
    searchQ = '';
    filtroSemestre = '';
    filtroAgno = '';
    router.get('/admin/syllabus', {});
  }

  // ─── Diálogo de fechas ───────────────────────────────────────────────────
  interface FechasDialog {
    open: boolean;
    curso: CursoSyllabus | null;
    fechaBasico: string;
    fechaCompleto: string;
    saving: boolean;
    error: string;
  }

  let dialog = $state<FechasDialog>({
    open: false,
    curso: null,
    fechaBasico: '',
    fechaCompleto: '',
    saving: false,
    error: '',
  });

  function abrirDialogFechas(curso: CursoSyllabus) {
    dialog = {
      open: true,
      curso,
      fechaBasico: isoToInputDate(curso.fecha_limite_entrega_basico),
      fechaCompleto: isoToInputDate(curso.fecha_limite_entrega_syllabus),
      saving: false,
      error: '',
    };
  }

  function cerrarDialog() {
    dialog = { ...dialog, open: false, curso: null };
  }

  function guardarFechas() {
    if (!dialog.curso) return;

    dialog = { ...dialog, saving: true, error: '' };

    router.put(
      `/admin/cursos/${dialog.curso.id_curso}/programa/fechas`,
      {
        fecha_limite_entrega_basico: dialog.fechaBasico || null,
        fecha_limite_entrega_syllabus: dialog.fechaCompleto || null,
      },
      {
        onSuccess: () => {
          dialog = { ...dialog, open: false, saving: false };
        },
        onError: (errors) => {
          const msg = Object.values(errors).flat().join('. ');
          dialog = { ...dialog, saving: false, error: msg };
        },
      },
    );
  }

  // ─── Utilidades de fecha ─────────────────────────────────────────────────

  /** Convierte ISO string → valor de <input type="date"> (YYYY-MM-DD) */
  function isoToInputDate(iso: string | null): string {
    if (!iso) return '';
    return iso.substring(0, 10);
  }

  /** Días restantes desde hoy hasta `iso`. Negativo = pasado. */
  function diasRestantes(iso: string | null): number | null {
    if (!iso) return null;
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const target = new Date(iso);
    target.setHours(0, 0, 0, 0);
    return Math.round((target.getTime() - now.getTime()) / 86_400_000);
  }

  /** Formato legible de fecha */
  function formatFecha(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('es-EC', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  // ─── Lógica de acción por curso ──────────────────────────────────────────
  type AccionTipo = 'definir-basico' | 'dias-basico' | 'definir-completo' | 'dias-completo';

  interface Accion {
    tipo: AccionTipo;
    dias?: number | null;
  }

  function getAccion(c: CursoSyllabus): Accion {
    // 4. Si ya se definió la fecha completa → días restantes para syllabus completo
    if (c.fecha_limite_entrega_syllabus) {
      return { tipo: 'dias-completo', dias: diasRestantes(c.fecha_limite_entrega_syllabus) };
    }

    // 3. Si el básico ya está terminado (BASICO_COMPLETO) → button definir fecha completo
    if (c.programa && (c.programa.estado === 'BASICO_COMPLETO' || (c.programa.tipo_syllabus !== 'BASICO' && c.programa.estado !== 'BORRADOR'))) {
      return { tipo: 'definir-completo' };
    }

    // 2. Si el syllabus es versión básica EN PROGRESO y tiene fecha básico → días restantes básico
    if (c.fecha_limite_entrega_basico && c.programa && c.programa.tipo_syllabus === 'BASICO') {
      return { tipo: 'dias-basico', dias: diasRestantes(c.fecha_limite_entrega_basico) };
    }

    // 1. Por defecto → definir fecha básico
    return { tipo: 'definir-basico' };
  }

  // ─── Estado del syllabus: badge ──────────────────────────────────────────
  const estadoBadge: Record<string, { label: string; cls: string }> = {
    BORRADOR: { label: 'Borrador', cls: 'bg-slate-100 text-slate-700' },
    BASICO_COMPLETO: { label: 'Básico completo', cls: 'bg-blue-100 text-blue-700' },
    COMPLETO: { label: 'Completo', cls: 'bg-indigo-100 text-indigo-700' },
    APROBADO: { label: 'Aprobado', cls: 'bg-green-100 text-green-700' },
    PUBLICADO: { label: 'Publicado', cls: 'bg-emerald-100 text-emerald-700' },
  };

  // ─── Paginación ──────────────────────────────────────────────────────────
  function irAPagina(p: number) {
    const params: Record<string, string> = { page: String(p) };
    if (searchQ) params.q = searchQ;
    if (filtroSemestre) params.semestre = filtroSemestre;
    if (filtroAgno) params.agno = filtroAgno;
    router.get('/admin/syllabus', params, { preserveState: true });
  }
</script>

<AdminLayout {breadcrumbs}>
  <div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Encabezado -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-slate-900 mb-1 flex items-center gap-3">
        <BookOpen class="text-blue-600" size={32} />
        Syllabus de Cursos
      </h1>
      <p class="text-slate-500 text-sm">Gestión de syllabus y fechas límite de entrega. Solo administradores.</p>
    </div>

    <!-- Filtros -->
    <Card.Root class="mb-6 shadow-sm">
      <Card.Content class="p-4">
        <div class="flex flex-wrap gap-3 items-end">
          <!-- Búsqueda -->
          <div class="flex-1 min-w-[200px]">
            <Label class="text-xs text-slate-500 mb-1 block">Buscar curso</Label>
            <div class="relative">
              <Search class="absolute left-2.5 top-2.5 text-slate-400" size={15} />
              <Input bind:value={searchQ} placeholder="Nombre o código…" class="pl-8 h-9" onkeydown={(e) => e.key === 'Enter' && aplicarFiltros()} />
            </div>
          </div>

          <!-- Año -->
          <div>
            <Label class="text-xs text-slate-500 mb-1 block">Año</Label>
            <select
              bind:value={filtroAgno}
              class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option value="">Todos</option>
              {#each agnos as a (a)}
                <option value={String(a)}>{a}</option>
              {/each}
            </select>
          </div>

          <!-- Semestre -->
          <div>
            <Label class="text-xs text-slate-500 mb-1 block">Semestre</Label>
            <select
              bind:value={filtroSemestre}
              class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option value="">Todos</option>
              {#each semestres as s (s)}
                <option value={s}>{s}</option>
              {/each}
            </select>
          </div>

          <Button onclick={aplicarFiltros} size="sm" class="h-9">Buscar</Button>

          {#if searchQ || filtroSemestre || filtroAgno}
            <Button variant="ghost" onclick={limpiarFiltros} size="sm" class="h-9 text-slate-500">
              <X size={14} class="mr-1" /> Limpiar
            </Button>
          {/if}
        </div>
      </Card.Content>
    </Card.Root>

    <!-- Total -->
    <p class="text-sm text-slate-500 mb-4">
      {pagination.total} curso{pagination.total !== 1 ? 's' : ''} encontrado{pagination.total !== 1 ? 's' : ''}
    </p>

    <!-- Tabla -->
    <Card.Root class="shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left py-3 px-4 font-semibold text-slate-600">Curso / Asignatura</th>
              <th class="text-left py-3 px-4 font-semibold text-slate-600">Carrera</th>
              <th class="text-left py-3 px-4 font-semibold text-slate-600">Estado syllabus</th>
              <th class="text-left py-3 px-4 font-semibold text-slate-600">Fecha básico</th>
              <th class="text-left py-3 px-4 font-semibold text-slate-600">Fecha completo</th>
              <th class="text-center py-3 px-4 font-semibold text-slate-600">Acción</th>
              <th class="text-center py-3 px-4 font-semibold text-slate-600">Ver/Editar</th>
            </tr>
          </thead>
          <tbody>
            {#if cursos.length === 0}
              <tr>
                <td colspan="7" class="text-center py-16 text-slate-400">
                  <FileText size={40} class="mx-auto mb-3 opacity-40" />
                  <p>No se encontraron cursos</p>
                </td>
              </tr>
            {/if}

            {#each cursos as curso (curso.id_curso)}
              {@const accion = getAccion(curso)}
              {@const badge = curso.programa
                ? (estadoBadge[curso.programa.estado] ?? { label: curso.programa.estado, cls: 'bg-slate-100 text-slate-700' })
                : null}

              <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                <!-- Curso / Asignatura -->
                <td class="py-3 px-4">
                  <p class="font-medium text-slate-900 leading-tight">{curso.nombre}</p>
                  <p class="text-xs text-slate-500 mt-0.5">{curso.cod_curso} · {curso.asignatura}</p>
                  {#if curso.agno_real || curso.semestre_real}
                    <p class="text-xs text-slate-400">{curso.agno_real ?? ''}{curso.semestre_real ? ` · ${curso.semestre_real}` : ''}</p>
                  {/if}
                </td>

                <!-- Carrera -->
                <td class="py-3 px-4 text-slate-600 text-xs leading-tight max-w-[180px]">
                  {curso.carrera}
                </td>

                <!-- Estado syllabus -->
                <td class="py-3 px-4">
                  {#if badge}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium {badge.cls}">
                      <CheckCircle2 size={11} />
                      {badge.label}
                    </span>
                    {#if curso.programa}
                      <p class="text-xs text-slate-400 mt-1">
                        {curso.programa.tipo_syllabus ?? '?'} · v{curso.programa.version_programa}
                        · {curso.programa.completud}% completo
                      </p>
                    {/if}
                  {:else}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-500">
                      <AlertCircle size={11} />
                      Sin syllabus
                    </span>
                  {/if}
                </td>

                <!-- Fecha básico -->
                <td class="py-3 px-4 text-xs text-slate-600">
                  {#if curso.fecha_limite_entrega_basico}
                    <div class="flex items-center gap-1">
                      <Calendar size={12} class="text-slate-400 shrink-0" />
                      <span>{formatFecha(curso.fecha_limite_entrega_basico)}</span>
                    </div>
                  {:else}
                    <span class="text-slate-300">—</span>
                  {/if}
                </td>

                <!-- Fecha completo -->
                <td class="py-3 px-4 text-xs text-slate-600">
                  {#if curso.fecha_limite_entrega_syllabus}
                    <div class="flex items-center gap-1">
                      <Calendar size={12} class="text-slate-400 shrink-0" />
                      <span>{formatFecha(curso.fecha_limite_entrega_syllabus)}</span>
                    </div>
                  {:else}
                    <span class="text-slate-300">—</span>
                  {/if}
                </td>

                <!-- ACCIÓN: lógica de días vs definir fecha -->
                <td class="py-3 px-4 text-center">
                  {#if accion.tipo === 'dias-basico'}
                    {@const d = accion.dias ?? 0}
                    <div
                      class="inline-flex flex-col items-center justify-center rounded-lg px-3 py-1.5 text-center leading-tight
                        {d < 0
                        ? 'bg-red-50 text-red-700 border border-red-200'
                        : d <= 3
                          ? 'bg-orange-50 text-orange-700 border border-orange-200'
                          : 'bg-blue-50 text-blue-700 border border-blue-200'}"
                    >
                      <span class="text-lg font-bold">{d < 0 ? 0 : d}</span>
                      <span class="text-[10px] font-medium">
                        {d < 0 ? 'Plazo básico vencido' : d === 1 ? 'día p. básico' : 'días p. básico'}
                      </span>
                    </div>
                  {:else if accion.tipo === 'dias-completo'}
                    {@const d = accion.dias ?? 0}
                    <div
                      class="inline-flex flex-col items-center justify-center rounded-lg px-3 py-1.5 text-center leading-tight
                        {d < 0
                        ? 'bg-red-50 text-red-700 border border-red-200'
                        : d <= 3
                          ? 'bg-orange-50 text-orange-700 border border-orange-200'
                          : 'bg-indigo-50 text-indigo-700 border border-indigo-200'}"
                    >
                      <span class="text-lg font-bold">{d < 0 ? 0 : d}</span>
                      <span class="text-[10px] font-medium">
                        {d < 0 ? 'Plazo completo vencido' : d === 1 ? 'día p. completo' : 'días p. completo'}
                      </span>
                    </div>
                  {:else if accion.tipo === 'definir-completo'}
                    <button
                      onclick={() => abrirDialogFechas(curso)}
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition-colors"
                    >
                      <Calendar size={13} />
                      Definir fecha<br />completo
                    </button>
                  {:else}
                    <!-- definir-basico (default) -->
                    <button
                      onclick={() => abrirDialogFechas(curso)}
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                    >
                      <Calendar size={13} />
                      Definir fecha
                    </button>
                  {/if}
                </td>

                <!-- Ver / Editar syllabus -->
                <td class="py-3 px-4 text-center">
                  {#if curso.programa}
                    <a
                      href="/admin/cursos/{curso.id_curso}/programa/revisar"
                      class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium text-xs"
                    >
                      <Edit size={13} />
                      Ver / Editar
                    </a>
                  {:else}
                    <span class="text-slate-300 text-xs">Sin programa</span>
                  {/if}
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    </Card.Root>

    <!-- Paginación -->
    {#if pagination.last_page > 1}
      <div class="flex justify-center gap-2 mt-6 flex-wrap">
        {#each Array.from({ length: pagination.last_page }, (_, i) => i + 1) as p (p)}
          <button
            onclick={() => irAPagina(p)}
            class="w-9 h-9 rounded border text-sm font-medium transition-colors
              {p === pagination.current_page ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 text-slate-700 hover:bg-slate-100'}"
          >
            {p}
          </button>
        {/each}
      </div>
    {/if}
  </div>
</AdminLayout>

<!-- ─── Diálogo de fechas ─────────────────────────────────────────────────── -->
<Dialog.Root
  open={dialog.open}
  onOpenChange={(v) => {
    if (!v) cerrarDialog();
  }}
>
  <Dialog.Content class="max-w-md">
    <Dialog.Header>
      <Dialog.Title class="flex items-center gap-2 text-slate-900">
        <Clock size={18} class="text-blue-600" />
        Fechas límite de entrega
      </Dialog.Title>
      <Dialog.Description class="text-slate-500 text-sm">
        {dialog.curso?.nombre ?? ''}
      </Dialog.Description>
    </Dialog.Header>

    <div class="space-y-5 py-2">
      <!-- Fecha básico -->
      <div class="space-y-1.5">
        <Label for="fecha-basico" class="text-sm font-medium text-slate-700 flex items-center gap-1.5">
          <span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-400"></span>
          Fecha límite — Syllabus básico
        </Label>
        <Input id="fecha-basico" type="date" bind:value={dialog.fechaBasico} class="text-sm" />
        <p class="text-xs text-slate-400">Plazo que tiene el docente para entregar la versión básica (5 secciones).</p>
      </div>

      <!-- Fecha completo -->
      <div class="space-y-1.5">
        <Label for="fecha-completo" class="text-sm font-medium text-slate-700 flex items-center gap-1.5">
          <span class="inline-block w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
          Fecha límite — Syllabus completo
        </Label>
        <Input id="fecha-completo" type="date" bind:value={dialog.fechaCompleto} min={dialog.fechaBasico || undefined} class="text-sm" />
        <p class="text-xs text-slate-400">Plazo para la versión completa (9 secciones). Debe ser igual o posterior al básico.</p>
      </div>

      {#if dialog.error}
        <p class="text-sm text-red-600 bg-red-50 rounded-md px-3 py-2 border border-red-200">
          {dialog.error}
        </p>
      {/if}
    </div>

    <Dialog.Footer class="gap-2">
      <Button variant="ghost" onclick={cerrarDialog} disabled={dialog.saving}>Cancelar</Button>
      <Button onclick={guardarFechas} disabled={dialog.saving} class="bg-blue-600 hover:bg-blue-700">
        {dialog.saving ? 'Guardando…' : 'Guardar fechas'}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
