import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DayCell, type IntensityLevel } from './day-cell';

describe('DayCell', () => {
    it.each([0, 1, 2, 3, 4, 5] as IntensityLevel[])(
        'applies the ramp step for level %i',
        (level) => {
            const { container } = render(
                <DayCell date="2026-07-15" level={level} />,
            );
            const cell = container.firstElementChild;

            expect(cell).toHaveClass(`bg-intensity-${level}`);
        },
    );

    it('renders the day number from the date', () => {
        render(<DayCell date="2026-07-15" level={2} />);

        expect(screen.getByText('15')).toBeInTheDocument();
    });

    it('clears a 44px hit area', () => {
        const { container } = render(<DayCell date="2026-07-01" level={0} />);
        const cell = container.firstElementChild;

        expect(cell).toHaveClass('min-h-11', 'min-w-11');
    });

    it('marks a logged day and says so in its label', () => {
        const { container } = render(
            <DayCell date="2026-07-09" level={1} hasCheckin />,
        );
        const cell = container.firstElementChild;

        expect(cell).toHaveAttribute('data-checkin');
        expect(cell).toHaveAttribute(
            'aria-label',
            '2026-07-09, intensity 1 of 5, checked in',
        );
    });

    it('leaves an unlogged day unmarked', () => {
        const { container } = render(<DayCell date="2026-07-09" level={0} />);

        expect(container.firstElementChild).not.toHaveAttribute('data-checkin');
    });

    it('marks today with a static ring', () => {
        const { container } = render(
            <DayCell date="2026-07-09" level={3} isToday />,
        );
        const cell = container.firstElementChild;

        expect(cell).toHaveAttribute('data-today');
        expect(cell).toHaveClass('ring-primary');
    });
});
