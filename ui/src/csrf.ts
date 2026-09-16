/**
 * Double-submit CSRF for fetch(): the server issues the token as a readable
 * cookie (see Waypoint\Csrf); this repeats it as a header on every same-origin
 * POST/PUT/PATCH/DELETE. No cookie (CSRF not configured server-side) or a
 * header already set by the caller leaves the request untouched.
 */

const STATE_CHANGING = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

/** The current token straight from the cookie, '' if there is none. */
export function readCsrfToken(cookieName: string): string {
    const escaped = cookieName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${escaped}=([^;]*)`));
    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

export function installCsrfFetch(cookieName: string, headerName: string): void {
    const original = window.fetch.bind(window);

    window.fetch = (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const method = (init.method ?? (input instanceof Request ? input.method : 'GET')).toUpperCase();
        const token = STATE_CHANGING.has(method) && isSameOrigin(input) ? readCsrfToken(cookieName) : '';
        if (!token) return original(input, init);

        const headers = new Headers(init.headers ?? (input instanceof Request ? input.headers : undefined));
        if (!headers.has(headerName)) headers.set(headerName, token);
        return original(input, { ...init, headers });
    };
}

function isSameOrigin(input: RequestInfo | URL): boolean {
    try {
        return new URL(input instanceof Request ? input.url : String(input), location.href).origin === location.origin;
    } catch {
        return false;
    }
}
