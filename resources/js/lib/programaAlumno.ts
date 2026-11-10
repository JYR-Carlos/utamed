/**
 * Forma de leer el programa en la vista del alumno: las Unidades como
 * tarjetas y la sección VIII como una biblioteca agrupada.
 *
 * Trabaja sobre los bloques de `bloquesDeSeccion` (el texto que aplana
 * App\Traits\ParsesSyllabus); no inventa datos, sólo los reordena.
 */
import {
    bloquesDeSeccion,
    segmentarTexto,
    textoDeSeccion,
    type BloqueSyllabus,
} from '@/modules/resources/programa/utils/syllabusTexto';

/**
 * Un docente de la sección del alumno. Cada componente (cátedra, laboratorio,
 * taller) tiene un titular y puede tener un docente extra propio.
 */
export interface DocenteSyllabus {
    nombre: string;
    email?: string | null;
    es_titular: boolean;
    componente?: string | null;
}

export interface ComponenteEvaluacion {
    componente: string;
    porcentaje: number | string;
    genera_acta?: boolean;
    aprobacion_obligatoria?: boolean;
    asistencia_obligatoria?: number | string | null;
}

/** Sección tal como la arma App\Traits\ParsesSyllabus (igual que al docente). */
export interface SeccionPrograma {
    numeral_romano?: string;
    nombre_seccion: string;
    contenidos?: Array<{ texto_contenido: string | null }>;
    componentes?: ComponenteEvaluacion[];
    ponderacion_optativa?: { porcentaje?: number } | null;
}

export interface ProgramaPublicado {
    id_programa: number;
    version_programa: string;
    estado: string;
    creado_por?: string;
    fecha_creacion?: string;
    tipo_syllabus?: string;
}

type SeccionConTexto = Pick<SeccionPrograma, 'numeral_romano' | 'contenidos'>;

/** Las secciones con algo que leer, con las Unidades (VI) primero (T24). */
export function seccionesParaLeer(secciones: SeccionPrograma[]): SeccionPrograma[] {
    const conContenido = secciones.filter(
        (s) => textoDeSeccion(s.contenidos).trim() !== '' || (s.componentes?.length ?? 0) > 0,
    );
    return [
        ...conContenido.filter((s) => s.numeral_romano === 'VI'),
        ...conContenido.filter((s) => s.numeral_romano !== 'VI'),
    ];
}

/** «60 %»; guion si no hay dato. */
export function porcentaje(v: number | string | null | undefined): string {
    return v === null || v === undefined || v === '' ? '—' : `${Number(v).toLocaleString('es-CL')} %`;
}

/** «marzo de 2026». */
export function mesYAgno(fecha?: string | null): string {
    if (!fecha) return '';
    return new Date(fecha).toLocaleDateString('es-CL', { year: 'numeric', month: 'long' });
}

/** Un rótulo «Unidad N:» abre una unidad. */
const UNIDAD = /^unidad\s+(\d+)\s*:\s*/i;

/** Un texto entero en mayúsculas («PRODUCCIÓN WEB») se pasa a frase («Producción web»). */
function sinGritar(texto: string): string {
    if (texto !== texto.toUpperCase() || texto === texto.toLowerCase()) return texto;
    const bajo = texto.toLowerCase();
    return bajo.charAt(0).toUpperCase() + bajo.slice(1);
}

/**
 * «Unidad 1: Unidad 1 : Producción web» → { numero: 'Unidad 1', nombre: 'Producción web' }.
 * El backend repite el rótulo dentro del nombre; aquí se quita.
 */
export function partirTituloUnidad(texto: string): { numero: string; nombre: string } {
    const m = texto.match(UNIDAD);
    if (!m) return { numero: '', nombre: sinGritar(texto.trim()) };
    const nombre = texto.slice(m[0].length).replace(UNIDAD, '').trim();
    return { numero: `Unidad ${m[1]}`, nombre: sinGritar(nombre) };
}

export interface Unidad {
    numero: string;
    nombre: string;
    bloques: BloqueSyllabus[];
}

/** Agrupa los bloques bajo cada «Unidad N: …». Lo que va antes de la primera unidad queda en `antes`. */
export function agruparPorUnidad(bloques: BloqueSyllabus[]): { antes: BloqueSyllabus[]; unidades: Unidad[] } {
    const antes: BloqueSyllabus[] = [];
    const unidades: Unidad[] = [];
    for (const bloque of bloques) {
        if (bloque.tipo === 'subtitulo' && UNIDAD.test(bloque.texto)) {
            unidades.push({ ...partirTituloUnidad(bloque.texto), bloques: [] });
        } else if (unidades.length > 0) {
            unidades[unidades.length - 1].bloques.push(bloque);
        } else {
            antes.push(bloque);
        }
    }
    return { antes, unidades };
}

