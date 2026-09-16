import { describe, expect, it, vi } from 'vitest';
import { dispatchHandler, registerHandler } from '../src/handlers';

describe('dispatchHandler', () => {
    it('prefers a registered handler', () => {
        const fn = vi.fn();
        registerHandler('save', fn);
        dispatchHandler('save', { a: 1 }, new Event('click'));
        expect(fn).toHaveBeenCalledWith({ a: 1 }, expect.any(Event));
    });

    it('falls back to a same-named global function', () => {
        const fn = vi.fn();
        (window as unknown as Record<string, unknown>).globalOne = fn;
        dispatchHandler('globalOne', 'd', new Event('click'));
        expect(fn).toHaveBeenCalledOnce();
    });

    it('warns instead of throwing for an unknown name', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        dispatchHandler('missing', null, new Event('click'));
        expect(warn).toHaveBeenCalledOnce();
        warn.mockRestore();
    });
});
