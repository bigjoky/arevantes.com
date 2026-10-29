import { Link, usePage } from '@inertiajs/react';
import { roleNames } from '@/components/accounting/account-input';
export function AccountingContextPanel() {
    const context = usePage().props.accountingContext as
        | {
              href: string;
              rows: {
                  role: string;
                  code: string | null;
                  name: string | null;
                  origin: string;
              }[];
          }
        | null
        | undefined;

    if (!context) {
return null;
}

    return (
        <section
            className="mx-4 mb-6 space-y-2 rounded border bg-card p-4 text-card-foreground"
            aria-label="Asignación contable de la ficha"
        >
            <h2 className="font-semibold">Asignación contable</h2>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {context.rows.map((r) => (
                    <p key={r.role} className="text-sm">
                        <strong>{roleNames[r.role]}</strong>:{' '}
                        {r.code
                            ? `${r.code} · ${r.name ?? ''}`
                            : 'Sin asignación específica'}
                        {!r.code && (
                            <small className="block text-muted-foreground">
                                Se revisarán reglas y cuenta general antes de
                                contabilizar.
                            </small>
                        )}
                    </p>
                ))}
            </div>
            <Link
                className="inline-block text-sm underline"
                href={context.href}
            >
                Revisar cuentas, origen y configuración
            </Link>
            <p className="text-xs text-muted-foreground">
                Las asociaciones se aplican a operaciones futuras. Un asiento
                contabilizado conserva sus cuentas originales.
            </p>
        </section>
    );
}
