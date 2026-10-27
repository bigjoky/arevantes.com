import { Link } from '@inertiajs/react';
import { useCompanyBrand } from '@/components/brand-logo';
export function PublicSiteFooter() {
    const brand = useCompanyBrand();

    return (
        <footer className="av-footer">
            <div className="av-footer-top">
                <Link href="/" aria-label="Arevantes, inicio">
                    <img
                        className="av-logo"
                        src={brand.canonicalLogoUrl}
                        alt="Arevantes"
                        width="182"
                        height="42"
                    />
                </Link>
                <nav aria-label="Navegación de pie de página">
                    <Link href="/servicios">Servicios</Link>
                    <Link href="/proyectos">Proyectos</Link>
                    <Link href="/empresa">Empresa</Link>
                    <Link href="/proceso">Proceso</Link>
                    <Link href="/contacto">Contacto</Link>
                </nav>
                <span>Baena · Córdoba · Andalucía</span>
            </div>
            <div className="av-footer-bottom">
                <span>© {new Date().getFullYear()} Arevantes, S.L.</span>
                <nav aria-label="Información legal">
                    <Link href="/aviso-legal">Aviso legal</Link>
                    <Link href="/privacidad">Privacidad</Link>
                    <Link href="/cookies">Cookies</Link>
                    <Link href="/login">Área privada</Link>
                </nav>
            </div>
        </footer>
    );
}
