import { delegate } from './dom';
import { serializeForm } from './form';
import { dispatchHandler } from './handlers';

/** wp-onclick="name": calls the handler with the element's dataset. */
export function registerOnClick(): void {
    delegate('click', 'wp-onclick', (el, event) => {
        const name = el.getAttribute('wp-onclick');
        if (name) dispatchHandler(name, el.dataset, event);
    });
}

/** wp-onsubmit="name": calls the handler with the serialized form; the native submit is prevented unless wp-default="true". */
export function registerOnSubmit(): void {
    delegate('submit', 'wp-onsubmit', (form, event) => {
        const name = form.getAttribute('wp-onsubmit');
        if (!name || !(form instanceof HTMLFormElement)) return;
        if (form.getAttribute('wp-default') !== 'true') event.preventDefault();
        dispatchHandler(name, serializeForm(form), event);
    });
}
