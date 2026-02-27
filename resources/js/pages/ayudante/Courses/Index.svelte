<script lang="ts">
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import { BookOpen, Plus } from 'lucide-svelte';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions.types';

  interface Props {
    cursos: Array<{
      id_curso: number;
      nombre: string;
      cod_curso: string;
      asignatura_nombre: string;
      carrera_nombre: string;
      fecha_inicio: string;
      fecha_fin?: string;
      userPermissions?: Permission[];
    }>;
  }

  let { cursos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/ayudante/dashboard' },
    { title: 'Mis Cursos (Ayudante)', href: '/ayudante/cursos' },
  ];

  function getCanCreatePrograma(userPermissions: Permission[]) {
    return hasPermission(userPermissions, 'cursos/programas:crear');
  }
</script>

<AyudanteLayout {breadcrumbs}>
  <div class="container mx-auto px-6 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-slate-900 mb-2">Cursos Asignados</h1>
      <p class="text-slate-600">Listado de cursos donde eres Ayudante</p>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
      {#if cursos.length > 0}
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-slate-200">
                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Asignatura</th>
                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Código</th>
                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Carrera</th>
                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Inicio</th>
                <th class="text-center py-3 px-4 text-sm font-semibold text-slate-700">Estado</th>
                <th class="text-center py-3 px-4 text-sm font-semibold text-slate-700">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {#each cursos as curso (curso.id_curso)}
                <tr class="border-b border-slate-200 hover:bg-slate-50 transition-colors">
                  <td class="py-3 px-4 text-slate-900 font-medium">{curso.asignatura_nombre}</td>
                  <td class="py-3 px-4 text-slate-600">{curso.cod_curso}</td>
                  <td class="py-3 px-4 text-slate-600">{curso.carrera_nombre}</td>
                  <td class="py-3 px-4 text-slate-600">
                    {new Date(curso.fecha_inicio).toLocaleDateString('es-CL')}
                  </td>
                  <td class="py-3 px-4 text-center">
                    {#if getCanCreatePrograma(curso.userPermissions || [])}
                      <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                        <Plus size={14} />
                        Crear
                      </span>
                    {:else}
                      <span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full"> Sin permisos </span>
                    {/if}
                  </td>
                  <td class="py-3 px-4 text-center">
                    <Link href={`/ayudante/cursos/${curso.id_curso}`} class="text-blue-600 hover:text-blue-700 font-medium text-sm">Ver Curso</Link>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {:else}
        <div class="text-center py-12">
          <BookOpen class="mx-auto text-slate-400 mb-3" size={32} />
          <p class="text-slate-600 mb-2">No tienes asignaciones como ayudante.</p>
        </div>
      {/if}
    </div>
  </div>
</AyudanteLayout>
