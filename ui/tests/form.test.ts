import { describe, expect, it } from 'vitest';
import { serializeForm } from '../src/form';

function form(html: string): HTMLFormElement {
    document.body.innerHTML = `<form>${html}</form>`;
    return document.querySelector('form') as HTMLFormElement;
}

describe('serializeForm', () => {
    it('maps plain fields', () => {
        expect(serializeForm(form('<input name="a" value="1"><input name="b" value="2">'))).toEqual({ a: '1', b: '2' });
    });

    it('appends items[] into a list', () => {
        expect(serializeForm(form('<input name="items[]" value="x"><input name="items[]" value="y">'))).toEqual({ items: ['x', 'y'] });
    });

    it('nests address[city]', () => {
        expect(serializeForm(form('<input name="address[city]" value="Bonn"><input name="address[zip]" value="53111">'))).toEqual({
            address: { city: 'Bonn', zip: '53111' },
        });
    });

    it('nests two levels deep', () => {
        expect(serializeForm(form('<input name="a[b][c]" value="v">'))).toEqual({ a: { b: { c: 'v' } } });
    });
});
