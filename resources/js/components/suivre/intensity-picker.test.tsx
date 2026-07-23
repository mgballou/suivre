import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { IntensityPicker } from './intensity-picker';

describe('IntensityPicker', () => {
    it('offers the whole 0–10 scale in order', () => {
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={null}
                level={0}
                onSelect={vi.fn()}
            />,
        );

        expect(
            screen.getAllByRole('radio').map((radio) => radio.getAttribute('value')),
        ).toEqual(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10']);
    });

    it('groups the scale under the condition name', () => {
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={null}
                level={0}
                onSelect={vi.fn()}
            />,
        );

        expect(screen.getByRole('group', { name: 'Headache' })).toBeInTheDocument();
    });

    it('marks the saved rating as checked', () => {
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={7}
                level={4}
                onSelect={vi.fn()}
            />,
        );

        expect(screen.getByRole('radio', { name: '7' })).toBeChecked();
        expect(screen.getByRole('radio', { name: '6' })).not.toBeChecked();
    });

    it('treats a rating of zero as a record, not as nothing saved', () => {
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={0}
                level={0}
                onSelect={vi.fn()}
            />,
        );

        expect(screen.getByRole('radio', { name: '0' })).toBeChecked();
    });

    it('leaves every step unchecked when nothing is saved', () => {
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={null}
                level={0}
                onSelect={vi.fn()}
            />,
        );

        for (const radio of screen.getAllByRole('radio')) {
            expect(radio).not.toBeChecked();
        }
    });

    it('reports the tapped rating once', () => {
        const onSelect = vi.fn();
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={null}
                level={0}
                onSelect={onSelect}
            />,
        );

        fireEvent.click(screen.getByRole('radio', { name: '8' }));

        expect(onSelect).toHaveBeenCalledTimes(1);
        expect(onSelect).toHaveBeenCalledWith(8);
    });

    it('does not clear the current rating when it is tapped again', () => {
        const onSelect = vi.fn();
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={8}
                level={4}
                onSelect={onSelect}
            />,
        );

        fireEvent.click(screen.getByRole('radio', { name: '8' }));

        expect(onSelect).not.toHaveBeenCalled();
    });

    it('resolves its ramp through the condition hue', () => {
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="plum"
                value={null}
                level={0}
                onSelect={vi.fn()}
            />,
        );

        expect(screen.getByRole('group', { name: 'Headache' })).toHaveAttribute(
            'data-hue',
            'plum',
        );
    });

    it('colours only the saved step, so the ramp bucket is never derived here', () => {
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={7}
                level={4}
                onSelect={vi.fn()}
            />,
        );

        const selected = screen.getByRole('radio', { name: '7' }).closest('label');

        expect(selected).toHaveClass('bg-condition-4');
        expect(selected).toHaveClass('text-ramp-ink-4');
    });

    it('clears a 44px hit area on every step', () => {
        render(
            <IntensityPicker
                name="condition-1"
                label="Headache"
                hue="moss"
                value={null}
                level={0}
                onSelect={vi.fn()}
            />,
        );

        for (const radio of screen.getAllByRole('radio')) {
            expect(radio.closest('label')).toHaveClass('min-h-11');
            expect(radio.closest('label')).toHaveClass('min-w-11');
        }
    });
});
