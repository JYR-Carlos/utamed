/**
 * Composable para gestionar filtros y paginación basados en URL params.
 * Reutilizable en múltiples páginas de administración.
 */
import { page } from '@inertiajs/svelte';
import { router } from '@inertiajs/svelte';
import { derived, get } from 'svelte/store';
import { QUERY_PARAMS } from '@/constants/admin';

interface FilterOptions {
    pathname: string;
    defaultPerPage?: number;
}

export function useFilteredList(options: FilterOptions) {
    const { pathname, defaultPerPage = 15 } = options;

    /**
     * Obtiene los parámetros de URL actuales
     */
    function getUrlParams(): URLSearchParams {
        const currentPage = get(page);
        return new URL(currentPage.url, window.location.origin).searchParams;
    }

    /**
     * Navega con parámetros, preservando los existentes
     */
    function navigate(extra: Record<string, string | number | undefined>, resetPage = true) {
        const params: Record<string, string> = {};

        // Copia parámetros existentes
        getUrlParams().forEach((v, k) => {
            params[k] = v;
        });

        // Aplica cambios
        for (const [k, v] of Object.entries(extra)) {
            if (v === undefined || v === '') {
                delete params[k];
            } else {
                params[k] = String(v);
            }
        }

        // Reset a página 1 en cambios de filtro
        if (resetPage) {
            delete params[QUERY_PARAMS.PAGE];
        }

        router.get(pathname, params, { preserveState: true, preserveScroll: true });
    }

    /**
     * Obtiene un parámetro específico con valor por defecto
     */
    function getParam(key: string, defaultValue: any = undefined) {
        const val = getUrlParams().get(key);
        return val ?? defaultValue;
    }

    /**
     * Valores de filtros derivados
     */
    const searchTerm = derived(page, ($page) => getParam(QUERY_PARAMS.SEARCH, ''));
    const currentPage = derived(page, ($page) => Number(getParam(QUERY_PARAMS.PAGE, '1')));
    const perPage = derived(page, ($page) => Number(getParam(QUERY_PARAMS.PER_PAGE, String(defaultPerPage))));
    const status = derived(page, ($page) => getParam(QUERY_PARAMS.STATUS, 'active'));

    /**
     * Helpers para acciones comunes
     */
    function setSearch(term: string) {
        navigate({ [QUERY_PARAMS.SEARCH]: term || undefined });
    }

    function setStatus(s: string) {
        navigate({ [QUERY_PARAMS.STATUS]: s });
    }

    function setPerPage(v: number) {
        navigate({ [QUERY_PARAMS.PER_PAGE]: String(v) });
    }

    function goToPage(p: number) {
        navigate({ [QUERY_PARAMS.PAGE]: String(p) }, false);
    }

    return {
        getParam,
        getUrlParams,
        navigate,
        searchTerm,
        currentPage,
        perPage,
        status,
        setSearch,
        setStatus,
        setPerPage,
        goToPage,
    };
}
