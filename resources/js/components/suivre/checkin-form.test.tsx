import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { routerPost } = vi.hoisted(() => ({ routerPost: vi.fn() }));

vi.mock('@inertiajs/react', () => ({ router: { post: routerPost } }));

import { CheckinForm, type CheckinValues } from './checkin-form';

const scales = {
    mood: [
        { value: 1, label: 'Low' },
        { value: 2, label: 'Fine' },
    ],
    sleep: [{ value: 1, label: 'Poor' }],
    stress: [{ value: 1, label: 'Calm' }],
};

const saved: CheckinValues = {
    mood: 1,
    sleep: null,
    stress: null,
    note: null,
};

/** Answer the next write the way the server would, refusing it with `reason`. */
function refuseWith(reason: string) {
    routerPost.mockImplementation(
        (
            _url: string,
            _data: unknown,
            options: { onError: (errors: Record<string, string>) => void },
        ) => options.onError({ note: reason }),
    );
}

function renderForm(values: CheckinValues = saved) {
    return render(<CheckinForm date="2026-07-20" values={values} scales={scales} />);
}

describe('CheckinForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('writes the tapped value without a save button', () => {
        renderForm();

        fireEvent.click(screen.getByTestId('mood-2'));

        expect(routerPost).toHaveBeenCalledOnce();
        expect(routerPost.mock.calls[0][1]).toMatchObject({ mood: 2 });
    });

    it('shows the reason a write was refused', () => {
        refuseWith('That day has not happened yet.');

        renderForm();

        fireEvent.click(screen.getByTestId('mood-2'));

        expect(
            screen.getByText('That day has not happened yet.'),
        ).toBeInTheDocument();
    });

    it('rolls the refused selection back out of the draft', () => {
        refuseWith('That day has not happened yet.');

        renderForm();

        fireEvent.click(screen.getByTestId('mood-2'));

        const mood = within(screen.getByRole('group', { name: 'Mood' }));

        expect(mood.getByDisplayValue('1')).toBeChecked();
        expect(mood.getByDisplayValue('2')).not.toBeChecked();
    });

    it('clears the reason once a later write is accepted', () => {
        refuseWith('That day has not happened yet.');

        renderForm();

        fireEvent.click(screen.getByTestId('mood-2'));
        expect(
            screen.getByText('That day has not happened yet.'),
        ).toBeInTheDocument();

        routerPost.mockImplementation(() => undefined);
        fireEvent.click(screen.getByTestId('sleep-1'));

        expect(
            screen.queryByText('That day has not happened yet.'),
        ).not.toBeInTheDocument();
    });
});
