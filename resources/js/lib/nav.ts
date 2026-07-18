import { CalendarDays, LineChart, Settings } from 'lucide-react';
import { calendar, insights } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

/**
 * The one nav definition. Rendered two ways: a bottom TabBar on mobile and a
 * desktop icon rail. Three destinations, in order.
 */
export const mainNavItems: NavItem[] = [
    {
        title: 'Calendar',
        href: calendar(),
        icon: CalendarDays,
        match: '/calendar',
    },
    {
        title: 'Insights',
        href: insights(),
        icon: LineChart,
        match: '/insights',
    },
    {
        title: 'Settings',
        href: editProfile(),
        icon: Settings,
        match: '/settings',
    },
];
