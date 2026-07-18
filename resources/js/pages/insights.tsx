import { Head } from '@inertiajs/react';

export default function Insights() {
    return (
        <>
            <Head title="Insights" />
            <div className="flex flex-1 flex-col gap-1 p-6">
                <h1 className="text-lg font-semibold tracking-tight">Insights</h1>
                <p className="text-sm text-muted-foreground">
                    Lag-aware correlations arrive in E4. Suggestive, never proof.
                </p>
            </div>
        </>
    );
}
