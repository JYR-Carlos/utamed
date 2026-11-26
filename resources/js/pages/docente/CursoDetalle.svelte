<script lang="ts">
  /**
   * Pestaña «Estudiantes» del curso — /docente/cursos/{curso}.
   *
   * Es la pestaña inicial del curso (dirección institucional UTA, revisión
   * «Mi UTAmed»): los estudiantes inscritos con su promedio, su asistencia y
   * lo que tienen pendiente, un buscador y el botón «Asistencia», que cambia
   * la tabla por el pase de lista del componente. La ficha de cada estudiante
   * se abre encima.
   *
   * El gobierno del curso (equipo, permisos, componentes) vive en la pestaña
   * Gestión; las actividades, en la suya.
   */
  import { page, router } from '@inertiajs/svelte';
  import { ArrowLeft, Search, UserCheck } from 'lucide-svelte';
  import CursoDocenteLayout from '@/layouts/CursoDocenteLayout.svelte';
  import { btnSecundario, campo, chip, cuerpo, tarjeta, tarjetaCabecera, titulo, tono } from '@/lib/inst';
  import type { PermissionItem } from '@/services/permissionValidator';
  import type { SidebarCourse } from '@/types';
  import EstudianteDetalleModal from './components/EstudianteDetalleModal.svelte';
  import AsistenciaPanel from './components/AsistenciaPanel.svelte';
  import EstudiantesTable from './components/EstudiantesTable.svelte';
  import ComponentePills from './components/ComponentePills.svelte';

  interface Componente {
    id_componente: number;
    tipo_componente: string;
    es_titular: boolean;
    total_docentes: number;
    total_estudiantes: number;
    total_sesiones: number;
    porcentaje_asistencia_obligatoria?: number | null;
  }

  interface EstudianteComponente {
    id_inscripcion_componente: number;
    id_componente: number;
    tipo_componente: string;
    nota_componente: number | null;
    asistencia?: { presentes: number; total: number };
    pendientes?: { por_revisar: number; no_entrego: number };
    estudiante: {
      id_estudiante: number;
      nombre: string;
      username: string;
    };
  }

  interface Curso {
    etiqueta?: string;
    id_curso: number;
    nombre: string;
    es_titular_curso: boolean;
    puede_delegar?: boolean;
    userPermissions?: PermissionItem[];
    userPermissionsByComponent?: Record<number, string[]>;
  }

  interface Props {
    curso: Curso;
    mis_componentes: Componente[];
    mis_estudiantes: EstudianteComponente[];
  }

  let { curso, mis_componentes, mis_estudiantes }: Props = $props();

  // «DM069 · Grupo A» para el subtítulo de la ficha: el código visible viene del menú.
  const codigoCurso = $derived.by(() => {
    const c = (($page.props.auth?.docente_courses as SidebarCourse[]) ?? []).find((x) => x.id_curso === curso.id_curso);
    const cod = c?.cod_asignatura || c?.cod_curso;
    if (!cod) return curso.etiqueta;
    return c?.letra_grupo ? `${cod} · Grupo ${c.letra_grupo}` : cod;
  });

  // «Asistencia» cambia la tabla por el pase de lista. Se abre directo con
  // ?vista=asistencia (o el ?tab=asistencia de antes, que usa el centro de asistencia).
  const inicioAsistencia = (() => {
    const q = new URLSearchParams($page.url.split('?')[1] ?? '');
    return (q.get('vista') ?? q.get('tab')) === 'asistencia' && mis_componentes.length > 0;
  })();
  let vistaAsistencia = $state(inicioAsistencia);

  function cambiarVista(asistencia: boolean) {
    vistaAsistencia = asistencia;
    const url = `/docente/cursos/${curso.id_curso}${asistencia ? '?vista=asistencia' : ''}`;
    router.replace({ url, preserveScroll: true, preserveState: true });
  }

  let componenteActivo = $state<number | null>(null);

  $effect.pre(() => {
    if (componenteActivo === null && mis_componentes.length > 0) {
      const query = $page.url.split('?')[1] ?? '';
      const compParam = Number(new URLSearchParams(query).get('componente'));
      const desdeUrl = compParam ? mis_componentes.find((c) => c.id_componente === compParam) : null;
      if (desdeUrl) {
        componenteActivo = desdeUrl.id_componente;
      } else {
        // Se abre en el primer componente con inscritos: abrir en uno vacío
        // (p. ej. Cátedra sin alumnos) escondía a los estudiantes.
        const conEstudiantes = mis_componentes.find((c) => c.total_estudiantes > 0);
        componenteActivo = (conEstudiantes ?? mis_componentes[0]).id_componente;
      }
    }
  });

  const componenteActual = $derived(mis_componentes.find((c) => c.id_componente === componenteActivo) ?? null);
  const estudiantesActivos = $derived(mis_estudiantes.filter((e) => e.id_componente === componenteActivo));

  let busqueda = $state('');
  const filtrados = $derived.by(() => {
    const q = busqueda.toLowerCase().trim();
    const qRut = q.replace(/[.\s]/g, '');
    if (!q) return estudiantesActivos;
    return estudiantesActivos.filter(
      (e) =>
        e.estudiante.nombre.toLowerCase().includes(q) ||
        e.estudiante.username.toLowerCase().replace(/[.\s]/g, '').includes(qRut),
    );
  });

  let modalEstudiante = $state(false);
  let estudianteSeleccionado = $state<EstudianteComponente | null>(null);

  function abrirFicha(est: EstudianteComponente) {
    estudianteSeleccionado = est;
    modalEstudiante = true;
  }
