<script lang="ts">
  /**
   * Centro de Calificaciones del docente — /docente/calificaciones
   * (dirección institucional UTA).
   *
   * Una tabla por curso con todas sus actividades: cuántos grupos entregaron y
   * esperan nota (sumativas) o evaluación (formativas, que al evaluarse quedan
   * revisadas), cuántos ya están listos y el botón para ir a evaluar. Primero
   * aparece lo que tiene entregas esperando.
   *
   * Reglas: el titular del curso ve todos los componentes; el resto, sólo los
   * que imparte. La evaluación (rúbrica + nota) se hace en la pantalla de la
   * actividad.
   */
  import { Link } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import type { ComponenteCentro, CursoCentro } from '@/types/centro-docente';
  import {
    btnPrimario,
    btnSecundario,
    campo,
    chico,
    chip,
    codigo,
    cuerpo,
    etiqueta,
    tarjeta,
    tarjetaCabecera,
    titulo,
    tono,
  } from '@/lib/inst';
  import type { BreadcrumbItem } from '@/types';

  interface ActividadCalif {
    id_actividad: number;
    nombre: string;
    tipo_actividad: string;
    es_grupal: boolean;
    fecha_limite: string | null;
    tiene_grupos: boolean;
    por_calificar: number;
    calificados: number;
    por_revisar: number;
    revisados: number;
  }

  interface ComponenteCalif extends ComponenteCentro {
    actividades: ActividadCalif[];
  }

  interface Props {
    cursos: CursoCentro<ComponenteCalif>[];
  }

  let { cursos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Calificaciones', href: '/docente/calificaciones' },
  ];

  // ── Período: por defecto el más reciente ───────────────────────────────────
  const clavePeriodo = (c: CursoCentro<ComponenteCalif>) =>
    c.agno_real && c.semestre_real ? `${c.agno_real}-${c.semestre_real}` : 'Sin período';

  const periodos = $derived(
    [...new Set(cursos.map(clavePeriodo))].sort((a, b) => {
      const [aa, as] = a.split('-').map((n) => Number(n) || 0);
      const [ba, bs] = b.split('-').map((n) => Number(n) || 0);
      return ba - aa || bs - as;
    }),
  );
  let periodoElegido = $state<string | null>(null);
  const periodo = $derived(periodoElegido ?? periodos[0] ?? '');

  const ceja = $derived.by(() => {
    const [agno, sem] = periodo.split('-');
    return Number(agno) && Number(sem) ? `Semestre ${sem} · ${agno}` : undefined;
  });

  // ── Filas ───────────────────────────────────────────────────────────────────
  const esSumativa = (a: ActividadCalif) => a.tipo_actividad === 'SUMATIVA';
  const pendientes = (a: ActividadCalif) => (esSumativa(a) ? a.por_calificar : a.por_revisar);
  const listas = (a: ActividadCalif) => (esSumativa(a) ? a.calificados : a.revisados);

  type Fila = ActividadCalif & { componente: string };

  const secciones = $derived.by(() =>
    cursos
      .filter((c) => clavePeriodo(c) === periodo)
      .map((curso) => {
        const variosComponentes = curso.componentes.length > 1;
        const filas: Fila[] = curso.componentes
          .flatMap((comp) =>
            comp.actividades.map((a) => ({ ...a, componente: variosComponentes ? comp.tipo_componente : '' })),
          )
          // Lo que espera evaluación primero; dentro, por fecha de cierre.
          .sort(
            (a, b) =>
              Number(pendientes(b) > 0) - Number(pendientes(a) > 0) ||
              (a.fecha_limite ?? '9999').localeCompare(b.fecha_limite ?? '9999'),
          );
        const totalPendientes = filas.reduce((n, a) => n + pendientes(a), 0);
        return { curso, filas, totalPendientes };
      })
      .sort((a, b) => b.totalPendientes - a.totalPendientes),
  );

  const resumen = $derived.by(() => {
    const filas = secciones.flatMap((s) => s.filas);
    const sumativas = filas.filter(esSumativa);
    const formativas = filas.filter((a) => !esSumativa(a));
    return {
      porCalificar: sumativas.reduce((n, a) => n + a.por_calificar, 0),
      actividadesPorCalificar: sumativas.filter((a) => a.por_calificar > 0).length,
      porRevisar: formativas.reduce((n, a) => n + a.por_revisar, 0),
      calificadas: sumativas.reduce((n, a) => n + a.calificados, 0),
    };
  });

  function codigoCurso(c: CursoCentro<ComponenteCalif>) {
    const cod = c.cod_asignatura || c.cod_curso;
    return c.letra_grupo ? `${cod} · Grupo ${c.letra_grupo}` : cod;
  }

  /** El nombre del curso sin el «Grupo A» final, que ya va en el código. */
  function nombreCurso(c: CursoCentro<ComponenteCalif>) {
    const base = c.etiqueta || c.nombre;
    return c.letra_grupo ? base.replace(new RegExp(`\\s+Grupo\\s+${c.letra_grupo}$`), '') : base;
  }

  const hoy = new Date().toISOString().slice(0, 10);

  function cierre(fecha: string | null): string {
    if (!fecha) return 'Sin fecha';
    const texto = new Date(`${fecha}T12:00:00`).toLocaleDateString('es-CL', {
      weekday: fecha >= hoy ? 'short' : undefined,
      day: '2-digit',
      month: 'short',
    });
    return fecha >= hoy ? texto : `cerró el ${texto}`;
  }

  function evaluar(curso: CursoCentro<ComponenteCalif>, a: ActividadCalif) {
    return `/docente/cursos/${curso.id_curso}/actividades/${a.id_actividad}/evaluacion`;
  }
