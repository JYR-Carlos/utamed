import '@inertiajs/svelte';
import { type Docente } from './admin.types';

export interface SharedAuth {
    user: User;
    roles: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: any;
    isActive?: boolean;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: SharedAuth;
    [key: string]: unknown;
};

export interface User {
    id_usuario: number;
    username: string;
    email: string;
    nombre1: string;
    nombre2?: string;
    apellido1: string;
    apellido2?: string;
    rut: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    docente?: Docente;
}

export type BreadcrumbItemType = BreadcrumbItem;
