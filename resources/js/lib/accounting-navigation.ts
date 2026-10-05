import {
    BookOpenText,
    ChartNoAxesCombined,
    FileChartColumn,
    FileText,
    Home,
    Landmark,
    List,
    Settings2,
    ShoppingBag,
    SlidersHorizontal,
    SquareLibrary,
    UsersRound,
    WalletCards,
} from 'lucide-react';
import type { NavItem } from '@/types';

export function accountingNavigation(navigation: NavItem[], year?: number) {
    const leaves = navigation.flatMap((item) => item.children ?? [item]);
    const used = new Set<string>();
    const find = (href: string, title?: string, icon = FileText): NavItem[] => {
        const item = leaves.find((candidate) => candidate.href === href);

        if (!item) {
return [];
}

        used.add(href);

        return [{ ...item, title: title ?? item.title, icon }];
    };
    const journal = leaves.find((item) => item.href === '/accounting');
    const report = (
        view: string,
        title: string,
        icon = BookOpenText,
    ): NavItem[] => {
        if (!journal) {
return [];
}

        used.add('/accounting');
        const params = new URLSearchParams({ view });

        if (year) {
params.set('year', String(year));
}

        return [{ title, href: `/accounting?${params}`, icon }];
    };
    const home = find('/dashboard', 'Inicio', Home);
    const groups = [
        {
            label: 'Contabilidad',
            items: [
                ...find('/finance', 'Vista general', ChartNoAxesCombined),
                ...report('journal', 'Libro Diario', SquareLibrary),
                ...find(
                    '/accounting/accounts',
                    'Plan General Contable',
                    BookOpenText,
                ),
                ...report('trial', 'Balances', ChartNoAxesCombined),
                ...report('profit', 'Cuenta de resultados', FileChartColumn),
                ...report('ledger', 'Libro mayor', BookOpenText),
                ...report('parties', 'Saldos de terceros', UsersRound),
                ...find('/tax/issued', undefined, FileText),
                ...find('/tax/received', undefined, FileText),
            ],
        },
        {
            label: 'Gestión',
            items: [
                ...find('/sales', 'Facturación', FileText),
                ...find('/people', 'Clientes', UsersRound),
                ...find('/suppliers', 'Proveedores', ShoppingBag),
                ...find('/catalog', 'Productos y servicios', ShoppingBag),
                ...find('/expenses', 'Gastos', WalletCards),
                ...find('/banking', 'Bancos', Landmark),
            ],
        },
        {
            label: 'Configuración',
            items: [
                ...find('/accounting/templates', undefined, List),
                ...find('/tax', 'Impuestos', Landmark),
                ...find('/corporate-tax', undefined, Landmark),
                ...report('mappings', 'Mapeos contables', SlidersHorizontal),
                ...find('/company-fiscal', 'Configuración', Settings2),
            ],
        },
    ].filter((group) => group.items.length);
    const remaining = navigation.flatMap((item) => {
        if (!item.children) {
return used.has(String(item.href)) ? [] : [item];
}

        const children = item.children.filter(
            (child) => !used.has(String(child.href)),
        );

        return children.length
            ? [{ ...item, href: children[0].href, children }]
            : [];
    });

    return { home, groups, remaining };
}
