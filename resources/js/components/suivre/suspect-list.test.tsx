import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    SuspectList,
    type ConditionInsight,
    type SuspectHint,
} from './suspect-list';

function hint(overrides: Partial<SuspectHint> = {}): SuspectHint {
    return {
        tags: ['Dairy'],
        granularity: 'single_tag',
        lift: 1.4,
        exposedDays: 31,
        baselineDays: 58,
        peakLag: 3,
        clearsNoiseBand: true,
        ...overrides,
    };
}

function insight(overrides: Partial<ConditionInsight> = {}): ConditionInsight {
    return {
        conditionId: 1,
        conditionName: 'Eczema',
        hue: 'marine',
        suspects: [hint()],
        loggedDays: 94,
        windowDays: 3,
        ...overrides,
    };
}

describe('SuspectList', () => {
    it('names a separable tag with its lift and sample sizes', () => {
        render(<SuspectList insights={[insight()]} />);

        expect(screen.getByText('Dairy')).toBeInTheDocument();
        expect(screen.getByText('+1.4 points')).toBeInTheDocument();
        expect(
            screen.getByText(/31 days with · 58 without/),
        ).toBeInTheDocument();
    });

    it('frames the whole surface as questions rather than findings', () => {
        render(<SuspectList insights={[insight()]} />);

        expect(screen.getByText(/not conclusions/i)).toBeInTheDocument();
        expect(
            screen.getByText(/cannot reliably tell a real pattern/i),
        ).toBeInTheDocument();
    });

    it('names the pattern, never one member, when tags cannot be pulled apart', () => {
        render(
            <SuspectList
                insights={[
                    insight({
                        suspects: [
                            hint({
                                tags: ['Dairy', 'Added sugar'],
                                granularity: 'co_occurrence_cluster',
                            }),
                        ],
                    }),
                ]}
            />,
        );

        expect(
            screen.getByText('Dairy with Added sugar'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/points at the pattern rather than any one/i),
        ).toBeInTheDocument();
    });

    it('says on the row when a lift sits inside the noise band', () => {
        render(
            <SuspectList
                insights={[
                    insight({ suspects: [hint({ clearsNoiseBand: false })] }),
                ]}
            />,
        );

        expect(
            screen.getByText(/inside the range chance alone produces/i),
        ).toBeInTheDocument();
    });

    it('leaves a row that clears the noise band unqualified', () => {
        render(<SuspectList insights={[insight()]} />);

        expect(
            screen.queryByText(/inside the range chance alone produces/i),
        ).not.toBeInTheDocument();
    });

    it('states when the effect peaked', () => {
        render(<SuspectList insights={[insight()]} />);

        expect(screen.getByText(/strongest 3 days later/)).toBeInTheDocument();
    });

    it('says same-day rather than zero days later', () => {
        render(
            <SuspectList
                insights={[insight({ suspects: [hint({ peakLag: 0 })] })]}
            />,
        );

        expect(screen.getByText(/strongest the same day/)).toBeInTheDocument();
    });

    it('treats an empty ranking as a result, not a gap', () => {
        render(<SuspectList insights={[insight({ suspects: [] })]} />);

        expect(
            screen.getByText(/nothing separated itself from chance/i),
        ).toBeInTheDocument();
    });

    it('renders nothing when no condition has earned a ranking', () => {
        const { container } = render(<SuspectList insights={[]} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('never calls a suspect a trigger', () => {
        const { container } = render(<SuspectList insights={[insight()]} />);

        expect(container.textContent).not.toMatch(/trigger|caus(e|ed|ing)/i);
    });
});
