import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
import {
    ScalePicker,
    type ScaleOption,
} from '@/components/suivre/scale-picker';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { checkin as recordCheckin } from '@/routes/day';
import type { IsoDate } from '@/types';

export type CheckinValues = {
    mood: number | null;
    sleep: number | null;
    stress: number | null;
    note: string | null;
};

export type CheckinScales = {
    mood: ScaleOption[];
    sleep: ScaleOption[];
    stress: ScaleOption[];
};

type CheckinFormProps = {
    date: IsoDate;
    values: CheckinValues;
    scales: CheckinScales;
};

/** Mirrors the server's note handling so a blur cannot post a no-op change. */
function normalise(note: string | null): string | null {
    const trimmed = note?.trim() ?? '';

    return trimmed === '' ? null : trimmed;
}

/**
 * The check-in itself. Every scale writes on tap — that tap is the second of
 * the two the ticket is named for, so there is no save button between the
 * gesture and the record.
 *
 * The draft is local state seeded from the server's saved values, which keeps
 * the selection immediate while the round-trip is in flight. The caller must
 * key this component by date, because Inertia re-renders the day page in place
 * when navigating between days and stale state would otherwise carry over.
 *
 * The note is the exception: it saves on blur rather than per keystroke, since
 * a request per character is friction dressed as responsiveness.
 */
export function CheckinForm({ date, values, scales }: CheckinFormProps) {
    const [draft, setDraft] = useState<CheckinValues>(values);
    const noteField = useId();

    const save = (next: CheckinValues): void => {
        setDraft(next);

        router.post(
            recordCheckin.url({ date }),
            { ...next },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div className="flex flex-col gap-8">
            <ScalePicker
                name="mood"
                label="Mood"
                options={scales.mood}
                value={draft.mood}
                onSelect={(mood) => save({ ...draft, mood })}
            />

            <ScalePicker
                name="sleep"
                label="Sleep"
                options={scales.sleep}
                value={draft.sleep}
                onSelect={(sleep) => save({ ...draft, sleep })}
            />

            <ScalePicker
                name="stress"
                label="Stress"
                options={scales.stress}
                value={draft.stress}
                onSelect={(stress) => save({ ...draft, stress })}
            />

            <div className="flex flex-col gap-2">
                <Label htmlFor={noteField}>Note</Label>
                <Textarea
                    id={noteField}
                    rows={3}
                    value={draft.note ?? ''}
                    placeholder="Anything worth remembering about today."
                    onChange={(event) =>
                        setDraft({ ...draft, note: event.target.value })
                    }
                    onBlur={() => {
                        const note = normalise(draft.note);

                        if (note !== normalise(values.note)) {
                            save({ ...draft, note });
                        }
                    }}
                />
            </div>
        </div>
    );
}
