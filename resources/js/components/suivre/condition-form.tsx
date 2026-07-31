import { useForm } from '@inertiajs/react';
import { useId } from 'react';
import { HuePicker, type HueOption } from '@/components/suivre/hue-picker';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/conditions';
import type { ConditionHue } from '@/types';

type ConditionFormProps = {
    hues: HueOption[];
    /** Absent when adding; present when editing that condition in place. */
    condition?: { id: number; name: string; hue: ConditionHue };
    defaultHue: ConditionHue;
    submitLabel: string;
    onDone?: () => void;
};

/**
 * Add or rename a condition. The same two fields either way — a condition is a
 * name and a colour, and nothing about editing one is different from creating
 * it, so a second form would only be a place for the two to drift apart.
 */
export function ConditionForm({
    hues,
    condition,
    defaultHue,
    submitLabel,
    onDone,
}: ConditionFormProps) {
    const nameField = useId();

    const form = useForm({
        name: condition?.name ?? '',
        color: condition?.hue ?? defaultHue,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onDone?.();
            },
        };

        if (condition) {
            form.patch(update.url({ condition: condition.id }), options);

            return;
        }

        form.post(store.url(), options);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
                <Label htmlFor={nameField}>Name</Label>
                <Input
                    id={nameField}
                    value={form.data.name}
                    maxLength={60}
                    placeholder="Joint pain"
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
                <InputError message={form.errors.name} />
            </div>

            <HuePicker
                name={`hue-${condition?.id ?? 'new'}`}
                options={hues}
                value={form.data.color}
                onSelect={(color) => form.setData('color', color)}
            />
            <InputError message={form.errors.color} />

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {submitLabel}
                </Button>

                {onDone && (
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                )}
            </div>
        </form>
    );
}
