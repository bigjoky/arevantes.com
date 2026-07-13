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
import type { User } from '@/types';

const actions = [
    { label: 'Customer', href: '/people/create', icon: UserPlus, group: 'CRM' },
    {
        label: 'Opportunity',
        href: '/opportunities?create=1',
        icon: Target,
        group: 'CRM',
    },
    {
        label: 'Installation',
        href: '/installations/create',
        icon: Wrench,
        group: 'CRM',
    },
    {
        label: 'Quote',
        href: '/quotes/create',
        icon: FilePlus2,
        group: 'CRM',
    },
    {
        label: 'Work order',
        href: '/work-orders?create=1',
        icon: ClipboardPlus,
        group: 'Operations',
    },
    {
        label: 'Purchase',
        href: '/purchases?create=1',
        icon: ShoppingCart,
        group: 'Supply',
    },
    {
        label: 'Expense',
        href: '/expenses?create=1',
        icon: ReceiptText,
        group: 'Supply',
    },
] as const;

export function QuickCreate() {
    const { user } = usePage<{ auth: { user: User } }>().props.auth;

    if (user.role === 'technician') {
        return null;
    }

    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            size="icon"
                            className="size-9"
                            aria-label="Create new"
                        >
                            <Plus className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>Create new</TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end" className="w-52">
                {['CRM', 'Operations', 'Supply'].map((group, index) => (
                    <div key={group}>
                        {index > 0 && <DropdownMenuSeparator />}
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            {group}
                        </DropdownMenuLabel>
                        {actions
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
