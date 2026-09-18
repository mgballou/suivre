import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { mainNavItems } from '@/lib/nav';
import { cn } from '@/lib/utils';

/**
 * Mobile bottom navigation. The desktop rail (AppSidebar) presents the same
 * `mainNavItems`.
 *
 * The bar is real glass (D28): it genuinely overlays the page it sits above, so
 * translucency here says something true about depth. Its contrast is proven
 * against the composite over the worst backdrop it can sit over, in
 * MaterialLayerTest — not against the token's nominal fill.
 *
 * The active indicator is two pills at different speeds inside one gooey group.
 * At rest they coincide and read as a single shape; mid-travel they separate
 * and the filter fuses the gap into a short ligature. Under reduced motion both
 * transitions are removed, so the pill appears on the new tab without
 * travelling — the filter is static and has nothing left to act on.
 *
 * The label is `text-foreground`, not `text-primary`: petrol measures 3.99:1 on
 * the indicator and fails AA. The pill is what says "you are here".
 */
export function TabBar({ className }: { className?: string }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const activeIndex = mainNavItems.findIndex((item) =>
        isCurrentOrParentUrl(item.match ?? item.href),
    );

    return (
        <nav
            aria-label="Primary"
            className={cn('glass fixed inset-x-0 bottom-0 z-50 border-t', className)}
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <div className="relative mx-auto max-w-lg">
                {activeIndex >= 0 && (
                    <div
                        aria-hidden
                        data-slot="tab-indicator"
                        className="pointer-events-none absolute inset-y-1 left-0 w-full"
                        style={
                            {
                                '--tab-index': activeIndex,
                                '--tab-count': mainNavItems.length,
                                filter: 'url(#gooey)',
                            } as React.CSSProperties
                        }
                    >
                        <span
                            className={cn(
                                'absolute inset-y-0 rounded-md bg-[var(--tab-indicator)]',
                                'transition-[left] duration-[var(--dur-spatial)] ease-quiet',
                                'motion-reduce:transition-none',
                            )}
                            style={{
                                width: 'calc(100% / var(--tab-count) - 0.5rem)',
                                left: 'calc(var(--tab-index) * (100% / var(--tab-count)) + 0.25rem)',
                            }}
                        />
                        <span
                            className={cn(
                                'absolute inset-y-0 rounded-md bg-[var(--tab-indicator)]',
                                'transition-[left] duration-[var(--dur-base)] ease-quiet',
                                'motion-reduce:transition-none',
                            )}
                            style={{
                                width: 'calc(100% / var(--tab-count) - 0.5rem)',
                                left: 'calc(var(--tab-index) * (100% / var(--tab-count)) + 0.25rem)',
                            }}
                        />
                    </div>
                )}

                <ul className="relative flex items-stretch justify-around">
                    {mainNavItems.map((item) => {
                        const active = isCurrentOrParentUrl(item.match ?? item.href);
                        const Icon = item.icon;

                        return (
                            <li key={item.title} className="flex-1">
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex min-h-11 min-w-11 flex-col items-center justify-center gap-1 px-2 py-2 text-xs font-medium',
                                        'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                        active
                                            ? 'text-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {Icon && <Icon className="size-5" aria-hidden />}
                                    <span>{item.title}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </nav>
    );
}
