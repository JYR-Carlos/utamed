<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';
  import { page, Link } from '@inertiajs/svelte';
  import { ArrowUpRight, BookOpen, CalendarClock, ChevronRight, LifeBuoy } from 'lucide-svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import SemanaAlumno from '@/components/student/inicio/SemanaAlumno.svelte';
  import MensajesSinLeer from '@/components/student/inicio/MensajesSinLeer.svelte';
  import NotasRecientes, { type NotaReciente } from '@/components/student/inicio/NotasRecientes.svelte';
  import TarjetaCurso from '@/components/student/inicio/TarjetaCurso.svelte';
  import BotonSgeq from '@/components/custom/common/BotonSgeq.svelte';
  import { plazoDe, type ActividadPorVencer } from '@/lib/plazoAlumno';
  import { estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import { btnNoche, btnPrimario, btnSecundario, enlace, etiqueta, pildora, tituloCond, tono } from '@/lib/dm';
  import { claveDia, parseFechaSoloDia } from '@/utils/formatters';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    cursos: Array<{
      id_curso: number;
      nombre: string;
      cod_curso: string;
      cod_asignatura?: string | null;
      asignatura_nombre: string;
      carrera_nombre: string;
      fecha_inicio: string;
      fecha_fin?: string;
      profesor: string;
      semestre_real: number;
      agno_real: number;
    }>;
    stats: {
      total_cursos: number;
      nombre_completo: string;
    };

    /** Mensajes de curso (curso.mensaje) que el alumno aún no abre. */
    mensajeria?: {
      no_leidos: number;
      cursos: Array<{ id_curso: number; nombre: string; cod_curso: string; cod_asignatura?: string | null; no_leidos: number }>;
    };
    /** Actividades cuyo plazo termina pronto (ya con la holgura aplicada). */
    proximasAVencer?: ActividadPorVencer[];
    /** Últimas notas y retroalimentaciones del equipo docente. */
    notasRecientes?: NotaReciente[];
    isAyudante?: boolean;
    periodoActual: { semestre: number, agno: number}
    /** El servidor ya evaluó si esta persona puede entrar a SGEQ. */
    puedeAbrirSgeq?: boolean;
  }

  let {
    cursos,
    stats,
    mensajeria,
    proximasAVencer = [],
    notasRecientes = [],
    isAyudante = false,
    puedeAbrirSgeq = false,
    periodoActual
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/estudiante/dashboard' }];

  const cursosPeriodoActual = $derived(
    cursos.filter(
      (c) =>
        c.semestre_real === periodoActual.semestre &&
        c.agno_real === periodoActual.agno
    )
  );

  /** No leídos de mensajería por curso, para la tarjeta de cada curso. */
  const noLeidosPorCurso = $derived(
    new Map((mensajeria?.cursos ?? []).map((c) => [c.id_curso, c.no_leidos])),
  );

  const authUser = $derived(($page.props.auth as any)?.user);
  const nombreCompleto = $derived(stats?.nombre_completo || authUser?.name || 'Estudiante');
  const primerNombre = $derived.by(() => {
    const p = nombreCompleto.split(' ')[0] || 'Estudiante';
    return p.charAt(0).toUpperCase() + p.slice(1).toLowerCase();
  });

  const listaMenu = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? []);
  const colorDe = (idCurso: number) => estiloCurso(numeroColorCurso(idCurso, listaMenu));

  const hoy = claveDia();
  const fechaHoy = $derived.by(() => {
    const texto = parseFechaSoloDia(hoy).toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' });
    return `${texto.charAt(0).toUpperCase()}${texto.slice(1).replace(',', '')} · ${periodoActual.semestre}.º semestre ${periodoActual.agno}`;
  });

  /** Lo que se puede entregar todavía, de lo que cierra antes a lo que cierra después. */
  const abiertas = $derived(
    proximasAVencer
      .filter((a) => plazoDe(a, hoy).abierta)
      .sort((a, b) =>
        (a.plazo_hasta ?? a.fecha_limite).localeCompare(b.plazo_hasta ?? b.fecha_limite) ||
        a.fecha_limite.localeCompare(b.fecha_limite),
      ),
  );
  const destacada = $derived(abiertas[0] ?? null);
  const cierraHoy = $derived(destacada !== null && (destacada.plazo_hasta ?? destacada.fecha_limite).slice(0, 10) === hoy);

  function nombreCurso(idCurso: number): string {
    const c = cursos.find((x) => x.id_curso === idCurso);
    return c?.asignatura_nombre || c?.nombre || '';
  }

  /** Una línea con lo urgente: lo que vence hoy, lo atrasado que aún se recibe y los mensajes. */
  const resumen = $derived.by(() => {
    const venceHoy = abiertas.filter((a) => a.fecha_limite.slice(0, 10) === hoy).length;
    const atrasadas = abiertas.filter((a) => a.fecha_limite.slice(0, 10) < hoy).length;
    const mensajes = mensajeria?.no_leidos ?? 0;

    const tienes: string[] = [];
    if (atrasadas) tienes.push(atrasadas === 1 ? 'una atrasada que todavía se recibe' : `${atrasadas} atrasadas que todavía se reciben`);
    if (mensajes) tienes.push(mensajes === 1 ? 'un mensaje sin leer' : `${mensajes} mensajes sin leer`);

    const partes: string[] = [];
    if (venceHoy) partes.push(venceHoy === 1 ? 'Hoy vence una entrega' : `Hoy vencen ${venceHoy} entregas`);
    if (tienes.length) partes.push(`${partes.length ? 'tienes' : 'Tienes'} ${tienes.join(' y ')}`);
    if (!partes.length) return 'Nada vence hoy y no tienes mensajes sin leer.';
    return partes.join(partes.length === 2 && tienes.length === 1 ? ' y ' : ', ') + '.';
  });

  function proximaDe(idCurso: number): ActividadPorVencer | null {
    return abiertas.find((a) => a.id_curso === idCurso) ?? null;
  }

  const hrefCalendario = $derived(
    cursosPeriodoActual.length > 0
      ? `/estudiante/cursos/${cursosPeriodoActual[0].id_curso}?seccion=calendario&alcance=semestre`
      : null,
  );
