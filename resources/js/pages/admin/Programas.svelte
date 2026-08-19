<script lang="ts">
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import PageHeader from '@/components/admin/PageHeader.svelte';
  import { Link } from '@inertiajs/svelte';
  import type { BreadcrumbItem } from '@/types';
  import { CheckCircle, Clock, XCircle, Eye } from 'lucide-svelte';
  import * as Card from '@/components/ui/card';
  import {
    cambiarEstadoFiltro,
    verPrograma,
    goToPage,
  } from '@/modules/resources/programa/services/programaApi';

  interface Props {
    programas: Array<{
      id_programa: number;
      id_curso: number;
      version: number;
      estado: string;
      asignatura: string;
      carrera: string;
      docente: string;
      fecha_creacion: string;
    }>;
    stats: {
      pendientes: number;
      aprobados: number;
      rechazados: number;
      borradores: number;
    };
    pagination: {
      current_page: number;
      last_page: number;
      total: number;
      per_page: number;
    };
    estado_filtro: string;
  }

  let { programas, stats, pagination, estado_filtro }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Programas', href: '/admin/programas' },
  ];

  const estadoConfig: Record<string, { color: string; icon: any; label: string }> = {
    BORRADOR: { color: 'bg-gray-100 text-gray-700', icon: Clock, label: 'Borrador' },
    PENDIENTE: { color: 'bg-blue-100 text-blue-700', icon: Clock, label: 'Pendiente' },
    APROBADO: { color: 'bg-green-100 text-green-700', icon: CheckCircle, label: 'Aprobado' },
    RECHAZADO: { color: 'bg-red-100 text-red-700', icon: XCircle, label: 'Rechazado' },
  };

  function cambiarEstado(estado: string) {
    cambiarEstadoFiltro(estado);
  }

  function verProgramaHandler(idCurso: number) {
    verPrograma(idCurso);
  }
</script>

<AdminLayout {breadcrumbs}>
  <div>
    <PageHeader title="Programas de cursos" subtitle="Revisión y aprobación de syllabus" />

    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
      <Card.Root class="border-blue-200">
        <Card.Content class="p-4">
          <p class="text-blue-600 text-sm font-medium">Pendientes</p>
          <p class="text-3xl font-bold text-blue-900">{stats.pendientes}</p>
        </Card.Content>
      </Card.Root>

      <Card.Root class="border-green-200">
        <Card.Content class="p-4">
          <p class="text-green-600 text-sm font-medium">Aprobados</p>
          <p class="text-3xl font-bold text-green-900">{stats.aprobados}</p>
        </Card.Content>
      </Card.Root>

      <Card.Root class="border-red-200">
        <Card.Content class="p-4">
          <p class="text-red-600 text-sm font-medium">Rechazados</p>
          <p class="text-3xl font-bold text-red-900">{stats.rechazados}</p>
        </Card.Content>
      </Card.Root>

      <Card.Root class="border-gray-200">
        <Card.Content class="p-4">
          <p class="text-gray-600 text-sm font-medium">Borradores</p>
          <p class="text-3xl font-bold text-gray-900">{stats.borradores}</p>
        </Card.Content>
      </Card.Root>
    </div>

    <!-- Filtros por estado -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
      {#each ['PENDIENTE', 'APROBADO', 'RECHAZADO', 'BORRADOR'] as est}
        <button
          onclick={() => cambiarEstado(est)}
          class="px-4 py-2 rounded-lg whitespace-nowrap transition-colors {estado_filtro === est
            ? 'bg-blue-600 text-white'
            : 'bg-slate-200 text-slate-700 hover:bg-slate-300'}"
        >
          {estadoConfig[est].label}
        </button>
      {/each}
    </div>

    <!-- Tabla de programas -->
    <Card.Root>
      <Card.Content class="p-6">
        {#if programas.length > 0}
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-slate-200">
                  <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700"
                    >Asignatura</th
                  >
                  <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Carrera</th>
                  <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Docente</th>
                  <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Estado</th>
                  <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Fecha</th>
                  <th class="text-center py-3 px-4 text-sm font-semibold text-slate-700">Acción</th>
                </tr>
              </thead>
              <tbody>
                {#each programas as prog (prog.id_programa)}
                  <tr class="border-b border-slate-200 hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-4 text-slate-900 font-medium">{prog.asignatura}</td>
                    <td class="py-3 px-4 text-slate-600 text-sm">{prog.carrera}</td>
                    <td class="py-3 px-4 text-slate-600 text-sm">{prog.docente}</td>
                    <td class="py-3 px-4">
                      <span
                        class="px-3 py-1 rounded-full text-xs font-medium {estadoConfig[prog.estado]
                          .color}"
                      >
                        {estadoConfig[prog.estado].label}
                      </span>
                    </td>
                    <td class="py-3 px-4 text-slate-600 text-sm">
                      {new Date(prog.fecha_creacion).toLocaleDateString('es-CL')}
                    </td>
                    <td class="py-3 px-4 text-center">
                      <button
                        onclick={() => verProgramaHandler(prog.id_curso)}
                        class="flex items-center justify-center gap-1 text-blue-600 hover:text-blue-700 font-medium text-sm"
                      >
                        <Eye size={16} />
                        Ver
                      </button>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {:else}
          <div class="text-center py-12">
            <p class="text-slate-600">
              No hay programas en estado {estadoConfig[estado_filtro].label}
            </p>
          </div>
        {/if}
      </Card.Content>
    </Card.Root>

    <!-- Paginación -->
    {#if pagination.last_page > 1}
      <div class="flex justify-center gap-2 mt-6">
        {#each Array.from({ length: pagination.last_page }, (_, i) => i + 1) as page}
          <button
            onclick={() => goToPage(page, estado_filtro)}
            class="px-3 py-2 rounded border {page === pagination.current_page
              ? 'bg-blue-600 text-white border-blue-600'
              : 'border-slate-300 text-slate-700 hover:bg-slate-100'}"
          >
            {page}
          </button>
        {/each}
      </div>
    {/if}
  </div>
</AdminLayout>
