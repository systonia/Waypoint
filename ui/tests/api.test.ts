import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadApi() {
    vi.resetModules();
    const mod = await import('../src/index');
    return mod.Waypoint;
}

describe('Waypoint global API for plugin scripts', () => {
    beforeEach(() => {
        document.body.innerHTML = '<main wp-container="main"><p>old</p></main><aside wp-container="side"></aside>';
    });

    it('directive() delegates to elements added later', async () => {
        const Waypoint = await loadApi();
        const handler = vi.fn();
        Waypoint.directive('wp-sort', 'click', handler);
        document.body.innerHTML += '<a id="s" wp-sort="name">sort</a>';

        document.getElementById('s')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(handler).toHaveBeenCalledOnce();
        expect((handler.mock.calls[0]?.[0] as HTMLElement).getAttribute('wp-sort')).toBe('name');
    });

    it('swap() replaces the container, routes out-of-band fragments and announces the navigation', async () => {
        const Waypoint = await loadApi();
        const seen = vi.fn();
        Waypoint.onNavigated(seen);

        Waypoint.swap('main', '<p>new</p><template wp-swap-oob="side"><b>badge</b></template>');

        expect(document.querySelector('main')?.innerHTML).toBe('<p>new</p>');
        expect(document.querySelector('aside')?.innerHTML).toBe('<b>badge</b>');
        expect(seen).toHaveBeenCalledWith(expect.objectContaining({ target: 'main' }));
    });

    it('swap() warns for an unknown container instead of throwing', async () => {
        const Waypoint = await loadApi();
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        Waypoint.swap('nope', '<p>x</p>');

        expect(warn).toHaveBeenCalledOnce();
        warn.mockRestore();
    });
});
