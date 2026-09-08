<script lang="ts">
  /**
   * Asistente de syllabus — `/{docente|admin|ayudante}/cursos/{curso}/programa/editar`.
   *
   * **Era un modal y ahora es una página.** Tiene URL propia, se puede enlazar,
   * recargar y volver atrás, y el documento que estaba debajo ya no compite por
   * la atención. Es el único punto de escritura del syllabus: aquí no se envía a
   * revisión, no se publica y no se cambia de estado — eso vive en el visor
   * (`docente/Programa.svelte`).
   *
   * Lo que la lámina pide y el modelo no permite, y por qué:
   *
   * - **Sin autoguardado.** La lámina guarda al cambiar de paso. Cada guardado
   *   pasa por `POST {base}/cursos/{curso}/programa`, y
   *   `ProgramaService::generateProgramaWithSyllabus` **crea una versión nueva**
   *   cada vez (marca la anterior `es_actual = false` e incrementa
   *   `version_programa`). Autoguardar por paso dejaría nueve versiones de una
   *   sentada en `auditoria.programa_historial`. Se guarda cuando lo pides.
   * - **El paso bloqueado se lee.** Los permisos por módulo
   *   (`cursos/programas/modificar:modulo_N`) sí existen y son los mismos que
   *   valida el guardado, así que la barra los refleja.
   *
   * El resto de omisiones está anotado en `ProgramaWizardSteps.svelte`, junto al
   * campo que las provoca.
   */
  import { router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import {
    AlertTriangle,
    Check,
    ChevronLeft,
    ChevronRight,
    FileText,
    Save,
    ShieldAlert,
    X,
  } from 'lucide-svelte';
  import ProgramaWizardSteps from '@/modules/resources/programa/components/ProgramaWizardSteps.svelte';
  import SyllabusPasos from '@/modules/resources/programa/components/SyllabusPasos.svelte';
  import SyllabusResumen from '@/modules/resources/programa/components/SyllabusResumen.svelte';
  import {
    pasosDeTipo,
    seccionesDeTipo,
    type IdPaso,
    type TipoSyllabus,
  } from '@/modules/resources/programa/utils/syllabusPasos';
  import {
    generatePrograma,
    extractErrorMessage,
  } from '@/modules/resources/programa/services/programaApi';
  import type {
    WizardUnidad,
    WizardActividad,
    WizardComponente,
  } from '@/modules/resources/programa/types/programa.types';
  import { formatFechaHora } from '@/utils/formatters';

  interface Props {
    curso: {
      id_curso: number;
      cod_curso?: string | number | null;
      cod_asignatura?: string | null;
      nombre?: string | null;
      asignatura_nombre?: string | null;
      carrera_nombre?: string | null;
      letra_grupo?: string | null;
      agno_real?: number | null;
      semestre_real?: number | null;
      creditos_sct?: number | null;
      horas_catedra?: number | null;
      horas_taller?: number | null;
      horas_laboratorio?: number | null;
      docente_titular?: string | null;
    };
    programa: {
      id_programa: number;
      version_programa: number;
      estado: string;
      tipo_syllabus: string | null;
      secciones: Record<string, any>;
    } | null;
    tipoSyllabus: TipoSyllabus;
    seccionesEditables: string[];
    componentes: WizardComponente[];
    actividades: { id_actividad: number; nombre: string; fecha_limite: string }[];
    editandoDeOtro: boolean;
    docenteTitular: string | null;
    layoutType?: 'docente' | 'admin' | 'ayudante';
  }

  let {
    curso,
    programa,
    tipoSyllabus,
    seccionesEditables,
    componentes: componentesCurso,
    actividades: actividadesCurso,
    editandoDeOtro,
    docenteTitular,
    layoutType = 'docente',
  }: Props = $props();

  const esBasico = $derived(tipoSyllabus === 'BASICO');
  const basePath = $derived(
    layoutType === 'admin'
      ? '/admin/cursos'
      : layoutType === 'ayudante'
        ? '/ayudante/cursos'
        : '/docente/cursos',
  );
  const urlVisor = $derived(
    layoutType === 'admin'
      ? `/admin/cursos/${curso.id_curso}/programa/revisar`
      : `${basePath}/${curso.id_curso}/programa`,
  );

  const codigoAsignatura = $derived(curso.cod_asignatura ?? String(curso.cod_curso ?? ''));
  const nombreAsignatura = $derived(curso.asignatura_nombre ?? curso.nombre ?? '');

  // ── Estado del formulario ─────────────────────────────────────────────────
  // Todo lo que viaja a `secciones.*` del JSONB. Los nombres son los del payload
  // que valida SyllabusRules, no los de la lámina.

  const sec = (n: string) => programa?.secciones?.[n]?.contenido ?? {};

  let codigo = $state(String(sec('I').codigo ?? curso.cod_asignatura ?? curso.cod_curso ?? ''));
  let creditos_sct = $state(String(sec('I').creditos_sct ?? curso.creditos_sct ?? ''));
  let horas_catedra = $state(String(sec('I').horas?.catedra ?? curso.horas_catedra ?? ''));
  let horas_taller = $state(String(sec('I').horas?.taller ?? curso.horas_taller ?? ''));
  let horas_laboratorio = $state(
    String(sec('I').horas?.laboratorio ?? curso.horas_laboratorio ?? ''),
  );
  let categoria = $state(String(sec('I').categoria ?? 'Obligatorio'));

  let presentacion = $state(String(sec('II').texto ?? ''));
  let estandares = $state(String(sec('III').texto ?? ''));

  const listaTitulos = (v: any): { titulo: string }[] =>
    Array.isArray(v) && v.length > 0 ? v.map((c: any) => ({ titulo: c?.titulo ?? '' })) : [];

  let competencias_especificas = $state<{ titulo: string }[]>(
    listaTitulos(sec('IV').competencias_especificas).length > 0
      ? listaTitulos(sec('IV').competencias_especificas)
      : [{ titulo: '' }],
  );
  let competencias_genericas = $state<{ titulo: string }[]>(
    listaTitulos(sec('IV').competencias_genericas).length > 0
      ? listaTitulos(sec('IV').competencias_genericas)
      : [{ titulo: '' }],
  );
  let subcompetencias = $state<{ titulo: string }[]>(listaTitulos(sec('IV').subcompetencias));

  let items_evaluacion = $state<{ titulo: string; descripcion: string }[]>(
    Array.isArray(sec('V').items) && sec('V').items.length > 0
      ? sec('V').items.map((i: any) => ({
          titulo: i?.titulo ?? '',
          descripcion: i?.descripcion ?? '',
        }))
      : [{ titulo: '', descripcion: '' }],
  );

  let unidades = $state<WizardUnidad[]>(
    Array.isArray(sec('VI').unidades) && sec('VI').unidades.length > 0
      ? sec('VI').unidades.map((u: any, idx: number) => ({
          numero: u?.numero ?? idx + 1,
          titulo: u?.titulo ?? '',
          contenidos: u?.contenidos_items?.[0]?.item ?? '',
          resultados_aprendizaje:
            Array.isArray(u?.resultados_aprendizaje) && u.resultados_aprendizaje.length > 0
              ? u.resultados_aprendizaje.map((r: any) => ({ resultado: r?.resultado ?? '' }))
              : [{ resultado: '' }],
        }))
      : [{ numero: 1, titulo: '', contenidos: '', resultados_aprendizaje: [{ resultado: '' }] }],
  );

  let actividades = $state<WizardActividad[]>(
    Array.isArray(sec('VII').actividades) && sec('VII').actividades.length > 0
      ? sec('VII').actividades.map((a: any) => ({
          id_actividad: a?.id_actividad ?? null,
          nombre: a?.nombre ?? '',
          tipo: a?.tipo ?? 'participación',
          id_unidad: null,
          id_seccion: null,
          nombre_unidad: a?.nombre_unidad ?? '',
        }))
      : [],
  );

  let metodologia = $state(String(sec('VII').metodologia?.tipo_estrategia ?? ''));
  let evaluacion = $state(String(sec('VII').evaluacion?.tipo_evaluacion ?? ''));

  let recursos = $state<{ descripcion: string; tipo: string; ubicacion: string }[]>(
    Array.isArray(sec('VIII').recursos) && sec('VIII').recursos.length > 0
      ? sec('VIII').recursos.map((r: any) => ({
          descripcion: r?.descripcion ?? '',
          tipo: r?.tipo ?? 'Libro',
          ubicacion: r?.ubicacion ?? '',
        }))
      : [{ descripcion: '', tipo: 'Libro', ubicacion: '' }],
  );

  let normativa_curso = $state(String(sec('IX').descripcion ?? ''));
  let ponderacion_optativa = $state(String(sec('IX').ponderacion_optativa?.porcentaje ?? '0'));
  let componentes = $state<WizardComponente[]>(
    Array.isArray(sec('IX').tabla_componentes) && sec('IX').tabla_componentes.length > 0
      ? sec('IX').tabla_componentes.map((c: any) => ({
          componente: c?.componente ?? '',
          porcentaje: c?.porcentaje ?? 0,
          genera_acta: c?.genera_acta ?? false,
          aprobacion_obligatoria: c?.aprobacion_obligatoria ?? false,
          asistencia_obligatoria: c?.asistencia_obligatoria ?? 0,
        }))
      : componentesCurso.length > 0
        ? componentesCurso.map((c) => ({ ...c }))
        : [
            {
              componente: '',
              porcentaje: 0,
              genera_acta: false,
              aprobacion_obligatoria: false,
              asistencia_obligatoria: 0,
            },
          ],
  );

  /** Los resultados de la sección VII se consolidan desde las unidades. */
  const resultadosConsolidados = $derived.by(() => {
    const vistos = new Set<string>();
    for (const u of unidades) {
      for (const r of u.resultados_aprendizaje) {
        const texto = r.resultado.trim();
        if (texto) vistos.add(texto);
      }
    }
    return Array.from(vistos).map((resultado) => ({ resultado }));
  });

  // ── Pasos ─────────────────────────────────────────────────────────────────

  const pasos = $derived(pasosDeTipo(tipoSyllabus));
  const secciones = $derived(seccionesDeTipo(tipoSyllabus));

  /** Al continuar un borrador se abre en el primer paso incompleto. */
  function primerPasoPendiente(): IdPaso {
    if (!programa) return 'I';
    const pendiente = secciones.find((n) => !tieneContenido(n));
    return (pendiente ?? secciones[0]) as IdPaso;
  }

  let pasoActual = $state<IdPaso>('I');
  let iniciado = false;

  $effect(() => {
    if (iniciado) return;
    iniciado = true;
    pasoActual = primerPasoPendiente();
  });

  const indiceActual = $derived(pasos.findIndex((p) => p.id === pasoActual));
  const esUltimo = $derived(indiceActual === pasos.length - 1);
  const puedeEditarPaso = $derived(
    pasoActual === 'RESUMEN' || seccionesEditables.includes(pasoActual),
  );

  function tieneContenido(numeral: string): boolean {
    switch (numeral) {
      case 'I':
        return !!codigo.trim() && !!creditos_sct.trim() && !!categoria.trim();
      case 'II':
        return !!presentacion.trim();
      case 'III':
        return !!estandares.trim();
      case 'IV':
        return (
          competencias_especificas.some((c) => c.titulo.trim()) &&
          competencias_genericas.some((c) => c.titulo.trim())
        );
      case 'V':
        return items_evaluacion.some((i) => i.titulo.trim());
      case 'VI':
        return unidades.some((u) => u.titulo.trim());
      case 'VII':
        return esBasico
          ? actividades.some((a) => a.nombre.trim())
          : resultadosConsolidados.length > 0 && !!metodologia.trim() && !!evaluacion.trim();
      case 'VIII':
        return recursos.some((r) => r.descripcion.trim());
      case 'IX':
        return !!normativa_curso.trim() && componentes.some((c) => c.componente.trim());
      default:
        return false;
    }
  }

  const completos = $derived(secciones.filter((n) => tieneContenido(n)));

  /** Campos obligatorios que faltan por sección; alimenta el badge de la barra. */
  const errores = $derived.by(() => {
    const faltan: Record<string, number> = {};
    const sumar = (numeral: string, cuantos: number) => {
      if (cuantos > 0) faltan[numeral] = cuantos;
    };

    sumar(
      'I',
      [codigo.trim(), creditos_sct.trim(), horas_catedra.trim(), categoria.trim()].filter(
        (v) => !v,
      ).length,
    );

    if (!esBasico) {
      sumar('II', presentacion.trim() ? 0 : 1);
      sumar('III', estandares.trim() ? 0 : 1);
      sumar(
        'IV',
        (competencias_especificas.some((c) => c.titulo.trim()) ? 0 : 1) +
          (competencias_genericas.some((c) => c.titulo.trim()) ? 0 : 1),
      );
      sumar('V', items_evaluacion.some((i) => i.titulo.trim()) ? 0 : 1);
      sumar('VI', unidades.some((u) => u.titulo.trim()) ? 0 : 1);
      sumar(
        'VII',
        (resultadosConsolidados.length > 0 ? 0 : 1) +
          (metodologia.trim() ? 0 : 1) +
          (evaluacion.trim() ? 0 : 1),
      );
      sumar('VIII', recursos.filter((r) => r.descripcion.trim()).length >= 2 ? 0 : 1);
      sumar(
        'IX',
        (normativa_curso.trim() ? 0 : 1) + (componentes.some((c) => c.componente.trim()) ? 0 : 1),
      );
    }

    return faltan;
  });

  const erroresPasoActual = $derived(errores[pasoActual] ?? 0);
  const totalErrores = $derived(Object.values(errores).reduce((a, b) => a + b, 0));

  const razonBloqueo = $derived.by(() => {
    if (erroresPasoActual > 0) {
      return `Faltan ${erroresPasoActual} ${erroresPasoActual === 1 ? 'campo obligatorio' : 'campos obligatorios'} en este paso.`;
    }
    return null;
  });

  function irA(id: IdPaso) {
    pasoActual = id;
    if (typeof window !== 'undefined') window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function anterior() {
    if (indiceActual > 0) irA(pasos[indiceActual - 1].id);
  }

  function siguiente() {
    if (erroresPasoActual > 0) return;
    if (indiceActual < pasos.length - 1) irA(pasos[indiceActual + 1].id);
  }

  // ── Guardado ──────────────────────────────────────────────────────────────

  function construirSecciones(): Record<string, any> {
    const seccionVII = esBasico
      ? {
          contenido: {
            actividades: actividades
              .filter((a) => a.nombre.trim() || a.id_actividad)
              .map((a) => ({
                id_actividad: a.id_actividad || null,
                nombre: a.nombre.trim() || 'Actividad',
                tipo: a.tipo,
                nombre_unidad: a.nombre_unidad?.trim() || '',
              })),
          },
        }
      : {
          contenido: {
            resultados_aprendizaje: {
              titulo: 'Resultados de Aprendizaje',
              items: resultadosConsolidados,
            },
            metodologia: { titulo: 'Metodología', tipo_estrategia: metodologia.trim() },
            evaluacion: { titulo: 'Evaluación', tipo_evaluacion: evaluacion.trim() },
          },
        };

    const todas: Record<string, any> = {
      I: {
        contenido: {
          nombre_asignatura: nombreAsignatura,
          codigo: codigo.trim(),
          creditos_sct: parseInt(creditos_sct) || 0,
          horas: {
            catedra: parseInt(horas_catedra) || 0,
            taller: parseInt(horas_taller) || 0,
            laboratorio: parseInt(horas_laboratorio) || 0,
          },
          categoria,
        },
      },
      II: { contenido: { texto: presentacion.trim() } },
      III: { contenido: { texto: estandares.trim() } },
      IV: {
        contenido: {
          competencias_especificas: competencias_especificas
            .filter((c) => c.titulo.trim())
            .map((c) => ({ titulo: c.titulo.trim() })),
          competencias_genericas: competencias_genericas
            .filter((c) => c.titulo.trim())
            .map((c) => ({ titulo: c.titulo.trim() })),
          subcompetencias: subcompetencias
            .filter((s) => s.titulo.trim())
            .map((s) => ({ titulo: s.titulo.trim() })),
        },
      },
      V: {
        contenido: {
          items: items_evaluacion
            .filter((i) => i.titulo.trim())
            .map((i) => ({ titulo: i.titulo.trim(), descripcion: i.descripcion.trim() || null })),
        },
      },
      VI: {
        contenido: {
          unidades: unidades
            .filter((u) => u.titulo.trim())
            .map((u) => ({
              numero: u.numero,
              titulo: u.titulo.trim(),
              contenidos_items: u.contenidos.trim() ? [{ item: u.contenidos.trim() }] : [],
              resultados_aprendizaje: u.resultados_aprendizaje
                .filter((r) => r.resultado.trim())
                .map((r) => ({ resultado: r.resultado.trim() })),
            })),
        },
      },
      VII: seccionVII,
      VIII: {
        contenido: {
          recursos: recursos
            .filter((r) => r.descripcion.trim())
            .map((r) => ({
              descripcion: r.descripcion.trim(),
              tipo: r.tipo,
              ubicacion: r.ubicacion.trim() || null,
            })),
        },
      },
      IX: {
        contenido: {
          descripcion: normativa_curso.trim(),
          ponderacion_optativa: { porcentaje: parseFloat(ponderacion_optativa) || 0 },
          tabla_componentes: componentes
            .filter((c) => c.componente.trim())
            .map((c) => ({
              componente: c.componente.trim(),
              porcentaje: Number(c.porcentaje) || 0,
              genera_acta: !!c.genera_acta,
              aprobacion_obligatoria: !!c.aprobacion_obligatoria,
              asistencia_obligatoria: Number(c.asistencia_obligatoria) || 0,
            })),
        },
      },
    };

    // BÁSICO guarda I, II, VI, VII y VIII — las mismas que exige
    // `SyllabusRules::basico()` y `ProgramaService::getRequiredSecciones`.
    return esBasico
      ? Object.fromEntries(secciones.map((n) => [n, todas[n]]))
      : todas;
  }

  /** Firma del formulario, para saber si hay cambios sin guardar. */
  const firma = $derived(JSON.stringify(construirSecciones()));
  let firmaGuardada = $state<string | null>(null);
  const sucio = $derived(firmaGuardada !== null && firma !== firmaGuardada);

  $effect(() => {
    if (firmaGuardada === null) firmaGuardada = firma;
  });

  let guardando = $state(false);
  let errorGuardado = $state<string | null>(null);
  let guardadoEn = $state<string | null>(null);
  let saliendo = $state(false);

  async function guardar(): Promise<boolean> {
    guardando = true;
    errorGuardado = null;
    try {
      const nuevas = actividades.filter((a) => !a.id_actividad && a.nombre.trim());
      await generatePrograma(
        curso.id_curso,
        {
          secciones: construirSecciones() as any,
          syllabus_type: esBasico ? 'simplified' : 'complete',
          actividades_to_create: nuevas.map((a) => ({
            nombre: a.nombre.trim(),
            tipo_actividad: 1,
            tipo_entrega: 'online',
            es_grupal: false,
            max_integrantes: 1,
            nombre_unidad: a.nombre_unidad?.trim() || '',
          })),
        } as any,
        basePath,
      );
      firmaGuardada = firma;
      guardadoEn = new Date().toISOString();
      return true;
    } catch (err) {
      errorGuardado = extractErrorMessage(err, 'No pudimos guardar el syllabus.');
      return false;
    } finally {
      guardando = false;
    }
  }

  async function guardarYVolver() {
    if (await guardar()) volverAlVisor();
  }

  function volverAlVisor(destino?: string) {
    saliendo = true;
    router.visit(destino ?? urlVisor);
  }

  // ── Salida con cambios sin guardar ────────────────────────────────────────

  let dialogoSalida = $state(false);
  let salidaPendiente = $state<string | null>(null);

  function intentarSalir() {
    if (sucio) {
      salidaPendiente = urlVisor;
      dialogoSalida = true;
      return;
    }
    volverAlVisor();
  }

  $effect(() =>
    router.on('before', (evento: any) => {
      if (saliendo || !sucio) return;
      salidaPendiente = String(evento?.detail?.visit?.url ?? urlVisor);
      dialogoSalida = true;
      return false;
    }),
  );

  $effect(() => {
    const avisar = (e: BeforeUnloadEvent) => {
      if (!sucio) return;
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', avisar);
    return () => window.removeEventListener('beforeunload', avisar);
  });

  function alTeclear(e: KeyboardEvent) {
    if (e.key === 'Escape' && !dialogoSalida) intentarSalir();
  }

  // ── Vocabulario visual ────────────────────────────────────────────────────
  const BTN_GHOST =
    'rounded-lg border-none bg-transparent px-1 py-2.5 text-[13px] font-medium text-[#5A5E6E] transition-colors hover:text-[#1A1A24] disabled:opacity-50';
  const BTN_OUTLINE =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2.5 text-[13px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA] disabled:opacity-50';
  const BTN_PRIMARY =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#002F6C] bg-[#002F6C] px-4 py-2.5 text-[13px] font-semibold text-white transition-colors hover:bg-[#1B4789] disabled:opacity-60';
  const BTN_BLOQUEADO =
    'inline-flex cursor-not-allowed items-center gap-[7px] rounded-lg border border-[#C3C8D2] bg-[#C3C8D2] px-4 py-2.5 text-[13px] font-semibold text-white';
</script>

<svelte:window onkeydown={alTeclear} />

{#snippet contenido()}
  <!-- Fondo blanco: el gris #EDEFF3 de la lámina es el mantel del lienzo de
       diseño, no una superficie de la aplicación. -->
  <div class="min-h-screen bg-white py-4 sm:py-6">
    <div class="mx-auto max-w-[1440px] px-3 sm:px-6">
      <div
        class="flex flex-col overflow-hidden rounded-[10px] border border-[#E5E7EB] bg-white"
      >
        <!-- ── Aviso de auditoría ── -->
        {#if editandoDeOtro && docenteTitular}
          <p
            class="m-0 flex items-center gap-2.5 border-b border-[#E3D9C6] bg-[#F5F1EA] px-5 py-2.5 text-[12.5px] text-[#6B5B3E] sm:px-7"
          >
            <ShieldAlert size={15} class="shrink-0" aria-hidden="true" />
            Editando en nombre del docente titular
            <strong class="font-semibold">{docenteTitular}</strong> — quedará registrado en el
            historial del programa.
          </p>
        {/if}

        <!-- ── Cabecera ── -->
        <header class="flex flex-wrap items-start gap-5 px-5 pt-4 sm:px-7">
          <div class="flex min-w-0 flex-col gap-1">
            <h1 class="m-0 text-[21px] font-semibold tracking-[-0.01em] text-[#1A1A24]">
              {programa ? 'Editar syllabus' : `Crear syllabus ${tipoSyllabus}`}
            </h1>
            <p class="m-0 flex flex-wrap items-center gap-2 text-[12.5px] text-[#5A5E6E]">
              {#if codigoAsignatura}
                <span class="font-mono">{codigoAsignatura}</span>
                <span class="text-[#D6D9E0]" aria-hidden="true">·</span>
              {/if}
              <span>{nombreAsignatura}</span>
              {#if curso.letra_grupo}
                <span class="text-[#D6D9E0]" aria-hidden="true">·</span>
                <span>Grupo {curso.letra_grupo}</span>
              {/if}
              {#if curso.agno_real && curso.semestre_real}
                <span class="text-[#D6D9E0]" aria-hidden="true">·</span>
                <span>{curso.semestre_real}.º semestre {curso.agno_real}</span>
              {/if}
              {#if programa}
                <span class="text-[#D6D9E0]" aria-hidden="true">·</span>
                <span>Versión {programa.version_programa} · {programa.estado}</span>
              {/if}
            </p>
          </div>

          <div class="ml-auto flex flex-none items-center gap-3">
            <span
              class="inline-flex items-center gap-[7px] rounded-full px-3 py-1 text-[12px] font-semibold {esBasico
                ? 'bg-[#F5F1EA] text-[#6B5B3E]'
                : 'bg-[#E8EDF5] text-[#002F6C]'}"
            >
              <FileText size={13} aria-hidden="true" />
              {tipoSyllabus} · {secciones.length} secciones
            </span>
            <button
              type="button"
              onclick={intentarSalir}
              aria-label="Cerrar el asistente"
              class="flex size-8 items-center justify-center rounded-lg border border-[#D6D9E0] bg-white text-[#5A5E6E] transition-colors hover:bg-[#F5F1EA]"
            >
              <X size={16} aria-hidden="true" />
            </button>
          </div>
        </header>

        <!-- ── Error de guardado ── -->
        {#if errorGuardado}
          <div
            class="mx-5 mt-3.5 flex flex-wrap items-center gap-2.5 rounded-[9px] border border-[#F5DFA8] bg-[#FFFBEB] px-3.5 py-3 sm:mx-7"
            role="alert"
          >
            <AlertTriangle size={16} class="shrink-0 text-[#B45309]" aria-hidden="true" />
            <span class="text-[13px] text-[#7C4A02]">
              {errorGuardado} Lo que escribiste sigue en pantalla; puedes reintentar.
            </span>
            <button
              type="button"
              onclick={guardar}
              disabled={guardando}
              class="ml-auto rounded-[7px] border border-[#E0C68A] bg-white px-3 py-1.5 text-[12.5px] font-semibold text-[#7C4A02] transition-colors hover:bg-[#FFF7E4] disabled:opacity-50"
            >
              Reintentar
            </button>
          </div>
        {/if}

        <!-- ── Barra de pasos ── -->
        <SyllabusPasos
          {pasos}
          actual={pasoActual}
          {completos}
          editables={seccionesEditables}
          {errores}
          onIr={irA}
        />

        <!-- ── Cuerpo ── -->
        <div
          class="px-5 py-7 sm:px-7 {pasoActual === 'RESUMEN' ? 'bg-[#FAFAFB]' : 'bg-white'}"
        >
          {#if pasoActual === 'RESUMEN'}
            <SyllabusResumen
              codigo={codigoAsignatura}
              tipo={tipoSyllabus}
              version={programa?.version_programa ?? null}
              {nombreAsignatura}
              {creditos_sct}
              {horas_catedra}
              {horas_taller}
              {horas_laboratorio}
              {categoria}
              {docenteTitular}
              {presentacion}
              {unidades}
              {actividades}
              {recursos}
            />
          {:else}
            <ProgramaWizardSteps
              seccion={pasoActual}
              tipo={tipoSyllabus}
              editable={puedeEditarPaso}
              {curso}
              bind:codigo
              bind:creditos_sct
              bind:horas_catedra
              bind:horas_taller
              bind:horas_laboratorio
              bind:categoria
              bind:presentacion
              bind:estandares
              bind:competencias_especificas
              bind:competencias_genericas
              bind:subcompetencias
              bind:items_evaluacion
              bind:actividades
              existingActividades={actividadesCurso}
              bind:unidades
              resultados_aprendizaje={resultadosConsolidados}
              bind:metodologia
              bind:evaluacion
              bind:recursos
              bind:normativa_curso
              bind:ponderacion_optativa
              bind:componentes
            />
          {/if}
        </div>

        <!-- ── Pie ── -->
        <div
          class="sticky bottom-0 z-10 flex flex-wrap items-center gap-4 border-t border-[#E5E7EB] bg-white px-5 py-3.5 sm:px-7"
        >
          <button type="button" onclick={intentarSalir} disabled={guardando} class={BTN_GHOST}>
            Cancelar
          </button>
          <button type="button" onclick={guardar} disabled={guardando} class={BTN_OUTLINE}>
            <Save size={15} class="text-[#5A5E6E]" aria-hidden="true" />
            {guardando ? 'Guardando…' : 'Guardar borrador'}
          </button>

          <span class="mx-auto text-[11.5px] text-[#9AA0AE]">
            {#if guardadoEn}
              Guardado a las {formatFechaHora(guardadoEn)}
            {:else}
              Cada guardado crea una versión nueva del programa
            {/if}
          </span>

          <button
            type="button"
            onclick={anterior}
            disabled={indiceActual <= 0 || guardando}
            class="{BTN_OUTLINE} disabled:border-[#EDEFF3] disabled:text-[#C3C8D2]"
          >
            <ChevronLeft size={15} aria-hidden="true" />
            Anterior
          </button>

          {#if esUltimo}
            <button
              type="button"
              onclick={guardarYVolver}
              disabled={guardando || totalErrores > 0}
              class={totalErrores > 0 ? BTN_BLOQUEADO : BTN_PRIMARY}
              title={totalErrores > 0
                ? 'Quedan campos obligatorios sin completar en el documento'
                : undefined}
            >
              <Check size={15} aria-hidden="true" />
              {guardando ? 'Guardando…' : esBasico ? 'Guardar syllabus básico' : 'Guardar syllabus'}
            </button>
          {:else if razonBloqueo}
            <span class="relative inline-flex flex-col items-end gap-2">
              <span
                class="max-w-[300px] rounded-lg bg-[#1A1A24] px-3 py-2.5 text-[12px] leading-[1.5] text-white"
              >
                {razonBloqueo}
              </span>
              <span class={BTN_BLOQUEADO} aria-disabled="true">
                Siguiente
                <ChevronRight size={15} aria-hidden="true" />
              </span>
            </span>
          {:else}
            <button type="button" onclick={siguiente} disabled={guardando} class={BTN_PRIMARY}>
              Siguiente
              <ChevronRight size={15} aria-hidden="true" />
            </button>
          {/if}
        </div>
      </div>
    </div>
  </div>

  <!-- ── Cambios sin guardar ── -->
  {#if dialogoSalida}
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#1A1A24]/40 p-4"
      role="presentation"
      onclick={(e) => {
        if (e.target === e.currentTarget) dialogoSalida = false;
      }}
    >
      <div
        class="flex w-full max-w-[452px] flex-col gap-4 rounded-xl bg-white p-6 shadow-[0_18px_44px_rgba(0,0,0,.22)]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="salida-titulo"
      >
        <div class="flex items-start gap-3">
          <span
            class="flex size-[34px] flex-none items-center justify-center rounded-[9px] bg-[#FFFBEB]"
          >
            <AlertTriangle size={17} class="text-[#B45309]" aria-hidden="true" />
          </span>
          <div class="flex flex-col gap-1.5">
            <h2 id="salida-titulo" class="m-0 text-[16px] font-semibold text-[#1A1A24]">
              Hay cambios sin guardar
            </h2>
            <p class="m-0 text-[13px] text-pretty text-[#5A5E6E]">
              Modificaste el syllabus y no se ha guardado desde entonces. ¿Guardar antes de salir?
            </p>
          </div>
        </div>
        <div class="flex flex-col gap-2">
          <button
            type="button"
            disabled={guardando}
            onclick={async () => {
              if (await guardar()) {
                dialogoSalida = false;
                volverAlVisor(salidaPendiente ?? undefined);
              }
            }}
            class="flex items-center justify-center gap-2 rounded-lg border-none bg-[#002F6C] px-4 py-2.5 text-[13px] font-semibold text-white transition-colors hover:bg-[#1B4789] disabled:opacity-60"
          >
            <Save size={15} aria-hidden="true" />
            {guardando ? 'Guardando…' : 'Guardar y salir'}
          </button>
          <button
            type="button"
            onclick={() => {
              dialogoSalida = false;
              volverAlVisor(salidaPendiente ?? undefined);
            }}
            class="rounded-lg border border-[#D6D9E0] bg-white px-4 py-2.5 text-[13px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA]"
          >
            Salir sin guardar
          </button>
          <button
            type="button"
            onclick={() => {
              dialogoSalida = false;
              salidaPendiente = null;
            }}
            class="rounded-lg border-none bg-transparent px-4 py-2 text-[13px] font-medium text-[#5A5E6E] transition-colors hover:text-[#1A1A24]"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>
  {/if}
{/snippet}

{#if layoutType === 'admin'}
  <AdminLayout>
    {@render contenido()}
  </AdminLayout>
{:else if layoutType === 'ayudante'}
  <AyudanteLayout>
    {@render contenido()}
  </AyudanteLayout>
{:else}
  <DocenteLayout>
    {@render contenido()}
  </DocenteLayout>
{/if}
