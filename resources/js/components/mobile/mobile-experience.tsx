import type { FormComponentRef } from '@inertiajs/core';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Bell,
    CalendarDays,
    ChevronDown,
    ChevronRight,
    Home,
    LayoutGrid,
    Menu,
    Plus,
    Search,
    Settings,
    UserRound,
    Wrench,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import type { ReactNode } from 'react';
import { ActionCenter } from '@/components/action-center';
import {
    SidebarCompany,
    workspaceNavigationIcons,
} from '@/components/app-sidebar';
import { CommercialFieldPwa } from '@/components/commercial-field-pwa';
import { FavoritesBar } from '@/components/favorites-bar';
import { GlobalSearch } from '@/components/global-search';
import { LanguageSelector } from '@/components/language-selector';
import { MobileTaskForm } from '@/components/mobile/mobile-task-form';
import { PwaUpdate } from '@/components/mobile/pwa-update';
import { quickCreateActions } from '@/components/quick-create';
import { useShellContext } from '@/components/shell-context';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { UserInfo } from '@/components/user-info';
import { useInitials } from '@/hooks/use-initials';
import { useMobileViewport } from '@/hooks/use-mobile-viewport';
import {
    mobileDestinations,
    mobilePlatform,
    mobileTabActive,
    mobileUrl,
    mobileView,
} from '@/lib/mobile/navigation';
import type { MobileView } from '@/lib/mobile/navigation';
import { TaskDraftGuard } from '@/lib/mobile/task-draft-guard';
import { activeNavigation, contextualHref } from '@/lib/workspace-navigation';
import { logout } from '@/routes';

function subscribeEnvironment(callback: () => void) {
    const display = window.matchMedia('(display-mode: standalone)');
    display.addEventListener('change', callback);
    window.addEventListener('online', callback);
    window.addEventListener('offline', callback);

    return () => {
        display.removeEventListener('change', callback);
        window.removeEventListener('online', callback);
        window.removeEventListener('offline', callback);
    };
}
function readEnvironment() {
    const platform = mobilePlatform(
        navigator.userAgent,
        navigator.platform,
        navigator.maxTouchPoints,
        window.matchMedia('(pointer: coarse)').matches,
    );
    const installed =
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone;

    return `${platform}:${navigator.onLine ? 'online' : 'offline'}:${installed ? 'installed' : 'browser'}`;
}

const tabIcons = {
    home: Home,
    agenda: CalendarDays,
    commercial: LayoutGrid,
    operations: Wrench,
};
const titles: Record<MobileView, string> = {
    menu: 'Menú',
    search: 'Buscar',
    notifications: 'Notificaciones',
    profile: 'Tu cuenta',
    create: 'Crear',
};

