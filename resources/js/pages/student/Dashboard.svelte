<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';
  import { page, Link } from '@inertiajs/svelte';
  import { ArrowUpRight, BookOpen, ChevronRight, CircleAlert, LifeBuoy } from 'lucide-svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import SemanaAlumno from '@/components/student/inicio/SemanaAlumno.svelte';
  import MensajesSinLeer from '@/components/student/inicio/MensajesSinLeer.svelte';
  import TarjetaCurso from '@/components/student/inicio/TarjetaCurso.svelte';
  import DestacadaDelDia from '@/components/student/inicio/DestacadaDelDia.svelte';
  import VencidasModal from '@/components/student/inicio/VencidasModal.svelte';
  import BotonSgeq from '@/components/custom/common/BotonSgeq.svelte';
  import {
    abiertas as abiertasDe,
    textoVencidas,
    vencidas as vencidasDe,
    type ActividadPorVencer,
  } from '@/lib/plazoAlumno';
  import { estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import { btnAmarillo, btnSecundario, enlace, etiqueta, tituloCond } from '@/lib/dm';
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
  const abiertas = $derived(abiertasDe(proximasAVencer, hoy));
  const destacada = $derived(abiertas[0] ?? null);

  /**
   * Lo que venció sin entrega en el semestre no desaparece del Inicio: el
   * alumno tiene que verlo para escribirle a su docente o apelar. El aviso
   * abre una ventana con todas, de cualquier curso.
   */
  const vencidas = $derived(vencidasDe(proximasAVencer));
  let verVencidas = $state(false);
  const diaDeCierre = (a: ActividadPorVencer) => (a.plazo_hasta ?? a.fecha_limite).slice(0, 10);
  const cierraHoy = $derived(destacada !== null && diaDeCierre(destacada) === hoy);
  /** Las que cierran el mismo día que la destacada: la tarjeta las alterna (OBS-08). */
  const delDia = $derived(destacada ? abiertas.filter((a) => diaDeCierre(a) === diaDeCierre(destacada)) : []);

  function nombreCurso(idCurso: number): string {
    const c = cursos.find((x) => x.id_curso === idCurso);
    return c?.asignatura_nombre || c?.nombre || '';
  }

  /** Una línea con lo urgente: lo que vence hoy, lo atrasado, lo vencido y los mensajes. */
  const resumen = $derived.by(() => {
    const venceHoy = abiertas.filter((a) => a.fecha_limite.slice(0, 10) === hoy).length;
    const atrasadas = abiertas.filter((a) => a.fecha_limite.slice(0, 10) < hoy).length;
    const mensajes = mensajeria?.no_leidos ?? 0;

    const tienes: string[] = [];
    if (atrasadas) tienes.push(atrasadas === 1 ? 'una atrasada que todavía se recibe' : `${atrasadas} atrasadas que todavía se reciben`);
    if (vencidas.length) tienes.push(vencidas.length === 1 ? 'una vencida sin entregar' : `${vencidas.length} vencidas sin entregar`);
    if (mensajes) tienes.push(mensajes === 1 ? 'un mensaje sin leer' : `${mensajes} mensajes sin leer`);

    const partes: string[] = [];
    if (venceHoy) partes.push(venceHoy === 1 ? 'Hoy vence una entrega' : `Hoy vencen ${venceHoy} entregas`);
    if (tienes.length) partes.push(`${partes.length ? 'tienes' : 'Tienes'} ${unir(tienes)}`);
    if (!partes.length) return 'Nada vence hoy y no tienes mensajes sin leer.';
    return partes.join(partes.length === 2 && tienes.length === 1 ? ' y ' : ', ') + '.';
  });

  /** «a», «a y b», «a, b y c». */
  const unir = (l: string[]) => (l.length < 2 ? l.join('') : `${l.slice(0, -1).join(', ')} y ${l[l.length - 1]}`);

  function proximaDe(idCurso: number): ActividadPorVencer | null {
    return abiertas.find((a) => a.id_curso === idCurso) ?? null;
  }

  const vencidasDelCurso = (idCurso: number) => vencidas.filter((a) => a.id_curso === idCurso).length;

  // El calendario general junta todos los cursos (OBS-23); antes llevaba al del primer curso.
  const hrefCalendario = $derived(cursosPeriodoActual.length > 0 ? '/estudiante/calendario' : null);
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
            <BotonSgeq visible={puedeAbrirSgeq} class="{btnAmarillo} font-bold" />
          </div>
        </div>

        <div
          class="flex w-full flex-col gap-3 rounded-xl bg-dm-elevada p-4 text-dm-ink shadow-[0_8px_24px_rgb(11_12_40/0.28)] lg:w-auto lg:flex-[0_1_360px] lg:p-5"
        >
          {#if destacada}
            <DestacadaDelDia
              items={delDia}
              titulo={cierraHoy ? 'Lo de hoy' : 'Lo próximo'}
              {cierraHoy}
              {hoy}
              {colorDe}
              {nombreCurso}
            />
          {:else}
            <span class="{etiqueta} text-dm-ink-3">Lo de hoy</span>
            {#if vencidas.length}
              <span class="text-lg leading-6 font-semibold">Nada por entregar en los próximos 7 días</span>
            {:else}
              <span class="text-lg leading-6 font-semibold">Nada pendiente por ahora</span>
              <span class="text-sm text-dm-ink-2">No tienes entregas pendientes en los próximos 7 días.</span>
            {/if}
          {/if}
          {#if vencidas.length}
            <button
              type="button"
              aria-haspopup="dialog"
              onclick={() => (verVencidas = true)}
              class="mt-1 flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-dm-error bg-dm-error-s px-3 py-2 text-left text-sm font-semibold text-dm-error hover:underline"
            >
              <CircleAlert size={16} aria-hidden="true" class="shrink-0" />
              <span class="min-w-0 flex-1">{textoVencidas(vencidas.length)}</span>
              <ChevronRight size={16} aria-hidden="true" class="shrink-0" />
            </button>
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

        <!-- Sin «Notas recientes» (OBS-07): las notas se leen en cada actividad y en la pestaña Notas del curso. -->
        <div class="flex flex-col gap-6 lg:flex-row-reverse lg:items-start">
          {#if mensajeria && mensajeria.no_leidos > 0}
            <aside class="flex min-w-0 flex-col gap-6 lg:w-85 lg:shrink-0">
              <MensajesSinLeer total={mensajeria.no_leidos} cursos={mensajeria.cursos} {colorDe} />
            </aside>
          {/if}

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
                  vencidas={vencidasDelCurso(curso.id_curso)}
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

<VencidasModal bind:open={verVencidas} items={vencidas} {hoy} {colorDe} {nombreCurso} />
