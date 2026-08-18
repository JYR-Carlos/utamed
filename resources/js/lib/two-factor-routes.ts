/**
 * URLs de las rutas 2FA de Fortify — sustituto del módulo generado `@/routes/two-factor`.
 *
 * El 2FA está retirado: `Features::twoFactorAuthentication()` quedó comentada en
 * config/fortify.php, así que Fortify ya no registra sus ~10 rutas y Wayfinder
 * (que genera `resources/js/routes/`, directorio ignorado por git) dejó de emitir
 * `@/routes/two-factor`. Las páginas y componentes de 2FA se conservan como código
 * muerto para cuando se retome la feature; este archivo les devuelve las mismas
 * exportaciones que consumían, con las URL literales declaradas en
 * vendor/laravel/fortify/routes/routes.php.
 *
 * NINGUNA de estas rutas está registrada hoy: invocarlas responde 404.
 *
 * Al reimplementar el 2FA (trait TwoFactorAuthenticatable en Usuario + migración de
 * two_factor_secret / two_factor_recovery_codes): descomentar la feature, borrar este
 * archivo y devolver los imports a '@/routes/two-factor'.
 */

type UrlDefinition = { url: string; method: 'get' };
type FormDefinition = { action: string; method: 'post' };

/** Endpoint GET consumido vía fetch() en lib/two-factor-auth.svelte.ts */
const getEndpoint = (url: string) => (): UrlDefinition => ({ url, method: 'get' });

/** Endpoint de escritura consumido vía `<Form {...x.form()}>` de Inertia */
function formEndpoint(url: string, verb: 'POST' | 'DELETE' = 'POST') {
    // Wayfinder emite los verbos distintos de POST como method spoofing (?_method=…)
    const action = verb === 'POST' ? url : `${url}?_method=${verb}`;
    return { form: (): FormDefinition => ({ action, method: 'post' }) };
}

// ─── two-factor.qr-code / secret-key / recovery-codes ────────────────────────
export const qrCode = getEndpoint('/user/two-factor-qr-code');
export const secretKey = getEndpoint('/user/two-factor-secret-key');
export const recoveryCodes = getEndpoint('/user/two-factor-recovery-codes');

// ─── two-factor.enable / disable / confirm / regenerate-recovery-codes ───────
export const enable = formEndpoint('/user/two-factor-authentication');
export const disable = formEndpoint('/user/two-factor-authentication', 'DELETE');
export const confirm = formEndpoint('/user/confirmed-two-factor-authentication');
export const regenerateRecoveryCodes = formEndpoint('/user/two-factor-recovery-codes');

// ─── two-factor.login.store (desafío tras el login) ──────────────────────────
export const login = { store: formEndpoint('/two-factor-challenge') };
