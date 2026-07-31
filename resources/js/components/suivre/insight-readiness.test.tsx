import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    InsightReadiness,
    type ConditionReadiness,
} from './insight-readiness';

function readiness(
    overrides: Partial<ConditionReadiness> = {},
): ConditionReadiness {
    return {
        id: 1,
        name: 'Eczema',
        hue: 'marine',
        loggedDays: 34,
        requiredDays: 90,
        remainingDays: 56,
        isReady: false,
        ...overrides,
    };
}

describe('InsightReadiness', () => {
    it('states the progress and the threshold it is working towards', () => {
        render(<InsightReadiness conditions={[readiness()]} />);

        expect(screen.getByText('Eczema')).toBeInTheDocument();
        expect(screen.getByText('34 of 90 days rated')).toBeInTheDocument();
    });

    it('says why the wait exists rather than presenting it as a locked feature', () => {
        render(<InsightReadiness conditions={[readiness()]} />);

        expect(
            screen.getByText(/not enough to tell a real pattern from chance/i),
        ).toBeInTheDocument();
    });

    it('exposes progress to assistive technology', () => {
        render(<InsightReadiness conditions={[readiness()]} />);

        const bar = screen.getByRole('progressbar');

        expect(bar).toHaveAttribute('aria-valuenow', '34');
        expect(bar).toHaveAttribute('aria-valuemax', '90');
    });

    it('drops a condition that has cleared the threshold', () => {
        render(
            <InsightReadiness
                conditions={[
                    readiness(),
                    readiness({
                        id: 2,
                        name: 'Migraine',
                        loggedDays: 90,
                        remainingDays: 0,
                        isReady: true,
                    }),
                ]}
            />,
        );

        expect(screen.getByText('Eczema')).toBeInTheDocument();
        expect(screen.queryByText('Migraine')).not.toBeInTheDocument();
    });

    it('renders nothing once every condition is ready', () => {
        const { container } = render(
            <InsightReadiness
                conditions={[
                    readiness({
                        loggedDays: 120,
                        remainingDays: 0,
                        isReady: true,
                    }),
                ]}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('renders nothing when no condition is tracked', () => {
        const { container } = render(<InsightReadiness conditions={[]} />);

        expect(container).toBeEmptyDOMElement();
    });
});
