import { PageProps as InertiaPageProps } from '@inertiajs/core';
import { AxiosInstance } from 'axios';
import { PageProps as AppPageProps } from './';

declare global {
    interface Window {
        axios: AxiosInstance;
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps { }
}

declare module 'svelte/elements' {
    interface HTMLAttributes<T> {
        /** Marca un contenedor con scroll propio para que Inertia guarde y restaure su posición. */
        'scroll-region'?: boolean | '' | null;
    }
}
