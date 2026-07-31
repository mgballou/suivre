export type LoggedTag = {
    name: string;
    slug: string;
    days: number;
};

type LoggedTagsProps = {
    tags: LoggedTag[];
    windowDays: number;
};

/**
 * What the user ate most over the window, as counts and nothing else.
 *
 * The bar is a share of the window, not a share of the largest tag — so a month
 * where nothing was eaten often reads as a page of short bars rather than one
 * full bar and some stubs. Normalising to the leader would invent a hierarchy
 * out of thin data.
 *
 * Nothing here is set against a condition. The counts describe the diet; the
 * moment one is placed beside a symptom it becomes a claim, and that claim is
 * exactly what the readiness threshold exists to hold back.
 */
export function LoggedTags({ tags, windowDays }: LoggedTagsProps) {
    if (tags.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nothing to summarise yet. Once meals are logged and matched to
                the food catalogue, what you ate most shows up here.
            </p>
        );
    }

    return (
        <ul className="flex flex-col gap-3">
            {tags.map((tag) => (
                <li key={tag.slug} className="flex flex-col gap-1.5">
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="text-sm">{tag.name}</span>
                        <span className="text-xs tabular-nums text-muted-foreground">
                            {tag.days} of {windowDays} days
                        </span>
                    </div>

                    <div className="h-1 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-intensity-4"
                            style={{
                                width: `${(tag.days / windowDays) * 100}%`,
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}
