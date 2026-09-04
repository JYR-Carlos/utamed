<script lang="ts">
  /**
   * Vista semana: mismo período que el mes, pero las sesiones de asistencia
   * ocupan su bloque horario real y las fechas límite —que no tienen hora—
   * viven en la franja "sin hora" de la cabecera.
   *
   * Sólo existen las sesiones YA TOMADAS: la sesión es implícita en
   * curso.asistencia (dia, hora_inicio, hora_fin), así que las semanas futuras
   * aparecen vacías de bloques horarios mientras no se registre asistencia.
   */
  import { TriangleAlert } from 'lucide-svelte';
  import type { CalendarCurso, CalendarDay, CalendarItem, CursoAccent, Sobrecarga } from '../types';
  import { DIAS_SEMANA, minutosAhora, minutosDeHora, todayKey } from '../utils/calendar';
  import EventPill from './EventPill.svelte';

  interface Props {
    /** Los siete días (lunes → domingo) de la semana mostrada. */
    dias: CalendarDay[];
    itemsPorDia: Record<string, CalendarItem[]>;
    accentPorCurso: Record<number, CursoAccent>;
    cursoPorId: Record<number, CalendarCurso>;
    sobrecargas: Record<string, Sobrecarga[]>;
    onSelectDay?: (iso: string) => void;
    onSelectItem?: (item: CalendarItem) => void;
  }

  let {
    dias,
    itemsPorDia,
    accentPorCurso,
    cursoPorId,
    sobrecargas,
    onSelectDay,
    onSelectItem,
  }: Props = $props();

  /** Alto en píxeles de una hora de la rejilla. */
  const ALTO_HORA = 46;

  function itemsDe(iso: string): CalendarItem[] {
    return itemsPorDia[iso] ?? [];
  }

  /** Marcas sin hora del día: fechas límite e hitos. */
  function sinHora(iso: string): CalendarItem[] {
    return itemsDe(iso).filter((i) => i.familia !== 'SESION');
  }

  /** Sesiones del día, ordenadas por hora de inicio. */
  function sesionesDe(iso: string) {
    return itemsDe(iso)
      .flatMap((i) => (i.familia === 'SESION' ? [i] : []))
      .sort((a, b) => minutosDeHora(a.sesion.hora_inicio) - minutosDeHora(b.sesion.hora_inicio));
  }

  /** Rango horario que cubre la semana, redondeado a horas enteras. */
  const rango = $derived.by(() => {
    let min = 24 * 60;
    let max = 0;

    for (const dia of dias) {
      for (const item of itemsDe(dia.iso)) {
        if (item.familia !== 'SESION') continue;
        min = Math.min(min, minutosDeHora(item.sesion.hora_inicio));
        max = Math.max(max, minutosDeHora(item.sesion.hora_fin));
      }
    }

    // Sin sesiones registradas se muestra una jornada estándar.
    if (min > max) return { desde: 8 * 60, hasta: 18 * 60 };

    return {
      desde: Math.floor(min / 60) * 60,
      hasta: Math.max(Math.ceil(max / 60) * 60, Math.floor(min / 60) * 60 + 60),
    };
  });

  const horas = $derived.by(() => {
    const lista: number[] = [];
    for (let m = rango.desde; m < rango.hasta; m += 60) lista.push(m);
    return lista;
  });

  const altoRejilla = $derived((horas.length || 1) * ALTO_HORA);

  function etiquetaHora(minutos: number): string {
    return `${String(Math.floor(minutos / 60)).padStart(2, '0')}:00`;
  }

  /**
   * Reparte las sesiones de un día en carriles para que las que se solapan
   * queden lado a lado en vez de una encima de otra.
   */
  function disponer(iso: string) {
    const finDeCarril: number[] = [];

    const colocadas = sesionesDe(iso).map((item) => {
      const inicio = minutosDeHora(item.sesion.hora_inicio);
      const fin = Math.max(minutosDeHora(item.sesion.hora_fin), inicio + 30);

      let carril = finDeCarril.findIndex((f) => f <= inicio);
      if (carril === -1) {
        carril = finDeCarril.length;
        finDeCarril.push(fin);
      } else {
        finDeCarril[carril] = fin;
      }

      return { item, inicio, fin, carril };
    });

    const carriles = Math.max(finDeCarril.length, 1);

    return colocadas.map((c) => ({
      item: c.item,
      top: ((c.inicio - rango.desde) / 60) * ALTO_HORA,
      alto: Math.max(((c.fin - c.inicio) / 60) * ALTO_HORA, 30),
      ancho: 100 / carriles,
      izquierda: (100 / carriles) * c.carril,
    }));
  }

  const hoy = todayKey();
  const ahora = minutosAhora();

  /** Posición de la línea de "ahora" dentro de la rejilla, si cae en rango. */
  const topAhora = $derived(
    ahora >= rango.desde && ahora <= rango.hasta ? ((ahora - rango.desde) / 60) * ALTO_HORA : null,
  );

  function etiquetaCurso(id: number): string {
    const curso = cursoPorId[id];
    if (!curso) return '';
    const cod = curso.cod_curso ?? curso.asignatura;
    return curso.letra_grupo ? `${cod} · ${curso.letra_grupo}` : String(cod);
  }

  function fondoColumna(dia: CalendarDay, hayCarga: boolean): string {
    if (dia.isToday) return '#F7FAFF';
    if (hayCarga) return '#FFFCFC';
    if (dia.isWeekend) return '#FBFAF8';
    return '#FFFFFF';
  }

  /** Avisos de sobrecarga de toda la semana, para la banda superior. */
  const avisosSemana = $derived.by(() => {
    const avisos: { iso: string; carga: Sobrecarga }[] = [];
    for (const dia of dias) {
      for (const carga of sobrecargas[dia.iso] ?? []) avisos.push({ iso: dia.iso, carga });
    }
    return avisos;
  });
