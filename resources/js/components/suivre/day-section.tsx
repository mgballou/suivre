import { ChevronDown } from 'lucide-react';
import { useId, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type DaySectionSummary = {
    key: string;
    title: string;
    summary: string;
    recorded: boolean;
};

type DaySectionProps = {
    section: DaySectionSummary;
    open: boolean;
    onToggle: (key: string) => void;
    children: ReactNode;
};

/**
 * One section of the day: a row stating what is on file, and a body that
 * expands in place beneath it.
 *
 * The summary is a server prop and is deliberately flat — "3 items", "None",
 * "Not recorded". It never counts toward a total and never carries a tick,
 * because a journal that scores the day stops being a journal (D20/D28).
 *
 * Height comes from a grid row going 0fr to 1fr, which the browser can
 * interpolate without anyone measuring the body. The alternative — reading
 * scrollHeight and animating a pixel value — costs a layout read on every
 * toggle and breaks the moment the body's own content resizes.
 */
export function DaySection({
    section,
    open,
    onToggle,
    children,
}: DaySectionProps) {
    const bodyId = useId();

    return (
        <div className="border-b border-border last:border-b-0">
            <button
                type="button"
                aria-expanded={open}
                aria-controls={bodyId}
                onClick={() => onToggle(section.key)}
                className="flex min-h-14 w-full items-center gap-3 px-4 text-left transition-colors duration-[var(--dur-micro)] ease-quiet hover:bg-accent/40"
            >
                <span className="flex-1 text-sm font-medium text-foreground">
                    {section.title}
                </span>
                <span className="text-sm text-muted-foreground tabular-nums">
                    {section.summary}
                </span>
                <ChevronDown
                    aria-hidden
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground',
                        'transition-transform duration-[var(--dur-base)] ease-quiet',
                        'motion-reduce:transition-none',
                        open && 'rotate-180',
                    )}
                />
            </button>

            <div
                id={bodyId}
                className={cn(
                    'grid',
                    'transition-[grid-template-rows,opacity] duration-[var(--dur-base)] ease-quiet',
                    'motion-reduce:transition-[opacity]',
                    open ? 'opacity-100' : 'opacity-0',
                )}
                style={{ gridTemplateRows: open ? '1fr' : '0fr' }}
            >
                <div className="overflow-hidden" inert={!open}>
                    <div className="panel-tint rounded-md px-4 pt-2 pb-6">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
