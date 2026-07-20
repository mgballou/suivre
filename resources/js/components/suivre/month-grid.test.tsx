import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
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

import type { IsoDate } from '@/types';
import { MonthGrid, type CalendarDay, type MonthGridProps } from './month-grid';

function daysOfJuly(overrides: CalendarDay[] = []): CalendarDay[] {
    return Array.from({ length: 31 }, (_, index) => {
        // Zero-padding widens the type back to `string`; re-assert the format.
        const date = `2026-07-${String(index + 1).padStart(2, '0')}` as IsoDate;
        const override = overrides.find((day) => day.date === date);

        return {
            date,
            level: 0,
            hasCheckin: false,
            isToday: false,
            ...override,
        } satisfies CalendarDay;
    });
}

function props(overrides: Partial<MonthGridProps> = {}): MonthGridProps {
    return {
        month: '2026-07',
        label: 'July 2026',
        previousMonth: '2026-06',
        nextMonth: '2026-08',
        leadingBlanks: 2,
        days: daysOfJuly(),
        ...overrides,
    };
}

describe('MonthGrid', () => {
    it('renders every day of the month under a Monday-first header', () => {
        render(<MonthGrid {...props()} />);

        expect(screen.getByRole('heading', { name: 'July 2026' })).toBeInTheDocument();
        expect(screen.getAllByRole('link')).toHaveLength(33); // 31 days + 2 nav
        expect(screen.getByText('Mon')).toBeInTheDocument();
        expect(screen.getByText('Sun')).toBeInTheDocument();
    });

    it('navigates to the neighbouring months', () => {
        render(<MonthGrid {...props()} />);

        expect(
            screen.getByRole('link', { name: 'Previous month' }),
        ).toHaveAttribute('href', '/calendar/2026-06');
        expect(screen.getByRole('link', { name: 'Next month' })).toHaveAttribute(
            'href',
            '/calendar/2026-08',
        );
    });

    it('links each day to its day view', () => {
        render(<MonthGrid {...props()} />);

        expect(
            screen.getByRole('link', { name: /^2026-07-15/ }),
        ).toHaveAttribute('href', '/day/2026-07-15');
    });

    it('pads the grid so the 1st falls on its weekday', () => {
        const { container } = render(<MonthGrid {...props({ leadingBlanks: 4 })} />);
        const grid = container.querySelector('[data-direction]');

        expect(grid?.querySelectorAll('span[aria-hidden]')).toHaveLength(4);
    });

    it('pans forward when the month advances and back when it retreats', () => {
        const { container, rerender } = render(<MonthGrid {...props()} />);

        expect(container.querySelector('[data-direction="none"]')).not.toBeNull();

        rerender(
            <MonthGrid
                {...props({ month: '2026-08', label: 'August 2026', days: [] })}
            />,
        );
        expect(
            container.querySelector('[data-direction="forward"]'),
        ).not.toBeNull();

        rerender(
            <MonthGrid
                {...props({ month: '2026-07', label: 'July 2026', days: [] })}
            />,
        );
        expect(container.querySelector('[data-direction="back"]')).not.toBeNull();
    });

    it('marks a day that has a check-in', () => {
        render(
            <MonthGrid
                {...props({
                    days: daysOfJuly([
                        {
                            date: '2026-07-09',
                            level: 1,
                            hasCheckin: true,
                            isToday: false,
                        },
                    ]),
                })}
            />,
        );

        const marked = screen.getByLabelText(
            '2026-07-09, intensity 1 of 5, checked in',
        );

        expect(marked).toHaveAttribute('data-checkin');
        expect(marked).toHaveClass('bg-intensity-1');
    });
});
