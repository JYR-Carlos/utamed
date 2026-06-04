<script lang="ts">
  import * as Dialog from '@/components/ui/dialog';
  import { Button } from '@/components/ui/button';
  import { Shield, Loader2, Save, X } from 'lucide-svelte';
  import { useForm } from '@inertiajs/svelte';

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
    special_permissions: PermisoEspecialActual[];
  }

  interface Props {
    isOpen: boolean;
    cursoId: number;
    colegiado: ColegiadoDocente | null;
    availablePermissions: Record<string, PermisoDisponible[]>;
    onClose: () => void;
  }

  let { isOpen = $bindable(), cursoId, colegiado, availablePermissions = {}, onClose }: Props = $props();

  let successMsg = $state<string | null>(null);

  // Formularios de Inertia
  let form = useForm({
    roles: [] as number[],
    special_permissions: {} as Record<number, boolean | null>,
  });

  // Permisos seleccionados/deseleccionados por el usuario (id_permiso → boolean)
  let selectedPerms = $state<Record<number, boolean>>({});

  $effect(() => {
    if (isOpen && colegiado) {
      loadPermissions();
    }
  });

  function loadPermissions() {
    if (!colegiado) return;
    successMsg = null;
    $form.clearErrors();
    
    selectedPerms = {};
    if (colegiado.special_permissions) {
      for (const perm of colegiado.special_permissions) {
        if (perm.esta_permitido) {
          selectedPerms[perm.id_permiso] = true;
        }
      }
    }
  }

  function togglePerm(id: number) {
    selectedPerms[id] = !selectedPerms[id];
  }

  function savePermissions() {
    if (!colegiado) return;
    successMsg = null;

    const specialPermissions: Record<number, boolean | null> = {};
    const allPermIds = Object.values(availablePermissions)
      .flat()
      .map((p) => p.id_permiso);

    for (const id of allPermIds) {
      if (selectedPerms[id]) {
        specialPermissions[id] = true;
      }
    }

    $form.special_permissions = specialPermissions;
    $form.roles = [];

    $form.post(`/docente/cursos/${cursoId}/team/${colegiado.id_usuario}/sync-permissions`, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        successMsg = 'Permisos actualizados correctamente';
      },
    });
  }

  const allPerms = $derived(Object.values(availablePermissions).flat());
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
      {#if Object.keys($form.errors).length > 0}
        <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          Revisa los errores antes de continuar.
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

    {#if allPerms.length > 0}
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
        <Button onclick={savePermissions} disabled={$form.processing} class="gap-1">
          {#if $form.processing}
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
