import { router, usePage } from '@inertiajs/react';
import {
    ClipboardPlus,
    FilePlus2,
    Plus,
    ReceiptText,
    ShoppingCart,
    Target,
    UserPlus,
    Wrench,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { AuthAbilities } from '@/types';

export const quickCreateActions = [
    {
        label: 'Customer',
        href: '/people/create',
        icon: UserPlus,
        group: 'CRM',
        abilities: ['manageCustomers'],
    },
    {
        label: 'Opportunity',
        href: '/opportunities?create=1',
        icon: Target,
        group: 'CRM',
        abilities: ['manageCustomers'],
    },
    {
        label: 'Installation',
        href: '/installations/create',
        icon: Wrench,
        group: 'CRM',
        abilities: ['manageInstallations'],
    },
    {
        label: 'Quote',
        href: '/quotes/create',
        icon: FilePlus2,
        group: 'CRM',
        abilities: ['manageInstallations'],
    },
    {
        label: 'Work order',
        href: '/work-orders?create=1',
        icon: ClipboardPlus,
        group: 'Operations',
        abilities: ['manageOperations', 'manageInstallations'],
    },
    {
        label: 'Purchase',
        href: '/purchases?create=1',
        icon: ShoppingCart,
        group: 'Supply',
        abilities: ['managePurchases'],
    },
    {
        label: 'Expense',
        href: '/expenses?create=1',
        icon: ReceiptText,
        group: 'Supply',
        abilities: ['manageCatalog', 'viewFinance'],
    },
] as const satisfies ReadonlyArray<{
    label: string;
    href: string;
    icon: typeof UserPlus;
    group: string;
    abilities: ReadonlyArray<keyof AuthAbilities>;
}>;

export function QuickCreate() {
    const { abilities } = usePage().props.auth;
    const allowedActions = quickCreateActions.filter((action) =>
        action.abilities.every((ability) => abilities[ability]),
    );

    if (allowedActions.length === 0) {
        return null;
    }

    const groups = [...new Set(allowedActions.map((action) => action.group))];

    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            size="icon"
                            className="size-8 rounded-full bg-primary"
                            aria-label="Create new"
                        >
                            <Plus className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>Create new</TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end" className="w-52">
                {groups.map((group, index) => (
                    <div key={group}>
                        {index > 0 && <DropdownMenuSeparator />}
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            {group}
                        </DropdownMenuLabel>
                        {allowedActions
                            .filter((action) => action.group === group)
                            .map((action) => (
                                <DropdownMenuItem
                                    key={action.href}
                                    onSelect={() => router.visit(action.href)}
                                >
                                    <action.icon />
                                    {action.label}
                                </DropdownMenuItem>
                            ))}
                    </div>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
