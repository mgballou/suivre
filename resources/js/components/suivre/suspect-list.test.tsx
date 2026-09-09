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
        measuredTags: 9,
        thinTags: 2,
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

    it('prints a negative lift with one sign, not two', () => {
        render(
            <SuspectList
                insights={[insight({ suspects: [hint({ lift: -0.4 })] })]}
            />,
        );

        expect(screen.getByText('-0.4 points')).toBeInTheDocument();
        expect(screen.queryByText('+-0.4 points')).not.toBeInTheDocument();
    });

    it('calls an empty ranking a result when foods were measured', () => {
        render(
            <SuspectList
                insights={[
                    insight({ suspects: [], measuredTags: 9, thinTags: 2 }),
                ]}
            />,
        );

        expect(
            screen.getByText(
                /9 foods had enough days to compare, and none came out above your baseline/i,
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/a real result, not a gap/i),
        ).toBeInTheDocument();
    });

    it('claims no result when nothing could be measured at all', () => {
        render(
            <SuspectList
                insights={[
                    insight({ suspects: [], measuredTags: 0, thinTags: 6 }),
                ]}
            />,
        );

        expect(
            screen.getByText(/nothing could be measured here yet/i),
        ).toBeInTheDocument();
        expect(
            screen.queryByText(/a real result, not a gap/i),
        ).not.toBeInTheDocument();
    });

    it('names the missing meals when no food was logged at all', () => {
        render(
            <SuspectList
                insights={[
                    insight({ suspects: [], measuredTags: 0, thinTags: 0 }),
                ]}
            />,
        );

        expect(
            screen.getByText(/no meals are logged against these days/i),
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
