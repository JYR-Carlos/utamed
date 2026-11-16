/**
 * Utilidades para manejo reactivo y robusto de tokens CSRF en el cliente SPA.
 */

export function getXsrfCookieToken(): string {
  if (typeof document === 'undefined') return '';
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
  return match && match[1] ? decodeURIComponent(match[1]) : '';
}

export function getCsrfToken(): string {
  if (typeof document === 'undefined') return '';

  const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
  if (metaToken) {
    return metaToken;
  }

  if (typeof window !== 'undefined' && window.axios?.defaults?.headers?.common?.['X-CSRF-TOKEN']) {
    return String(window.axios.defaults.headers.common['X-CSRF-TOKEN']);
  }

  return getXsrfCookieToken();
}

export function csrfHeaders(customHeaders: HeadersInit = {}): Record<string, string> {
  const headers: Record<string, string> = {
    'X-Requested-With': 'XMLHttpRequest',
  };

  const csrfToken = getCsrfToken();
  if (csrfToken) {
    headers['X-CSRF-TOKEN'] = csrfToken;
  }

  const xsrfCookie = getXsrfCookieToken();
  if (xsrfCookie && !headers['X-XSRF-TOKEN']) {
    headers['X-XSRF-TOKEN'] = xsrfCookie;
  }

  if (customHeaders instanceof Headers) {
    customHeaders.forEach((value, key) => {
      headers[key] = value;
    });
  } else if (Array.isArray(customHeaders)) {
    customHeaders.forEach(([key, value]) => {
      headers[key] = value;
    });
  } else if (customHeaders && typeof customHeaders === 'object') {
    Object.assign(headers, customHeaders);
  }

  return headers;
}

export async function csrfFetch(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const mergedHeaders = csrfHeaders(init?.headers);
  return fetch(input, {
    ...init,
    credentials: init?.credentials ?? 'same-origin',
    headers: mergedHeaders,
  });
}
