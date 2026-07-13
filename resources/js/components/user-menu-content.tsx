import { Form, Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpen,
    DatabaseBackup,
    LogOut,
    ScrollText,
    Settings,
    UsersRound,
    Landmark,
} from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const notifications = usePage<{
        auth: {
            notifications: Array<{ id: string; message: string; url: string }>;
        };
    }>().props.auth.notifications;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            {notifications.length ? (
                <>
                    <DropdownMenuGroup>
                        <DropdownMenuLabel className="flex items-center gap-2 text-xs">
                            <Bell className="size-3" /> Alerts
                        </DropdownMenuLabel>
                        {notifications.map((notification) => (
                            <DropdownMenuItem key={notification.id} asChild>
                                <Link
                                    href={notification.url}
                                    className="block max-w-64 text-xs whitespace-normal"
                                >
                                    {notification.message}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href="/company-fiscal"
                                onClick={cleanup}
                            >
                                <Landmark className="mr-2" /> Empresa y
                                fiscalidad
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Form action="/notifications/read" method="post">
                                <button
                                    type="submit"
                                    className="w-full text-left text-xs"
                                >
                                    Mark all as read
                                </button>
                            </Form>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                </>
            ) : null}
            {user.role === 'admin' ? (
                <>
                    <DropdownMenuGroup>
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Management
                        </DropdownMenuLabel>
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href="/staff"
                                onClick={cleanup}
                            >
                                <UsersRound className="mr-2" /> Team & access
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href="/admin/backups"
                                onClick={cleanup}
                            >
                                <DatabaseBackup className="mr-2" /> Backups
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href="/admin/audit"
                                onClick={cleanup}
                            >
                                <ScrollText className="mr-2" /> Audit trail
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href="/knowledge"
                                onClick={cleanup}
                            >
                                <BookOpen className="mr-2" /> Arevia knowledge
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                </>
            ) : null}
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
