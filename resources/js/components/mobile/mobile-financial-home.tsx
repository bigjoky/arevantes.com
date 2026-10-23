import { Head, Link } from '@inertiajs/react';
import {
    ChartNoAxesCombined,
    TrendingUp,
    PieChart,
    ReceiptText,
    ChevronRight,
} from 'lucide-react';
import { HomeHeading } from '@/components/dashboard/home-heading';
import { useLocale } from '@/hooks/use-locale';
import type { FinancialOverview } from '@/types/financial-dashboard';

export function MobileFinancialHome({
    overview,
}: {
    overview: FinancialOverview;
}) {
    const { locale } = useLocale();
    const formatLocale = locale === 'es' ? 'es-ES' : 'en-GB';
    const money = (value: number) =>
        new Intl.NumberFormat(formatLocale, {
            style: 'currency',
            currency: 'EUR',
        }).format(value);
    const metrics = [
        {
            label: 'Ingresos acumulados',
            value: overview.metrics.income,
            comparison: overview.comparison.income,
            icon: ChartNoAxesCombined,
        },
        {
            label: 'Gastos acumulados',
            value: overview.metrics.expenses,
            comparison: overview.comparison.expenses,
            icon: TrendingUp,
        },
        {
            label: 'Resultado del ejercicio',
            value: overview.metrics.result,
            comparison: overview.comparison.result,
            icon: PieChart,
        },
        {
            label: 'IVA a ingresar',
            value: overview.vat_payable,
            comparison: null,
            icon: ReceiptText,
        },
    ];

    return (
        <div className="mobile-home">
            <Head title="Inicio" />
            <HomeHeading today={overview.today} />
            <div className="mobile-home-kpis">
                {metrics.map((item) => (
                    <section key={item.label} className="mobile-home-kpi">
                        <span className="mobile-home-kpi-icon">
                            <item.icon />
                        </span>
                        <div>
                            <h2>{item.label}</h2>
                            <strong>
                                {item.value === null ? '—' : money(item.value)}
                            </strong>
                            <p>
                                {item.value === null
                                    ? 'Pendiente de integración'
                                    : item.comparison === null
                                      ? 'Sin base comparable'
                                      : `${new Intl.NumberFormat(formatLocale, { maximumFractionDigits: 1, signDisplay: 'always' }).format(item.comparison)}% vs. año anterior`}
                            </p>
                        </div>
                    </section>
                ))}
            </div>
            <section className="mobile-home-section">
                <h2>Actividad reciente</h2>
                {overview.activity.length ? (
                    <ul>
                        {overview.activity.map((item) => (
                            <li key={item.id}>
                                <Link href={item.href}>
                                    <span className="mobile-home-event">
                                        <ReceiptText />
                                    </span>
                                    <span>
                                        <b>{item.reference}</b>
                                        <small>{item.description}</small>
                                        <time dateTime={item.occurred_at}>
                                            {item.occurred_at}
                                        </time>
                                    </span>
                                    <ChevronRight />
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="mobile-empty">No hay actividad reciente.</p>
                )}
            </section>
            <section className="mobile-home-section">
                <h2>Tareas pendientes</h2>
                {overview.tasks.length ? (
                    overview.tasks.map((item) => (
                        <Link
                            className="mobile-home-task"
                            key={item.id}
                            href={item.href}
                        >
                            <span>{item.title}</span>
                            <b>{item.count}</b>
                            <ChevronRight />
                        </Link>
                    ))
                ) : (
                    <p className="mobile-empty">
                        Sin conciliaciones pendientes. Otras tareas y
                        vencimientos pendientes de integración.
                    </p>
                )}
            </section>
            <details className="mobile-home-section">
                <summary>Evolución mensual · {overview.year}</summary>
                <p className="mobile-capability-note">
                    Importes del ejercicio, calculados por el servicio contable
                    existente.
                </p>
                <div className="mobile-data-scroll">
                    <table>
                        <caption className="sr-only">
                            Ingresos, gastos y resultado mensual
                        </caption>
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Ingresos</th>
                                <th>Gastos</th>
                                <th>Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {overview.monthly.map((row) => (
                                <tr key={row.month}>
                                    <th>
                                        {new Intl.DateTimeFormat(formatLocale, {
                                            month: 'short',
                                        }).format(
                                            new Date(
                                                overview.year,
                                                row.month - 1,
                                                1,
                                            ),
                                        )}
                                    </th>
                                    {[row.income, row.expenses, row.result].map(
                                        (value, index) => (
                                            <td key={index}>
                                                {value === null
                                                    ? '—'
                                                    : money(value)}
                                            </td>
                                        ),
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    );
}