</script>

{#snippet accion(curso: CursoCentro<ComponenteCalif>, a: ActividadCalif)}
  {#if a.es_grupal && !a.tiene_grupos}
    <span class="{chip} {tono.warn}">Sin grupos asignados</span>
  {:else if pendientes(a) > 0}
    <Link href={evaluar(curso, a)} class="{btnPrimario} {chico}" aria-label="{esSumativa(a) ? 'Calificar' : 'Revisar'} {a.nombre}">
      {esSumativa(a) ? 'Calificar' : 'Revisar'}
    </Link>
  {:else}
    <Link href={evaluar(curso, a)} class="{btnSecundario} {chico}" aria-label="Ver {a.nombre}">Ver</Link>
  {/if}
{/snippet}

{#snippet tipo(a: ActividadCalif)}
  <span class="{chip} {esSumativa(a) ? tono.accion : tono.oro}">{esSumativa(a) ? 'Sumativa' : 'Formativa'}</span>
{/snippet}

<DocenteLayout {breadcrumbs} {ceja} titulo="Calificaciones">
  {#snippet acciones()}
    <p class="m-0 max-w-[340px] text-[13px] text-inst-ink-2">
      Elige una actividad para calificar. Primero aparece lo que tiene entregas esperando.
    </p>
  {/snippet}

  <div class={cuerpo}>
    {#if cursos.length === 0}
      <div class="{tarjeta} px-6 py-10 text-center">
        <p class="m-0 font-bold">No tienes actividades para calificar.</p>
        <p class="m-0 mt-1 text-inst-ink-2">
          Las calificaciones se hacen sobre las actividades de los componentes que impartes; aún no hay ninguna.
        </p>
      </div>
    {:else}
      <div class="{tarjeta} flex flex-wrap items-stretch">
        <div class="flex min-w-[180px] flex-1 flex-col gap-1 px-5 py-4">
          <span class={etiqueta}>Por calificar</span>
          <span class="{titulo} text-[28px] leading-8 {resumen.porCalificar > 0 ? 'text-inst-warn' : ''}">{resumen.porCalificar}</span>
          <span class="text-[13px] text-inst-ink-2">
            {resumen.porCalificar === 1 ? 'entrega' : 'entregas'} en {resumen.actividadesPorCalificar}
            {resumen.actividadesPorCalificar === 1 ? 'actividad sumativa' : 'actividades sumativas'}
          </span>
        </div>
        <div class="flex min-w-[180px] flex-1 flex-col gap-1 border-inst-linea px-5 py-4 max-sm:border-t sm:border-l">
          <span class={etiqueta}>Por revisar</span>
          <span class="{titulo} text-[28px] leading-8">{resumen.porRevisar}</span>
          <span class="text-[13px] text-inst-ink-2">entregas formativas sin evaluar</span>
        </div>
        <div class="flex min-w-[180px] flex-1 flex-col gap-1 border-inst-linea px-5 py-4 max-sm:border-t sm:border-l">
          <span class={etiqueta}>Calificadas</span>
          <span class="{titulo} text-[28px] leading-8">{resumen.calificadas}</span>
          <span class="text-[13px] text-inst-ink-2">grupos con nota en el período</span>
        </div>
        {#if periodos.length > 1}
          <label class="flex items-center gap-2 border-inst-linea px-5 py-4 max-lg:w-full max-lg:border-t lg:border-l">
            <span class="text-[13px] font-bold">Período</span>
            <select value={periodo} onchange={(e) => (periodoElegido = e.currentTarget.value)} class="{campo} w-auto">
              {#each periodos as p (p)}
                <option value={p}>{p}</option>
              {/each}
            </select>
          </label>
        {/if}
      </div>

      {#each secciones as { curso, filas, totalPendientes } (curso.id_curso)}
        <section aria-labelledby="c-{curso.id_curso}" class="{tarjeta} overflow-hidden">
          <div class={tarjetaCabecera}>
            <span class="flex min-w-0 flex-col">
              <span class={codigo}>{codigoCurso(curso)}</span>
              <h2 id="c-{curso.id_curso}" class="{titulo} m-0 text-base leading-6">{nombreCurso(curso)}</h2>
            </span>
            {#if !curso.es_titular_curso}<span class="{chip} {tono.oro}">Componente</span>{/if}
            <span class="{chip} {totalPendientes > 0 ? tono.warn : tono.ok} ml-auto">
              {totalPendientes > 0 ? `${totalPendientes} ${totalPendientes === 1 ? 'pendiente' : 'pendientes'}` : 'Al día'}
            </span>
          </div>
          <!-- Teléfono: una ficha compacta por actividad. -->
          <ul class="m-0 list-none p-0 md:hidden">
            {#each filas as a (a.id_actividad)}
              {@const pend = pendientes(a)}
              <li class="flex flex-col gap-2 border-b border-inst-linea px-4 py-3.5 last:border-b-0">
                <span class="font-bold">{a.nombre}</span>
                <span class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-inst-ink-2">
                  {@render tipo(a)}
                  <span>{cierre(a.fecha_limite)}</span>
                  <span>· {a.es_grupal ? 'Grupal' : 'Individual'}{a.componente ? ` · ${a.componente}` : ''}</span>
                </span>
                <span class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px]">
                  <span>Pendientes <b class={pend > 0 ? 'text-inst-warn' : 'text-inst-ink-3'}>{pend}</b></span>
                  <span>Listas <b>{listas(a)}</b></span>
                  <span class="ml-auto">{@render accion(curso, a)}</span>
                </span>
              </li>
            {/each}
          </ul>
          <table class="tabla-inst max-md:hidden">
            <thead>
              <tr>
                <th scope="col">Actividad</th>
                <th scope="col">Tipo</th>
                <th scope="col">Cierre</th>
                <th scope="col" class="text-right">Pendientes</th>
                <th scope="col" class="text-right">Listas</th>
                <th scope="col"><span class="sr-only">Acción</span></th>
              </tr>
            </thead>
            <tbody>
              {#each filas as a (a.id_actividad)}
                {@const pend = pendientes(a)}
                <tr>
                  <td data-l="Actividad">
                    <span class="flex min-w-0 flex-col">
                      <span class="font-bold">{a.nombre}</span>
                      <span class="text-[13px] text-inst-ink-2">
                        {a.es_grupal ? 'Grupal' : 'Individual'}{a.componente ? ` · ${a.componente}` : ''}
                      </span>
                    </span>
                  </td>
                  <td data-l="Tipo">{@render tipo(a)}</td>
                  <td data-l="Cierre" class="text-[13px] whitespace-nowrap">{cierre(a.fecha_limite)}</td>
                  <td data-l="Pendientes" class="text-right">
                    <span class="font-extrabold {pend > 0 ? 'text-inst-warn' : 'text-inst-ink-3'}">{pend}</span>
                  </td>
                  <td data-l="Listas" class="text-right">{listas(a)}</td>
                  <td class="text-right">{@render accion(curso, a)}</td>
                </tr>
              {/each}
            </tbody>
          </table>
        </section>
      {/each}
    {/if}
  </div>
</DocenteLayout>
