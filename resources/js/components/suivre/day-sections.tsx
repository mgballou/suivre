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
 * The server picks the card the day opens on. That choice is re-adopted
 * whenever it changes, because Inertia re-renders this page in place when
 * navigating between days: state seeded once would carry yesterday's open card
 * into today.
 */
export function DaySections({
    sections,
    openSection,
    children,
}: DaySectionsProps) {
    const [open, setOpen] = useState<string | null>(openSection);
    const [chosen, setChosen] = useState<string | null>(openSection);

    if (chosen !== openSection) {
        setChosen(openSection);
        setOpen(openSection);
    }

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
