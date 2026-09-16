import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { navigateTo, registerNavigation } from '../src/navigation';

function htmlResponse(body: string, init: ResponseInit & { url?: string } = {}): Response {
    const response = new Response(body, { status: init.status ?? 200, headers: { 'Content-Type': 'text/html', ...(init.headers as Record<string, string>) } });
    Object.defineProperty(response, 'url', { value: init.url ?? '' });
    return response;
}

describe('navigation', () => {
    const fetchMock = vi.fn();
    const pushState = vi.fn();

    beforeAll(() => registerNavigation('/assets'));

    beforeEach(() => {
        fetchMock.mockReset();
        pushState.mockReset();
        window.fetch = fetchMock as unknown as typeof fetch;
        history.pushState = pushState;
        document.body.innerHTML = '<main wp-container="main"><p>old</p></main>';
    });

    it('swaps the fragment in, records the final URL and announces the navigation', async () => {
        fetchMock.mockImplementation(async () => htmlResponse('<p>dash</p>', { url: 'http://localhost/dashboard?x=1' }));
        const navigated = vi.fn();
        document.addEventListener('wp:navigated', navigated);

        await navigateTo('/login', 'main', '/assets');

        expect(fetchMock).toHaveBeenCalledWith('/login', { headers: { 'X-Waypoint-Accept': 'partial' } });
        expect(document.querySelector('main')?.innerHTML).toBe('<p>dash</p>');
        expect(pushState).toHaveBeenCalledWith({ wpTarget: 'main' }, '', '/dashboard?x=1');
        expect(navigated).toHaveBeenCalledOnce();
    });

    it('shows an HTML error page but drops a non-HTML error', async () => {
        fetchMock.mockResolvedValueOnce(htmlResponse('<p>not found</p>', { status: 404 }));
        await navigateTo('/nope', 'main', '/assets');
        expect(document.querySelector('main')?.textContent).toBe('not found');

        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        fetchMock.mockResolvedValueOnce(new Response('{}', { status: 500, headers: { 'Content-Type': 'application/problem+json' } }));
        await navigateTo('/boom', 'main', '/assets');
        expect(document.querySelector('main')?.textContent).toBe('not found');
        expect(warn).toHaveBeenCalledOnce();
        warn.mockRestore();
    });

    it('intercepts a wp-target link click but not a plain link', async () => {
        document.body.innerHTML += '<a id="wp" href="/a" wp-target="main">a</a><a id="plain" href="/b">b</a>';
        fetchMock.mockImplementation(async () => htmlResponse('<p>a</p>'));

        const wpEvent = new MouseEvent('click', { bubbles: true, cancelable: true });
        document.getElementById('wp')?.dispatchEvent(wpEvent);
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
        expect(wpEvent.defaultPrevented).toBe(true);

        const plainEvent = new MouseEvent('click', { bubbles: true, cancelable: true });
        document.getElementById('plain')?.dispatchEvent(plainEvent);
        expect(plainEvent.defaultPrevented).toBe(false);
        expect(fetchMock).toHaveBeenCalledOnce();
    });

    it('submits a POST form with FormData and the partial header', async () => {
        document.body.innerHTML += '<form id="f" method="post" action="/login" wp-target="main"><input name="email" value="e"></form>';
        fetchMock.mockImplementation(async () => htmlResponse('<p>ok</p>'));

        document.getElementById('f')?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());

        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/login');
        expect(init.method).toBe('POST');
        expect(init.body).toBeInstanceOf(FormData);
        expect((init.body as FormData).get('email')).toBe('e');
    });

    it('turns a GET form into a query string navigation', async () => {
        document.body.innerHTML += '<form id="g" method="get" action="/search" wp-target="main"><input name="q" value="x y"></form>';
        fetchMock.mockImplementation(async () => htmlResponse('<p>r</p>'));

        document.getElementById('g')?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());

        expect(fetchMock.mock.calls[0]?.[0]).toBe('/search?q=x+y');
    });
});
