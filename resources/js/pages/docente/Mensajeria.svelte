<script lang="ts">
  /**
   * Mensajería del docente para un curso — hilos sostenidos por el componente.
   *
   * Distinta de docente/Mensajes.svelte, que lee agenda.agenda (feedback de
   * entregas por actividad). Aquí la conversación no depende de ninguna
   * actividad: son avisos al componente y un canal por alumno.
   *
   * Es la pestaña «Mensajes» del curso. Toda la UI vive en BandejaStaff,
   * compartida con el ayudante.
   */
  import CursoDocenteLayout from '@/layouts/CursoDocenteLayout.svelte';
  import BandejaStaff from '@/components/mensajeria/BandejaStaff.svelte';

  let {
    curso,
    componentes = [],
    componente_activo = null,
    base_ruta,
    panel = null,
  } = $props();

</script>

<CursoDocenteLayout idCurso={curso?.id_curso} seccion="mensajes" nombre={curso?.etiqueta ?? curso?.nombre}>
  <div class="mx-auto w-full max-w-[1200px] px-4 pt-4 pb-8 lg:px-10 lg:pt-7">
    <BandejaStaff {curso} {componentes} {componente_activo} {base_ruta} {panel} />
  </div>
</CursoDocenteLayout>
