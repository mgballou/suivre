import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DaySection } from './day-section';

const section = {
    key: 'meals',
    title: 'Meals',
    summary: '3 items',
    recorded: true,
};

describe('DaySection', () => {
    it('states what is on file in the collapsed row', () => {
        render(
            <DaySection section={section} open={false} onToggle={vi.fn()}>
                <p>body</p>
            </DaySection>,
        );

        expect(screen.getByText('Meals')).toBeInTheDocument();
        expect(screen.getByText('3 items')).toBeInTheDocument();
    });

    it('reports its open state to assistive technology', () => {
        const { rerender } = render(
            <DaySection section={section} open={false} onToggle={vi.fn()}>
                <p>body</p>
            </DaySection>,
        );

        expect(screen.getByRole('button')).toHaveAttribute(
            'aria-expanded',
            'false',
        );

        rerender(
            <DaySection section={section} open onToggle={vi.fn()}>
                <p>body</p>
            </DaySection>,
        );

        expect(screen.getByRole('button')).toHaveAttribute(
            'aria-expanded',
            'true',
        );
    });

    it('takes a closed body out of the tab order rather than merely hiding it', () => {
        const { container } = render(
            <DaySection section={section} open={false} onToggle={vi.fn()}>
                <button type="button">inside</button>
            </DaySection>,
        );

        expect(container.querySelector('[inert]')).not.toBeNull();
    });

    it('hands its key back on activation so the parent can decide what closes', () => {
        const onToggle = vi.fn();
        render(
            <DaySection section={section} open={false} onToggle={onToggle}>
                <p>body</p>
            </DaySection>,
        );

        fireEvent.click(screen.getByRole('button'));

        expect(onToggle).toHaveBeenCalledWith('meals');
    });

    it('carries no completion state of any kind', () => {
        const { container } = render(
            <DaySection section={section} open={false} onToggle={vi.fn()}>
                <p>body</p>
            </DaySection>,
        );

        // D20/D28: a card describes what is on file and never scores it.
        expect(container.textContent).not.toMatch(
            /\bof\b|\bdone\b|✓|complete/i,
        );
    });
});
