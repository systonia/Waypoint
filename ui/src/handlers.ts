/**
 * Named handlers for wp-onclick / wp-onsubmit: the attribute value is a name,
 * looked up here or as a same-named global function -- never eval'd, so a
 * strict CSP without unsafe-eval is fine.
 */

export type Handler = (data: unknown, event: Event) => void;

const registry = new Map<string, Handler>();

export function registerHandler(name: string, handler: Handler): void {
    registry.set(name, handler);
}

export function dispatchHandler(name: string, data: unknown, event: Event): void {
    const handler = registry.get(name) ?? (window as unknown as Record<string, unknown>)[name];
    if (typeof handler !== 'function') {
        console.warn(`[waypoint] No handler named "${name}" (registered or global).`);
        return;
    }
    (handler as Handler)(data, event);
}
