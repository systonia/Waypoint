import { delegate, warn } from './dom';
import { applyViewAssets, parseFragments, swapInto } from './fragments';

/**
 * wp-target / wp-container: a link or form that works without JS is enhanced
 * to re-issue the same request with `X-Waypoint-Accept: partial` and swap the
 * fragment into `[wp-container="<key>"]` instead of reloading.
 *
 *   <a href="/products/12" wp-target="main">…</a>
 *   <form method="post" action="/products" wp-target="main">…</form>
 *   <main wp-container="main">…</main>
 *
 * fetch() follows a redirect on its own (a form's success case), so history
 * records response.url, not the requested one. A 401 on an authenticated page
 * is already a server-side redirect to the login page for partial navigations.
 */

interface HistoryState {
    wpTarget?: string;
}

export interface NavigateOptions {
    pushHistory?: boolean;
}

const PARTIAL = { 'X-Waypoint-Accept': 'partial' };

export function registerNavigation(assetsPath: string): void {
    delegate('click', 'wp-target', (el, event) => {
        // closest() also finds a <form wp-target> around a button; those are handled on submit.
        if (!(el instanceof HTMLAnchorElement)) return;
        const href = el.getAttribute('href');
        const key = el.getAttribute('wp-target');
        if (!href || !key) return;
        event.preventDefault();
        void navigateTo(href, key, assetsPath);
    });

    delegate('submit', 'wp-target', (el, event) => {
        const key = el.getAttribute('wp-target');
        if (!(el instanceof HTMLFormElement) || !key) return;
        event.preventDefault();
        void submitForm(el, key, assetsPath);
    });

    window.addEventListener('popstate', (event) => {
        const state = event.state as HistoryState | null;
        if (state?.wpTarget) {
            void navigateTo(location.pathname + location.search, state.wpTarget, assetsPath, { pushHistory: false });
        } else {
            // Back past the first partial navigation, onto the hard-loaded page: a reload is exactly F5.
            location.reload();
        }
    });
}

export function navigateTo(href: string, key: string, assetsPath: string, options: NavigateOptions = {}): Promise<void> {
    return run(key, href, () => fetch(href, { headers: PARTIAL }), assetsPath, options);
}

/** A GET form's fields go into the query string, as the browser's own submit would; anything else is sent as a body. */
function submitForm(form: HTMLFormElement, key: string, assetsPath: string): Promise<void> {
    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    const action = form.getAttribute('action') || location.pathname;

    if (method === 'GET') {
        const url = new URL(action, location.href);
        const params = new URLSearchParams();
        for (const [name, value] of new FormData(form)) {
            if (typeof value === 'string') params.append(name, value);
        }
        url.search = params.toString();
        return navigateTo(url.pathname + url.search, key, assetsPath);
    }
    return run(key, action, () => fetch(action, { method, headers: PARTIAL, body: new FormData(form) }), assetsPath);
}

async function run(
    key: string,
    href: string,
    request: () => Promise<Response>,
    assetsPath: string,
    options: NavigateOptions = {},
): Promise<void> {
    const container = document.querySelector(`[wp-container="${key}"]`);
    if (!container) {
        warn(`No element with wp-container="${key}".`);
        return;
    }

    let response: Response;
    try {
        response = await request();
    } catch (err) {
        warn(`Navigation to ${href} failed:`, err);
        return;
    }
    // An HTML error page or a re-rendered form (422) is content the user should see; anything else isn't.
    if (!response.ok && !(response.headers.get('Content-Type') ?? '').startsWith('text/html')) {
        warn(`Navigation to ${href} returned ${response.status}.`);
        return;
    }

    const { primary, oob } = parseFragments(await response.text());
    applyViewAssets(container, response, assetsPath); // before the swap, so scoped CSS matches on first paint
    swapInto(container, primary);
    for (const [oobKey, fragment] of oob) {
        const target = document.querySelector(`[wp-container="${oobKey}"]`);
        if (target) swapInto(target, fragment);
    }

    if (options.pushHistory !== false) {
        const url = new URL(response.url || href, location.href);
        history.pushState({ wpTarget: key } satisfies HistoryState, '', url.pathname + url.search);
    }
    document.dispatchEvent(new CustomEvent('wp:navigated', { detail: { href, target: key } }));
}
