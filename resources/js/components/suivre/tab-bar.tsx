import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { mainNavItems } from '@/lib/nav';
import { cn } from '@/lib/utils';

/**
 * Mobile bottom navigation. The desktop rail (AppSidebar) presents the same
 * `mainNavItems`. Motion is colour-only, so it satisfies `prefers-reduced-motion`
 * without special-casing — nothing travels.
 */
export function TabBar({ className }: { className?: string }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <nav
            aria-label="Primary"
            className={cn(
                'fixed inset-x-0 bottom-0 z-50 border-t border-sidebar-border/60 bg-sidebar/95 backdrop-blur',
                className,
            )}
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <ul className="mx-auto flex max-w-lg items-stretch justify-around">
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
                                        ? 'text-primary'
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
        </nav>
    );
}