</script>

<div
  class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_1px_3px_rgba(0,0,0,.06)]"
>
  <div class="overflow-x-auto">
    <div class="min-w-[860px]">
      <!-- Cabecera de días -->
      <div class="flex border-b border-[#E5E7EB] bg-[#F8F7F4]">
        <div class="w-[52px] shrink-0 border-r border-[#E5E7EB]"></div>
        {#each dias as dia, i (dia.iso)}
          {@const cargas = sobrecargas[dia.iso] ?? []}
          <div
            class="flex flex-1 flex-col gap-px border-r border-[#E5E7EB] px-2 pb-2.5 pt-2 last:border-r-0"
            style="background:{fondoColumna(dia, cargas.length > 0)};{dia.isToday
              ? 'box-shadow:inset 0 4px 0 #002F6C'
              : ''}"
          >
            <span class="flex flex-wrap items-center gap-1.5">
              <span
                class="text-[10.5px] font-semibold uppercase tracking-[0.06em] {dia.isToday
                  ? 'font-bold text-[#002F6C]'
                  : dia.isWeekend
                    ? 'text-[#A1A1AA]'
                    : 'text-[#5A5E6E]'}"
              >
                {DIAS_SEMANA[i]}{dia.isToday ? ' · hoy' : ''}
              </span>
              {#each cargas as carga (carga.clave)}
                <span
                  class="inline-flex items-center gap-1 rounded-full border border-[#FECACA] bg-[#FEF2F2] px-1.5 text-[9.5px] font-bold text-[#B91C1C]"
                  title="Grupo {carga.letra} tiene {carga.total} entregas este día: {carga.titulos.join(
                    ', ',
                  )}"
                >
                  <TriangleAlert size={9} color="#B91C1C" />
                  {carga.letra} ×{carga.total}
                </span>
              {/each}
            </span>
            <button
              type="button"
              onclick={() => onSelectDay?.(dia.iso)}
              class="w-fit text-[15px] font-semibold transition-colors hover:text-[#002F6C] {dia.isToday
                ? 'text-[#002F6C]'
                : dia.isWeekend
                  ? 'text-[#A1A1AA]'
                  : 'text-[#1A1A24]'}"
            >
              {dia.dayOfMonth}
            </button>
          </div>
        {/each}
      </div>

      <!-- Franja "sin hora": fechas límite e hitos -->
      <div class="flex border-b border-[#D6D9E0] bg-[#FCFCFB]">
        <div
          class="w-[52px] shrink-0 border-r border-[#E5E7EB] px-1.5 py-2 text-[9.5px] font-bold uppercase leading-[1.3] tracking-[0.06em] text-[#5A5E6E]"
        >
          sin<br />hora
        </div>
        {#each dias as dia (dia.iso)}
          {@const marcas = sinHora(dia.iso)}
          {@const cargas = sobrecargas[dia.iso] ?? []}
          <div
            class="flex min-h-[44px] min-w-0 flex-1 flex-col gap-1 border-r border-[#E5E7EB] p-1.5 last:border-r-0"
            style="background:{fondoColumna(dia, cargas.length > 0)}"
          >
            {#each marcas as item (item.key)}
              <EventPill
                {item}
                accent={accentPorCurso[item.id_curso]}
                etiquetaCurso={etiquetaCurso(item.id_curso)}
                onClick={onSelectItem}
              />
            {/each}
          </div>
        {/each}
      </div>

      <!-- Avisos de sobrecarga de la semana -->
      {#each avisosSemana as aviso (aviso.iso + aviso.carga.clave)}
        <div
          class="flex items-start gap-2 border-b border-[#FBD5D5] bg-[#FFF8F8] px-3 py-2 text-[11.5px] leading-[1.45] text-[#7F1D1D]"
        >
          <TriangleAlert size={13} class="mt-0.5 shrink-0" color="#B91C1C" />
          <span>
            <span class="font-bold">
              Grupo {aviso.carga.letra}: {aviso.carga.total} entregas el día {aviso.iso
                .split('-')
                .reverse()
                .join('-')} —
            </span>
            {aviso.carga.titulos.join(', ')}.
            {#if aviso.carga.ocultas > 0}
              <span class="font-semibold">
                {aviso.carga.ocultas}
                {aviso.carga.ocultas === 1 ? 'está' : 'están'} en cursos ocultos por el filtro.
              </span>
            {/if}
          </span>
        </div>
      {/each}

      <!-- Rejilla horaria con las sesiones de asistencia -->
      <div class="flex">
        <div class="w-[52px] shrink-0 border-r border-[#E5E7EB]">
          {#each horas as minutos (minutos)}
            <div
              class="px-1.5 pt-0.5 text-right font-mono text-[10.5px] text-[#8A8E9C]"
              style="height:{ALTO_HORA}px"
            >
              {etiquetaHora(minutos)}
            </div>
          {/each}
        </div>

        {#each dias as dia (dia.iso)}
          {@const cargas = sobrecargas[dia.iso] ?? []}
          {@const bloques = disponer(dia.iso)}
          <div
            class="relative min-w-0 flex-1 border-r border-[#E5E7EB] last:border-r-0"
            style="height:{altoRejilla}px;background:{fondoColumna(
              dia,
              cargas.length > 0,
            )};background-image:repeating-linear-gradient(to bottom,#EFF1F4 0 1px,transparent 1px {ALTO_HORA}px)"
          >
            {#each bloques as bloque (bloque.item.key)}
              {@const accent = accentPorCurso[bloque.item.id_curso]}
              {@const sesion = bloque.item.sesion}
              <button
                type="button"
                onclick={() => onSelectItem?.(bloque.item)}
                class="absolute flex flex-col gap-px overflow-hidden rounded-lg px-1.5 py-1 text-left transition-[filter] hover:brightness-[.97]"
                style="top:{bloque.top}px;height:{bloque.alto}px;left:calc({bloque.izquierda}% + 4px);width:calc({bloque.ancho}% - 8px);background:{accent.soft};border:1px solid {accent.border};border-left:3px solid {accent.base}"
                title="{sesion.componente} · {sesion.presentes} de {sesion.total} presentes"
              >
                <span class="font-mono text-[10px] leading-none" style="color:{accent.text}">
                  {sesion.hora_inicio} – {sesion.hora_fin}
                </span>
                <span class="truncate text-[11.5px] font-semibold leading-[1.2] text-[#1A1A24]">
                  {etiquetaCurso(bloque.item.id_curso)}
                </span>
                <span class="truncate text-[10.5px] font-semibold text-[#1F6F45]">
                  {sesion.presentes}/{sesion.total} presentes
                </span>
              </button>
            {/each}

            {#if dia.iso === hoy && topAhora !== null}
              <div
                class="pointer-events-none absolute inset-x-0 z-[5] border-t-2 border-[#002F6C]"
                style="top:{topAhora}px"
              >
                <span
                  class="absolute -top-[5px] left-0 h-2 w-2 rounded-full bg-[#002F6C]"
                  aria-hidden="true"
                ></span>
              </div>
            {/if}
          </div>
        {/each}
      </div>
    </div>
  </div>
</div>
