import { Link } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useCompanyBrand } from '@/components/brand-logo';
import { areas } from './site-content';

export function PublicSiteHeader({ active }: { active?: string }) {
    const brand = useCompanyBrand();
    const [open, setOpen] = useState(false);
    const button = useRef<HTMLButtonElement>(null);
    const header = useRef<HTMLElement>(null);
    useEffect(() => {
        const close = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                header.current
                    ?.querySelectorAll('details')
                    .forEach((item) => item.removeAttribute('open'));
                button.current?.focus();
            }
        };
        document.addEventListener('keydown', close);

        return () => document.removeEventListener('keydown', close);
    }, []);

    return (
        <header className="av-header" ref={header}>
            <Link href="/" aria-label="Arevantes, inicio">
                <img
                    className="av-logo"
                    src={brand.canonicalLogoUrl}
                    alt="Arevantes"
                    width="182"
                    height="42"
                />
            </Link>
            <button
                ref={button}
                className="av-menu"
                aria-label={open ? 'Cerrar menú' : 'Abrir menú'}
                aria-expanded={open}
                aria-controls="av-navigation"
                onClick={() => setOpen(!open)}
            >
                {open ? <X /> : <Menu />}
            </button>
            <nav
                id="av-navigation"
                className={open ? 'av-nav is-open' : 'av-nav'}
                aria-label="Navegación principal"
            >
                <details>
                    <summary>Servicios</summary>
                    <div className="av-dropdown">
                        <Link href="/servicios" onClick={() => setOpen(false)}>
                            Todos los servicios
                        </Link>
                        {areas.map((area) => (
                            <Link
                                key={area.slug}
                                href={`/servicios/${area.slug}`}
                                onClick={() => setOpen(false)}
                            >
                                {area.name}
                            </Link>
                        ))}
                    </div>
                </details>
                {(
                    [
                        ['Proyectos', '/proyectos'],
                        ['Empresa', '/empresa'],
                        ['Nuestro proceso', '/proceso'],
                        ['Contacto', '/contacto'],
                    ] as const
                ).map(([label, href]) => (
                    <Link
                        key={href}
                        href={href}
                        aria-current={
                            active === href ||
                            (active === 'projects' && href === '/proyectos')
                                ? 'page'
                                : undefined
                        }
                        onClick={() => setOpen(false)}
                    >
                        {label}
                    </Link>
                ))}
                <Link
                    className="av-button"
                    href="/contacto"
                    onClick={() => setOpen(false)}
                >
                    Cuéntanos tu proyecto <span aria-hidden="true">→</span>
                </Link>
            </nav>
        </header>
    );
}
