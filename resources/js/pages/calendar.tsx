import { Head } from '@inertiajs/react';
import { MonthGrid, type MonthGridProps } from '@/components/suivre/month-grid';

export default function Calendar(props: MonthGridProps) {
    return (
        <>
            <Head title={props.label} />
            <div className="flex flex-1 flex-col p-4 sm:p-6">
                <MonthGrid {...props} />
            </div>
        </>
    );
}
