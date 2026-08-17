import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DaySections } from './day-sections';

const sections = [
    {
        key: 'checkin',
        title: 'Check-in',
        summary: 'Not recorded',
        recorded: false,
    },
    {
        key: 'conditions',
        title: 'Conditions',
        summary: '2 rated',
        recorded: true,
    },
    { key: 'meals', title: 'Meals', summary: '3 items', recorded: true },
    { key: 'flares', title: 'Flares', summary: 'None', recorded: false },
];

const bodies = {
    checkin: <p>checkin body</p>,
    conditions: <p>conditions body</p>,
    meals: <p>meals body</p>,
    flares: <p>flares body</p>,
};

describe('DaySections', () => {
    it('renders one card per section, in server order', () => {
        render(
            <DaySections sections={sections} openSection={null}>
                {bodies}
            </DaySections>,
        );

        expect(
            screen.getAllByRole('button').map((button) => button.textContent),
        ).toEqual([
            expect.stringContaining('Check-in'),
            expect.stringContaining('Conditions'),
            expect.stringContaining('Meals'),
            expect.stringContaining('Flares'),
        ]);
    });

    it('opens the card the server chose', () => {
        render(
            <DaySections sections={sections} openSection="checkin">
                {bodies}
            </DaySections>,
        );

        expect(
            screen.getByRole('button', { name: /Check-in/ }),
        ).toHaveAttribute('aria-expanded', 'true');
        expect(
            screen.getByRole('button', { name: /Conditions/ }),
        ).toHaveAttribute('aria-expanded', 'false');
    });

    it('leaves every card closed when the server chose none', () => {
        render(
            <DaySections sections={sections} openSection={null}>
                {bodies}
            </DaySections>,
        );

        for (const button of screen.getAllByRole('button')) {
            expect(button).toHaveAttribute('aria-expanded', 'false');
        }
    });

    it('closes the open card when another is opened', () => {
        render(
            <DaySections sections={sections} openSection="checkin">
                {bodies}
            </DaySections>,
        );

        fireEvent.click(screen.getByRole('button', { name: /Meals/ }));

        expect(screen.getByRole('button', { name: /Meals/ })).toHaveAttribute(
            'aria-expanded',
            'true',
        );
        expect(
            screen.getByRole('button', { name: /Check-in/ }),
        ).toHaveAttribute('aria-expanded', 'false');
    });

    it('closes the open card when it is activated again', () => {
        render(
            <DaySections sections={sections} openSection="checkin">
                {bodies}
            </DaySections>,
        );

        fireEvent.click(screen.getByRole('button', { name: /Check-in/ }));

        expect(
            screen.getByRole('button', { name: /Check-in/ }),
        ).toHaveAttribute('aria-expanded', 'false');
    });

    it('adopts the server choice again when the day changes underneath it', () => {
        const { rerender } = render(
            <DaySections sections={sections} openSection="checkin">
                {bodies}
            </DaySections>,
        );

        rerender(
            <DaySections sections={sections} openSection="meals">
                {bodies}
            </DaySections>,
        );

        expect(screen.getByRole('button', { name: /Meals/ })).toHaveAttribute(
            'aria-expanded',
            'true',
        );
    });
});
