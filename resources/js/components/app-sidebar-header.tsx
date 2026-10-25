import { usePage } from '@inertiajs/react';
import { Menu, MoreHorizontal } from 'lucide-react';
import { ActionCenter } from '@/components/action-center';
import { AreviaChat } from '@/components/arevia-chat';
import BrandLogo from '@/components/brand-logo';
import { FavoritesBar } from '@/components/favorites-bar';
import { GlobalSearch } from '@/components/global-search';
import { LanguageSelector } from '@/components/language-selector';
import { NavUser } from '@/components/nav-user';
import { QuickCreate } from '@/components/quick-create';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';

export function AppSidebarHeader() {
    const { isMobile, toggleSidebar, openMobile, mobileTriggerRef } =
        useSidebar();
    const { abilities } = usePage().props.auth;
    const canSearch =
        abilities.manageCustomers ||
        abilities.viewInstallations ||
        abilities.manageInstallations ||
        abilities.manageOperations ||
        abilities.manageCatalog ||
        abilities.viewPurchases;
    const hasActionCenter =
        abilities.viewInstallations ||
        abilities.manageOperations ||
        abilities.managePurchases ||
        abilities.approvePurchaseOrders ||
        abilities.viewFinance;

    return (
        <header className="shell-topbar">
            {isMobile ? (
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={toggleSidebar}
                    ref={mobileTriggerRef}
                    aria-expanded={openMobile}
                    aria-haspopup="dialog"
                    aria-label="Mostrar u ocultar la barra lateral"
                >
                    <Menu className="size-4" />
                </Button>
            ) : (
                <SidebarTrigger />
            )}
            <BrandLogo className="shell-mobile-wordmark h-6 max-w-32" />
            <div className="shell-search">
                {canSearch && <GlobalSearch expanded />}
            </div>
            <div className="shell-topbar-actions">
                <div className="hidden sm:block">
                    <QuickCreate />
                </div>
                {hasActionCenter && <ActionCenter />}
                <NavUser placement="header" />
                <Sheet>
                    <SheetTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Más herramientas"
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent className="overflow-y-auto">
                        <SheetHeader>
                            <SheetTitle>
                                Herramientas del espacio de trabajo
                            </SheetTitle>
                            <SheetDescription>
                                Idioma, favoritos y accesos disponibles para tu
                                perfil.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="space-y-4 p-4">
                            <LanguageSelector />
                            <div className="sm:hidden">
                                <QuickCreate />
                            </div>
                            {abilities.useAi && <AreviaChat />}
                            <FavoritesBar />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </header>
    );
}
