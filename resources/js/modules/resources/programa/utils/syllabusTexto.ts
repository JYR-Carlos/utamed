/**
 * Vuelve a dar forma al texto plano de cada sección del syllabus.
 *
 * El backend (`App\Traits\ParsesSyllabus`) aplana los DTO de `App\Syllabus` a un
 * único string por sección, con convenciones fijas: `Etiqueta: valor` para los
 * campos, `Etiqueta:` sola como encabezado de grupo, `• ` para los ítems y dos
 * espacios de sangría para los ítems anidados. Este parser recupera esa
 * estructura para poder componer el documento como documento —columnas, listas,
 * campos— en vez de volcar un `<pre>`.
 *
 * No inventa datos: lo que no encaja en una convención se emite como párrafo.
 */

export type BloqueSyllabus =
  | { tipo: 'campo'; etiqueta: string; valor: string }
  | { tipo: 'subtitulo'; texto: string }
  | { tipo: 'lista'; items: Array<{ texto: string; anidado: boolean }> }
  | { tipo: 'parrafo'; texto: string };

/** Secciones que el backend emite como prosa libre (`SeccionTextoContenido`). */
const SECCIONES_PROSA = new Set(['II', 'III']);

/** `Etiqueta: valor` — la etiqueta es corta y no arrastra puntuación de frase. */
const CAMPO = /^([^:]{1,40}):[ \t]+(.+)$/;
/** `Etiqueta:` sola, encabezando un grupo de ítems. */
const SUBTITULO = /^([^:]{1,40}):[ \t]*$/;
/** `Unidad 3: Prototipo` abre un bloque, no es un campo. */
const UNIDAD = /^Unidad\s+\d+\s*:/i;

/**
 * Convierte el texto de una sección en bloques renderizables.
 *
 * @param texto    Contenido plano de la sección.
 * @param numeral  Numeral romano; decide si la sección es prosa o estructurada.
 */
export function bloquesDeSeccion(
  texto: string | null | undefined,
  numeral?: string,
): BloqueSyllabus[] {
  const bruto = (texto ?? '').replace(/\r\n/g, '\n');
  if (!bruto.trim()) return [];

  if (numeral && SECCIONES_PROSA.has(numeral)) {
    return bruto
      .split(/\n{2,}/)
      .map((p) => p.trim())
      .filter(Boolean)
      .map((p): BloqueSyllabus => ({ tipo: 'parrafo', texto: p }));
  }

  const bloques: BloqueSyllabus[] = [];
  let lista: Array<{ texto: string; anidado: boolean }> | null = null;

  const cerrarLista = () => {
    if (lista && lista.length > 0) bloques.push({ tipo: 'lista', items: lista });
    lista = null;
  };

  for (const linea of bruto.split('\n')) {
    const contenido = linea.trim();
    if (!contenido) {
      cerrarLista();
      continue;
    }

    if (contenido.startsWith('•')) {
      const item = contenido.replace(/^•\s*/, '').trim();
      if (!item) continue;
      // Dos espacios o más de sangría marcan un ítem dependiente del anterior.
      const anidado = /^\s{2,}/.test(linea);
      if (lista === null) lista = [];
      lista.push({ texto: item, anidado });
      continue;
    }

    cerrarLista();

    if (UNIDAD.test(contenido)) {
      bloques.push({ tipo: 'subtitulo', texto: contenido });
      continue;
    }

    const subtitulo = contenido.match(SUBTITULO);
    if (subtitulo) {
      bloques.push({ tipo: 'subtitulo', texto: subtitulo[1].trim() });
      continue;
    }

    const campo = contenido.match(CAMPO);
    if (campo) {
      bloques.push({ tipo: 'campo', etiqueta: campo[1].trim(), valor: campo[2].trim() });
      continue;
    }

    bloques.push({ tipo: 'parrafo', texto: contenido });
  }

  cerrarLista();

  return bloques;
}

/** Une los contenidos de una sección en un solo texto. */
export function textoDeSeccion(
  contenidos: Array<{ texto_contenido?: string | null }> | undefined,
): string {
  return (contenidos ?? [])
    .map((c) => c.texto_contenido ?? '')
    .filter((t) => t.trim())
    .join('\n\n');
}
