<script lang="ts">
    /**
     * Carcasa de las páginas de un curso del docente: la misma de DocenteLayout
     * con la cabecera del curso fija arriba (código, grupo y semestre; nombre
     * de la asignatura) y las seis pestañas Programa · Estudiantes ·
     * Actividades · Mensajes · Biblioteca · Gestión.
     *
     * Los textos de la cabecera salen de los cursos del docente que ya viajan
     * en cada página (auth.docente_courses), así cada página sólo dice qué
     * curso y qué sección es.
     */
    import { Link, page } from '@inertiajs/svelte';
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import { nombreSemestre, seccionesCurso, type SeccionCurso } from '@/lib/cursoDocente';
    import type { SidebarCourse } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        idCurso: number;
        seccion: SeccionCurso;
        /** Respaldo si el curso no está entre los del docente (p. ej. el admin). */
        nombre?: string;
        /** Miga extra al final de la ruta (p. ej. «Delegación de permisos» dentro de Gestión). */
        subpagina?: string;
        acciones?: Snippet;
        children?: Snippet;
    }

    let { idCurso, seccion, nombre, subpagina, acciones, children }: Props = $props();

    const info = $derived(
        (($page.props.auth?.docente_courses as SidebarCourse[]) ?? []).find((c) => Number(c.id_curso) === Number(idCurso)),
    );

    /** La etiqueta termina en «Grupo A»; el grupo va en la ceja. */
    const titulo = $derived.by(() => {
        const base = info?.etiqueta ?? info?.nombre ?? nombre ?? 'Curso';
        return info?.letra_grupo ? base.replace(new RegExp(`\\s+Grupo\\s+${info.letra_grupo}$`), '') : base;
    });

    const ceja = $derived(
        info
            ? [
                  info.cod_asignatura || info.cod_curso,
                  info.letra_grupo ? `Grupo ${info.letra_grupo}` : null,
                  nombreSemestre(info.semestre_real, info.agno_real),
              ]
                  .filter(Boolean)
                  .join(' · ')
            : undefined,
    );

    const secciones = $derived(seccionesCurso(idCurso));

    const breadcrumbs = $derived([
        { title: 'Mis cursos', href: '/docente/cursos' },
        ...(subpagina
            ? [
                  { title: titulo, href: secciones.find((s) => s.id === seccion)?.href ?? `/docente/cursos/${idCurso}` },
                  { title: subpagina, href: '' },
              ]
            : [{ title: titulo, href: '' }]),
    ]);
</script>

<DocenteLayout {breadcrumbs} {ceja} {titulo} {acciones}>
    {#snippet pestanas()}
        <nav aria-label="Secciones del curso" class="-mb-px flex gap-0.5 overflow-x-auto">
            {#each secciones as s (s.id)}
                {@const activa = s.id === seccion}
                <Link
                    href={s.href}
                    aria-current={activa ? 'page' : undefined}
                    class="inline-flex min-h-12 shrink-0 items-center px-4 text-sm font-bold whitespace-nowrap no-underline transition-colors hover:bg-inst-sutil hover:text-inst-ink {activa
                        ? 'border-b-[3px] border-inst-oro text-inst-ink'
                        : 'border-b-[3px] border-transparent text-inst-ink-2'}"
                >
                    {s.label}
                </Link>
            {/each}
        </nav>
    {/snippet}
    {@render children?.()}
</DocenteLayout>
