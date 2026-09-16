import { beforeEach, describe, expect, it } from 'vitest';
import { applyViewAssets, parseFragments, swapInto } from '../src/fragments';

beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '<main wp-container="main"><p>old</p></main><aside wp-container="side"></aside>';
});

describe('parseFragments', () => {
    it('separates the primary fragment from out-of-band templates', () => {
        const { primary, oob } = parseFragments('<p>new</p><template wp-swap-oob="side"><b>badge</b></template>');
        expect(primary.querySelector('p')?.textContent).toBe('new');
        expect(primary.querySelector('template')).toBeNull();
        expect(oob.get('side')?.querySelector('b')?.textContent).toBe('badge');
    });
});

describe('swapInto', () => {
    it('replaces the children and re-creates each script as a fresh node (parsed scripts never execute)', () => {
        const main = document.querySelector('[wp-container="main"]') as Element;
        const { primary } = parseFragments('<p>new</p><script data-x="1">window.__ran = 1;</script>');
        const parsedScript = primary.querySelector('script');

        swapInto(main, primary);

        const script = main.querySelector('script');
        expect(main.innerHTML).toContain('<p>new</p>');
        expect(script).not.toBe(parsedScript);
        expect(script?.getAttribute('data-x')).toBe('1');
        expect(script?.textContent).toBe('window.__ran = 1;');
    });
});

describe('applyViewAssets', () => {
    const response = (headers: Record<string, string>) => new Response('', { headers });

    it('sets data-view and injects css/js once under the assets path', () => {
        const main = document.querySelector('[wp-container="main"]') as Element;
        applyViewAssets(main, response({ 'X-Waypoint-View-Name': 'Admin/Users', 'X-Waypoint-View-Css': 'a.css', 'X-Waypoint-View-Js': 'b.js' }), '/static');
        applyViewAssets(main, response({ 'X-Waypoint-View-Name': 'Admin/Users', 'X-Waypoint-View-Css': 'a.css' }), '/static');

        expect(main.getAttribute('data-view')).toBe('Admin/Users');
        expect(document.head.querySelectorAll('link[data-wp-asset="a.css"]')).toHaveLength(1);
        expect(document.head.querySelector('link')?.getAttribute('href')).toBe('/static/a.css');
        expect(document.head.querySelector('script')?.getAttribute('src')).toBe('/static/b.js');
    });

    it('clears data-view for a view without assets', () => {
        const main = document.querySelector('[wp-container="main"]') as Element;
        main.setAttribute('data-view', 'Old');
        applyViewAssets(main, response({}), '/assets');
        expect(main.hasAttribute('data-view')).toBe(false);
    });
});
