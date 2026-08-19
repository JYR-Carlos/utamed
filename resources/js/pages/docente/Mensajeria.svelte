<script lang="ts">
  /**
   * Mensajería del docente para un curso — hilos sostenidos por el componente.
   *
   * Distinta de docente/Mensajes.svelte, que lee agenda.agenda (feedback de
   * entregas por actividad). Aquí la conversación no depende de ninguna
   * actividad: son avisos al componente y un canal por alumno.
   *
   * Se entra desde el curso, así que el breadcrumb vuelve a él. Toda la UI vive
   * en BandejaStaff, compartida con el ayudante.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
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
    { title: 'Dashboard', href: '/docente/dashboard' },
    { title: 'Mis Cursos', href: '/docente/cursos' },
    { title: curso?.nombre ?? 'Curso', href: `/docente/cursos/${curso?.id_curso}` },
    { title: 'Mensajería', href: '' },
  ]);
</script>

<DocenteLayout {breadcrumbs}>
  <BandejaStaff {curso} {componentes} {componente_activo} {base_ruta} {panel} />
</DocenteLayout>
