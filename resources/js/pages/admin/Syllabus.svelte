<script lang="ts">
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import PageHeader from '@/components/admin/PageHeader.svelte';
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import { router, page } from '@inertiajs/svelte';
  import type { BreadcrumbItem } from '@/types';
  import {
    Calendar,
    Clock,
    CheckCircle2,
    AlertCircle,
    Edit,
    Plus,
  } from 'lucide-svelte';
  import * as Dialog from '@/components/ui/dialog';
  import { Button } from '@/components/ui/button';
  import { Label } from '@/components/ui/label';
  import DatePickerCL from '@/components/custom/common/DatePickerCL.svelte';

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
    filters: { q?: string; search?: string; semestre?: string; agno?: string };
    semestres: string[];
    agnos: number[];
  }

  let { cursos, pagination, filters, semestres, agnos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Syllabus', href: '/admin/syllabus' },
  ];

  let cursosData = $derived({ data: cursos, meta: pagination });

  const columns = [
    { key: 'nombre', label: 'Curso / Asignatura' },
    { key: 'carrera', label: 'Carrera' },
    { key: 'estado', label: 'Estado syllabus' },
    { key: 'fecha_basico', label: 'Fecha básico' },
    { key: 'fecha_completo', label: 'Fecha completo' },
    { key: 'acciones', label: 'Acciones', class: 'text-right' },
  ];

  let activeAgno = $derived(
    new URL($page.url, window.location.origin).searchParams.get('agno') ?? (filters.agno || ''),
  );
  let activeSemestre = $derived(
    new URL($page.url, window.location.origin).searchParams.get('semestre') ??
      (filters.semestre || ''),
  );

  function handleAgnoChange(val: string) {
    const url = new URL($page.url, window.location.origin);
    if (val) url.searchParams.set('agno', val);
    else url.searchParams.delete('agno');
    url.searchParams.delete('page');
    router.get(url.pathname, Object.fromEntries(url.searchParams), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  function handleSemestreChange(val: string) {
    const url = new URL($page.url, window.location.origin);
    if (val) url.searchParams.set('semestre', val);
    else url.searchParams.delete('semestre');
    url.searchParams.delete('page');
    router.get(url.pathname, Object.fromEntries(url.searchParams), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  // ─── Diálogo crear programa ──────────────────────────────────────────────
  interface CrearDialog {
    open: boolean;
    curso: CursoSyllabus | null;
    tipo: 'BASICO' | 'COMPLETO';
    saving: boolean;
    error: string;
  }

  let crearDialog = $state<CrearDialog>({
    open: false,
    curso: null,
    tipo: 'BASICO',
    saving: false,
    error: '',
  });

  function abrirCrearDialog(curso: CursoSyllabus) {
    const tipo = curso.fecha_limite_entrega_syllabus ? 'COMPLETO' : 'BASICO';
    crearDialog = { open: true, curso, tipo, saving: false, error: '' };
  }

  function cerrarCrearDialog() {
    crearDialog = { ...crearDialog, open: false, curso: null };
  }

  function crearPrograma() {
    const curso = crearDialog.curso;
    if (!curso) return;
    crearDialog = { ...crearDialog, saving: true, error: '' };
    router.post(
      `/admin/cursos/${curso.id_curso}/programa/instanciar`,
      { tipo_syllabus: crearDialog.tipo },
      {
        onSuccess: () => cerrarCrearDialog(),
        onError: (errors) => {
          const msg = Object.values(errors).flat().join('. ');
          crearDialog = { ...crearDialog, saving: false, error: msg };
        },
      },
    );
  }

  // ─── Diálogo de fechas ───────────────────────────────────────────────────
  interface FechasDialog {
    open: boolean;
    curso: CursoSyllabus | null;
    modo: 'basico' | 'completo';
    fecha: string;
    saving: boolean;
    error: string;
  }

  let dialog = $state<FechasDialog>({
    open: false,
    curso: null,
    modo: 'basico',
    fecha: '',
    saving: false,
    error: '',
  });

  function abrirDialogBasico(curso: CursoSyllabus) {
    dialog = {
      open: true,
      curso,
      modo: 'basico',
      fecha: isoToInputDate(curso.fecha_limite_entrega_basico),
      saving: false,
      error: '',
    };
  }

  function abrirDialogCompleto(curso: CursoSyllabus) {
    dialog = {
      open: true,
      curso,
      modo: 'completo',
      fecha: isoToInputDate(curso.fecha_limite_entrega_syllabus),
      saving: false,
      error: '',
    };
  }

  function cerrarDialog() {
    dialog = { ...dialog, open: false, curso: null };
  }

  function guardarFechas() {
    const curso = dialog.curso;
    if (!curso) return;

    dialog = { ...dialog, saving: true, error: '' };

    const payload =
      dialog.modo === 'basico'
        ? { fecha_limite_entrega_basico: dialog.fecha || null }
        : { fecha_limite_entrega_syllabus: dialog.fecha || null };

    router.put(`/admin/cursos/${curso.id_curso}/programa/fechas`, payload, {
      onSuccess: () => {
        dialog = { ...dialog, open: false, saving: false };
      },
      onError: (errors) => {
        const msg = Object.values(errors).flat().join('. ');
        dialog = { ...dialog, saving: false, error: msg };
      },
    });
  }

  // ─── Utilidades de fecha ─────────────────────────────────────────────────
  function isoToInputDate(iso: string | null): string {
    if (!iso) return '';
    return iso.slice(0, 10);
  }

  function parseLocalDate(iso: string): Date {
    const [year, month, day] = iso.slice(0, 10).split('-').map(Number);
    return new Date(year, month - 1, day);
  }

  function diasRestantes(iso: string | null): number | null {
    if (!iso) return null;
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const target = parseLocalDate(iso);
    return Math.round((target.getTime() - now.getTime()) / 86_400_000);
  }

  function formatFecha(iso: string | null): string {
    if (!iso) return '—';
    return parseLocalDate(iso).toLocaleDateString('es-CL', {
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
    if (c.fecha_limite_entrega_syllabus) {
      return { tipo: 'dias-completo', dias: diasRestantes(c.fecha_limite_entrega_syllabus) };
    }

    if (
      c.programa &&
      (c.programa.estado === 'BASICO_COMPLETO' ||
        (c.programa.tipo_syllabus !== 'BASICO' && c.programa.estado !== 'BORRADOR'))
    ) {
      return { tipo: 'definir-completo' };
    }

    if (c.fecha_limite_entrega_basico) {
      return { tipo: 'dias-basico', dias: diasRestantes(c.fecha_limite_entrega_basico) };
    }

    return { tipo: 'definir-basico' };
  }

  const estadoBadge: Record<string, { label: string; cls: string }> = {
    BORRADOR: { label: 'Borrador', cls: 'bg-slate-100 text-slate-700' },
    BASICO_COMPLETO: { label: 'Básico completo', cls: 'bg-blue-100 text-blue-700' },
    COMPLETO: { label: 'Completo', cls: 'bg-indigo-100 text-indigo-700' },
    APROBADO: { label: 'Aprobado', cls: 'bg-green-100 text-green-700' },
    PUBLICADO: { label: 'Publicado', cls: 'bg-emerald-100 text-emerald-700' },
  };
</script>

<AdminLayout {breadcrumbs}>
  <div class="space-y-4">
    <PageHeader
      title="Syllabus de cursos"
      subtitle="Define la fecha límite de entrega y crea el syllabus de cada curso."
    />

    <DataTable
      data={cursosData}
      {columns}
      searchPlaceholder="Buscar por nombre o código…"
      only={['cursos', 'pagination']}
    >
      {#snippet filterSnippet()}
        <div class="flex items-center gap-2">
          <select
            value={activeAgno}
            onchange={(e) => handleAgnoChange((e.target as HTMLSelectElement).value)}
            class="px-2.5 py-1.5 text-sm border border-gray-300 rounded-md bg-white text-gray-700 cursor-pointer focus:outline-none focus:border-blue-400"
          >
            <option value="">Año (Todos)</option>
            {#each agnos as a (a)}
              <option value={String(a)}>{a}</option>
            {/each}
          </select>

          <select
            value={activeSemestre}
            onchange={(e) => handleSemestreChange((e.target as HTMLSelectElement).value)}
            class="px-2.5 py-1.5 text-sm border border-gray-300 rounded-md bg-white text-gray-700 cursor-pointer focus:outline-none focus:border-blue-400"
          >
            <option value="">Semestre (Todos)</option>
            {#each semestres as s (s)}
              <option value={s}>{s}</option>
            {/each}
          </select>
        </div>
      {/snippet}

      {#snippet cellSnippet({ item, column })}
        {#if column.key === 'nombre'}
          <div>
            <p class="font-medium text-slate-900 leading-tight">{item.nombre}</p>
            <p class="text-xs text-slate-500 mt-0.5">
              {item.cod_curso} · {item.asignatura}
            </p>
            {#if item.agno_real || item.semestre_real}
              <p class="text-xs text-slate-400">
                {item.agno_real ?? ''}{item.semestre_real ? ` · ${item.semestre_real}` : ''}
              </p>
            {/if}
          </div>
        {:else if column.key === 'carrera'}
          <span class="text-slate-600 text-xs leading-tight line-clamp-2 max-w-[200px]">
            {item.carrera}
          </span>
        {:else if column.key === 'estado'}
          {@const badge = item.programa
            ? (estadoBadge[item.programa.estado] ?? {
                label: item.programa.estado,
                cls: 'bg-slate-100 text-slate-700',
              })
            : null}
          {#if badge}
            <span
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium {badge.cls}"
            >
              <CheckCircle2 size={11} />
              {badge.label}
            </span>
            {#if item.programa}
              <p class="text-xs text-slate-400 mt-1">
                {item.programa.tipo_syllabus ?? '?'} · v{item.programa.version_programa} · {item.programa.completud}%
                completo
              </p>
            {/if}
          {:else}
            <span
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-500"
            >
              <AlertCircle size={11} />
              Sin syllabus
            </span>
          {/if}
        {:else if column.key === 'fecha_basico'}
          {#if item.fecha_limite_entrega_basico}
            <div class="flex items-center gap-1 text-xs text-slate-600">
              <Calendar size={12} class="text-slate-400 shrink-0" />
              <span>{formatFecha(item.fecha_limite_entrega_basico)}</span>
            </div>
          {:else}
            <span class="text-slate-300 text-xs">—</span>
          {/if}
        {:else if column.key === 'fecha_completo'}
          {#if item.fecha_limite_entrega_syllabus}
            <div class="flex items-center gap-1 text-xs text-slate-600">
              <Calendar size={12} class="text-slate-400 shrink-0" />
              <span>{formatFecha(item.fecha_limite_entrega_syllabus)}</span>
            </div>
          {:else}
            <span class="text-slate-300 text-xs">—</span>
          {/if}
        {:else if column.key === 'acciones'}
          {@const accion = getAccion(item)}
          <div class="flex items-center justify-end gap-2 flex-wrap">
            {#if accion.tipo === 'dias-basico'}
              {@const d = accion.dias ?? 0}
              <div
                class="inline-flex flex-col items-center justify-center rounded-lg px-2.5 py-1 text-center leading-tight
                  {d < 0
                  ? 'bg-red-50 text-red-700 border border-red-200'
                  : d <= 3
                    ? 'bg-orange-50 text-orange-700 border border-orange-200'
                    : 'bg-blue-50 text-blue-700 border border-blue-200'}"
              >
                <span class="text-base font-bold">{d < 0 ? 0 : d}</span>
                <span class="text-[9px] font-medium">
                  {d < 0
                    ? 'Plazo vencido'
                    : d === 1
                      ? 'día básico'
                      : 'días básico'}
                </span>
              </div>
            {:else if accion.tipo === 'dias-completo'}
              {@const d = accion.dias ?? 0}
              <div
                class="inline-flex flex-col items-center justify-center rounded-lg px-2.5 py-1 text-center leading-tight
                  {d < 0
                  ? 'bg-red-50 text-red-700 border border-red-200'
                  : d <= 3
                    ? 'bg-orange-50 text-orange-700 border border-orange-200'
                    : 'bg-indigo-50 text-indigo-700 border border-indigo-200'}"
              >
                <span class="text-base font-bold">{d < 0 ? 0 : d}</span>
                <span class="text-[9px] font-medium">
                  {d < 0
                    ? 'Plazo vencido'
                    : d === 1
                      ? 'día completo'
                      : 'días completo'}
                </span>
              </div>
            {:else if accion.tipo === 'definir-completo'}
              <button onclick={() => abrirDialogCompleto(item)} class="btn btn-neutral btn-sm">
                <Calendar size={14} />
                Definir fecha completo
              </button>
            {:else}
              <button onclick={() => abrirDialogBasico(item)} class="btn btn-neutral btn-sm">
                <Calendar size={14} />
                Definir fecha
              </button>
            {/if}

            {#if item.programa}
              <a
                href="/admin/cursos/{item.id_curso}/programa/revisar"
                class="btn btn-neutral btn-sm"
              >
                <Edit size={14} />
                Ver syllabus
              </a>
            {:else}
              <button onclick={() => abrirCrearDialog(item)} class="btn btn-primary btn-sm">
                <Plus size={14} />
                Crear syllabus
              </button>
            {/if}
          </div>
        {/if}
      {/snippet}
    </DataTable>
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
        <Clock size={18} class={dialog.modo === 'basico' ? 'text-blue-600' : 'text-indigo-600'} />
        {dialog.modo === 'basico'
          ? 'Fecha límite — Syllabus básico'
          : 'Fecha límite — Syllabus completo'}
      </Dialog.Title>
      <Dialog.Description class="text-slate-500 text-sm">
        {dialog.curso?.nombre ?? ''}
      </Dialog.Description>
    </Dialog.Header>

    <div class="space-y-5 py-2">
      {#if dialog.modo === 'basico'}
        <div class="space-y-1.5">
          <Label for="fecha-basico" class="text-sm font-medium text-slate-700">
            Fecha límite de entrega
          </Label>
          <DatePickerCL
            id="fecha-basico"
            value={dialog.fecha || null}
            onchange={(v) => (dialog = { ...dialog, fecha: v ?? '' })}
          />
          <p class="text-xs text-slate-400">
            Plazo que tiene el docente para entregar la versión básica (5 secciones).
          </p>
        </div>
      {:else}
        <div class="space-y-1.5">
          <Label for="fecha-completo" class="text-sm font-medium text-slate-700">
            Fecha límite de entrega
          </Label>
          <DatePickerCL
            id="fecha-completo"
            value={dialog.fecha || null}
            minValue={dialog.curso?.fecha_limite_entrega_basico
              ? isoToInputDate(dialog.curso.fecha_limite_entrega_basico)
              : null}
            onchange={(v) => (dialog = { ...dialog, fecha: v ?? '' })}
          />
          <p class="text-xs text-slate-400">
            Plazo para la versión completa (9 secciones). Debe ser igual o posterior al básico.
          </p>
        </div>
      {/if}

      {#if dialog.error}
        <p class="text-sm text-red-600 bg-red-50 rounded-md px-3 py-2 border border-red-200">
          {dialog.error}
        </p>
      {/if}
    </div>

    <Dialog.Footer class="gap-2">
      <Button variant="ghost" onclick={cerrarDialog} disabled={dialog.saving}>Cancelar</Button>
      <Button onclick={guardarFechas} disabled={dialog.saving} class="btn btn-primary">
        {dialog.saving ? 'Guardando…' : 'Guardar fecha'}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<!-- ─── Diálogo crear programa ───────────────────────────────────────────── -->
<Dialog.Root
  open={crearDialog.open}
  onOpenChange={(v) => {
    if (!v) cerrarCrearDialog();
  }}
>
  <Dialog.Content class="max-w-sm">
    <Dialog.Header>
      <Dialog.Title class="text-slate-900">Crear syllabus</Dialog.Title>
      <Dialog.Description class="text-slate-500 text-sm">
        {crearDialog.curso?.nombre ?? ''}
      </Dialog.Description>
    </Dialog.Header>

    <div class="space-y-4 py-2">
      <p class="text-sm text-slate-700 leading-relaxed">
        {#if crearDialog.tipo === 'BASICO'}
          Se creará un <strong>syllabus básico</strong> de 5 secciones obligatorias. El docente
          podrá completarlo hasta la fecha límite.
        {:else}
          Se creará un <strong>syllabus completo</strong> de 9 secciones, tomando como base los
          datos de la versión básica.
        {/if}
      </p>

      {#if crearDialog.error}
        <p class="text-sm text-red-600 bg-red-50 rounded-md px-3 py-2 border border-red-200">
          {crearDialog.error}
        </p>
      {/if}
    </div>

    <Dialog.Footer class="gap-2">
      <Button variant="ghost" onclick={cerrarCrearDialog} disabled={crearDialog.saving}>
        Cancelar
      </Button>
      <Button onclick={crearPrograma} disabled={crearDialog.saving} class="btn btn-primary">
        {crearDialog.saving ? 'Creando…' : 'Crear syllabus'}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