</script>

<svelte:head>
  <title>Estudiantes · {curso.etiqueta ?? curso.nombre} | UTAmed</title>
</svelte:head>

<CursoDocenteLayout idCurso={curso.id_curso} seccion="estudiantes" nombre={curso.etiqueta ?? curso.nombre}>
  <div class={cuerpo}>
    {#if mis_componentes.length === 0}
      <section class="{tarjeta} px-6 py-8 text-center">
        <p class="m-0 font-bold">No dictas ningún componente de este curso</p>
        <p class="m-0 mt-1 text-inst-ink-2">Los estudiantes aparecen aquí cuando tienes un componente asignado.</p>
      </section>
    {:else}
      <section aria-labelledby="t-inscritos" class="{tarjeta} overflow-hidden">
        <div class={tarjetaCabecera}>
          {#if vistaAsistencia}
            <button type="button" class="{btnSecundario} min-h-9 px-3 text-[13px]" onclick={() => cambiarVista(false)}>
              <ArrowLeft size={15} aria-hidden="true" />
              Estudiantes
            </button>
            <h2 id="t-inscritos" class="{titulo} m-0 text-[17px] leading-[22px]">Asistencia</h2>
          {:else}
            <h2 id="t-inscritos" class="{titulo} m-0 text-[17px] leading-[22px]">Estudiantes inscritos</h2>
            <span class="{chip} {tono.off}">{estudiantesActivos.length}</span>
          {/if}
          {#if mis_componentes.length > 1}
            <ComponentePills
              componentes={mis_componentes}
              {componenteActivo}
              mostrarContador={!vistaAsistencia}
              onSelect={(id) => {
                componenteActivo = id;
                busqueda = '';
              }}
            />
          {/if}
          {#if !vistaAsistencia}
            <label class="relative ml-auto w-full sm:w-[280px]">
              <span class="sr-only">Buscar estudiante</span>
              <Search
                size={16}
                aria-hidden="true"
                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-inst-ink-3"
              />
              <input type="search" bind:value={busqueda} placeholder="Buscar por nombre o RUT" class="{campo} pl-9" />
            </label>
            <button type="button" class={btnSecundario} onclick={() => cambiarVista(true)}>
              <UserCheck size={16} aria-hidden="true" />
              Asistencia
            </button>
          {/if}
        </div>

        {#if vistaAsistencia}
          <div class="p-4.5">
            {#if componenteActivo !== null}
              {#key componenteActivo}
                <AsistenciaPanel
                  idCurso={curso.id_curso}
                  idComponente={componenteActivo}
                  tipoComponente={componenteActual?.tipo_componente ?? 'Componente'}
                />
              {/key}
            {/if}
          </div>
        {:else if estudiantesActivos.length === 0}
          <p class="m-0 px-4.5 py-8 text-center text-inst-ink-2">
            Todavía no hay estudiantes inscritos en {componenteActual?.tipo_componente ?? 'este componente'}.
          </p>
        {:else if filtrados.length === 0}
          <p class="m-0 px-4.5 py-8 text-center text-inst-ink-2">Ningún estudiante coincide con «{busqueda}».</p>
        {:else}
          {#key componenteActivo}
            <EstudiantesTable
              estudiantes={filtrados}
              minAsistencia={componenteActual?.porcentaje_asistencia_obligatoria ?? null}
              onDetalle={(item) => abrirFicha(item as EstudianteComponente)}
              inicial={busqueda ? null : 8}
            />
          {/key}
        {/if}
      </section>
    {/if}
  </div>

  {#if estudianteSeleccionado}
    <EstudianteDetalleModal
      abierto={modalEstudiante}
      estudiante={estudianteSeleccionado}
      idCurso={curso.id_curso}
      cursoEtiqueta={codigoCurso}
      onCerrar={() => {
        modalEstudiante = false;
        estudianteSeleccionado = null;
      }}
    />
  {/if}
</CursoDocenteLayout>
