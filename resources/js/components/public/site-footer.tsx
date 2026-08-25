import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';

export function PublicSiteFooter() {
    return (
        <footer className="px-5 py-12 sm:px-8">
            <div className="mx-auto flex max-w-7xl flex-col gap-8 border-t border-zinc-200 pt-10 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <Link href="/">
                        <img
                            src="/brand/arevantes-logo-light.svg"
                            alt="Arevantes"
                            className="h-7 w-auto"
                        />
                    </Link>
                    <p className="mt-4 max-w-sm text-sm leading-6 text-zinc-600">
                        Energía, electricidad y seguridad con un único equipo
                        responsable.
                    </p>
                </div>
                <div className="flex flex-col gap-2 text-sm text-zinc-600 sm:items-end">
                    <span className="flex items-center gap-2">
                        <MapPin className="size-4 text-[#289548]" />
                        Baena · Córdoba · Andalucía
                    </span>
                    <a href="mailto:contact@arevantes.com">
                        contact@arevantes.com
                    </a>
                </div>
            </div>
            <div className="mx-auto mt-10 flex max-w-7xl flex-col gap-3 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
                <span>© {new Date().getFullYear()} Arevantes, S.L.</span>
                <nav className="flex flex-wrap gap-x-5 gap-y-2">
                    <Link href="/proyectos">Proyectos</Link>
                    <Link href="/aviso-legal">Aviso legal</Link>
                    <Link href="/privacidad">Privacidad</Link>
                    <Link href="/cookies">Cookies</Link>
                </nav>
            </div>
        </footer>
    );
}
