import { Head } from '@inertiajs/react';

export default function Day({ date }: { date: string }) {
    return (
        <>
            <Head title="Day" />
            <div className="flex flex-1 flex-col gap-1 p-6">
                <h1 className="text-lg font-semibold tracking-tight tabular-nums">
                    {date}
                </h1>
                <p className="text-sm text-muted-foreground">
                    The two-tap check-in flow arrives in SUI-7.
                </p>
            </div>
        </>
    );
}
