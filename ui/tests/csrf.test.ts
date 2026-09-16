import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { installCsrfFetch, readCsrfToken } from '../src/csrf';

describe('readCsrfToken', () => {
    it('reads and decodes the named cookie', () => {
        document.cookie = 'other=1';
        document.cookie = 'csrf_token=a%20b';
        expect(readCsrfToken('csrf_token')).toBe('a b');
    });

    it('is empty without the cookie', () => {
        expect(readCsrfToken('nope')).toBe('');
    });
});

describe('installCsrfFetch', () => {
    const original = vi.fn<(input: RequestInfo | URL, init?: RequestInit) => Promise<Response>>(async () => new Response('ok'));

    beforeEach(() => {
        window.fetch = original as unknown as typeof fetch;
        document.cookie = 'csrf_token=tok';
        installCsrfFetch('csrf_token', 'X-CSRF-Token');
    });
    afterEach(() => original.mockClear());

    function sentHeaders(): Headers {
        const init = original.mock.calls[0]?.[1] as RequestInit | undefined;
        return new Headers(init?.headers);
    }

    it('adds the header to a same-origin POST', async () => {
        await fetch('/x', { method: 'post' });
        expect(sentHeaders().get('X-CSRF-Token')).toBe('tok');
    });

    it('leaves a GET untouched', async () => {
        await fetch('/x');
        expect(original.mock.calls[0]?.[1]).toEqual({});
    });

    it('leaves a cross-origin POST untouched', async () => {
        await fetch('https://elsewhere.test/x', { method: 'POST' });
        expect(sentHeaders().has('X-CSRF-Token')).toBe(false);
    });

    it('never overwrites a header the caller set', async () => {
        await fetch('/x', { method: 'POST', headers: { 'X-CSRF-Token': 'mine' } });
        expect(sentHeaders().get('X-CSRF-Token')).toBe('mine');
    });
});
