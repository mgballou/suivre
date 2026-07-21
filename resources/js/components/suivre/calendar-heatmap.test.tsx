import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { INTENSITY_BG } from '@/lib/intensity';
import type { IntensityLevel, IsoDate, IsoMonth } from '@/types';
import { CalendarHeatmap, type HeatmapDay } from './calendar-heatmap';

const MONTH: IsoMonth = '2026-07';

function daysFrom(levels: IntensityLevel[]): HeatmapDay[] {
    return levels.map((level, index) => ({
        date: `2026-07-${String(index + 1).padStart(2, '0')}` as IsoDate,
        level,
    }));
}

function renderHeatmap(days: HeatmapDay[], leadingBlanks = 0) {
    return render(
        <CalendarHeatmap
            month={MONTH}
            label="July 2026"
            leadingBlanks={leadingBlanks}
            days={days}
        />,
    );
}

describe('CalendarHeatmap', () => {
    it.each([0, 1, 2, 3, 4, 5] as IntensityLevel[])(
        'paints level %i with the shared ramp step',
        (level) => {
            renderHeatmap(daysFrom([level]));

            const cell = screen.getByRole('listitem');

            expect(cell).toHaveClass(`bg-intensity-${level}`);
            expect(cell).toHaveAttribute('data-level', String(level));
        },
    );

    it('draws every ramp step from the one shared scale', () => {
        renderHeatmap(daysFrom([0, 1, 2, 3, 4, 5]));

        const cells = screen.getAllByRole('listitem');

        cells.forEach((cell, level) => {
            expect(cell).toHaveClass(INTENSITY_BG[level as IntensityLevel]);
        });
    });

    it('renders a cell for every day of the month', () => {
        const levels = Array.from({ length: 31 }, () => 2 as IntensityLevel);

        renderHeatmap(daysFrom(levels));

        expect(screen.getAllByRole('listitem')).toHaveLength(31);
    });

    it('offsets the first day by the leading blanks the server sends', () => {
        const { container } = renderHeatmap(daysFrom([3]), 2);

        expect(container.querySelectorAll('[data-blank]')).toHaveLength(
            2 + 4, // two before the 1st, four padding the week out
        );
    });

    it('pads the final week so the grid stays rectangular', () => {
        const levels = Array.from({ length: 10 }, () => 1 as IntensityLevel);

        const { container } = renderHeatmap(daysFrom(levels), 3);

        const blanks = container.querySelectorAll('[data-blank]').length;

        expect((blanks + 10) % 7).toBe(0);
    });

    it('renders no cells and no padding for an empty month', () => {
        const { container } = renderHeatmap([]);

        expect(screen.queryAllByRole('listitem')).toHaveLength(0);
        expect(container.querySelectorAll('[data-blank]')).toHaveLength(0);
    });

    it('names each day and its step, so the fill is never the only encoding', () => {
        renderHeatmap(daysFrom([0, 4]));

        expect(
            screen.getByLabelText('2026-07-01, No entry'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('2026-07-02, Strong')).toBeInTheDocument();
    });

    it('keys the whole ramp so a low step is readable off the legend', () => {
        const { container } = renderHeatmap(daysFrom([1]));

        const keySwatches = container.querySelectorAll('.size-3');

        expect(keySwatches).toHaveLength(6);
    });
});
