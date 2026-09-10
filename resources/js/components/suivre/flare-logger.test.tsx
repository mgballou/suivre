import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { routerPost } = vi.hoisted(() => ({ routerPost: vi.fn() }));

vi.mock('@inertiajs/react', () => ({ router: { post: routerPost } }));

import { FlareLogger } from './flare-logger';

const conditions = [{ id: 3, name: 'Eczema' }];

const intensities = [
    { value: 1, label: 'Mild' },
    { value: 2, label: 'Moderate' },
];

/** Answer the next write the way the server would, refusing it with `reason`. */
function refuseWith(reason: string) {
    routerPost.mockImplementation(
        (
            _url: string,
            _data: unknown,
            options: { onError: (errors: Record<string, string>) => void },
        ) => options.onError({ date: reason }),
    );
}

function renderLogger() {
    return render(
        <FlareLogger
            date="2026-07-20"
            conditions={conditions}
            intensities={intensities}
            flares={[]}
        />,
    );
}

describe('FlareLogger', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('logs a flare on the intensity tap', () => {
        renderLogger();

        fireEvent.click(screen.getByRole('button', { name: 'Moderate' }));

        expect(routerPost).toHaveBeenCalledOnce();
        expect(routerPost.mock.calls[0][1]).toMatchObject({ intensity: 2 });
    });

    it('shows the reason a flare was refused', () => {
        refuseWith('That day has not happened yet.');

        renderLogger();

        fireEvent.click(screen.getByRole('button', { name: 'Mild' }));

        expect(
            screen.getByText('That day has not happened yet.'),
        ).toBeInTheDocument();
    });

    it('keeps the detail a refused flare carried', () => {
        refuseWith('That day has not happened yet.');

        renderLogger();

        fireEvent.click(screen.getByRole('button', { name: /add detail/i }));
        fireEvent.change(screen.getByLabelText(/duration/i), {
            target: { value: '45' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Mild' }));

        expect(screen.getByLabelText(/duration/i)).toHaveValue(45);
        expect(routerPost.mock.calls[0][1]).toMatchObject({
            duration_minutes: 45,
        });
    });

    it('clears the reason once a later flare is accepted', () => {
        refuseWith('That day has not happened yet.');

        renderLogger();

        fireEvent.click(screen.getByRole('button', { name: 'Mild' }));
        expect(
            screen.getByText('That day has not happened yet.'),
        ).toBeInTheDocument();

        routerPost.mockImplementation(() => undefined);
        fireEvent.click(screen.getByRole('button', { name: 'Mild' }));

        expect(
            screen.queryByText('That day has not happened yet.'),
        ).not.toBeInTheDocument();
    });
});
