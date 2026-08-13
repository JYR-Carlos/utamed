<script lang="ts">
  /**
   * Mensajería del docente — hilos sostenidos por el componente.
   *
   * Distinta de docente/Mensajes.svelte, que lee agenda.agenda (feedback de
   * entregas por actividad). Aquí la conversación no depende de ninguna
   * actividad: son avisos al componente y un canal por alumno.
   *
   * Toda la UI vive en BandejaStaff, compartida con el ayudante.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import BandejaStaff from '@/components/mensajeria/BandejaStaff.svelte';
  import type { BreadcrumbItem } from '@/types';

  let { cursos = [], base_ruta = '/docente/mensajeria', panel = null } = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/docente/dashboard' },
    { title: 'Mensajería', href: '/docente/mensajeria' },
  ];
</script>

<DocenteLayout {breadcrumbs}>
  <BandejaStaff {cursos} {base_ruta} {panel} />
</DocenteLayout>
