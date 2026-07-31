import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
    ExposureTimeline,
    type ExposureTimelineData,
} from './exposure-timeline';

function timeline(
    overrides: Partial<ExposureTimelineData> = {},
): ExposureTimelineData {
    return {
        days: [
            { date: '2026-07-28', label: '28 Jul', level: 0 },
            { date: '2026-07-29', label: '29 Jul', level: 3 },
            { date: '2026-07-30', label: '30 Jul', level: 5 },
        ],
        tags: [
            {
                name: 'Dairy',
                slug: 'dairy',
                present: [false, true, true],
                days: 2,
            },
        ],
        rangeDays: 30,
        ...overrides,
    };
}

function renderTimeline(
    overrides: Partial<ExposureTimelineData> = {},
    onRangeChange = vi.fn(),
) {
    render(
        <ExposureTimeline
            {...timeline(overrides)}
            ranges={[30, 90]}
            onRangeChange={onRangeChange}
        />,
    );

    return onRangeChange;
}

describe('ExposureTimeline', () => {
    it('draws a ramp cell per day, labelled by its step', () => {
        renderTimeline();

        const ramp = screen.getByRole('list', { name: 'Intensity' });

        expect(within(ramp).getAllByLabelText(/Jul/)).toHaveLength(3);
        expect(
            within(ramp).getByLabelText('30 Jul, Severe'),
        ).toBeInTheDocument();
        expect(
            within(ramp).getByLabelText('28 Jul, No entry'),
        ).toBeInTheDocument();
    });

    it('marks a tag on the columns it appeared on, and only those', () => {
        const { container } = render(
            <ExposureTimeline
                {...timeline()}
                ranges={[30, 90]}
                onRangeChange={vi.fn()}
            />,
        );

        const row = container.querySelector('[aria-label="Dairy"]');
        const marked = row?.querySelectorAll('[data-present]') ?? [];

        expect(marked).toHaveLength(2);
        expect(
            Array.from(marked).map((mark) => mark.getAttribute('data-date')),
        ).toEqual(['2026-07-29', '2026-07-30']);
    });

    it('keeps every row on the same columns as the ramp', () => {
        const { container } = render(
            <ExposureTimeline
                {...timeline()}
                ranges={[30, 90]}
                onRangeChange={vi.fn()}
            />,
        );

        const dates = (label: string) =>
            Array.from(
                container
                    .querySelector(`[aria-label="${label}"]`)
                    ?.querySelectorAll('[data-date]') ?? [],
            ).map((cell) => cell.getAttribute('data-date'));

        expect(dates('Dairy')).toEqual(dates('Intensity'));
    });

    it('states how many days each tag turned up on', () => {
        renderTimeline();

        expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('marks the range in play and reports a change', () => {
        const onRangeChange = renderTimeline();

        expect(screen.getByRole('button', { name: '30 days' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );

        fireEvent.click(screen.getByRole('button', { name: '90 days' }));

        expect(onRangeChange).toHaveBeenCalledWith(90);
    });

    it('says when nothing in the range has been matched to the catalogue', () => {
        renderTimeline({ tags: [] });

        expect(
            screen.getByText(/nothing to lay against the ramp yet/i),
        ).toBeInTheDocument();
    });

    it('says when the range holds nothing at all', () => {
        renderTimeline({ days: [], tags: [] });

        expect(screen.getByText(/nothing logged in this range/i)).toBeInTheDocument();
    });
});
