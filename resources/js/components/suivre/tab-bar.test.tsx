import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

const state = vi.hoisted(() => ({ url: '/insights' }));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ url: state.url, props: {} }),
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
    }) => {
        const resolved = typeof href === 'string' ? href : href.url;

        return (
            <a href={resolved} {...props}>
                {children}
            </a>
        );
    },
}));

import { TabBar } from './tab-bar';

describe('TabBar', () => {
    it('marks the tab matching the current route active', () => {
        state.url = '/insights';
        render(<TabBar />);

        expect(
            screen.getByRole('link', { name: 'Insights' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: 'Calendar' }),
        ).not.toHaveAttribute('aria-current');
        expect(
            screen.getByRole('link', { name: 'Settings' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('keeps Calendar active on a nested month route', () => {
        state.url = '/calendar/2026-07';
        render(<TabBar />);

        expect(
            screen.getByRole('link', { name: 'Calendar' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: 'Insights' }),
        ).not.toHaveAttribute('aria-current');
    });
});
