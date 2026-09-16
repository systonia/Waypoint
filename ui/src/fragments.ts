/**
 * The partial-response wire format: plain HTML for the primary container, plus
 * any number of `<template wp-swap-oob="containerKey">...</template>` blocks
 * that go to other wp-container elements in the same round trip.
 */

export interface Fragments {
    primary: DocumentFragment;
    oob: Map<string, DocumentFragment>;
}

/** Parsed through an inert <template>: no scripts run, nothing loads, nested templates come out as real elements. */
export function parseFragments(html: string): Fragments {
    const wrapper = document.createElement('template');
    wrapper.innerHTML = html;

    const oob = new Map<string, DocumentFragment>();
    for (const el of Array.from(wrapper.content.querySelectorAll<HTMLTemplateElement>('template[wp-swap-oob]'))) {
        const key = el.getAttribute('wp-swap-oob');
        if (key) oob.set(key, el.content);
        el.remove();
    }
    return { primary: wrapper.content, oob };
}

/** Replaces `target`'s children and re-creates each <script> so the browser actually executes it (parsed scripts are inert). */
export function swapInto(target: Element, fragment: DocumentFragment): void {
    target.replaceChildren(fragment);
    for (const old of Array.from(target.querySelectorAll('script'))) {
        const fresh = document.createElement('script');
        for (const attr of Array.from(old.attributes)) fresh.setAttribute(attr.name, attr.value);
        fresh.textContent = old.textContent;
        old.replaceWith(fresh);
    }
}

/**
 * Applies the X-Waypoint-View-Name/-Css/-Js headers of a View response: data-view
 * on the container (what the scoped CSS matches on) and the view's own CSS/JS,
 * injected once per filename (data-wp-asset also marks server-rendered tags).
 */
export function applyViewAssets(container: Element, response: Response, assetsPath: string): void {
    const viewName = response.headers.get('X-Waypoint-View-Name');
    if (viewName) container.setAttribute('data-view', viewName);
    else container.removeAttribute('data-view');

    const css = response.headers.get('X-Waypoint-View-Css');
    if (css && !document.querySelector(`link[data-wp-asset="${css}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `${assetsPath}/${css}`;
        link.setAttribute('data-wp-asset', css);
        document.head.appendChild(link);
    }

    const js = response.headers.get('X-Waypoint-View-Js');
    if (js && !document.querySelector(`script[data-wp-asset="${js}"]`)) {
        const script = document.createElement('script');
        script.src = `${assetsPath}/${js}`;
        script.setAttribute('data-wp-asset', js);
        document.head.appendChild(script);
    }
}
