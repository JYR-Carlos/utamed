<script lang="ts">
  /**
   * Centro de Asistencia del docente — /docente/asistencia (dirección
   * institucional UTA).
   *
   * En la cabecera se elige el curso y el componente (por defecto, el primero
   * del período más reciente); debajo, AsistenciaPanel con las sesiones, el
   * pase de lista y la asistencia por estudiante.
   *
   * Reglas: cualquier docente asignado a un componente puede tomar asistencia
   * (sea o no titular del componente; sólo el titular califica). El titular
   * del curso puede tomar asistencia de cualquier componente del curso.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { campo, cuerpo, tarjeta } from '@/lib/inst';
  import type { BreadcrumbItem } from '@/types';
  import type { ComponenteCentro, CursoCentro } from '@/types/centro-docente';
  import AsistenciaPanel from './components/AsistenciaPanel.svelte';

  interface ComponenteAsistencia extends ComponenteCentro {
    total_estudiantes: number;
  }

  type Curso = CursoCentro<ComponenteAsistencia>;

  interface Props {
    cursos: Curso[];
  }

  let { cursos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Asistencia', href: '/docente/asistencia' },
  ];

  /** El nombre del curso sin el «Grupo A» final, que ya va en el código. */
  function nombreCurso(c: Curso) {
    const base = c.etiqueta || c.nombre;
    return c.letra_grupo ? base.replace(new RegExp(`\\s+Grupo\\s+${c.letra_grupo}$`), '') : base;
  }

  const codigoDe = (c: Curso) => c.cod_asignatura || c.cod_curso;

  // ── Opciones del selector, por período (el más reciente primero) ───────────
  interface Opcion {
    valor: string;
    curso: Curso;
    comp: ComponenteAsistencia;
  }

  const grupos = $derived.by(() => {
    const porClave = new Map<string, { orden: number; etiqueta: string; opciones: Opcion[] }>();
    for (const curso of cursos) {
      const conPeriodo = curso.agno_real && curso.semestre_real;
      const clave = conPeriodo ? `${curso.agno_real}-${curso.semestre_real}` : 'sin';
      if (!porClave.has(clave)) {
        porClave.set(clave, {
          orden: conPeriodo ? curso.agno_real! * 10 + curso.semestre_real! : -1,
          etiqueta: conPeriodo ? `Semestre ${curso.semestre_real} · ${curso.agno_real}` : 'Sin período',
          opciones: [],
        });
      }
      for (const comp of curso.componentes) {
        porClave.get(clave)!.opciones.push({ valor: `${curso.id_curso}-${comp.id_componente}`, curso, comp });
      }
    }
    return [...porClave.values()].sort((a, b) => b.orden - a.orden);
  });

  const opciones = $derived(grupos.flatMap((g) => g.opciones));

  // Por defecto, el primer componente con estudiantes del período más reciente.
  let elegido = $state<string | null>(null);
  const actual = $derived(
    opciones.find((o) => o.valor === elegido) ??
      grupos[0]?.opciones.find((o) => o.comp.total_estudiantes > 0) ??
      opciones[0] ??
      null,
  );

  const ceja = $derived(
    actual
      ? [codigoDe(actual.curso), actual.curso.letra_grupo && `Grupo ${actual.curso.letra_grupo}`, actual.comp.tipo_componente]
          .filter(Boolean)
          .join(' · ')
      : undefined,
  );

  function etiquetaOpcion(o: Opcion) {
    const partes = [codigoDe(o.curso), o.curso.letra_grupo, nombreCurso(o.curso), o.comp.tipo_componente];
    const texto = partes.filter(Boolean).join(' · ');
    return o.comp.total_estudiantes === 0 ? `${texto} (sin estudiantes)` : texto;
  }
</script>

<DocenteLayout {breadcrumbs} {ceja} titulo="Asistencia">
  {#snippet acciones()}
    {#if opciones.length > 1}
      <label class="flex w-full flex-col gap-1 sm:w-auto">
        <span class="sr-only">Curso y componente</span>
        <select
          value={actual?.valor}
          onchange={(e) => (elegido = e.currentTarget.value)}
          class="{campo} sm:w-auto sm:max-w-[440px]"
        >
          {#each grupos as g (g.etiqueta)}
            <optgroup label={g.etiqueta}>
              {#each g.opciones as o (o.valor)}
                <option value={o.valor}>{etiquetaOpcion(o)}</option>
              {/each}
            </optgroup>
          {/each}
        </select>
      </label>
    {/if}
  {/snippet}

  <div class={cuerpo}>
    {#if !actual}
      <div class="{tarjeta} px-6 py-10 text-center">
        <p class="m-0 font-bold">No tienes componentes para tomar asistencia.</p>
        <p class="m-0 mt-1 text-inst-ink-2">
          La asistencia se toma sobre los componentes que impartes; aún no tienes ninguno asignado.
        </p>
      </div>
    {:else}
      {#key actual.valor}
        <AsistenciaPanel
          idCurso={actual.curso.id_curso}
          idComponente={actual.comp.id_componente}
          tipoComponente={actual.comp.tipo_componente}
        />
      {/key}
    {/if}
  </div>
</DocenteLayout>
