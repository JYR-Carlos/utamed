<script lang="ts">
  /**
   * MobileSidebar — cajón de navegación para pantallas por debajo de `lg`.
   *
   * El sidebar de escritorio es `hidden lg:flex`, así que en teléfono y
   * tableta vertical no había ningún menú: el botón de la cabecera cambiaba
   * un estado que nada dibujaba. Este cajón reutiliza `RoleSidebar` (mismos
   * enlaces y permisos que en escritorio) y se cierra solo al navegar.
   */
  import { page } from '@inertiajs/svelte';
  import * as Sheet from '@/components/ui/sheet';
  import { useSidebar } from '@/components/ui/sidebar/context.svelte';
  import RoleSidebar from './RoleSidebar.svelte';

  const sidebar = useSidebar();

  // Cada visita Inertia cierra el cajón: sin esto, tocar un enlace dejaba el
  // menú abierto tapando la página nueva.
  $effect(() => {
    $page.url;
    sidebar.setOpenMobile(false);
  });
</script>

<Sheet.Root bind:open={() => sidebar.openMobile, (abierto) => sidebar.setOpenMobile(abierto)}>
  <!-- `mobile-nav` (app.css) agranda texto y filas para el dedo; el sidebar de
       escritorio no cambia. El foco inicial no se mueve al primer enlace: dejaba
       «Inicio» con un anillo de foco permanente nada más abrir. -->
  <Sheet.Content
    side="left"
    class="mobile-nav w-80 max-w-[88vw] gap-0 p-0 sm:max-w-80 lg:hidden"
    onOpenAutoFocus={(e) => e.preventDefault()}
  >
    <Sheet.Header class="sr-only">
      <Sheet.Title>Menú de navegación</Sheet.Title>
      <Sheet.Description>Accesos a las secciones del sistema.</Sheet.Description>
    </Sheet.Header>
    <RoleSidebar />
  </Sheet.Content>
</Sheet.Root>
