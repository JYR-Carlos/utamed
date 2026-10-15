<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import PageHeader from '@/components/admin/PageHeader.svelte';
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import Toast from '@/components/admin/Toast.svelte';
  import type { BreadcrumbItem } from '@/types';
  import {
    Bug,
    Download,
    Eye,
    FileText,
    Image as ImageIcon,
    Search,
    Trash2,
    X,
    ExternalLink,
    User,
    Calendar,
    AlertCircle,
  } from 'lucide-svelte';

  interface ReportItem {
    id: string;
    metadata: {
      title?: string;
      description?: string;
      expected?: string | null;
      actual?: string | null;
      id?: string;
      image_file?: string | null;
      created_at?: string;
      rut_usuario?: string;
    };
    usuario?: {
      rut: string;
      nombre_completo: string;
      email?: string | null;
    } | null;
    has_pdf: boolean;
    has_evidence: boolean;
    created_at?: string | null;
  }

  interface Props {
    reports: ReportItem[];
  }

  let { reports = [] }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin/dashboard' },
    { title: 'Reportes e Incidencias', href: '/admin/reportes' },
  ];

  let searchQuery = $state('');
  let selectedReport = $state<ReportItem | null>(null);
  let reportToDelete = $state<ReportItem | null>(null);
  let isDeleting = $state(false);
  let toast = $state<{ type: 'success' | 'error'; message: string } | null>(null);

  // Filtrado de reportes
  let filteredReports = $derived(
    reports.filter((r) => {
      const q = searchQuery.trim().toLowerCase();
      if (!q) return true;
      const title = r.metadata?.title?.toLowerCase() || '';
      const desc = r.metadata?.description?.toLowerCase() || '';
      const user = r.usuario?.nombre_completo?.toLowerCase() || '';
      const rut = (r.usuario?.rut || r.metadata?.rut_usuario || '').toLowerCase();
      const email = r.usuario?.email?.toLowerCase() || '';
      return (
        title.includes(q) ||
        desc.includes(q) ||
        user.includes(q) ||
        rut.includes(q) ||
        email.includes(q)
      );
    })
  );

  function formatDate(isoOrStr?: string | null): string {
    if (!isoOrStr) return 'Sin fecha';
    try {
      const d = new Date(isoOrStr);
      if (isNaN(d.getTime())) return isoOrStr;
      return d.toLocaleString('es-CL', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return isoOrStr;
    }
  }

  function confirmDelete(report: ReportItem) {
    reportToDelete = report;
  }

  function handleDelete() {
    if (!reportToDelete) return;
    isDeleting = true;

    router.delete(`/admin/reportes/${encodeURIComponent(reportToDelete.id)}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast = { type: 'success', message: 'Reporte y archivos asociados eliminados correctamente.' };
        if (selectedReport?.id === reportToDelete?.id) {
          selectedReport = null;
        }
        reportToDelete = null;
      },
      onError: (err) => {
        const msg = Object.values(err)[0] as string;
        toast = { type: 'error', message: msg || 'Error al eliminar el reporte.' };
      },
      onFinish: () => {
        isDeleting = false;
      },
    });
  }
</script>

<AdminLayout {breadcrumbs}>
  <div class="space-y-6">
    <PageHeader
      title="Reportes e Incidencias"
      subtitle="Revisa y gestiona los reportes de bugs encontrados por los usuarios de la plataforma"
    />

    {#if toast}
      <Toast type={toast.type} message={toast.message} onClose={() => (toast = null)} />
    {/if}

    <!-- Barra de búsqueda y contadores -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="relative w-full sm:max-w-md">
        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
        <input
          type="text"
          placeholder="Buscar por título, usuario, RUT o descripción..."
          bind:value={searchQuery}
          class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-4 text-xs text-slate-800 placeholder-slate-400 shadow-xs transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
        />
        {#if searchQuery}
          <button
            type="button"
            onclick={() => (searchQuery = '')}
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            aria-label="Limpiar búsqueda"
          >
            <X size={14} />
          </button>
        {/if}
      </div>

      <div class="flex items-center gap-3 text-xs text-slate-500">
        <span class="rounded-lg bg-slate-100 px-2.5 py-1 font-medium text-slate-700">
          Total: <strong class="text-slate-900">{reports.length}</strong>
        </span>
        {#if searchQuery}
          <span class="rounded-lg bg-blue-50 px-2.5 py-1 font-medium text-blue-700">
            Filtrados: <strong class="text-blue-900">{filteredReports.length}</strong>
          </span>
        {/if}
      </div>
    </div>

    <!-- Lista de reportes -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
      {#if filteredReports.length === 0}
        <div class="flex flex-col items-center justify-center p-12 text-center">
          <div class="flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
            <Bug size={24} />
          </div>
          <h3 class="mt-4 text-sm font-semibold text-slate-800">No se encontraron reportes</h3>
          <p class="mt-1 text-xs text-slate-500">
            {searchQuery
              ? 'No hay incidencias que coincidan con los términos de búsqueda.'
              : 'Aún no se han enviado reportes de incidencias.'}
          </p>
        </div>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-left text-sm">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/75 text-slate-600">
                <th class="px-5 py-3 font-semibold">Fecha</th>
                <th class="px-5 py-3 font-semibold">Usuario</th>
                <th class="px-5 py-3 font-semibold">Incidencia</th>
                <th class="px-5 py-3 font-semibold text-center">Adjuntos</th>
                <th class="px-5 py-3 text-center font-semibold">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              {#each filteredReports as report (report.id)}
                <tr class="transition hover:bg-slate-50/50">
                  <!-- Fecha -->
                  <td class="whitespace-nowrap px-5 py-4 text-sm">
                    {formatDate(report.created_at)}
                  </td>

                  <!-- Usuario -->
                  <td class="px-5 py-4">
                    <div class="font-medium text-black">
                      {report.usuario?.nombre_completo || 'Usuario no identificado'}
                    </div>
                    <div class="text-[11px] text-black">
                      {#if report.usuario?.rut || report.metadata?.rut_usuario}
                        <span class="font-mono">{report.usuario?.rut || report.metadata?.rut_usuario}</span>
                      {/if}
                      {#if report.usuario?.email}
                        <span class="mx-1 text-black">•</span>
                        <span>{report.usuario.email}</span>
                      {/if}
                    </div>
                  </td>

                  <!-- Incidencia -->
                  <td class="max-w-md px-5 py-4">
                    <div title={report.metadata.description} class="cursor-help font-semibold text-slate-900">
                      {report.metadata?.title || 'Sin título'}
                    </div>
        
                  </td>

                  <!-- Adjuntos -->
                  <td class="whitespace-nowrap px-5 py-4 text-center">
                    <div class="flex flex-col items-center gap-1.5">
                      {#if report.has_pdf}
                        <span
                          class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-200"
                          title="PDF compilado disponible"
                        >
                          <FileText size={13} /> PDF
                        </span>
                      {/if}
                      {#if report.has_evidence}
                        <span
                          class="inline-flex items-center gap-1 rounded-md bg-purple-50 px-2 py-0.5 text-[10px] font-semibold text-purple-700 ring-1 ring-inset ring-purple-200"
                          title="Captura de evidencia disponible"
                        >
                          <ImageIcon size={13} /> Captura
                        </span>
                      {/if}
                    </div>
                  </td>

                  <!-- Acciones -->
                  <td class="whitespace-nowrap px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                      <!-- Ver Detalle -->
                      <button
                        type="button"
                        onclick={() => (selectedReport = report)}
                        class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        title="Ver detalles completos"
                      >
                        <Eye size={18} />
                      </button>

                      <!-- Descargar PDF -->
                      {#if report.has_pdf}
                        <a
                          href={`/admin/reportes/${encodeURIComponent(report.id)}/download`}
                          class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-emerald-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
                          title="Descargar PDF"
                          download
                        >
                          <Download size={18} />
                        </a>
                      {/if}

                      <!-- Eliminar -->
                      <button
                        type="button"
                        onclick={() => confirmDelete(report)}
                        class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        title="Eliminar reporte"
                        aria-label="Eliminar reporte"
                      >
                        <Trash2 size={18} />
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
  </div>
</AdminLayout>

<!-- Modal de detalle del reporte -->
{#if selectedReport}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="report-detail-title"
      class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
    >
      <header class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
        <div class="flex items-center gap-3">
          <div class="flex size-9 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
            <Bug size={18} />
          </div>
          <div>
            <h2 id="report-detail-title" class="text-base font-bold text-slate-900">
              {selectedReport.metadata?.title || 'Reporte de Incidencia'}
            </h2>
            <div class="flex flex-wrap items-center gap-2 pt-0.5 text-xs text-black">
              <span>Enviado por</span>
              <span class="inline-flex items-center gap-1">
                <User size={12} />
                {selectedReport.usuario?.nombre_completo || selectedReport.metadata?.rut_usuario || 'Anónimo'}
              </span>
              <span class="text-slate-300">•</span>
              <span class="inline-flex items-center gap-1 text-sm">
                <Calendar size={12} />
                {formatDate(selectedReport.created_at)}
              </span>
            </div>
          </div>
        </div>

        <button
          type="button"
          onclick={() => (selectedReport = null)}
          class="cursor-pointer rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
          aria-label="Cerrar modal"
        >
          <X size={18} />
        </button>
      </header>

      <div class="space-y-4 overflow-y-auto px-6 py-5 text-xs text-slate-700">
        <!-- Descripción -->
        <div>
          <h4 class="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">
            Descripción del problema
          </h4>
          <div class="rounded-xl border border-slate-100 bg-slate-50/75 p-3.5 leading-relaxed text-slate-800 whitespace-pre-wrap">
            {selectedReport.metadata?.description || 'Sin descripción.'}
          </div>
        </div>

        <!-- Esperado vs Actual -->
        {#if selectedReport.metadata?.expected || selectedReport.metadata?.actual}
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {#if selectedReport.metadata?.expected}
              <div>
                <h4 class="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  Resultado esperado
                </h4>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3 text-slate-800 whitespace-pre-wrap">
                  {selectedReport.metadata.expected}
                </div>
              </div>
            {/if}

            {#if selectedReport.metadata?.actual}
              <div>
                <h4 class="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  Resultado real / actual
                </h4>
                <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-3 text-slate-800 whitespace-pre-wrap">
                  {selectedReport.metadata.actual}
                </div>
              </div>
            {/if}
          </div>
        {/if}

        <!-- Captura de pantalla -->
        {#if selectedReport.has_evidence}
          <div>
            <div class="mb-1.5 flex items-center justify-between">
              <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                Evidencia gráfica adjunta
              </h4>
              <a
                href={`/admin/reportes/${encodeURIComponent(selectedReport.id)}/evidence`}
                target="_blank"
                rel="noreferrer"
                class="inline-flex items-center gap-1 font-semibold text-blue-600 hover:underline"
              >
                <span>Abrir imagen</span>
                <ExternalLink size={12} />
              </a>
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-900/5 p-2 text-center">
              <img
                src={`/admin/reportes/${encodeURIComponent(selectedReport.id)}/evidence`}
                alt="Captura de evidencia"
                class="mx-auto max-h-72 rounded-lg object-contain shadow-xs"
              />
            </div>
          </div>
        {/if}
      </div>

      <footer class="flex items-center justify-between border-t border-slate-100 bg-slate-50/60 px-6 py-3.5">
        <button
          type="button"
          onclick={() => confirmDelete(selectedReport!)}
          class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-semibold text-red-600 transition hover:bg-red-50 hover:text-red-700"
        >
          <Trash2 size={14} />
          <span>Eliminar reporte</span>
        </button>

        <div class="flex items-center gap-2">
          {#if selectedReport.has_pdf}
            <a
              href={`/admin/reportes/${encodeURIComponent(selectedReport.id)}/download`}
              class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white shadow-xs transition hover:bg-emerald-700"
              download
            >
              <Download size={14} />
              <span>Descargar PDF</span>
            </a>
          {/if}

          <button
            type="button"
            onclick={() => (selectedReport = null)}
            class="cursor-pointer rounded-lg border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Cerrar
          </button>
        </div>
      </footer>
    </div>
  </div>
{/if}

<!-- Modal de confirmación de eliminación -->
{#if reportToDelete}
  <ConfirmationModal
    isOpen={true}
    title="Eliminar reporte de incidencia"
    recordName={reportToDelete.metadata?.title || reportToDelete.id}
    recordMeta={[
      `Usuario: ${reportToDelete.usuario?.nombre_completo || reportToDelete.metadata?.rut_usuario || 'Anónimo'}`,
      `Fecha: ${formatDate(reportToDelete.created_at)}`,
    ]}
    message="Esta acción es irreversible y eliminará el PDF compilado, los metadatos y cualquier captura de evidencia asociada."
    tone="danger"
    confirmLabel="Eliminar reporte"
    isLoading={isDeleting}
    onConfirm={handleDelete}
    onCancel={() => (reportToDelete = null)}
  />
{/if}