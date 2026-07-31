import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ConditionForm } from '@/components/suivre/condition-form';
import {
    ConditionList,
    type ConditionSummary,
} from '@/components/suivre/condition-list';
import type { HueOption } from '@/components/suivre/hue-picker';
import { Separator } from '@/components/ui/separator';
import { index as conditionsIndex } from '@/routes/conditions';
import { CONDITION_HUES } from '@/lib/condition-hue';
import type { ConditionHue } from '@/types';

type ConditionsProps = {
    conditions: ConditionSummary[];
    hues: HueOption[];
};

/**
 * The colour a new condition opens on: the first the user has not spent, so two
 * conditions never arrive the same colour without anyone choosing that.
 */
function unusedHue(conditions: ConditionSummary[]): ConditionHue {
    const taken = new Set(conditions.map((condition) => condition.hue));

    return CONDITION_HUES.find((hue) => !taken.has(hue)) ?? CONDITION_HUES[0];
}

export default function Conditions({ conditions, hues }: ConditionsProps) {
    return (
        <>
            <Head title="Conditions" />

            <h1 className="sr-only">Conditions</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Conditions"
                    description="What you track day to day. Stopping one keeps everything it has already recorded."
                />

                {conditions.length > 0 && (
                    <ConditionList conditions={conditions} hues={hues} />
                )}

                <Separator />

                {/*
                 * Keyed on the hue it opens with so a successful add re-seeds
                 * the form: `useForm` reset() restores its initial values, and
                 * that initial colour is now spent.
                 */}
                <h2 className="text-sm font-medium text-foreground">
                    Add a condition
                </h2>

                <ConditionForm
                    key={unusedHue(conditions)}
                    hues={hues}
                    defaultHue={unusedHue(conditions)}
                    submitLabel="Add condition"
                />
            </div>
        </>
    );
}

Conditions.layout = {
    breadcrumbs: [
        {
            title: 'Conditions',
            href: conditionsIndex(),
        },
    ],
};
