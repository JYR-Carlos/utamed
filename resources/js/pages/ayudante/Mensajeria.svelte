<script lang="ts">
  /**
   * Mensajería del ayudante — misma bandeja que el docente, acotada a los cursos
   * donde tiene el rol Ayudante.
   *
   * Cuando el ayudante responde, su mensaje entra en el mismo canal
   * (componente, alumno) que usan los docentes: la conversación no se divide.
   */
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import BandejaStaff from '@/components/mensajeria/BandejaStaff.svelte';
  import type { BreadcrumbItem } from '@/types';

  let { cursos = [], base_ruta = '/ayudante/mensajeria', panel = null } = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/ayudante/dashboard' },
    { title: 'Mensajería', href: '/ayudante/mensajeria' },
  ];
</script>

<AyudanteLayout {breadcrumbs}>
  <BandejaStaff {cursos} {base_ruta} {panel} />
</AyudanteLayout>