</script>

<StudentLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-280 flex-col gap-6 pb-10 lg:gap-8 lg:px-10 lg:pt-8 lg:pb-12">
    <section
      aria-labelledby="t-hola"
      class="sobre-noche relative overflow-hidden bg-dm-noche lg:rounded-xl lg:border lg:border-dm-noche-borde"
    >
      <Reticula class="left-[30%] lg:left-[35%]" />
      <div class="relative flex flex-wrap items-center justify-between gap-8 px-4 py-6 lg:p-10">
        <div class="flex min-w-0 flex-[1_1_360px] flex-col gap-3">
          <span class="{etiqueta} text-dm-amarillo">{fechaHoy}</span>
          <h1 id="t-hola" class="{tituloCond} text-[40px] leading-10 text-dm-noche-texto lg:text-[56px] lg:leading-[52px]">
            Hola, {primerNombre}
          </h1>
          <p class="max-w-105 text-base leading-6 text-dm-noche-texto-2">{resumen}</p>
          <div class="flex flex-wrap items-center gap-3">
            <Link
              href="/estudiante/perfil"
              class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-dm-noche-texto no-underline hover:underline lg:min-h-9"
            >
              Ver perfil <ChevronRight size={16} aria-hidden="true" />
            </Link>
            <BotonSgeq visible={puedeAbrirSgeq} class="{btnNoche} bg-transparent shadow-none" />
          </div>
        </div>

        <div
          class="flex w-full flex-col gap-3 rounded-xl bg-dm-elevada p-4 text-dm-ink shadow-[0_8px_24px_rgb(11_12_40/0.28)] lg:w-auto lg:flex-[0_1_360px] lg:p-5"
        >
          {#if destacada}
            {@const plazo = plazoDe(destacada, hoy)}
            <span class="{etiqueta} text-dm-ink-3">{cierraHoy ? 'Lo de hoy' : 'Lo próximo'}</span>
            <span class="{pildora} {tono.curso} self-start font-bold" style={colorDe(destacada.id_curso)}>
              <span aria-hidden="true" class="size-2 rounded-full bg-(--curso)"></span>{destacada.curso}{nombreCurso(destacada.id_curso)
                ? ` · ${nombreCurso(destacada.id_curso)}`
                : ''}
            </span>
            <span class="text-lg leading-6 font-semibold">{destacada.nombre}</span>
            <span class="{pildora} {plazo.urgente ? tono.warn : tono.neutro} self-start">
              <CalendarClock size={14} aria-hidden="true" />{destacada.es_sumativa ? 'Sumativa' : 'Formativa'} · {plazo.texto.charAt(0).toLowerCase()}{plazo.texto.slice(1)}
            </span>
            <Link href={`/estudiante/cursos/${destacada.id_curso}/actividad/${destacada.id_actividad}`} class="{btnPrimario} mt-1 w-full">
              Ir a la actividad <ChevronRight size={16} aria-hidden="true" />
            </Link>
          {:else}
            <span class="{etiqueta} text-dm-ink-3">Lo de hoy</span>
            <span class="text-lg leading-6 font-semibold">Nada pendiente por ahora</span>
            <span class="text-sm text-dm-ink-2">No tienes entregas pendientes en los próximos 7 días.</span>
          {/if}
        </div>
      </div>
    </section>

    <div class="flex flex-col gap-6 px-4 lg:gap-8 lg:px-0">
      {#if isAyudante}
        <section class="flex flex-wrap items-center gap-3.5 rounded-xl border border-dm-linea bg-dm-tarjeta p-4">
          <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-dm-accion-suave text-dm-accion-texto">
            <LifeBuoy size={18} aria-hidden="true" />
          </span>
          <span class="flex min-w-0 flex-[1_1_240px] flex-col gap-0.5">
            <span class="text-sm font-semibold">También eres ayudante</span>
            <span class="text-[13px] leading-[18px] text-dm-ink-2">
              Redactas syllabus y respondes mensajería como ayudante: es un área de trabajo aparte, no otro curso más.
            </span>
          </span>
          <Link href="/ayudante/dashboard" class="{btnSecundario} ml-auto">
            Ir a mi área de ayudante <ArrowUpRight size={16} aria-hidden="true" />
          </Link>
        </section>
      {/if}

      {#if cursos.length === 0}
        <EstadoVacio
          icono={BookOpen}
          titulo="Aún no tienes cursos matriculados"
          texto="Tu matrícula de este semestre está en trámite. En cuanto se confirme, tus cursos aparecerán aquí."
          class="min-h-60"
        />
      {:else}
        <SemanaAlumno items={proximasAVencer} {colorDe} {hrefCalendario} />

        <div class="flex flex-col gap-6 lg:flex-row-reverse lg:items-start">
          <aside class="flex min-w-0 flex-col gap-6 lg:w-85 lg:shrink-0">
            {#if mensajeria && mensajeria.no_leidos > 0}
              <MensajesSinLeer total={mensajeria.no_leidos} cursos={mensajeria.cursos} {colorDe} />
            {/if}
            <NotasRecientes items={notasRecientes} {colorDe} />
          </aside>

          <section aria-labelledby="t-cursos" class="flex min-w-0 flex-1 flex-col gap-3 lg:gap-4">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4">
              <div class="flex items-baseline gap-3">
                <h2 id="t-cursos" class="text-[22px] leading-7 font-bold">Tus cursos</h2>
                <span class="hidden text-[13px] leading-[18px] text-dm-ink-3 sm:inline">
                  {cursosPeriodoActual.length} {cursosPeriodoActual.length === 1 ? 'curso' : 'cursos'} este semestre
                </span>
              </div>
              <Link href="/estudiante/cursos" class={enlace}>Ver todos <ChevronRight size={16} aria-hidden="true" /></Link>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(auto-fill,minmax(260px,1fr))] lg:gap-4">
              {#each cursosPeriodoActual as curso (curso.id_curso)}
                <TarjetaCurso
                  id_curso={curso.id_curso}
                  codigo={curso.cod_asignatura || curso.cod_curso}
                  nombre={curso.asignatura_nombre || curso.nombre}
                  profesor={curso.profesor}
                  proxima={proximaDe(curso.id_curso)}
                  no_leidos={noLeidosPorCurso.get(curso.id_curso) ?? 0}
                  estilo={colorDe(curso.id_curso)}
                />
              {:else}
                <p class="text-sm text-dm-ink-2">No tienes cursos este semestre. Los anteriores están en «Ver todos».</p>
              {/each}
            </div>
          </section>
        </div>
      {/if}
    </div>
  </div>
</StudentLayout>
