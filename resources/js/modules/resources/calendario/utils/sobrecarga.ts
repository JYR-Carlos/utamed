/**
 * Detección de sobrecarga por grupo.
 *
 * Regla: un mismo grupo acumula tres o más fechas límite en un mismo día.
 *
 * El grupo es el del curso (`curso.letra_grupo`), acotado además al período
 * (`agno_real`-`semestre_real`) para no mezclar semestres distintos que
 * reutilizan la misma letra. Los cursos sin letra de grupo quedan fuera del
 * cálculo: no hay a qué grupo atribuirles la carga.
 *
 * El recuento se hace SIEMPRE sobre todas las entregas del docente, incluidas
 * las de cursos ocultos por el filtro — ocultar un curso no reduce la carga
 * real del grupo —, y el resultado declara cuántas quedaron fuera del filtro.
 */
import type { CalendarCurso, CalendarEvento, Sobrecarga } from '../types';

/** Entregas de un mismo grupo en un día a partir de las cuales se avisa. */
export const UMBRAL_SOBRECARGA = 3;

/** Clave de agrupación: período + letra del grupo. */
function claveGrupo(curso: CalendarCurso): string | null {
  if (!curso.letra_grupo) return null;
  return `${curso.agno_real ?? '?'}-${curso.semestre_real ?? '?'}-${curso.letra_grupo}`;
}

/**
 * Calcula las sobrecargas de cada día.
 *
 * @param entregas       Todas las fechas límite del docente, sin filtrar.
 * @param cursoPorId     Mapa id_curso → curso, para leer letra y período.
 * @param cursosVisibles Cursos que el filtro deja ver ahora mismo.
 * @returns              Mapa 'YYYY-MM-DD' → sobrecargas de ese día.
 */
export function calcularSobrecargas(
  entregas: CalendarEvento[],
  cursoPorId: Record<number, CalendarCurso>,
  cursosVisibles: Set<number>,
): Record<string, Sobrecarga[]> {
  /** fecha → clave de grupo → acumulador. */
  const acumulado: Record<string, Record<string, Sobrecarga>> = {};

  for (const entrega of entregas) {
    const curso = cursoPorId[entrega.id_curso];
    if (!curso) continue;

    const clave = claveGrupo(curso);
    if (!clave) continue;

    const delDia = (acumulado[entrega.fecha] ??= {});
    const grupo = (delDia[clave] ??= {
      clave,
      letra: curso.letra_grupo ?? '',
      total: 0,
      visibles: 0,
      ocultas: 0,
      titulos: [],
    });

    grupo.total += 1;
    if (cursosVisibles.has(entrega.id_curso)) grupo.visibles += 1;
    else grupo.ocultas += 1;
    grupo.titulos.push(entrega.titulo);
  }

  const resultado: Record<string, Sobrecarga[]> = {};

  for (const fecha in acumulado) {
    const grupos = Object.values(acumulado[fecha])
      .filter((g) => g.total >= UMBRAL_SOBRECARGA)
      .sort((a, b) => a.letra.localeCompare(b.letra));

    if (grupos.length > 0) resultado[fecha] = grupos;
  }

  return resultado;
}

/** Frase de aviso lista para mostrar: "Grupo A tiene 3 entregas hoy: …". */
export function frasesSobrecarga(grupo: Sobrecarga): { titulo: string; detalle: string } {
  return {
    titulo: `Grupo ${grupo.letra} tiene ${grupo.total} entregas este día`,
    detalle: grupo.titulos.join(' · '),
  };
}
