import { LayoutGrid, Settings } from 'lucide-react';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';
import type { Team, NavItem } from '@/types';
export function getShellNavItems(team: Team | null): NavItem[] {
    return [
        ...(team?.role === 'owner'
            ? [
                  {
                      title: 'Dashboard',
                      href: dashboard(team.slug),
                      icon: LayoutGrid,
                  },
              ]
            : []),
        {
            title: 'Settings',
            href: edit(),
            icon: Settings,
            matchPaths: ['/settings'],
        },
    ];
}
