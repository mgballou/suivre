import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /**
     * Path prefix used for active-state matching. Defaults to `href`.
     * Lets a destination stay active across sub-paths (e.g. `/settings`
     * highlights on `/settings/security`).
     */
    match?: NonNullable<InertiaLinkProps['href']>;
};
