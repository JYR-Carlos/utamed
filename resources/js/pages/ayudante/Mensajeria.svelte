<script lang="ts">
  /**
   * Mensajería del ayudante para un curso — misma bandeja que el docente,
   * acotada al curso desde el que se entra.
   *
   * Cuando el ayudante responde, su mensaje entra en el mismo canal
   * (componente, alumno) que usan los docentes: la conversación no se divide.
   */
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import BandejaStaff from '@/components/mensajeria/BandejaStaff.svelte';
  import type { BreadcrumbItem } from '@/types';

  let {
    curso,
    componentes = [],
    componente_activo = null,
    base_ruta,
    panel = null,
  } = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/ayudante/dashboard' },
    { title: 'Mis Cursos', href: '/ayudante/cursos' },
    { title: curso?.nombre ?? 'Curso', href: `/ayudante/cursos/${curso?.id_curso}` },
    { title: 'Mensajería', href: '' },
  ]);
</script>

<AyudanteLayout {breadcrumbs}>
  <BandejaStaff {curso} {componentes} {componente_activo} {base_ruta} {panel} />
</AyudanteLayout>
