/**
 * waypoint.js: Waypoint's client half. One dependency-free script that
 * enhances plain HTML through wp-* attributes; nothing to mount or hydrate.
 * Served by the framework itself (View::waypointJsTag()), which also passes
 * non-default CSRF names as data attributes on the <script> tag.
 */

import { installCsrfFetch, readCsrfToken } from './csrf';
import { registerOnClick, registerOnSubmit } from './directives';
import { delegate, onReady } from './dom';
import { dispatchHandler, registerHandler } from './handlers';

const script = document.currentScript as HTMLScriptElement | null;
const csrfCookie = script?.dataset.csrfCookie ?? 'csrf_token';
const csrfHeader = script?.dataset.csrfHeader ?? 'X-CSRF-Token';

installCsrfFetch(csrfCookie, csrfHeader);

/**
 * The public API, also what a plugin script (shipped via a server-side
 * ClientAsset hook, loaded after this file) builds its own wp-* directives on.
 */
export const Waypoint = {
    handlers: { register: registerHandler, dispatch: dispatchHandler },
    csrf: { token: () => readCsrfToken(csrfCookie) },

    /** A delegated wp-* directive: `handler` runs for `eventType` on any element carrying `attribute`, now or added later. */
    directive: (attribute: string, eventType: string, handler: (el: HTMLElement, event: Event) => void, capture = false): void =>
        delegate(eventType, attribute, handler, capture),
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
});
