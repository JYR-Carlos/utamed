<script lang="ts">
  /**
   * Panel de asistencia de un componente (vista del docente).
   *
   * Modelo: la asistencia se registra por inscripción-componente. No hay entidad
   * "sesión": una sesión es IMPLÍCITA — el conjunto de filas que comparten
   * (dia, hora_inicio, hora_fin). Este panel agrupa por ese trío.
   *
   * Layout de la lámina «Detalle de curso»: dos paneles.
   *  - Izquierda: las sesiones, agrupadas por día. Elegir una la abre a la derecha.
   *  - Derecha: el pase de lista de la sesión elegida, con interruptor por alumno.
   * Debajo queda el resumen por estudiante, que es donde vive el aviso de
   * asistencia bajo el mínimo obligatorio del componente.
   *
   * Carga sus propios datos vía GET JSON cuando cambia el componente activo, y
   * usa router.post/put/delete (Inertia) para crear/editar/eliminar sesiones,
   * recargando la lista tras cada cambio.
   */
  import { router } from '@inertiajs/svelte';
  import { formatFechaLarga } from '@/utils/formatters';
  import {
    CalendarPlus,
    Loader2,
    Trash2,
    Check,
    X,
    Users,
    ClipboardCheck,
    AlertTriangle,
  } from 'lucide-svelte';

  interface EstudianteAsistencia {
    id_inscripcion_componente: number;
    id_estudiante: number;
    nombre: string;
    username: string;
  }

  interface RegistroSesion {
    id_inscripcion_componente: number;
    esta_presente: boolean;
  }

  interface Sesion {
    dia: string;
    hora_inicio: string;
    hora_fin: string;
    total: number;
    presentes: number;
    registros: RegistroSesion[];
  }

  interface Props {
    idCurso: number;
    idComponente: number;
    tipoComponente: string;
  }

  let { idCurso, idComponente, tipoComponente }: Props = $props();

  let cargando = $state(false);
  let error = $state<string | null>(null);
  let estudiantes = $state<EstudianteAsistencia[]>([]);
  let sesiones = $state<Sesion[]>([]);
  let porcentajeObligatorio = $state<number | null>(null);

  // ── Pase de lista (crear / editar) ──
  /** 'idle' = nada abierto; 'editar' = sesión existente; 'nueva' = fecha/hora editables. */
  let modo = $state<'idle' | 'nueva' | 'editar'>('idle');
  let guardando = $state(false);
  let formError = $state<string | null>(null);
  let fDia = $state('');
  let fInicio = $state('08:00');
  let fFin = $state('09:30');
  /** Mapa id_inscripcion_componente → presente. */
  let presentes = $state<Record<number, boolean>>({});

  const hoy = new Date().toISOString().slice(0, 10);

  /** Identidad de una sesión: el trío que la define en la BD. */
  const claveSesion = (s: Sesion) => `${s.dia}|${s.hora_inicio}|${s.hora_fin}`;
  const claveActiva = $derived(modo === 'editar' ? `${fDia}|${fInicio}|${fFin}` : '');

  /** Sesiones agrupadas por día, respetando el orden que envía el servidor. */
  const sesionesPorDia = $derived.by(() => {
    const grupos: { dia: string; items: Sesion[] }[] = [];
    for (const s of sesiones) {
      const ultimo = grupos.at(-1);
      if (ultimo && ultimo.dia === s.dia) ultimo.items.push(s);
      else grupos.push({ dia: s.dia, items: [s] });
    }
    return grupos;
  });

  // Recargar cuando cambia el componente activo.
  $effect(() => {
    idComponente;
    cargar();
  });

  async function cargar() {
    cargando = true;
    error = null;
    modo = 'idle';
    try {
      // GET lazy de datos del panel (no muta estado del servidor → no requiere CSRF).
      // TODO(D-03): migrar a router.reload({ only:[...] }) cuando el controlador exponga prop lazy.
      const res = await fetch(
        `/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      if (!res.ok) throw new Error();
      const data = await res.json();
      estudiantes = data.estudiantes ?? [];
      sesiones = data.sesiones ?? [];
      porcentajeObligatorio = data.porcentaje_asistencia_obligatoria ?? null;
      // La sesión más reciente queda abierta: es la que el docente acaba de tomar.
      if (sesiones.length > 0) abrirSesion(sesiones[0]);
    } catch {
      error = 'No se pudo cargar la asistencia.';
    } finally {
      cargando = false;
    }
  }

  function nuevaSesion() {
    modo = 'nueva';
    formError = null;
    fDia = hoy;
    fInicio = '08:00';
    fFin = '09:30';
    // Por defecto todos presentes (coincide con el default de la BD).
    presentes = Object.fromEntries(estudiantes.map((e) => [e.id_inscripcion_componente, true]));
  }

  function abrirSesion(s: Sesion) {
    modo = 'editar';
    formError = null;
    fDia = s.dia;
    fInicio = s.hora_inicio;
    fFin = s.hora_fin;
    const map: Record<number, boolean> = {};
    for (const e of estudiantes) map[e.id_inscripcion_componente] = true;
    for (const r of s.registros) map[r.id_inscripcion_componente] = r.esta_presente;
    presentes = map;
  }

  function togglePresente(id: number) {
    presentes = { ...presentes, [id]: !presentes[id] };
  }

  function marcarTodos(valor: boolean) {
    presentes = Object.fromEntries(estudiantes.map((e) => [e.id_inscripcion_componente, valor]));
  }

  const totalPresentesForm = $derived(
    estudiantes.filter((e) => presentes[e.id_inscripcion_componente]).length,
  );

  const todosPresentes = $derived(
    estudiantes.length > 0 && totalPresentesForm === estudiantes.length,
  );

  function guardar() {
    if (!fDia || !fInicio || !fFin) {
      formError = 'Completa fecha y horario.';
      return;
    }
    if (fFin <= fInicio) {
      formError = 'La hora de término debe ser posterior a la de inicio.';
      return;
    }
    guardando = true;
    formError = null;

    const payload = {
      dia: fDia,
      hora_inicio: fInicio,
      hora_fin: fFin,
      asistencias: estudiantes.map((e) => ({
        id_inscripcion_componente: e.id_inscripcion_componente,
        esta_presente: !!presentes[e.id_inscripcion_componente],
      })),
    };

    const url = `/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`;
    const opts = {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        guardando = false;
        cargar();
      },
      onError: (errors: Record<string, string>) => {
        formError = (Object.values(errors)[0] as string) ?? 'No se pudo guardar.';
        guardando = false;
      },
    };

    if (modo === 'editar') router.put(url, payload, opts);
    else router.post(url, payload, opts);
  }

  function eliminarSesion() {
    if (
      !confirm(`¿Eliminar la sesión del ${formatFechaLarga(fDia)} (${fInicio}–${fFin})?`)
    )
      return;
    router.delete(`/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`, {
      data: { dia: fDia, hora_inicio: fInicio, hora_fin: fFin },
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => cargar(),
      onError: (errors: Record<string, string>) => {
        error = (Object.values(errors)[0] as string) ?? 'No se pudo eliminar la sesión.';
      },
    });
  }

  // ── Resumen por estudiante (% de asistencia sobre todas las sesiones) ──
  interface ResumenEstudiante {
    estudiante: EstudianteAsistencia;
    presentes: number;
    total: number;
    porcentaje: number;
  }

  const resumen = $derived.by<ResumenEstudiante[]>(() => {
    const totalSesiones = sesiones.length;
    return estudiantes.map((e) => {
      let pres = 0;
      for (const s of sesiones) {
        const r = s.registros.find(
          (x) => x.id_inscripcion_componente === e.id_inscripcion_componente,
        );
        if (r?.esta_presente) pres++;
      }
      return {
        estudiante: e,
        presentes: pres,
        total: totalSesiones,
        porcentaje: totalSesiones === 0 ? 0 : Math.round((pres / totalSesiones) * 100),
      };
    });
  });

  function bajoMinimo(porc: number): boolean {
    return porcentajeObligatorio != null && porc < porcentajeObligatorio;
  }

  function pctColor(porc: number): string {
    if (bajoMinimo(porc)) return 'text-[#B91C1C]';
    return porc >= 75 ? 'text-[#047857]' : 'text-[#B45309]';
  }

  const CAMPO =
    'px-2.5 py-1.5 text-[13px] rounded-lg border border-[#D6D9E0] bg-white focus:outline-none focus:border-[#002F6C]';
</script>

<div class="flex flex-col gap-4">
  {#if cargando}
    <div class="flex items-center justify-center gap-2 py-16 text-[#5A5E6E] text-sm">
      <Loader2 size={18} class="animate-spin" />
      Cargando asistencia…
    </div>
  {:else if error}
    <div class="text-center py-12 text-[#B91C1C] text-sm">{error}</div>
  {:else if estudiantes.length === 0}
    <div
      class="flex flex-col items-center justify-center gap-2 py-14 text-center rounded-xl border border-dashed bg-[#F5F1EA] border-[#D0CBC1]"
    >
      <Users size={26} class="text-[#8A8E9C]" />
      <p class="text-sm text-[#5A5E6E] m-0">Sin estudiantes inscritos en este componente.</p>
    </div>
  {:else}
    <div class="flex flex-col lg:flex-row gap-[18px] items-start">
      <!-- ── Panel izquierdo: sesiones ── -->
      <div class="w-full lg:w-[360px] lg:flex-none flex flex-col gap-2.5">
        <div class="flex items-baseline gap-2.5">
          <h3 class="text-sm font-semibold text-[#1A1A24] m-0">Sesiones</h3>
          <span class="text-[11.5px] text-[#5A5E6E]">
            {sesiones.length}
            {sesiones.length === 1 ? 'registrada' : 'registradas'}
          </span>
          <button
            onclick={nuevaSesion}
            class="ml-auto inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium text-[#002F6C] transition-colors hover:bg-[#E6ECF5]"
          >
            <CalendarPlus size={14} />
            Nueva sesión
          </button>
        </div>

        {#if sesiones.length === 0}
          <div
            class="flex flex-col items-center justify-center gap-2 rounded-[10px] border border-dashed border-[#D0CBC1] bg-[#F5F1EA] py-10 text-center"
          >
            <ClipboardCheck size={24} class="text-[#8A8E9C]" />
            <p class="text-[13px] text-[#5A5E6E] m-0 px-4">
              Aún no hay sesiones. Registra la primera con «Nueva sesión».
            </p>
          </div>
        {:else}
          {#each sesionesPorDia as grupo (grupo.dia)}
            <span
              class="font-mono text-[10.5px] uppercase tracking-[0.08em] text-[#5A5E6E] pt-1.5 first:pt-0.5"
            >
              {formatFechaLarga(grupo.dia)}
            </span>
            {#each grupo.items as s (claveSesion(s))}
              {@const activa = claveSesion(s) === claveActiva}
              <button
                onclick={() => abrirSesion(s)}
                aria-current={activa ? 'true' : undefined}
                class="flex w-full items-center gap-3 rounded-[10px] border px-3 py-2.5 text-left transition-colors duration-150 {activa
                  ? 'bg-[#F8FAFC] border-[#C9D6E6] border-l-[3px] border-l-[#002F6C]'
                  : 'bg-white border-[#E5E7EB] hover:bg-[#FCFBF9]'}"
              >
                <span class="flex flex-col gap-0.5 min-w-0">
                  <span
                    class="font-mono text-[13px] font-semibold tabular-nums {activa
                      ? 'text-[#002F6C]'
                      : 'text-[#1A1A24]'}">{s.hora_inicio} – {s.hora_fin}</span
                  >
                  <span class="text-[11.5px] text-[#5A5E6E]">{tipoComponente}</span>
                </span>
                <span class="ml-auto flex flex-none flex-col items-end gap-0.5">
                  <span class="font-mono text-sm font-semibold tabular-nums text-[#1A1A24]"
                    >{s.presentes}/{s.total}</span
                  >
                  <span class="text-[11px] text-[#5A5E6E]">presentes</span>
                </span>
              </button>
            {/each}
          {/each}
        {/if}
      </div>

      <!-- ── Panel derecho: pase de lista ── -->
      <div class="w-full lg:flex-1 min-w-0 rounded-xl border border-[#E5E7EB] flex flex-col">
        {#if modo === 'idle'}
          <div
            class="flex flex-col items-center justify-center gap-2 py-16 text-center text-[#5A5E6E]"
          >
            <ClipboardCheck size={28} class="text-[#8A8E9C]" />
            <p class="text-[13px] m-0">
              Elige una sesión de la izquierda o registra una nueva para pasar lista.
            </p>
          </div>
        {:else}
          <!-- Cabecera -->
          <div
            class="flex flex-wrap items-center gap-3.5 rounded-t-xl border-b border-[#E5E7EB] bg-[#F5F1EA] px-4 py-3"
          >
            <div class="flex flex-col gap-0.5 min-w-0">
              <span class="text-sm font-semibold text-[#1A1A24]"
                >Pase de lista · {tipoComponente}</span
              >
              {#if modo === 'editar'}
                <span class="font-mono text-[11.5px] text-[#5A5E6E]">
                  {formatFechaLarga(fDia)} · {fInicio} – {fFin}
                </span>
              {:else}
                <div class="flex flex-wrap items-center gap-2 pt-1">
                  <input type="date" bind:value={fDia} class={CAMPO} aria-label="Fecha" />
                  <input type="time" bind:value={fInicio} class={CAMPO} aria-label="Hora inicio" />
                  <span class="text-[#8A8E9C]">–</span>
                  <input type="time" bind:value={fFin} class={CAMPO} aria-label="Hora término" />
                </div>
              {/if}
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-3.5">
              <div class="flex flex-col items-end">
                <span class="font-mono text-lg font-semibold leading-tight tabular-nums text-[#1A1A24]"
                  >{totalPresentesForm}/{estudiantes.length}</span
                >
                <span class="text-[11px] text-[#5A5E6E]">presentes</span>
              </div>
              {#if modo === 'editar'}
                <button
                  onclick={eliminarSesion}
                  class="rounded-lg p-2 text-[#8A8E9C] transition-colors hover:bg-[#FEE2E2] hover:text-[#B91C1C]"
                  aria-label="Eliminar sesión"
                >
                  <Trash2 size={15} />
                </button>
              {/if}
              <button
                onclick={() => marcarTodos(!todosPresentes)}
                class="rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-[13px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA]"
              >
                {todosPresentes ? 'Desmarcar todos' : 'Marcar todos'}
              </button>
              <button
                onclick={guardar}
                disabled={guardando}
                class="inline-flex items-center gap-1.5 rounded-lg border border-[#002F6C] bg-[#002F6C] px-3.5 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-[#1B4789] disabled:opacity-50"
              >
                {#if guardando}
                  <Loader2 size={14} class="animate-spin" />
                {:else}
                  <Check size={14} />
                {/if}
                Guardar lista
              </button>
            </div>
          </div>

          {#if formError}
            <p class="border-b border-[#E5E7EB] px-4 py-2 text-[12.5px] text-[#B91C1C] m-0">
              {formError}
            </p>
          {/if}

          <!-- Grilla de alumnos -->
          <div class="grid grid-cols-1 md:grid-cols-2">
            {#each estudiantes as e, i (e.id_inscripcion_componente)}
              {@const pres = !!presentes[e.id_inscripcion_componente]}
              <div
                class="flex items-center gap-3 border-b border-[#E5E7EB] px-4 py-2.5 {i % 2 === 0
                  ? 'md:border-r md:border-r-[#E5E7EB]'
                  : ''} {Math.floor(i / 2) % 2 === 1 ? 'bg-[#FCFBF9]' : ''}"
              >
                <span class="w-[22px] shrink-0 font-mono text-[12px] tabular-nums text-[#5A5E6E]"
                  >{String(i + 1).padStart(2, '0')}</span
                >
                <span class="min-w-0 truncate text-[13.5px] font-medium text-[#1A1A24]"
                  >{e.nombre}</span
                >
                <span class="ml-auto flex flex-none items-center gap-2.5">
                  <span
                    class="text-[11.5px] {pres
                      ? 'font-semibold text-[#047857]'
                      : 'text-[#5A5E6E]'}">{pres ? 'Presente' : 'Ausente'}</span
                  >
                  <button
                    role="switch"
                    aria-checked={pres}
                    aria-label="{e.nombre}: marcar {pres ? 'ausente' : 'presente'}"
                    onclick={() => togglePresente(e.id_inscripcion_componente)}
                    class="flex h-[22px] w-[38px] shrink-0 items-center rounded-full p-0.5 transition-colors duration-150 {pres
                      ? 'justify-end bg-[#059669]'
                      : 'justify-start bg-[#E0E3E9]'}"
                  >
                    <span class="h-[18px] w-[18px] rounded-full bg-white shadow-sm"></span>
                  </button>
                </span>
              </div>
            {/each}
          </div>

          <div class="flex items-center gap-3 px-4 py-2.5 text-[12px] text-[#5A5E6E]">
            <span class="mr-auto">
              {estudiantes.length}
              {estudiantes.length === 1 ? 'estudiante' : 'estudiantes'} · el orden sigue la lista de
              clase
            </span>
            {#if modo === 'nueva'}
              <button
                onclick={() => (sesiones.length > 0 ? abrirSesion(sesiones[0]) : (modo = 'idle'))}
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 font-medium text-[#5A5E6E] transition-colors hover:bg-[#F5F1EA]"
              >
                <X size={13} />
                Descartar
              </button>
            {/if}
          </div>
        {/if}
      </div>
    </div>

    <!-- ── Resumen por estudiante ── -->
    {#if sesiones.length > 0}
      <div class="flex flex-col gap-2">
        <div class="flex items-baseline gap-2.5">
          <h3 class="text-sm font-semibold text-[#1A1A24] m-0">Asistencia por estudiante</h3>
          {#if porcentajeObligatorio != null}
            <span class="text-[11.5px] text-[#5A5E6E]">
              Mínimo obligatorio del componente:
              <strong class="font-semibold text-[#1A1A24]">{porcentajeObligatorio}%</strong>
            </span>
          {/if}
        </div>
        <div class="overflow-hidden rounded-xl border border-[#E5E7EB]">
          <table class="w-full border-collapse text-sm">
            <thead class="bg-[#F5F1EA]">
              <tr>
                <th
                  class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.05em] text-[#5A5E6E]"
                  >Estudiante</th
                >
                <th
                  class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.05em] text-[#5A5E6E]"
                  >Asistió</th
                >
                <th
                  class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.05em] text-[#5A5E6E]"
                  >%</th
                >
              </tr>
            </thead>
            <tbody>
              {#each resumen as r, i (r.estudiante.id_inscripcion_componente)}
                <tr class="border-t border-[#E5E7EB] {i % 2 === 1 ? 'bg-[#FCFBF9]' : ''}">
                  <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                      <span class="text-[13.5px] text-[#1A1A24]">{r.estudiante.nombre}</span>
                      {#if bajoMinimo(r.porcentaje)}
                        <span
                          class="inline-flex items-center gap-1 rounded-full bg-[#FFFBEB] border border-[#FDE68A] px-2 py-0.5 text-[10.5px] font-semibold text-[#B45309]"
                        >
                          <AlertTriangle size={10} /> Bajo mínimo
                        </span>
                      {/if}
                    </div>
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono tabular-nums text-[13px] text-[#5A5E6E]"
                    >{r.presentes}/{r.total}</td
                  >
                  <td
                    class="px-4 py-2.5 text-right font-mono text-[13px] font-semibold tabular-nums {pctColor(
                      r.porcentaje,
                    )}">{r.porcentaje}%</td
                  >
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      </div>
    {/if}
  {/if}
</div>
