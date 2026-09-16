/** Small DOM helpers every other module builds on. */

/**
 * One delegated listener on `document` for every element carrying `attribute`,
 * present now or inserted later -- nothing is ever bound per element, so a swap
 * never needs re-binding. `capture` is for events that don't bubble.
 */
export function delegate(
    eventType: string,
    attribute: string,
    handler: (el: HTMLElement, event: Event) => void,
    capture = false,
): void {
    document.addEventListener(
        eventType,
        (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const el = target.closest<HTMLElement>(`[${attribute}]`);
            if (el) handler(el, event);
        },
        capture,
    );
}

/** Runs `fn` once the DOM is parsed (immediately if it already is). */
export function onReady(fn: () => void): void {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
}

export function warn(message: string, ...rest: unknown[]): void {
    console.warn(`[waypoint] ${message}`, ...rest);
}
