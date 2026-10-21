import { Monitor, Moon, Sun } from 'lucide-svelte';

export type Appearance = 'light' | 'dark' | 'system';

/** Opciones del selector de tema (menú del usuario y Ajustes › Apariencia). */
export const OPCIONES_APARIENCIA = [
    { value: 'light', label: 'Claro', Icon: Sun },
    { value: 'dark', label: 'Oscuro', Icon: Moon },
    { value: 'system', label: 'Sistema', Icon: Monitor },
] as const;

/**
 * Tema por defecto mientras la persona no elija otro. El modo oscuro es
 * opcional: con 'system' se activaría solo en quien tenga el sistema en
 * oscuro.
 */
const PREDETERMINADO: Appearance = 'light';
const VALORES: Appearance[] = ['light', 'dark', 'system'];

const enNavegador = typeof window !== 'undefined' && typeof document !== 'undefined';
const consultaOscuro = () => window.matchMedia('(prefers-color-scheme: dark)');

function leer(): Appearance {
    if (!enNavegador) return PREDETERMINADO;
    // La cookie primero: es la que usa el servidor para el primer pintado, y si
    // aquí ganaba localStorage, una diferencia entre ambos deshacía ese tema.
    let guardado: string | null = document.cookie.match(/(?:^|; )appearance=([^;]*)/)?.[1] ?? null;
    if (guardado === null) {
        try {
            guardado = localStorage.getItem('appearance');
        } catch {
            // almacenamiento bloqueado: queda el predeterminado
        }
    }
    return VALORES.includes(guardado as Appearance) ? (guardado as Appearance) : PREDETERMINADO;
}

let appearance = $state<Appearance>(leer());

export function updateTheme(value: Appearance) {
    if (!enNavegador) return;
    const oscuro = value === 'dark' || (value === 'system' && consultaOscuro().matches);
    document.documentElement.classList.toggle('dark', oscuro);
}

/**
 * Se llama una vez al arrancar la app. app.blade.php ya puso la clase antes
 * del primer pintado (con la cookie); esto la mantiene al día si el sistema
 * cambia de claro a oscuro con la preferencia en 'system'.
 */
export function initializeTheme() {
    if (!enNavegador) return;
    updateTheme(appearance);
    consultaOscuro().addEventListener('change', () => updateTheme(appearance));
}

export function useAppearance() {
    function updateAppearance(value: Appearance) {
        appearance = value;
        try {
            localStorage.setItem('appearance', value);
        } catch {
            // sin almacenamiento local basta la cookie
        }
        // La cookie la lee el servidor (HandleAppearance) para pintar el tema
        // correcto desde el primer byte y evitar el destello claro.
        document.cookie = `appearance=${value};path=/;max-age=31536000;SameSite=Lax`;
        updateTheme(value);
    }

    return {
        get appearance() {
            return appearance;
        },
        updateAppearance,
    };
}
