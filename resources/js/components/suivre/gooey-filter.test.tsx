import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { GooeyFilter } from './gooey-filter';

describe('GooeyFilter', () => {
    it('exposes a filter the material layer can reference by id', () => {
        const { container } = render(<GooeyFilter />);

        expect(container.querySelector('filter#gooey')).not.toBeNull();
    });

    it('is hidden from assistive technology and takes no space', () => {
        const { container } = render(<GooeyFilter />);
        const svg = container.querySelector('svg');

        expect(svg?.getAttribute('aria-hidden')).toBe('true');
        expect(svg?.getAttribute('width')).toBe('0');
        expect(svg?.getAttribute('height')).toBe('0');
    });

    it('contrasts alpha tightly enough that the merge is felt, not seen', () => {
        const { container } = render(<GooeyFilter />);
        const matrix = container.querySelector('feColorMatrix');

        // stdDeviation 3 with an alpha slope of 20 keeps the ligature short.
        expect(
            container.querySelector('feGaussianBlur')?.getAttribute('stdDeviation'),
        ).toBe('3');
        expect(matrix?.getAttribute('values')?.trim().endsWith('20 -9')).toBe(true);
    });
});
