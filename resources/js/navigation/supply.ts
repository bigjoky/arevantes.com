import { ShoppingCart } from 'lucide-react';
import type { NavItem, PlatformAbilities } from '@/types';

export function supplyNavigation(abilities: PlatformAbilities): NavItem[] {
    if (!abilities.viewPurchases && !abilities.manageCatalog) {
        return [];
    }

    const children: NavItem[] = [];

    if (abilities.manageCatalog) {
        children.push(
            { title: 'Catalog', href: '/catalog' },
            { title: 'Inventory', href: '/inventory' },
            { title: 'Stock counts', href: '/inventory/counts' },
        );
    }

    if (abilities.managePurchases) {
        children.push({ title: 'Document inbox', href: '/purchase-inbox' });
    }

    if (abilities.viewPurchases) {
        children.push(
            { title: 'Purchases', href: '/purchases' },
            { title: 'Order control', href: '/supply-control' },
        );
    }

    if (abilities.managePurchases) {
        children.push(
            { title: 'Supply analytics', href: '/supply-analytics' },
            { title: 'Supplier intelligence', href: '/supplier-intelligence' },
            { title: 'Replenishment', href: '/replenishment' },
        );
    }

    if (abilities.viewPurchases) {
        children.push({ title: 'Quality claims', href: '/supplier-quality' });
    }

    if (abilities.manageCatalog) {
        children.push({ title: 'Expenses', href: '/expenses' });
    }

    return [
        {
            title: 'Supply',
            href: abilities.manageCatalog ? '/catalog' : '/purchases',
            icon: ShoppingCart,
            children,
        },
    ];
}
