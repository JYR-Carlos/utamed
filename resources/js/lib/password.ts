/**
 * Requisitos de la contraseña, en espejo de la política del backend
 * (AppServiceProvider: Password::min(8)->letters()->numbers()->symbols()).
 *
 * Las expresiones son las mismas que usa Laravel (Illuminate\Validation\Rules\
 * Password): letra = \p{L}, número = \p{N}, signo = \p{Z}|\p{S}|\p{P}. El
 * backend recorta los espacios de los extremos antes de validar, así que aquí
 * también.
 */
export interface RequisitoPassword {
  id: 'largo' | 'letra' | 'numero' | 'signo';
  texto: string;
  cumple: (password: string) => boolean;
}

export const LARGO_MINIMO_PASSWORD = 8;

export const REQUISITOS_PASSWORD: RequisitoPassword[] = [
  { id: 'largo', texto: `Al menos ${LARGO_MINIMO_PASSWORD} caracteres`, cumple: (p) => [...p.trim()].length >= LARGO_MINIMO_PASSWORD },
  { id: 'letra', texto: 'Una letra', cumple: (p) => /\p{L}/u.test(p.trim()) },
  { id: 'numero', texto: 'Un número', cumple: (p) => /\p{N}/u.test(p.trim()) },
  { id: 'signo', texto: 'Un signo (por ejemplo ! ? # . -)', cumple: (p) => /[\p{Z}\p{S}\p{P}]/u.test(p.trim()) },
];

/** Resumen de la política para textos de ayuda. */
export const DESCRIPCION_POLITICA_PASSWORD = `Al menos ${LARGO_MINIMO_PASSWORD} caracteres, con letras, números y algún signo.`;

export function passwordCumplePolitica(password: string): boolean {
  return REQUISITOS_PASSWORD.every((r) => r.cumple(password));
}
