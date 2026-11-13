import { LayoutGrid, Settings } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as inventory } from '@/routes/inventory';
import { index as products } from '@/routes/products';
import { index as categories } from '@/routes/categories';
import { edit } from '@/routes/profile';
import { index as pos } from '@/routes/pos';
import { index as sales } from '@/routes/sales';
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
                  {
                      title: 'POS',
                      href: pos(team.slug),
                      icon: LayoutGrid,
                  },
                  {
                      title: 'Sales',
                      href: sales(team.slug),
                      icon: LayoutGrid,
                  },
                  {
                      title: 'Catalog',
                      href: products(team.slug),
                      icon: LayoutGrid,
                      matchPaths: [
                          products(team.slug).url,
                          categories(team.slug).url,
                      ],
                  },
                  {
                      title: 'Inventory',
                      href: inventory(team.slug),
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