export function MobileExperience({ children }: { children: ReactNode }) {
    const initials = useInitials();
    const viewport = useMobileViewport();
    const page = usePage();
    const { auth } = page.props;
    useEffect(() => {
        if (!page.encryptHistory) {
            router.replace({
                encryptHistory: true,
                preserveState: true,
                preserveScroll: true,
            });
        }
    }, [page.encryptHistory]);
    const view = mobileView(page.url);
    // Keep the sheet mounted while a history traversal is being confirmed.
    const [createOpen, setCreateOpen] = useState(view === 'create');
    const createActive = useRef(view === 'create');
    const taskForm = useRef<FormComponentRef | null>(null);
    const [confirmDiscard, setConfirmDiscard] = useState(false);
    const [closeNotice, setCloseNotice] = useState('');
    const confirmationReturn = useRef<HTMLElement | null>(null);
    const continueEditingButton = useRef<HTMLButtonElement | null>(null);
    const [taskGuard] = useState(() => new TaskDraftGuard());
    useEffect(() => {
        taskGuard.connect(
            () => ({
                dirty: taskForm.current?.isDirty ?? false,
                processing: taskForm.current?.processing ?? false,
            }),
            (open) => {
                if (open) {
                    confirmationReturn.current =
                        document.activeElement as HTMLElement;
                }

                setConfirmDiscard(open);
            },
            () => taskForm.current?.resetAndClearErrors(),
        );
    }, [taskGuard]);
    const opener = useRef<HTMLButtonElement | null>(null);
    const openedHere = useRef(false);
    const heading = useRef<HTMLHeadingElement>(null);
    const previousView = useRef(view);
    const traversing = useRef(false);
    const environment = useSyncExternalStore(
        subscribeEnvironment,
        readEnvironment,
        () => 'universal:online',
    );
    const [platform, connection] = environment.split(':');
    const offline = connection === 'offline';
    useEffect(() => {
        if (view && view !== 'create') {
            heading.current?.focus();
        } else if (
            !view &&
            previousView.current &&
            previousView.current !== 'create'
        ) {
            // Also runs after native back, which does not call close().
            opener.current?.focus();
        }

        previousView.current = view;
    }, [view]);
    const open = (next: MobileView, target?: HTMLButtonElement) => {
        if (next === 'create') {
            createActive.current = true;
            setCreateOpen(true);
            setCloseNotice('');
        }

        if (target) {
            opener.current = target;
        }

        if (view === next) {
            return;
        }

        if (view) {
            router.replace({
                url: mobileUrl(page.url, next),
                preserveState: true,
                preserveScroll: true,
            });
        } else {
            openedHere.current = true;
            router.push({
                url: mobileUrl(page.url, next),
                preserveState: true,
                preserveScroll: true,
            });
        }
    };
    const close = useCallback(() => {
        if (openedHere.current) {
            openedHere.current = false;
            window.history.back();
        } else {
            router.replace({
                url: mobileUrl(page.url, null),
                preserveState: true,
                preserveScroll: true,
            });
        }

        opener.current?.focus();
    }, [page.url]);
    const finishTaskClose = useCallback(() => {
        createActive.current = false;
        setCreateOpen(false);
        setCloseNotice('');

        if (
            mobileView(window.location.pathname + window.location.search) ===
            'create'
        ) {
            close();
        } else {
            requestAnimationFrame(() => opener.current?.focus());
        }
    }, [close]);
    const requestTaskClose = useCallback(() => {
        if (!createActive.current) {
            return;
        }

        if (taskGuard.requestClose(finishTaskClose) === 'busy') {
            setCloseNotice('Espera a que termine el envío antes de cerrar.');
        }
    }, [taskGuard, finishTaskClose]);
    useEffect(() => {
        const pop = () => {
            traversing.current = true;
        };
        window.addEventListener('popstate', pop);
        const stopNavigate = router.on('navigate', (event) => {
            if (mobileView(event.detail.page.url) === 'create') {
                createActive.current = true;
                setCreateOpen(true);
            }

            if (!traversing.current) {
                return;
            }

            traversing.current = false;

            if (
                !createActive.current ||
                mobileView(event.detail.page.url) === 'create'
            ) {
                return;
            }

            if (!taskForm.current?.isDirty && !taskGuard.busy()) {
                finishTaskClose();

                return;
            }

            // popstate is not cancellable. Retain the mounted form and restore
            // a client-side entry before offering the same close decision.
            router.push({
                url: mobileUrl(event.detail.page.url, 'create'),
                preserveState: true,
                preserveScroll: true,
            });
            requestTaskClose();
        });
        const stopBefore = router.on('before', (event) => {
            if (!createActive.current || event.detail.visit.method !== 'get') {
                return;
            }

            if (!taskForm.current?.isDirty && !taskGuard.busy()) {
                createActive.current = false;
                setCreateOpen(false);

                return;
            }

            event.preventDefault();
            const visit = event.detail.visit;

            if (
                taskGuard.requestClose(() => {
                    createActive.current = false;
                    setCreateOpen(false);
                    router.visit(visit.url, visit);
                }) === 'busy'
            ) {
                setCloseNotice(
                    'Espera a que termine el envío antes de cerrar.',
                );
            }
        });
        const unload = (event: BeforeUnloadEvent) => {
            if (
                createActive.current &&
                (taskForm.current?.isDirty || taskGuard.busy())
            ) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', unload);

        return () => {
            stopNavigate();
            stopBefore();
            window.removeEventListener('popstate', pop);
            window.removeEventListener('beforeunload', unload);
        };
    }, [taskGuard, finishTaskClose, requestTaskClose]);
    useEffect(() => {
        if (!view || view === 'create') {
            return;
        }

        const escape = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !event.defaultPrevented) {
                event.preventDefault();
                close();
            }
        };
        window.addEventListener('keydown', escape);

        return () => window.removeEventListener('keydown', escape);
    }, [view, close]);

    const destinations = mobileDestinations(auth.navigation);
    const allowedActions = quickCreateActions.filter((action) =>
        action.abilities.every((key) => auth.abilities[key]),
    );
    const canCreate =
        auth.abilities.manageOperations || allowedActions.length > 0;
    const notifications = auth.notifications ?? [];
    const isDetail = /\/(\d+)(?:\/|$)/.test(page.url.split('?')[0]);

    return (
        <div
            className="mobile-app"
            data-platform={platform}
            data-display={environment.split(':')[2]}
            data-keyboard={viewport.keyboard || undefined}
        >
            <PwaUpdate />
            <header className="mobile-topbar">
                {isDetail && !view ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Volver"
                        onClick={() => window.history.back()}
                    >
                        <ArrowLeft />
                    </Button>
                ) : null}
                <Link
                    href="/dashboard"
                    className="mobile-wordmark"
                    aria-label="AREVANTES · Inicio"
                >
                    AREVANTES
                </Link>
                <div className="mobile-header-actions">
                    {(auth.abilities.manageCustomers ||
                        auth.abilities.viewInstallations ||
                        auth.abilities.manageInstallations ||
                        auth.abilities.manageCatalog ||
                        auth.abilities.viewPurchases ||
                        auth.abilities.manageOperations) && (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Búsqueda global"
                            onClick={(e) => open('search', e.currentTarget)}
                        >
                            <Search />
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="icon"
                        className="relative"
                        aria-label="Notificaciones"
                        onClick={(e) => open('notifications', e.currentTarget)}
                    >
                        <Bell />
                        {notifications.length > 0 && (
                            <span
                                className="mobile-count"
                                aria-label="Hay avisos no leídos"
                            >
                                •
                            </span>
                        )}
                    </Button>
                    <Button
                        variant="ghost"
                        className="mobile-avatar"
                        aria-label={`Cuenta de ${auth.user.name}`}
                        onClick={(e) => open('profile', e.currentTarget)}
                    >
                        {initials(auth.user.name)}
                    </Button>
                </div>
            </header>
            {offline && (
                <p role="status" className="mobile-offline">
                    Sin conexión. No podrás guardar hasta recuperar internet.
                </p>
            )}
            <div
                hidden={Boolean(view && view !== 'create')}
                inert={Boolean(view && view !== 'create')}
                className="mobile-page-content"
            >
                {children}
            </div>
            {view && view !== 'create' && (
                <section
                    className="mobile-screen"
                    aria-labelledby="mobile-screen-title"
                >
                    <div className="mobile-screen-heading">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Volver"
                            onClick={close}
                        >
                            <ArrowLeft />
                        </Button>
                        <h1
                            ref={heading}
                            tabIndex={-1}
                            id="mobile-screen-title"
                        >
                            {titles[view]}
                        </h1>
                    </div>
                    {view === 'menu' && <MobileModuleMenu />}
                    {view === 'search' && <GlobalSearch expanded embedded />}
                    {view === 'notifications' && <MobileNotifications />}
                    {view === 'profile' && <MobileProfile />}
                </section>
            )}
            <Sheet
                open={createOpen || view === 'create'}
                onOpenChange={(value) => {
                    if (!value) {
                        requestTaskClose();
                    }
                }}
            >
                <SheetContent
                    side="bottom"
                    className="mobile-action-sheet"
                    style={
                        viewport.height
                            ? {
                                  maxHeight: `${Math.max(200, viewport.height - 12)}px`,
                              }
                            : undefined
                    }
                    onInteractOutside={(event) => {
                        event.preventDefault();
                        requestTaskClose();
                    }}
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        opener.current?.focus();
                    }}
                >
                    <div className="mobile-sheet-handle" aria-hidden="true" />
                    <SheetHeader>
                        <SheetTitle>
                            {auth.abilities.manageOperations
                                ? 'Crear tarea'
                                : 'Crear'}
                        </SheetTitle>
                        <SheetDescription>
                            Acciones disponibles para tu perfil.
                        </SheetDescription>
                    </SheetHeader>
                    {auth.abilities.manageOperations && (
                        <MobileTaskForm
                            formRef={taskForm}
                            onBeforeSubmit={() => taskGuard.beginSubmit()}
                            onFinishSubmit={() => taskGuard.finishSubmit()}
                            offline={offline}
                            onSaved={() => {
                                taskGuard.saved(finishTaskClose);
                            }}
                        />
                    )}
                    {closeNotice && <p role="status">{closeNotice}</p>}
                    {allowedActions.length > 0 && (
                        <details
                            className="mobile-more-actions"
                            open={!auth.abilities.manageOperations}
                        >
                            <summary>Otras acciones</summary>
                            <div className="mobile-action-list">
                                {allowedActions.map((action) => (
                                    <Link key={action.href} href={action.href}>
                                        <action.icon />
                                        <span>{action.label}</span>
                                        <ChevronRight />
                                    </Link>
                                ))}
                            </div>
                        </details>
                    )}
                </SheetContent>
            </Sheet>
            <Dialog
                open={confirmDiscard}
                onOpenChange={(value) => {
                    if (!value) {
                        taskGuard.keepEditing();
                    }
                }}
            >
                <DialogContent
                    className="mobile-discard-dialog"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        requestAnimationFrame(() =>
                            continueEditingButton.current?.focus(),
                        );
                    }}
                    onEscapeKeyDown={(event) => {
                        event.preventDefault();
                        taskGuard.keepEditing();
                    }}
                    onPointerDownOutside={(event) => event.preventDefault()}
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();

                        if (createActive.current) {
                            const target = confirmationReturn.current;

                            if (
                                target?.isConnected &&
                                target.closest('.mobile-action-sheet')
                            ) {
                                target.focus();
                            } else {
                                document
                                    .getElementById('mobile-task-title')
                                    ?.focus();
                            }
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>¿Descartar los cambios?</DialogTitle>
                        <DialogDescription>
                            La tarea tiene cambios sin guardar. Si los
                            descartas, no podrás recuperarlos.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            ref={continueEditingButton}
                            variant="outline"
                            onClick={() => taskGuard.keepEditing()}
                        >
                            Seguir editando
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => taskGuard.discard()}
                        >
                            Descartar cambios
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            {!isDetail &&
                !/[?&](create|edit)=/.test(page.url) &&
                canCreate &&
                !/\/(create|edit)(?:\?|$)/.test(page.url) && (
                    <button
                        type="button"
                        className="mobile-fab"
                        hidden={Boolean(view)}
                        aria-label="Crear"
                        onClick={(e) => open('create', e.currentTarget)}
                    >
                        <Plus />
                    </button>
                )}
            <nav className="mobile-tabbar" aria-label="Navegación móvil">
                {destinations.map((item) => {
                    const Icon = tabIcons[item.key as keyof typeof tabIcons];
                    const active =
                        !view && mobileTabActive(page.url, item.href);

                    return (
                        <Link
                            key={item.key}
                            href={item.href}
                            aria-current={active ? 'page' : undefined}
                        >
                            <span>
                                <Icon />
                            </span>
                            {item.title}
                        </Link>
                    );
                })}
                <button
                    type="button"
                    aria-current={view === 'menu' ? 'page' : undefined}
                    onClick={(e) => open('menu', e.currentTarget)}
                >
                    <span>
                        <Menu />
                    </span>
                    Menú
                </button>
            </nav>
        </div>
    );
}

