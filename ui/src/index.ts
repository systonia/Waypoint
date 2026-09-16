/**
 * waypoint.js -- Waypoint's client half. One dependency-free script that
 * enhances plain HTML through wp-* attributes; nothing to mount or hydrate.
 * Served by the framework itself (View::waypointJsTag()), which also passes
 * non-default CSRF names as data attributes on the <script> tag.
 */

import { installCsrfFetch, readCsrfToken } from './csrf';
import { registerOnClick, registerOnSubmit } from './directives';
import { delegate, onReady, warn } from './dom';
import { parseFragments, swapInto } from './fragments';
import { dispatchHandler, registerHandler } from './handlers';
import { navigateTo, registerNavigation } from './navigation';

const script = document.currentScript as HTMLScriptElement | null;
const csrfCookie = script?.dataset.csrfCookie ?? 'csrf_token';
const csrfHeader = script?.dataset.csrfHeader ?? 'X-CSRF-Token';
// This script lives under the assets path, so view CSS/JS live next to it.
const assetsPath = script?.src ? new URL(script.src, location.href).pathname.replace(/\/[^/]*$/, '') : '/assets';

installCsrfFetch(csrfCookie, csrfHeader);

/**
 * The public API, also what a plugin script (shipped via a server-side
 * ClientAsset hook, loaded after this file) builds its own wp-* directives on.
 */
export const Waypoint = {
    handlers: { register: registerHandler, dispatch: dispatchHandler },
    navigate: (href: string, target: string) => navigateTo(href, target, assetsPath),
    csrf: { token: () => readCsrfToken(csrfCookie) },

    /** A delegated wp-* directive: `handler` runs for `eventType` on any element carrying `attribute`, now or after a swap. */
    directive: (attribute: string, eventType: string, handler: (el: HTMLElement, event: Event) => void, capture = false): void =>
        delegate(eventType, attribute, handler, capture),

    /** Runs after every partial navigation with `{href, target}`. */
    onNavigated: (listener: (detail: { href: string; target: string }) => void): void => {
        document.addEventListener('wp:navigated', (event) => listener((event as CustomEvent<{ href: string; target: string }>).detail));
    },

    /** Puts an HTML fragment into `[wp-container="target"]` (scripts executed, wp-swap-oob templates routed) and announces it. */
    swap: (target: string, html: string): void => {
        const container = document.querySelector(`[wp-container="${target}"]`);
        if (!container) {
            warn(`No element with wp-container="${target}".`);
            return;
        }
        const { primary, oob } = parseFragments(html);
        swapInto(container, primary);
        for (const [key, fragment] of oob) {
            const other = document.querySelector(`[wp-container="${key}"]`);
            if (other) swapInto(other, fragment);
        }
        document.dispatchEvent(new CustomEvent('wp:navigated', { detail: { href: location.pathname + location.search, target } }));
    },
};

declare global {
    interface Window {
        Waypoint: typeof Waypoint;
    }
}

window.Waypoint = Waypoint;
onReady(() => {
    registerOnClick();
    registerOnSubmit();
    registerNavigation(assetsPath);
});
