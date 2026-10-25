import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronRight, Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useCompanyBrand } from '@/components/brand-logo';
import { LanguageSelector } from '@/components/language-selector';

export function PublicSiteHeader({ active }: { active?: 'projects' }) {
    const brand = useCompanyBrand();
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('keydown', close);

        return () => document.removeEventListener('keydown', close);
    }, [open]);

    const links = [
        ['Inicio', '/'],
        ['Servicios', '/#servicios'],
        ['Proyectos', '/proyectos'],
        ['Cómo trabajamos', '/#metodo'],
        ['Contacto', '/#contacto'],
    ] as const;

    return (
        <header className="sticky top-0 z-50 border-b border-zinc-200/70 bg-[#f7f8f4]/90 backdrop-blur-2xl">
            <div className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8 lg:px-10">
                <Link href="/" aria-label="Arevantes, inicio">
                    <img
                        src={brand.logoUrl}
                        alt="Arevantes"
                        className="h-7 w-auto sm:h-8"
                    />
                </Link>
                <nav
                    aria-label="Navegación principal"
                    className="hidden items-center gap-7 text-sm font-medium text-zinc-600 lg:flex"
                >
                    {links.map(([label, href]) => (
                        <Link
                            key={href}
                            href={href}
                            aria-current={
                                active === 'projects' && href === '/proyectos'
                                    ? 'page'
                                    : undefined
                            }
                            className={
                                active === 'projects' && href === '/proyectos'
                                    ? 'text-[#217a3b]'
                                    : 'transition hover:text-[#217a3b]'
                            }
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                <div className="flex items-center gap-2">
                    <LanguageSelector />
                    <Link
                        href="/#contacto"
                        className="hidden items-center gap-2 rounded-full bg-zinc-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#217a3b] sm:inline-flex"
                    >
                        Cuéntanos tu proyecto
                        <ArrowRight className="size-4" />
                    </Link>
                    <button
                        type="button"
                        className="grid size-11 place-items-center rounded-full border border-zinc-300 bg-white text-zinc-900 shadow-sm lg:hidden"
                        aria-label={
                            open ? 'Cerrar navegación' : 'Abrir navegación'
                        }
                        aria-expanded={open}
                        aria-controls="project-site-navigation"
                        onClick={() => setOpen((value) => !value)}
                    >
                        {open ? (
                            <X className="size-5" />
                        ) : (
                            <Menu className="size-5" />
                        )}
                    </button>
                </div>
            </div>
            {open && (
                <div
                    id="project-site-navigation"
                    className="website-menu-enter border-t border-zinc-200/80 bg-[#f7f8f4] px-5 py-5 lg:hidden"
                >
                    <nav className="mx-auto grid max-w-7xl gap-1 text-lg font-semibold">
                        {links.map(([label, href]) => (
                            <Link
                                key={href}
                                href={href}
                                onClick={() => setOpen(false)}
                                className="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-white hover:text-[#217a3b]"
                            >
                                {label}
                                <ChevronRight className="size-4" />
                            </Link>
                        ))}
                    </nav>
                </div>
            )}
        </header>
    );
}
