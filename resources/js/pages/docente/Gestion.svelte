<script lang="ts">
  /**
   * Pestaña «Gestión» del curso — /docente/cursos/{curso}/gestion.
   *
   * Reúne lo que gobierna el curso y antes vivía como botones sueltos en su
   * ficha: el equipo docente, la delegación de permisos (sólo el titular), los
   * grupos de trabajo y el syllabus, con su estado a la vista.
   */
  import { Link } from '@inertiajs/svelte';
  import { BookOpen, ChevronRight, Shield, UserPlus, Users } from 'lucide-svelte';
  import CursoDocenteLayout from '@/layouts/CursoDocenteLayout.svelte';
  import { chip, cuerpo, tarjeta, titulo, tono, type Tono } from '@/lib/inst';

  interface Props {
    curso: { id_curso: number; nombre: string; etiqueta?: string; es_titular_curso: boolean };
    programa: { estado: string; razon_rechazo?: string | null } | null;
  }

  let { curso, programa }: Props = $props();

  const base = $derived(`/docente/cursos/${curso.id_curso}`);

  /** Estado del syllabus en las palabras del visor, con su tono. */
  const estadoSyllabus = $derived.by((): { texto: string; tono: Tono } => {
    switch (programa?.estado) {
      case undefined:
        return { texto: 'Sin syllabus todavía', tono: 'off' };
      case 'BORRADOR':
        return programa.razon_rechazo?.trim()
          ? { texto: 'Rechazado · requiere corrección', tono: 'error' }
          : { texto: 'Borrador', tono: 'warn' };
      case 'BASICO_COMPLETO':
        return { texto: 'Básico completo', tono: 'info' };
      case 'COMPLETO':
      case 'ENVIADO':
        return { texto: 'En revisión', tono: 'info' };
      case 'APROBADO':
      case 'PUBLICADO':
        return { texto: 'Aprobado', tono: 'ok' };
      default:
        return { texto: programa!.estado, tono: 'off' };
    }
  });

  const accesos = $derived(
    [
      curso.es_titular_curso && {
        icono: Users,
        titulo: 'Equipo docente',
        texto: 'Docentes y ayudantes del curso, por componente.',
        href: `${base}/docentes`,
      },
      curso.es_titular_curso && {
        icono: Shield,
        titulo: 'Delegación de permisos',
        texto: 'Qué puede hacer cada integrante del equipo en este curso.',
        href: `${base}/delegacion-permisos`,
      },
      {
        icono: UserPlus,
        titulo: 'Gestión de grupos',
        texto: 'Crea y modifica los grupos de trabajo de las actividades grupales.',
        href: `${base}/gestion/grupos`,
      },
      {
        icono: BookOpen,
        titulo: 'Syllabus',
        texto: 'Redacta el programa, envíalo a revisión y revisa sus versiones.',
        // Se redacta en el asistente; aprobado o en revisión sólo se lee, y sin
        // syllabus el visor es donde se elige su tipo.
        href:
          programa && ['BORRADOR', 'BASICO_COMPLETO'].includes(programa.estado)
            ? `${base}/programa/editar`
            : `${base}/programa`,
        estado: estadoSyllabus,
      },
    ].filter(Boolean) as Array<{
      icono: typeof Users;
      titulo: string;
      texto: string;
      href: string;
      estado?: { texto: string; tono: Tono };
    }>,
  );
</script>

<svelte:head>
  <title>Gestión · {curso.etiqueta ?? curso.nombre} | UTAmed</title>
</svelte:head>

<CursoDocenteLayout idCurso={curso.id_curso} seccion="gestion" nombre={curso.etiqueta ?? curso.nombre}>
  <div class={cuerpo}>
    <ul class="m-0 grid list-none grid-cols-1 gap-4 p-0 lg:grid-cols-2">
      {#each accesos as a (a.titulo)}
        {@const Icono = a.icono}
        <li>
          <Link
            href={a.href}
            class="{tarjeta} flex min-h-full gap-4 p-5 text-inst-ink no-underline transition-colors hover:bg-inst-sutil"
          >
            <span
              aria-hidden="true"
              class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-inst-accion-s text-inst-accion-t"
            >
              <Icono size={24} />
            </span>
            <span class="flex min-w-0 flex-1 flex-col gap-1.5">
              <span class="{titulo} text-[17px] leading-[22px]">{a.titulo}</span>
              <span class="text-inst-ink-2">{a.texto}</span>
              {#if a.estado}
                <span><span class="{chip} {tono[a.estado.tono]}">{a.estado.texto}</span></span>
              {/if}
            </span>
            <ChevronRight size={20} aria-hidden="true" class="shrink-0 self-center text-inst-ink-3" />
          </Link>
        </li>
      {/each}
    </ul>
  </div>
</CursoDocenteLayout>
