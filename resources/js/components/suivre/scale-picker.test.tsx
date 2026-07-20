import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ScalePicker, type ScaleOption } from './scale-picker';

const MOOD: ScaleOption[] = [
    { value: 1, label: 'Low' },
    { value: 2, label: 'Neutral' },
    { value: 3, label: 'Good' },
];

describe('ScalePicker', () => {
    it('renders one radio per option, in the order the server sent them', () => {
        render(
            <ScalePicker
                name="mood"
                label="Mood"
                options={MOOD}
                value={null}
                onSelect={vi.fn()}
            />,
        );

        expect(screen.getAllByRole('radio').map((radio) => radio.getAttribute('value'))).toEqual(['1', '2', '3']);
    });

    it('groups the options under the scale name', () => {
        render(
            <ScalePicker
                name="mood"
                label="Mood"
                options={MOOD}
                value={null}
                onSelect={vi.fn()}
            />,
        );

        expect(screen.getByRole('group', { name: 'Mood' })).toBeInTheDocument();
    });

    it('marks the saved value as checked', () => {
        render(
            <ScalePicker
                name="mood"
                label="Mood"
                options={MOOD}
                value={2}
                onSelect={vi.fn()}
            />,
        );

        expect(screen.getByRole('radio', { name: 'Neutral' })).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Good' })).not.toBeChecked();
    });

    it('leaves every option unchecked when nothing is saved', () => {
        render(
            <ScalePicker
                name="mood"
                label="Mood"
                options={MOOD}
                value={null}
                onSelect={vi.fn()}
            />,
        );

        for (const radio of screen.getAllByRole('radio')) {
            expect(radio).not.toBeChecked();
        }
    });

    it('reports the tapped value once', () => {
        const onSelect = vi.fn();
        render(
            <ScalePicker
                name="mood"
                label="Mood"
                options={MOOD}
                value={null}
                onSelect={onSelect}
            />,
        );

        fireEvent.click(screen.getByRole('radio', { name: 'Good' }));

        expect(onSelect).toHaveBeenCalledTimes(1);
        expect(onSelect).toHaveBeenCalledWith(3);
    });

    it('does not clear the current value when it is tapped again', () => {
        const onSelect = vi.fn();
        render(
            <ScalePicker
                name="mood"
                label="Mood"
                options={MOOD}
                value={3}
                onSelect={onSelect}
            />,
        );

        fireEvent.click(screen.getByRole('radio', { name: 'Good' }));

        expect(onSelect).not.toHaveBeenCalled();
    });

    it('clears a 44px hit area on every option', () => {
        render(
            <ScalePicker
                name="mood"
                label="Mood"
                options={MOOD}
                value={null}
                onSelect={vi.fn()}
            />,
        );

        for (const radio of screen.getAllByRole('radio')) {
            expect(radio.closest('label')).toHaveClass('min-h-11');
        }
    });
});
