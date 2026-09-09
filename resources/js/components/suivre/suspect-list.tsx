import type { ConditionHue } from '@/types';

export type SuspectHint = {
    tags: string[];
    granularity: 'single_tag' | 'co_occurrence_cluster';
    lift: number;
    exposedDays: number;
    baselineDays: number;
    peakLag: number | null;
    clearsNoiseBand: boolean;
};

export type ConditionInsight = {
    conditionId: number;
    conditionName: string;
    hue: ConditionHue;
    suspects: SuspectHint[];
    loggedDays: number;
    windowDays: number;
    measuredTags: number;
    thinTags: number;
};

type SuspectListProps = {
    insights: ConditionInsight[];
};

/**
 * How a suspect may be named.
 *
 * A cluster is a set of tags that turn up on the same days often enough that the
 * data cannot say which of them carries the effect. Naming one of them would be
 * an accusation the measurement does not support (D24), so the whole pattern is
 * named instead.
 */
function headline(suspect: SuspectHint): string {
    if (suspect.granularity === 'single_tag') {
        return suspect.tags[0];
    }

    if (suspect.tags.length === 2) {
        return `${suspect.tags[0]} with ${suspect.tags[1]}`;
    }

    return `${suspect.tags.slice(0, -1).join(', ')} with ${suspect.tags.at(-1)}`;
}

function countOf(total: number, noun: string): string {
    return `${total} ${noun}${total === 1 ? '' : 's'}`;
}

/**
 * A lift in rating points, carrying the sign the number itself has.
 *
 * The sign has to come out of the value rather than be prefixed to it: a
 * hard-coded `+` ahead of `toFixed(1)` rendered a lift of -0.4 as `+-0.4`. The
 * engine no longer ranks a lift at or below zero (D30), so this is the second
 * guard rather than the first — but it is the one that reads the number.
 * Rounding before the comparison keeps -0.04 from printing as `-0.0`.
 */
function points(lift: number): string {
    const rounded = Number(lift.toFixed(1));

    return `${rounded > 0 ? '+' : ''}${rounded.toFixed(1)} points`;
}

/**
 * What an empty ranking means, which is one of two different things (D30).
 *
 * With foods measured, the emptiness is the measurement's answer and saying so
 * is honest. With none measured, nothing was compared at all — and the sentence
 * that reads as a result in the first case asserts one the log has not earned in
 * the second. `thinTags` separates the two ways of measuring nothing: foods
 * logged but never on enough days, and no logged food at all.
 */
function emptyRanking(insight: ConditionInsight): string {
    if (insight.measuredTags > 0) {
        return `${countOf(insight.measuredTags, 'food')} had enough days to compare, and none came out above your baseline. That is a real result, not a gap.`;
    }

    if (insight.thinTags > 0) {
        return 'Nothing could be measured here yet. A food needs enough days with it, and enough days without, before the two can be compared — and nothing in your log clears both bars. Keep logging meals, and a food you eat now and then will add up.';
    }

    return 'Nothing could be measured here yet. No meals are logged against these days, so there is nothing to compare the ratings against.';
}

function timing(peakLag: number | null): string | null {
    if (peakLag === null) {
        return null;
    }

    if (peakLag === 0) {
        return 'strongest the same day';
    }

    return `strongest ${peakLag} ${peakLag === 1 ? 'day' : 'days'} later`;
}

/**
 * The ranked suspects, framed so the ranking cannot be read as a trigger list.
 *
 * The framing is a correctness requirement rather than a tone preference. On a
 * representative ninety-day draw the SUI-36 spike found the top two ranked tags
 * were pure noise and the real triggers sat third and fourth — so a single
 * person's ranking is a set of questions, not findings. Every row therefore
 * carries the days it was measured on, and a row whose lift sits inside the
 * range chance produces says so on its face.
 *
 * `clearsNoiseBand` is decided against the whole report, not the row (D29), so
 * on a log where nothing is a trigger the caveat is expected on every row —
 * including the first. The copy therefore cannot call the row the weakest of
 * the signals here, because most of the time it is all of them.
 *
 * Nothing below baseline reaches this list at all (D30), and an empty list says
 * which of two very different empties it is.
 *
 * Nothing here is coloured by severity and nothing is red: a suspect is
 * something to test, not an alarm (D20).
 */
export function SuspectList({ insights }: SuspectListProps) {
    if (insights.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-6">
            <div className="flex flex-col gap-1">
                <h2 className="text-sm font-medium">Worth noticing</h2>
                <p className="text-sm text-muted-foreground">
                    Patterns that turned up in your log, not conclusions. One
                    person's record cannot reliably tell a real pattern from a
                    coincidence, so treat these as things to try changing — and
                    see what happens.
                </p>
            </div>

            {insights.map((insight) => (
                <div
                    key={insight.conditionId}
                    data-hue={insight.hue}
                    className="flex flex-col gap-3"
                >
                    <div className="flex items-baseline justify-between gap-3">
                        <h3 className="text-sm font-medium">
                            {insight.conditionName}
                        </h3>
                        <span className="text-xs tabular-nums text-muted-foreground">
                            {insight.loggedDays} days rated
                        </span>
                    </div>

                    {insight.suspects.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {emptyRanking(insight)}
                        </p>
                    ) : (
                        <ol className="flex flex-col gap-3">
                            {insight.suspects.map((suspect) => (
                                <li
                                    key={suspect.tags.join('+')}
                                    className="flex flex-col gap-1 rounded-md border border-border px-3 py-2.5"
                                >
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="text-sm">
                                            {headline(suspect)}
                                        </span>
                                        <span className="text-xs tabular-nums text-muted-foreground">
                                            {points(suspect.lift)}
                                        </span>
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        {suspect.exposedDays} days with ·{' '}
                                        {suspect.baselineDays} without
                                        {timing(suspect.peakLag) !== null && (
                                            <> · {timing(suspect.peakLag)}</>
                                        )}
                                    </p>

                                    {suspect.granularity ===
                                        'co_occurrence_cluster' && (
                                        <p className="text-xs text-muted-foreground">
                                            These turn up together in your log,
                                            so this points at the pattern rather
                                            than any one of them.
                                        </p>
                                    )}

                                    {!suspect.clearsNoiseBand && (
                                        <p className="text-xs text-muted-foreground">
                                            Sits inside the range chance alone
                                            produces across everything you log.
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </div>
            ))}
        </section>
    );
}
