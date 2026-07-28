import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        svelte(),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    build: {
        chunkSizeWarningLimit: 800, // Aumenta el límite temporalmente
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // 1. Núcleo del framework (Rara vez cambia, caché a largo plazo)
                    if (id.includes('node_modules')) {
                        if (id.includes('@inertiajs')) return 'inertia';
                        if (id.includes('tailwindcss') || id.includes('@tailwindcss')) return 'tailwind';
                        return 'vendor';
                    }
                    // Modular chunks
                    if (id.includes('modules/resources')) return 'resources';
                    if (id.includes('modules/admin')) return 'admin';
                    if (id.includes('modules/docente')) return 'docente';
                    if (id.includes('modules/estudiante')) return 'estudiante';
                    if (id.includes('components')) return 'components';
                },
            },
        },
    },
});
