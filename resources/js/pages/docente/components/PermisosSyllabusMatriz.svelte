<script lang="ts">
  import * as Dialog from '@/components/ui/dialog';
  import { Badge } from '@/components/ui/badge';
  import { TableProperties, Check, X } from 'lucide-svelte';

  interface PermisoSyllabus {
    id_permiso: number;
    slug: string;
    nombre: string;
  }

  interface DocentePerms {
    id_usuario: number;
    nombre: string;
    perms: Record<string, boolean>;
  }

  interface SyllabusMatrix {
    permisos: PermisoSyllabus[];
    docentes: DocentePerms[];
  }

  interface Props {
    isOpen: boolean;
    matrix: SyllabusMatrix;
    cursoNombre: string;
  }

  let { isOpen = $bindable(), matrix, cursoNombre }: Props = $props();

  /**
   * Transforma el slug del permiso en una etiqueta legible.
   * Ej: "cursos/programas:ver" → "Ver"
   *     "cursos/programas/modificar:modulo_3" → "Modificar Módulo 3"
   */
  function formatPermLabel(slug: string): string {
    // Extraer la acción después del último ':'
    const parts = slug.split(':');
    const action = parts[parts.length - 1] ?? slug;

    // Si es un módulo: "modulo_3" → "Módulo 3"
    if (action.startsWith('modulo_')) {
      const num = action.replace('modulo_', '');
      return `Módulo ${num}`;
    }

    // Capitalizar y limpiar wildcards
    if (action === '*') return 'Todos';

    const labels: Record<string, string> = {
      ver: 'Ver',
      agregar: 'Crear',
      eliminar: 'Eliminar',
    };

    return labels[action] ?? action.charAt(0).toUpperCase() + action.slice(1);
  }

  /**
   * Agrupa los permisos por categoría.
   * - "cursos/programas:ver" → "Programa"
   * - "cursos/programas/modificar:modulo_1" → "Modificar Secciones"
   */
  function groupPermisos(
    permisos: PermisoSyllabus[],
  ): { label: string; perms: PermisoSyllabus[] }[] {
    const general: PermisoSyllabus[] = [];
    const modificar: PermisoSyllabus[] = [];

    for (const p of permisos) {
      if (p.slug.includes('/modificar:')) {
        modificar.push(p);
      } else {
        general.push(p);
      }
    }

    const groups: { label: string; perms: PermisoSyllabus[] }[] = [];
    if (general.length > 0) groups.push({ label: 'Programa', perms: general });
    if (modificar.length > 0) groups.push({ label: 'Modificar Secciones', perms: modificar });
    return groups;
  }

  const permisoGroups = $derived(groupPermisos(matrix.permisos));
</script>

<Dialog.Root bind:open={isOpen}>
  <Dialog.Content class="max-w-4xl max-h-[85vh] overflow-hidden flex flex-col">
    <Dialog.Header>
      <Dialog.Title class="flex items-center gap-2">
        <TableProperties class="h-5 w-5 text-indigo-600" />
        Matriz de Permisos del Syllabus
      </Dialog.Title>
      <Dialog.Description>
        Permisos que cada docente tiene sobre el programa de <strong>{cursoNombre}</strong>.
      </Dialog.Description>
    </Dialog.Header>

    <div class="overflow-auto flex-1 -mx-6 px-6 py-2">
      {#if matrix.docentes.length === 0}
        <div class="text-center py-10 text-slate-500">
          <TableProperties class="h-8 w-8 mx-auto mb-2 text-slate-300" />
          <p class="text-sm">No hay docentes colegiados para mostrar.</p>
        </div>
      {:else}
        {#each permisoGroups as group}
          <div class="mb-6">
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
              {group.label}
            </h4>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
              <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                  <tr>
                    <th
                      class="text-left py-2.5 px-3 font-semibold text-slate-700 sticky left-0 bg-slate-50 min-w-[160px]"
                    >
                      Docente
                    </th>
                    {#each group.perms as perm}
                      <th class="text-center py-2.5 px-2 font-medium text-slate-600 min-w-[80px]">
                        <span class="text-xs">{formatPermLabel(perm.slug)}</span>
                      </th>
                    {/each}
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  {#each matrix.docentes as doc}
                    <tr class="hover:bg-slate-50/50 transition-colors">
                      <td class="py-2.5 px-3 sticky left-0 bg-white">
                        <span
                          class="text-sm font-medium text-slate-800 truncate block max-w-[200px]"
                        >
                          {doc.nombre}
                        </span>
                      </td>
                      {#each group.perms as perm}
                        <td class="text-center py-2.5 px-2">
                          {#if doc.perms[perm.slug]}
                            <span
                              class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100"
                            >
                              <Check class="h-3.5 w-3.5 text-emerald-600" />
                            </span>
                          {:else}
                            <span
                              class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100"
                            >
                              <X class="h-3.5 w-3.5 text-slate-400" />
                            </span>
                          {/if}
                        </td>
                      {/each}
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          </div>
        {/each}

        <!-- Leyenda -->
        <div class="flex items-center gap-4 text-xs text-slate-500 pt-2">
          <div class="flex items-center gap-1.5">
            <span
              class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100"
            >
              <Check class="h-3 w-3 text-emerald-600" />
            </span>
            Permiso otorgado
          </div>
          <div class="flex items-center gap-1.5">
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100">
              <X class="h-3 w-3 text-slate-400" />
            </span>
            Sin permiso
          </div>
        </div>
      {/if}
    </div>
  </Dialog.Content>
</Dialog.Root>
