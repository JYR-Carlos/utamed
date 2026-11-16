import { createInertiaApp, router, type ResolvedComponent } from '@inertiajs/svelte';
import { hydrate, mount } from 'svelte';
import '../css/app.css';
import './bootstrap';
import { initializeTheme } from './hooks/useAppearance.svelte';

initializeTheme();

router.on('navigate', (event) => {
    const token = (event.detail.page.props as Record<string, any>)?.csrf_token;
    if (typeof token === 'string' && token) {
        const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', token);
        }
        if (window.axios?.defaults?.headers?.common) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    }
});

createInertiaApp({
    resolve: (name: string) => {
        const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.svelte', { eager: false });
        return pages[`./pages/${name}.svelte`]?.();
    },
    setup({ el, App, props }) {
        if (el && el.dataset.serverRendered === 'true') {
            hydrate(App, { target: el, props });
        } else if (el) {
            mount(App, { target: el, props });
        }
    },
});

