<script lang="ts">
  import * as Dialog from '@/components/ui/dialog';
  import { Button } from '@/components/ui/button';
  import { Badge } from '@/components/ui/badge';
  import { Shield, Loader2, Save, X } from 'lucide-svelte';

  interface PermisoDisponible {
    id_permiso: number;
    slug: string;
    nombre: string;
    descripcion?: string;
  }

  interface PermisoEspecialActual {
    id_permiso: number;
    esta_permitido: boolean;
    puede_delegar: boolean;
  }

  interface ColegiadoDocente {
    id_docente: number;
    id_usuario: number;
    nombre: string;
    username: string;
    es_titular: boolean;
  }

  interface Props {
    isOpen: boolean;
    cursoId: number;
    colegiado: ColegiadoDocente | null;
    onClose: () => void;
  }

  let { isOpen = $bindable(), cursoId, colegiado, onClose }: Props = $props();

  let isLoading = $state(false);
  let isSaving = $state(false);
  let error = $state<string | null>(null);
  let successMsg = $state<string | null>(null);

  // Permisos disponibles agrupados (viene del backend)
  let availablePermissions = $state<Record<string, PermisoDisponible[]>>({});
  // Estado actual de permisos especiales del colegiado
  let currentSpecialPerms = $state<PermisoEspecialActual[]>([]);
  // Permisos seleccionados/deseleccionados por el usuario (id_permiso → boolean)
  let selectedPerms = $state<Record<number, boolean>>({});

  $effect(() => {
    if (isOpen && colegiado) {
      loadPermissions();
    }
  });

  async function loadPermissions() {
    if (!colegiado) return;
    isLoading = true;
    error = null;
    successMsg = null;

    try {
      const res = await fetch(
        `/docente/cursos/${cursoId}/team/${colegiado.id_usuario}/permissions`,
        {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        },
      );

      if (!res.ok) {
        throw new Error('No se pudieron cargar los permisos');
      }

      const data = await res.json();
      availablePermissions = data.available_permissions ?? {};
      currentSpecialPerms = data.special_permissions ?? [];

      // Inicializar selección con los permisos actualmente concedidos
      selectedPerms = {};
      for (const perm of currentSpecialPerms) {
        if (perm.esta_permitido) {
          selectedPerms[perm.id_permiso] = true;
        }
      }
    } catch (e: any) {
      error = e.message || 'Error al cargar permisos';
      console.error('Error loading colegiado permissions:', e);
    } finally {
      isLoading = false;
    }
  }

  function togglePerm(id: number) {
    selectedPerms[id] = !selectedPerms[id];
  }

  async function savePermissions() {
    if (!colegiado) return;
    isSaving = true;
    error = null;
    successMsg = null;

    // Construir payload de special_permissions
    const specialPermissions: Record<number, boolean | null> = {};
    const allPermIds = Object.values(availablePermissions)
      .flat()
      .map((p) => p.id_permiso);

    for (const id of allPermIds) {
      if (selectedPerms[id]) {
        specialPermissions[id] = true;
      }
      // No enviar los que no están seleccionados (se desactivan por el backend al hacer sync)
    }

    try {
      const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

      const res = await fetch(
        `/docente/cursos/${cursoId}/team/${colegiado.id_usuario}/sync-permissions`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({
            roles: [],
            special_permissions: specialPermissions,
          }),
        },
      );

      if (!res.ok) {
        const text = await res.text();
        throw new Error(text || 'Error al guardar permisos');
      }

      successMsg = 'Permisos actualizados correctamente';
      // Recargar para reflejar el estado actualizado
      await loadPermissions();
    } catch (e: any) {
      error = e.message || 'Error al guardar permisos';
      console.error('Error saving colegiado permissions:', e);
    } finally {
      isSaving = false;
    }
  }

  const allPerms = $derived(Object.values(availablePermissions).flat());
  const hasChanges = $derived(() => {
    // Comparar selección actual con lo que había
    const currentSet = new Set(
      currentSpecialPerms.filter((p) => p.esta_permitido).map((p) => p.id_permiso),
    );
    const selectedSet = new Set(
      Object.entries(selectedPerms)
        .filter(([, v]) => v)
        .map(([k]) => Number(k)),
    );
    if (currentSet.size !== selectedSet.size) return true;
    for (const id of currentSet) {
      if (!selectedSet.has(id)) return true;
    }
    return false;
  });
</script>

<Dialog.Root bind:open={isOpen}>
  <Dialog.Content class="max-w-lg">
    <Dialog.Header>
      <Dialog.Title class="flex items-center gap-2">
        <Shield class="h-5 w-5 text-indigo-600" />
        Autorizaciones
      </Dialog.Title>
      <Dialog.Description>
        {#if colegiado}
          Gestionar permisos especiales de <strong>{colegiado.nombre}</strong>
        {/if}
      </Dialog.Description>
    </Dialog.Header>

    <div class="space-y-4 py-2">
      {#if isLoading}
        <div class="flex items-center justify-center py-8 text-slate-500">
          <Loader2 class="h-5 w-5 animate-spin mr-2" />
          Cargando permisos...
        </div>
      {:else if error}
        <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          {error}
        </div>
      {:else if allPerms.length === 0}
        <div class="text-center py-8 text-slate-500">
          <Shield class="h-8 w-8 mx-auto mb-2 text-slate-300" />
          <p class="text-sm">No hay permisos disponibles para delegar.</p>
        </div>
      {:else}
        {#if successMsg}
          <div
            class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700"
          >
            {successMsg}
          </div>
        {/if}

        {#each Object.entries(availablePermissions) as [group, perms]}
          <div>
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
              {group}
            </h4>
            <div class="space-y-1">
              {#each perms as perm}
                <button
                  type="button"
                  onclick={() => togglePerm(perm.id_permiso)}
                  class={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg border text-left transition-colors ${
                    selectedPerms[perm.id_permiso]
                      ? 'border-indigo-300 bg-indigo-50'
                      : 'border-slate-200 bg-white hover:bg-slate-50'
                  }`}
                >
                  <div
                    class={`w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-colors ${
                      selectedPerms[perm.id_permiso]
                        ? 'border-indigo-600 bg-indigo-600'
                        : 'border-slate-300'
                    }`}
                  >
                    {#if selectedPerms[perm.id_permiso]}
                      <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path
                          fill-rule="evenodd"
                          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    {/if}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800">{perm.nombre}</p>
                    <p class="text-xs text-slate-500 truncate">{perm.slug}</p>
                  </div>
                </button>
              {/each}
            </div>
          </div>
        {/each}
      {/if}
    </div>

    {#if !isLoading && allPerms.length > 0}
      <Dialog.Footer class="flex gap-2">
        <Button
          variant="outline"
          onclick={() => {
            isOpen = false;
            onClose();
          }}
          class="gap-1"
        >
          <X class="h-4 w-4" />
          Cancelar
        </Button>
        <Button onclick={savePermissions} disabled={isSaving} class="gap-1">
          {#if isSaving}
            <Loader2 class="h-4 w-4 animate-spin" />
            Guardando...
          {:else}
            <Save class="h-4 w-4" />
            Guardar permisos
          {/if}
        </Button>
      </Dialog.Footer>
    {/if}
  </Dialog.Content>
</Dialog.Root>