/**
 * Un campo cuyo valor es una enumeración («Estructura web., Hosting., …»)
 * se lee mejor como lista. Devuelve null si el valor es una sola idea.
 */
export function itemsDeEnumeracion(valor: string): string[] | null {
    if (!/\.,\s+/.test(valor)) return null;
    return valor
        .split(/\.,\s+/)
        .map((v) => v.trim().replace(/\.$/, ''))
        .filter(Boolean);
}

// ─── Biblioteca (sección VIII) ──────────────────────────────────────────────

export type TipoRecurso = 'libro' | 'archivo' | 'enlace';

export interface Recurso {
    texto: string;
    /** La cita, sólo si dice algo que el texto no dice ya. */
    cita?: string;
    tipo: TipoRecurso;
    href?: string;
    /** Dirección legible del enlace (sin https://). */
    direccion?: string;
}

export interface GrupoRecursos {
    id: string;
    titulo: string;
    /** Para los chips de móvil: «Unidad 1» en vez del nombre completo. */
    corto: string;
    recursos: Recurso[];
}

const comparable = (t: string) => t.toLowerCase().replace(/[^\p{L}\p{N}]/gu, '');

function direccionDe(href: string): string {
    try {
        const url = new URL(href);
        return `${url.host}${url.pathname}`.replace(/\/$/, '');
    } catch {
        return href;
    }
}

function recursoDe(texto: string, cita?: string): Recurso {
    const enlaces = segmentarTexto(texto).filter((s) => s.href);
    const archivo = enlaces.find((s) => s.tipo === 'archivo');
    const url = enlaces.find((s) => s.tipo === 'url');

    // El enlace se ofrece aparte («Abrir enlace»), así que sale del texto:
    // «[Enlace web: https://…]» entero, o la URL suelta.
    const limpio =
        texto
            .replace(/\s*\[[^\]]*(?:https?:\/\/|\/api\/bibliografias\/)[^\]]*\]/g, '')
            .replace(/\s*(?:https?:\/\/[^\s,\])>]+|\/api\/bibliografias\/[a-zA-Z0-9-]+\/archivo)/g, '')
            .trim() || texto.trim();

    const citaLimpia = cita?.replace(/^«\s*/, '').replace(/\s*»$/, '').trim();
    const citaUtil =
        citaLimpia && !comparable(limpio).includes(comparable(citaLimpia)) ? citaLimpia : undefined;

    if (archivo) return { texto: limpio, cita: citaUtil, tipo: 'archivo', href: archivo.href };
    if (url) return { texto: limpio, cita: citaUtil, tipo: 'enlace', href: url.href, direccion: direccionDe(url.href!) };
    return { texto: limpio, cita: citaUtil, tipo: 'libro' };
}

/**
 * Los recursos de la sección VIII agrupados por sus subtítulos («Bibliografía
 * obligatoria», «Unidad 1: …»). Lo que no tiene subtítulo va a «Bibliografía».
 */
export function gruposDeRecursos(secciones: SeccionConTexto[]): GrupoRecursos[] {
    const grupos: GrupoRecursos[] = [];

    const grupoActual = (): GrupoRecursos => {
        if (grupos.length === 0) grupos.push({ id: 'recursos-1', titulo: 'Bibliografía', corto: 'Bibliografía', recursos: [] });
        return grupos[grupos.length - 1];
    };

    for (const seccion of secciones.filter((s) => s.numeral_romano === 'VIII')) {
        for (const bloque of bloquesDeSeccion(textoDeSeccion(seccion.contenidos), seccion.numeral_romano)) {
            if (bloque.tipo === 'subtitulo') {
                const { numero, nombre } = partirTituloUnidad(bloque.texto);
                grupos.push({
                    id: `recursos-${grupos.length + 1}`,
                    titulo: numero ? `${numero} · ${nombre}` : nombre,
                    corto: numero || nombre,
                    recursos: [],
                });
            } else if (bloque.tipo === 'lista') {
                grupoActual().recursos.push(...bloque.items.map((i) => recursoDe(i.texto, i.cita)));
            } else if (bloque.tipo === 'campo') {
                grupoActual().recursos.push(recursoDe(`${bloque.etiqueta}: ${bloque.valor}`));
            } else {
                grupoActual().recursos.push(recursoDe(bloque.texto));
            }
        }
    }

    return grupos.filter((g) => g.recursos.length > 0);
}
