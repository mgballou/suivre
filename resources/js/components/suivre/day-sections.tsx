import { useState, type ReactNode } from 'react';
import {
    DaySection,
    type DaySectionSummary,
} from '@/components/suivre/day-section';

type DaySectionsProps = {
    sections: DaySectionSummary[];
    openSection: string | null;
    children: Record<string, ReactNode>;
};

/**
 * The day's four cards. One open at a time — this component's only job is
 * deciding which, so a card never has to know its siblings exist.
 *
 * `openSection` is the card the *day* opens on, read once rather than followed.
 * Every control inside these cards writes on tap, so saving a check-in returns
 * a response whose `openSection` has already advanced to the next gap; adopting
 * that would shut the card under the hands of the person still using it. The
 * caller keys this component by date, and that is what makes moving to another
 * day pick up the new choice.
 */
export function DaySections({
    sections,
    openSection,
    children,
}: DaySectionsProps) {
    const [open, setOpen] = useState<string | null>(openSection);

    return (
        <div className="elevation-raised overflow-hidden rounded-lg border border-border">
            {sections.map((section) => (
                <DaySection
                    key={section.key}
                    section={section}
                    open={open === section.key}
                    onToggle={(key) =>
                        setOpen((current) => (current === key ? null : key))
                    }
                >
                    {children[section.key]}
                </DaySection>
            ))}
        </div>
    );
}
