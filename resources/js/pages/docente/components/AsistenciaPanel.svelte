<script lang="ts">
  /**
   * Panel de asistencia de un componente (vista del docente).
   *
   * Modelo: cada sesión de clase es una entidad en `curso.clase` identificada
   * unívocamente por su `id_clase`. La asistencia se registra por
   * inscripción-componente en `curso.asistencia`.
   *
   * Composición del lienzo (Docente · Asistencia, dirección institucional UTA):
   *  - Izquierda: las sesiones, de la más reciente a la más antigua. Elegir
   *    una la abre a la derecha.
   *  - Derecha: el pase de lista de la sesión elegida, con Presente / Ausente
   *    por estudiante. El día y la fecha son el título, la hora el subtítulo,
   *    y la papelera para eliminar la sesión va en esa cabecera.
   * La asistencia acumulada de cada estudiante se ve en la tabla de la
   * pestaña Estudiantes (revisión «Mi UTAmed»: sin «Asistencia por estudiante»).
   *
   * Carga sus propios datos vía GET JSON cuando cambia el componente activo, y
   * usa router.post/put/delete (Inertia) para crear/editar/eliminar sesiones,
   * recargando la lista tras cada cambio.
   */
  import { router } from '@inertiajs/svelte';
  import { formatFechaLarga } from '@/utils/formatters';
  import ConfirmDialog from '@/components/custom/common/ConfirmDialog.svelte';
  import AvatarAlumno from '@/components/docente/AvatarAlumno.svelte';
  import { titleCase } from '@/lib/utils';
  import {
    aviso,
    avisoTono,
    btnFantasma,
    btnPrimario,
    btnSecundario,
    campo,
    chico,
    chip,
    tarjeta,
    tarjetaCabecera,
    tarjetaPie,
    titulo,
    tono,
  } from '@/lib/inst';
  import { CalendarPlus, Check, ClipboardCheck, Loader2, Trash2, Users, X } from 'lucide-svelte';

  interface EstudianteAsistencia {
    id_inscripcion_componente: number;
    id_estudiante: number;
    nombre: string;
    username: string;
    url_foto?: string | null;
  }

  interface RegistroSesion {
    id_inscripcion_componente: number;
    esta_presente: boolean;
  }

  interface Sesion {
    id_clase: number;
    correlativo?: number;
    dia: string;
    hora_inicio: string;
    hora_fin: string;
    asunto?: string | null;
    descripcion?: string | null;
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

  // ── Pase de lista (crear / editar) ──
  /** 'idle' = nada abierto; 'editar' = sesión existente; 'nueva' = sesión por crear. */
  let modo = $state<'idle' | 'nueva' | 'editar'>('idle');
  let idClaseActiva = $state<number | null>(null);
  let guardando = $state(false);
  let formError = $state<string | null>(null);
  let fDia = $state('');
  let fInicio = $state('08:00');
  let fFin = $state('09:30');
  let fAsunto = $state('');
  let fDescripcion = $state('');
  /** Mapa id_inscripcion_componente → presente. */
  let presentes = $state<Record<number, boolean>>({});
  /** Móvil: la lista de sesiones muestra sólo la última hasta pedir el resto. */
  let verTodas = $state(false);

  // Diálogo de confirmación para eliminar sesión
  let confirmEliminarOpen = $state(false);

  const hoy = new Date().toISOString().slice(0, 10);

  /** «martes 17 de noviembre»: el día y la fecha son el título de cada sesión. */
  function fechaLarga(dia: string): string {
    return new Date(`${dia}T12:00:00`).toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' }).replace(',', '');
  }

  // Recargar cuando cambia el componente activo.
  $effect(() => {
    idComponente;
    cargar();
  });

  async function cargar(preferirIdClase?: number | null) {
    cargando = true;
    error = null;
    modo = 'idle';
    try {
      const res = await fetch(
        `/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      if (!res.ok) throw new Error();
      const data = await res.json();
      estudiantes = (data.estudiantes ?? []).sort(
        (a: EstudianteAsistencia, b: EstudianteAsistencia) =>
          (a.nombre ?? '').localeCompare(b.nombre ?? '', 'es', { sensitivity: 'base' }),
      );
      sesiones = data.sesiones ?? [];

      if (sesiones.length > 0) {
        if (preferirIdClase != null) {
          const encontrada = sesiones.find((s) => s.id_clase === preferirIdClase);
          if (encontrada) {
            abrirSesion(encontrada);
            return;
          }
        }
        abrirSesion(sesiones[0]);
      } else {
        idClaseActiva = null;
      }
    } catch {
      error = 'No se pudo cargar la asistencia.';
    } finally {
      cargando = false;
    }
  }

  function nuevaSesion() {
    modo = 'nueva';
    idClaseActiva = null;
    formError = null;
    fDia = hoy;
    fInicio = '08:00';
    fFin = '09:30';
    fAsunto = '';
    fDescripcion = '';
    // Por defecto todos presentes (coincide con el default de la BD).
    presentes = Object.fromEntries(estudiantes.map((e) => [e.id_inscripcion_componente, true]));
  }

  function abrirSesion(s: Sesion) {
    modo = 'editar';
    idClaseActiva = s.id_clase;
    formError = null;
    fDia = s.dia;
    fInicio = s.hora_inicio;
    fFin = s.hora_fin;
    fAsunto = s.asunto ?? '';
    fDescripcion = s.descripcion ?? '';
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
      ...(modo === 'editar' && idClaseActiva != null ? { id_clase: idClaseActiva } : {}),
      dia: fDia,
      hora_inicio: fInicio,
      hora_fin: fFin,
      asunto: fAsunto.trim() || null,
      descripcion: fDescripcion.trim() || null,
      asistencias: estudiantes.map((e) => ({
        id_inscripcion_componente: e.id_inscripcion_componente,
        esta_presente: !!presentes[e.id_inscripcion_componente],
      })),
    };

    const targetClaseId = idClaseActiva;

    if (modo === 'editar' && targetClaseId != null) {
      router.put(`/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia/${targetClaseId}`, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          guardando = false;
          cargar(targetClaseId);
        },
        onError: (errors: Record<string, string>) => {
          formError = (Object.values(errors)[0] as string) ?? 'No se pudo guardar.';
          guardando = false;
        },
      });
    } else {
      router.post(`/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`, payload, {
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
      });
    }
  }

  function solicitarEliminarSesion() {
    if (modo !== 'editar' || idClaseActiva == null) return;
    confirmEliminarOpen = true;
  }

  function confirmarEliminarSesion() {
    if (idClaseActiva == null) return;
    const claseId = idClaseActiva;
    confirmEliminarOpen = false;

    router.delete(`/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia/${claseId}`, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => cargar(),
      onError: (errors: Record<string, string>) => {
        error = (Object.values(errors)[0] as string) ?? 'No se pudo eliminar la sesión.';
      },
    });
  }


  const segBoton =
    'inline-flex min-h-11 cursor-pointer items-center gap-1.5 border-0 border-r border-inst-linea px-3.5 text-[13px] font-bold last:border-r-0 lg:min-h-9';
</script>

<div class="flex flex-col gap-6">
  {#if cargando}
    <div class="{tarjeta} flex items-center justify-center gap-2 py-16 text-sm text-inst-ink-2">
      <Loader2 size={18} class="animate-spin" aria-hidden="true" />
      Cargando asistencia…
    </div>
  {:else if error}
    <div class="{aviso} {avisoTono.error}" role="alert">
      <p class="m-0 font-bold">{error}</p>
      <button type="button" onclick={() => cargar()} class="{btnSecundario} {chico} ml-auto">Reintentar</button>
    </div>
  {:else if estudiantes.length === 0}
    <div class="{tarjeta} flex flex-col items-center gap-2 px-6 py-12 text-center">
      <Users size={26} class="text-inst-ink-3" aria-hidden="true" />
      <p class="m-0 font-bold">Sin estudiantes inscritos en este componente.</p>
      <p class="m-0 text-inst-ink-2">El pase de lista se habilita cuando haya estudiantes inscritos.</p>
    </div>
  {:else if sesiones.length === 0 && modo === 'idle'}
    <!-- Sin sesiones: un solo estado vacío, con la acción adentro. -->
    <div class="{tarjeta} flex flex-col items-center gap-3 px-6 py-12 text-center">
      <ClipboardCheck size={28} class="text-inst-ink-3" aria-hidden="true" />
      <div class="flex flex-col gap-1">
        <p class="m-0 font-bold">Aún no hay sesiones de {tipoComponente}.</p>
        <p class="m-0 text-inst-ink-2">Registra la primera para pasar lista.</p>
      </div>
      <button type="button" onclick={nuevaSesion} class={btnPrimario}>
        <CalendarPlus size={16} aria-hidden="true" />
        Nueva sesión
      </button>
    </div>
  {:else}
    <div class="grid items-start gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
      <!-- ── Sesiones ── -->
      <section aria-labelledby="t-sesiones-{idComponente}" class={tarjeta}>
        <div class={tarjetaCabecera}>
          <h2 id="t-sesiones-{idComponente}" class="{titulo} m-0 text-base leading-6">Sesiones</h2>
          <span class="{chip} {tono.off}">{sesiones.length}</span>
          <button
            type="button"
            onclick={nuevaSesion}
            class="{modo === 'idle' ? btnPrimario : btnSecundario} {chico} ml-auto"
          >
            <CalendarPlus size={15} aria-hidden="true" />
            Nueva sesión
          </button>
        </div>

        {#if sesiones.length === 0}
          <p class="m-0 px-4.5 py-3.5 text-[13px] text-inst-ink-2">Ésta será la primera sesión del componente.</p>
        {:else}
          <ul class="m-0 list-none p-0">
            {#each sesiones as s, i (s.id_clase)}
              {@const activa = s.id_clase === idClaseActiva}
              <li class="border-b border-inst-linea last:border-b-0 {i > 0 && !verTodas ? 'max-lg:hidden' : ''}">
                <button
                  type="button"
                  onclick={() => abrirSesion(s)}
                  aria-current={activa ? 'true' : undefined}
                  class="flex min-h-11 w-full cursor-pointer items-center gap-3 border-0 border-l-4 px-4 py-2.5 text-left text-inst-ink transition-colors {activa
                    ? 'border-l-inst-oro bg-inst-sel'
                    : 'border-l-transparent bg-transparent hover:bg-inst-sutil'}"
                >
                  <span class="flex min-w-0 flex-col">
                    <span class="font-bold first-letter:uppercase">{fechaLarga(s.dia)}</span>
                    <span class="truncate text-[13px] text-inst-ink-2" title={s.asunto ?? undefined}>
                      {s.hora_inicio}–{s.hora_fin}{s.asunto ? ` · ${s.asunto}` : ''}
                    </span>
                  </span>
                  <span class="{chip} {tono.ok} ml-auto">{s.presentes} de {s.total}</span>
                </button>
              </li>
            {/each}
          </ul>
          {#if sesiones.length > 1}
            <div class="{tarjetaPie} lg:hidden">
              <button type="button" onclick={() => (verTodas = !verTodas)} class="{btnFantasma} {chico}">
                {verTodas ? 'Ver sólo la última sesión' : `Ver las ${sesiones.length} sesiones`}
              </button>
            </div>
          {/if}
        {/if}
      </section>

      <!-- ── Pase de lista ── -->
      <section
        aria-labelledby="t-lista-{idComponente}"
        class="{tarjeta} {modo === 'idle' ? 'max-lg:hidden' : 'max-lg:order-first'}"
      >
        {#if modo === 'idle'}
          <div class="flex flex-col items-center gap-2 px-6 py-16 text-center text-inst-ink-2">
            <ClipboardCheck size={28} class="text-inst-ink-3" aria-hidden="true" />
            <h2 id="t-lista-{idComponente}" class="m-0 text-[13px] font-normal">
              Elige una sesión o registra una nueva para pasar lista.
            </h2>
          </div>
        {:else}
          <div class="{tarjetaCabecera} items-start">
            <span class="flex min-w-0 flex-col">
              <h2 id="t-lista-{idComponente}" class="{titulo} m-0 text-lg leading-6 first-letter:uppercase">
                {modo === 'nueva' && !fDia ? 'Nueva sesión' : fDia ? fechaLarga(fDia) : 'Sin fecha'}
              </h2>
              <span class="text-inst-ink-2">{fInicio}–{fFin}</span>
            </span>
            {#if modo === 'editar'}
              <button
                type="button"
                onclick={solicitarEliminarSesion}
                class="{btnFantasma} ml-auto size-11 px-0! text-inst-error! lg:size-10"
                aria-label="Eliminar la sesión del {fDia ? fechaLarga(fDia) : 'día'}"
                title="Eliminar sesión"
              >
                <Trash2 size={17} aria-hidden="true" />
              </button>
            {/if}
          </div>

          <!-- Fecha, horario y tema de la clase -->
          <div class="flex flex-col gap-3 border-b border-inst-linea px-4.5 py-3.5">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)]">
              <label class="col-span-2 flex flex-col gap-1 sm:col-span-1">
                <span class="text-[13px] font-bold">Fecha</span>
                <input type="date" bind:value={fDia} class={campo} />
              </label>
              <label class="flex flex-col gap-1">
                <span class="text-[13px] font-bold">Inicio</span>
                <input type="time" bind:value={fInicio} class={campo} />
              </label>
              <label class="flex flex-col gap-1">
                <span class="text-[13px] font-bold">Término</span>
                <input type="time" bind:value={fFin} class={campo} />
              </label>
            </div>
            <label class="flex flex-col gap-1">
              <span class="flex items-baseline justify-between gap-2">
                <span class="text-[13px] font-bold">Asunto o tema de la clase</span>
                <span class="text-xs text-inst-ink-3 tabular-nums">{fAsunto.length}/150</span>
              </span>
              <input
                type="text"
                maxlength="150"
                bind:value={fAsunto}
                placeholder="Título o contenido principal abordado"
                class={campo}
              />
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-[13px] font-bold">Descripción o notas pedagógicas</span>
              <textarea
                rows="2"
                bind:value={fDescripcion}
                placeholder="Observaciones, acuerdos o notas sobre la sesión"
                class="{campo} resize-y py-2"
              ></textarea>
            </label>
          </div>

          <div class="flex flex-wrap items-center gap-2 border-b border-inst-linea px-4.5 py-3">
            <span class="{chip} {tono.ok}">Presentes · {totalPresentesForm}</span>
            <span class="{chip} {tono.error}">Ausentes · {estudiantes.length - totalPresentesForm}</span>
            <button type="button" onclick={() => marcarTodos(!todosPresentes)} class="{btnSecundario} {chico} ml-auto">
              <Check size={15} aria-hidden="true" />
              {todosPresentes ? 'Desmarcar todos' : 'Marcar todos presentes'}
            </button>
          </div>

          {#if formError}
            <div class="mx-4.5 mt-3 {aviso} {avisoTono.error}" role="alert">
              <p class="m-0 text-[13px] font-bold">{formError}</p>
            </div>
          {/if}

          <ul class="m-0 list-none p-0">
            {#each estudiantes as e (e.id_inscripcion_componente)}
              {@const pres = !!presentes[e.id_inscripcion_componente]}
              <li class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-inst-linea px-4.5 py-2.5 last:border-b-0">
                <AvatarAlumno
                  src={e.url_foto ?? null}
                  nombre={e.nombre}
                  class="size-8 bg-inst-sutil text-xs font-extrabold text-inst-ink-2"
                />
                <span class="min-w-0 flex-1 font-bold break-words">{titleCase(e.nombre)}</span>
                <span
                  role="group"
                  aria-label="Asistencia de {e.nombre}"
                  class="inline-flex overflow-hidden rounded-md border border-inst-control max-sm:w-full"
                >
                  <button
                    type="button"
                    aria-pressed={pres}
                    onclick={() => !pres && togglePresente(e.id_inscripcion_componente)}
                    class="{segBoton} max-sm:flex-1 max-sm:justify-center {pres
                      ? 'bg-inst-ok text-inst-tarjeta'
                      : 'bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil'}"
                  >
                    <Check size={15} aria-hidden="true" />
                    Presente
                  </button>
                  <button
                    type="button"
                    aria-pressed={!pres}
                    onclick={() => pres && togglePresente(e.id_inscripcion_componente)}
                    class="{segBoton} max-sm:flex-1 max-sm:justify-center {!pres
                      ? 'bg-inst-error text-inst-tarjeta'
                      : 'bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil'}"
                  >
                    <X size={15} aria-hidden="true" />
                    Ausente
                  </button>
                </span>
              </li>
            {/each}
          </ul>

          <div class={tarjetaPie}>
            <span class="text-[13px] text-inst-ink-2">
              {estudiantes.length}
              {estudiantes.length === 1 ? 'estudiante' : 'estudiantes'}
            </span>
            <span class="ml-auto flex flex-wrap items-center gap-2">
              {#if modo === 'nueva'}
                <button
                  type="button"
                  onclick={() => (sesiones.length > 0 ? abrirSesion(sesiones[0]) : (modo = 'idle'))}
                  class="{btnFantasma} {chico}"
                >
                  Descartar
                </button>
              {/if}
              <button type="button" onclick={guardar} disabled={guardando} class="{btnPrimario} {chico}">
                {#if guardando}
                  <Loader2 size={15} class="animate-spin" aria-hidden="true" />
                {:else}
                  <Check size={15} aria-hidden="true" />
                {/if}
                Guardar pase de lista
              </button>
            </span>
          </div>
        {/if}
      </section>
    </div>

  {/if}
</div>

<!-- Confirmación para eliminar la sesión -->
<ConfirmDialog
  open={confirmEliminarOpen}
  title="Eliminar sesión de asistencia"
  body={`¿Estás seguro de eliminar la sesión del ${formatFechaLarga(fDia)} (${fInicio}–${fFin})? Esta acción no se puede deshacer.`}
  confirmLabel="Eliminar sesión"
  cancelLabel="Cancelar"
  variant="danger"
  onConfirm={confirmarEliminarSesion}
  onCancel={() => (confirmEliminarOpen = false)}
/>
