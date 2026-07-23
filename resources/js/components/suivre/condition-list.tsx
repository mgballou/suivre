import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConditionForm } from '@/components/suivre/condition-form';
import type { HueOption } from '@/components/suivre/hue-picker';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { activation } from '@/routes/conditions';
import type { ConditionHue } from '@/types';

export type ConditionSummary = {
    id: number;
    name: string;
    hue: ConditionHue;
    isActive: boolean;
    ratings: number;
    flares: number;
};

type ConditionListProps = {
    conditions: ConditionSummary[];
    hues: HueOption[];
};

function history(condition: ConditionSummary): string {
    const parts = [
        `${condition.ratings} ${condition.ratings === 1 ? 'rating' : 'ratings'}`,
    ];

    if (condition.flares > 0) {
        parts.push(
            `${condition.flares} ${condition.flares === 1 ? 'flare' : 'flares'}`,
        );
    }

    return parts.join(' · ');
}

/**
 * Everything the user tracks, stopped ones included.
 *
 * Stopping is the only way a condition leaves the journal — there is no delete,
 * here or on the server — so a stopped row keeps its record count on show: the
 * count is the evidence that stopping cost nothing.
 */
export function ConditionList({ conditions, hues }: ConditionListProps) {
    const [editing, setEditing] = useState<number | null>(null);

    const setActive = (condition: ConditionSummary, isActive: boolean) => {
        router.put(
            activation.url({ condition: condition.id }),
            { is_active: isActive },
            { preserveScroll: true },
        );
    };

    return (
        <ul className="flex flex-col gap-2">
            {conditions.map((condition) => (
                <li
                    key={condition.id}
                    data-hue={condition.hue}
                    className="rounded-md border border-border px-3 py-3"
                >
                    {editing === condition.id ? (
                        <ConditionForm
                            hues={hues}
                            condition={condition}
                            defaultHue={condition.hue}
                            submitLabel="Save"
                            onDone={() => setEditing(null)}
                        />
                    ) : (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span
                                aria-hidden
                                className={cn(
                                    'size-3 shrink-0 rounded-full bg-condition-5',
                                    !condition.isActive && 'opacity-40',
                                )}
                            />

                            <div className="flex flex-1 flex-col">
                                <span
                                    className={cn(
                                        'text-sm font-medium',
                                        condition.isActive
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {condition.name}
                                </span>
                                <span className="text-xs tabular-nums text-muted-foreground">
                                    {condition.isActive
                                        ? history(condition)
                                        : `Stopped · ${history(condition)} kept`}
                                </span>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setEditing(condition.id)}
                            >
                                Edit
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    setActive(condition, !condition.isActive)
                                }
                            >
                                {condition.isActive ? 'Stop' : 'Resume'}
                            </Button>
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}