function MobileModuleMenu() {
    const page = usePage();
    const { year } = useShellContext();
    const [query, setQuery] = useState('');
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});
    const selected = activeNavigation(page.props.auth.navigation, page.url);
    const all = page.props.auth.navigation;
    const filter = query.trim().toLocaleLowerCase();

    return (
        <div className="mobile-module-menu">
            <label className="mobile-menu-search">
                <Search />
                <input
                    type="search"
                    placeholder="Buscar módulos y secciones…"
                    aria-label="Buscar módulos"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
            </label>
            <section className="mobile-menu-favorites">
                <h2>Favoritos</h2>
                <FavoritesBar />
            </section>
            <nav aria-label="Módulos de AREVANTES" className="mobile-modules">
                {all
                    .filter(
                        (item) =>
                            !filter ||
                            `${item.title} ${item.children?.map((child) => child.title).join(' ') ?? ''}`
                                .toLocaleLowerCase()
                                .includes(filter),
                    )
                    .map((item, index) => {
                        const Icon = item.icon
                            ? (workspaceNavigationIcons[item.icon] ??
                              LayoutGrid)
                            : LayoutGrid;
                        const key = item.id ?? item.code ?? item.href;
                        const active =
                            item.id === selected ||
                            item.children?.some(
                                (child) => child.id === selected,
                            );
                        const open = Boolean(
                            filter ||
                            expanded[key] ||
                            item.children?.some(
                                (child) => child.id === selected,
                            ),
                        );

                        return (
                            <section key={key}>
                                <div
                                    className="mobile-module-row"
                                    data-active={active || undefined}
                                >
                                    <span
                                        className={`mobile-module-icon mobile-module-color-${index % 6}`}
                                    >
                                        <Icon />
                                    </span>
                                    {item.navigable ||
                                    !item.children?.length ? (
                                        <Link
                                            href={contextualHref(
                                                item.href,
                                                year,
                                            )}
                                            aria-current={
                                                item.id === selected
                                                    ? 'page'
                                                    : undefined
                                            }
                                        >
                                            {item.title}
                                        </Link>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setExpanded((prev) => ({
                                                    ...prev,
                                                    [key]: !open,
                                                }))
                                            }
                                        >
                                            {item.title}
                                        </button>
                                    )}
                                    {item.children?.length ? (
                                        <button
                                            type="button"
                                            aria-label={`${open ? 'Contraer' : 'Expandir'} ${item.title}`}
                                            aria-expanded={open}
                                            aria-controls={`mobile-module-${key}`}
                                            onClick={() =>
                                                setExpanded((prev) => ({
                                                    ...prev,
                                                    [key]: !open,
                                                }))
                                            }
                                        >
                                            <ChevronDown
                                                className={
                                                    open ? '' : '-rotate-90'
                                                }
                                            />
                                        </button>
                                    ) : (
                                        <ChevronRight />
                                    )}
                                </div>
                                {item.children?.length && open && (
                                    <div
                                        id={`mobile-module-${key}`}
                                        className="mobile-module-children"
                                    >
                                        {item.children.map((child) => (
                                            <Link
                                                key={child.id ?? child.href}
                                                href={contextualHref(
                                                    child.href,
                                                    year,
                                                )}
                                                aria-current={
                                                    child.id === selected
                                                        ? 'page'
                                                        : undefined
                                                }
                                            >
                                                {child.title}
                                                <ChevronRight />
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </section>
                        );
                    })}
                {filter &&
                    !all.some((item) =>
                        `${item.title} ${item.children?.map((child) => child.title).join(' ') ?? ''}`
                            .toLocaleLowerCase()
                            .includes(filter),
                    ) && (
                        <p role="status">No hay módulos para esta búsqueda.</p>
                    )}
            </nav>
        </div>
    );
}

function MobileNotifications() {
    const { auth } = usePage().props;
    const [saving, setSaving] = useState(false);

    return (
        <div className="mobile-notifications">
            <p className="mobile-capability-note">
                Últimos avisos no leídos de tu cuenta. El historial completo y
                las prioridades están pendientes de integración.
            </p>
            {auth.notifications.length ? (
                <>
                    <Button
                        disabled={saving}
                        variant="outline"
                        onClick={() => {
                            setSaving(true);
                            router.post(
                                '/notifications/read',
                                {},
                                {
                                    preserveScroll: true,
                                    onFinish: () => setSaving(false),
                                },
                            );
                        }}
                    >
                        Marcar todos como leídos
                    </Button>
                    <ul>
                        {auth.notifications.map((item) => (
                            <li key={item.id}>
                                <Link href={item.url}>
                                    <Bell />
                                    <span>{item.message}</span>
                                    <ChevronRight />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <p className="mobile-empty">No tienes avisos pendientes.</p>
            )}
        </div>
    );
}

function MobileProfile() {
    const { auth } = usePage().props;

    return (
        <div className="mobile-profile">
            <div className="mobile-profile-user">
                <UserInfo user={auth.user} showEmail />
            </div>
            <Link href="/settings/profile">
                <UserRound /> Perfil <ChevronRight />
            </Link>
            <div className="mobile-company">
                <SidebarCompany />
            </div>
            <Link href="/settings/appearance">
                <Settings /> Preferencias <ChevronRight />
            </Link>
            <LanguageSelector />
            {(auth.abilities.viewInstallations ||
                auth.abilities.manageOperations ||
                auth.abilities.managePurchases ||
                auth.abilities.approvePurchaseOrders ||
                auth.abilities.viewFinance) && <ActionCenter />}

            <CommercialFieldPwa />
            <Link
                href={logout()}
                as="button"
                onClick={() => {
                    router.flushAll();
                    router.clearHistory();
                }}
                className="mobile-logout"
            >
                Cerrar sesión
            </Link>
        </div>
    );
}
