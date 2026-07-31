import type { ConditionHue } from '@/types';

export type ConditionReadiness = {
    id: number;
    name: string;
    hue: ConditionHue;
    loggedDays: number;
    requiredDays: number;
    remainingDays: number;
    isReady: boolean;
};

type InsightReadinessProps = {
    conditions: ConditionReadiness[];
};

/**
 * How far each tracked condition is from the point where correlation is worth
 * trusting, and why that point exists.
 *
 * The framing is load-bearing. Insights are not a locked feature being withheld
 * — below roughly ninety days a ranking is right about as often as a coin toss,
 * so naming a trigger early would be guessing in a confident voice. Saying that
 * plainly turns the wait into the reason to keep going.
 *
 * Deliberately not a streak. No praise, no warning, no colour for falling
 * behind, and a missed day costs nothing: the meter counts days rated in total,
 * so it only ever moves forward (D20).
 */
export function InsightReadiness({ conditions }: InsightReadinessProps) {
    if (conditions.length === 0) {
        return null;
    }

    const waiting = conditions.filter((condition) => !condition.isReady);

    if (waiting.length === 0) {
        // Every condition has cleared the threshold. Nothing to say about
        // waiting; SUI-22 puts the ranking itself above this section.
        return null;
    }

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-col gap-1">
                <h2 className="text-sm font-medium">Working towards insights</h2>
                <p className="text-sm text-muted-foreground">
                    Suivre starts looking for patterns once a condition has{' '}
                    {waiting[0].requiredDays} days of ratings. Before that there
                    is not enough to tell a real pattern from chance, and it
                    would rather say nothing than guess.
                </p>
            </div>

            <ul className="flex flex-col gap-3">
                {waiting.map((condition) => (
                    <li
                        key={condition.id}
                        data-hue={condition.hue}
                        className="flex flex-col gap-1.5"
                    >
                        <div className="flex items-baseline justify-between gap-3">
                            <span className="text-sm">{condition.name}</span>
                            <span className="text-xs tabular-nums text-muted-foreground">
                                {condition.loggedDays} of{' '}
                                {condition.requiredDays} days rated
                            </span>
                        </div>

                        <div
                            role="progressbar"
                            aria-label={`${condition.name} progress towards insights`}
                            aria-valuenow={condition.loggedDays}
                            aria-valuemin={0}
                            aria-valuemax={condition.requiredDays}
                            className="h-1 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                className="h-full rounded-full bg-condition-4 transition-[width] duration-[var(--dur-arrival)] ease-quiet"
                                style={{
                                    width: `${(condition.loggedDays / condition.requiredDays) * 100}%`,
                                }}
                            />
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
