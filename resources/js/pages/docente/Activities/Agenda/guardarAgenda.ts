/**
 * Guardados de la agenda del docente, compartidos por la actividad desplegada
 * (Actividades) y la agenda que se abre sobre otras pantallas (Inicio).
 *
 * Las peticiones van por Inertia y se encadenan con `await`: una visita nueva
 * cancela la anterior si no se espera, y la evaluación tiene que llegar
 * después de las décimas para recalcular la nota de cada integrante.
 */
import { router } from '@inertiajs/svelte';
import type { InteraccionItem } from '@/types/agenda';
import type { EnvioAgenda } from './AgendaDocente.svelte';

export type Visita = (method: 'post' | 'put', url: string, data: Record<string, unknown>) => Promise<void>;

/** Visita de Inertia como promesa, recargando sólo las props indicadas. */
export function visitaInertia(only: string[]): Visita {
  return (method, url, data) =>
    new Promise((ok, ko) =>
      router.visit(url, {
        method,
        data: data as any,
        preserveScroll: true,
        preserveState: true,
        replace: true,
        showProgress: false,
        only,
        onSuccess: () => ok(),
        onError: (e) => ko(e),
      }),
    );
}

export const urlFeedback = (idCurso: number, idGrupo: number) => `/docente/cursos/${idCurso}/grupos/${idGrupo}/feedback`;

/**
 * La conversación de un grupo, sin recalcular la pantalla que la muestra.
 * Pedirla marca como vistos los mensajes que el docente aún no leía.
 */
export async function pedirHilo(urlGrupo: string): Promise<InteraccionItem[]> {
  const r = await fetch(`${urlGrupo}/mensajes`, {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  if (!r.ok) throw new Error(String(r.status));
  return r.json();
}

/** El mensaje recién escrito, para mostrarlo antes de que vuelva del servidor. */
export function mensajeOptimista(autor: { nombre1?: string; apellido1?: string } | null | undefined, mensaje: string): InteraccionItem {
  return {
    id_interaccion: -Date.now(),
    fecha_emision: new Date().toISOString(),
    tipo_interaccion: 'Feedback',
    emisor: autor ? [autor.nombre1, autor.apellido1].filter(Boolean).join(' ') : 'Docente',
    mensaje,
    es_de_docente: true,
    es_propio: true,
    es_retroalimentacion: true,
  } as InteraccionItem;
}

/** Junta el hilo del servidor con los mensajes optimistas que aún no vuelven. */
export function conOptimistas(hilo: InteraccionItem[], optimistas: InteraccionItem[]): InteraccionItem[] {
  if (optimistas.length === 0) return hilo;
  const pendientes = optimistas.filter((o) => {
    const t = new Date(o.fecha_emision).getTime();
    return !hilo.some((s) => (s.es_propio || s.es_de_docente) && s.mensaje === o.mensaje && Math.abs(new Date(s.fecha_emision).getTime() - t) < 60000);
  });
  return [...hilo, ...pendientes];
}

export const fmtDecimas = (d: number) => `${d > 0 ? '+' : '−'}${Math.abs(d)} ${Math.abs(d) === 1 ? 'décima' : 'décimas'}`;

/**
 * Graba una evaluación desde la agenda. En una sumativa grupal van primero
 * las décimas de cada integrante y su retroalimentación (publicada en la
 * agenda del grupo: la base no tiene destinatario por integrante); la
 * evaluación va al final y recalcula la nota de cada uno.
 */
export async function guardarEvaluacion(
  visita: Visita,
  p: {
    idCurso: number;
    idActividad: number;
    grupo: {
      grupo: number;
      entrega: { id_agenda: number } | null;
      integrantes: Array<{ id_asignado_actividad: number; diferencia_decimas: number | null }>;
    };
    esSumativa: boolean;
    rubricaId: number | null;
    envio: EnvioAgenda;
  },
): Promise<void> {
  const { idCurso, idActividad, grupo: g, esSumativa, rubricaId, envio } = p;
  const base = `/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${g.grupo}`;

  for (const d of envio.decimas ?? []) {
    const integrante = g.integrantes.find((i) => i.id_asignado_actividad === d.id_asignado_actividad);
    const actuales = Math.round((integrante?.diferencia_decimas ?? 0) * 10);
    if (actuales !== d.decimas) {
      await visita('put', `${base}/integrantes/${d.id_asignado_actividad}`, { diferencia_decimas: d.decimas / 10 });
    }
    if (d.decimas !== 0 && d.retro) {
      await visita('post', urlFeedback(idCurso, g.grupo), {
        mensaje: `Retroalimentación para ${d.nombre} (${fmtDecimas(d.decimas)}): ${d.retro}`,
      });
    }
  }

  await visita('post', `${base}/evaluacion`, {
    id_agenda_entrega: g.entrega?.id_agenda ?? null,
    id_rubrica: esSumativa ? rubricaId : null,
    nota: esSumativa ? (envio.nota ?? null) : null,
    evaluacion_obtenida: esSumativa ? null : (envio.evaluacion_obtenida ?? null),
    mensaje: envio.mensaje,
    resultado_rubrica: esSumativa ? (envio.resultado_rubrica ?? null) : null,
    puntaje_obtenido: esSumativa ? (envio.puntaje_obtenido ?? null) : null,
  });
}

/** El primer mensaje de error que devuelve Laravel, o el texto por defecto. */
export function primerError(e: unknown, porDefecto: string): string {
  return Object.values((e ?? {}) as Record<string, string>)[0] ?? porDefecto;
}
