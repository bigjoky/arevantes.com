import {
    Bar,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { CommercialOverview } from '@/types/commercial-dashboard';

export function CommercialEvolution({
    overview,
    money,
    percent,
    locale,
    t,
}: {
    overview: CommercialOverview;
    money: (value: number) => string;
    percent: (value: number) => string;
    locale: string;
    t: (key: string) => string;
}) {
    const rows = overview.monthly.map((row) => ({
        ...row,
        label: new Intl.DateTimeFormat(locale, {
            month: 'short',
            year: '2-digit',
        }).format(new Date(`${row.month}-01T12:00:00`)),
    }));

    return (
        <>
            <div
                className="home-chart-canvas"
                role="img"
                aria-label={t('Ventas, presupuestos y conversión por mes')}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart
                        data={rows}
                        margin={{ top: 15, right: 5, left: -10, bottom: 0 }}
                        accessibilityLayer
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--border)"
                        />
                        <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={false}
                            tick={{ fontSize: 10 }}
                        />
                        <YAxis
                            yAxisId="money"
                            tick={{ fontSize: 10 }}
                            tickLine={false}
                            axisLine={false}
                            tickFormatter={(value) =>
                                new Intl.NumberFormat(locale, {
                                    notation: 'compact',
                                }).format(value)
                            }
                        />
                        {overview.permissions.crm && (
                            <YAxis
                                yAxisId="rate"
                                orientation="right"
                                tickFormatter={(value) => `${value}%`}
                                tick={{ fontSize: 10 }}
                                tickLine={false}
                                axisLine={false}
                                domain={[0, 100]}
                            />
                        )}
                        <Tooltip
                            formatter={(value, name) =>
                                value == null
                                    ? '—'
                                    : name === t('Conversión')
                                      ? percent(Number(value))
                                      : money(Number(value))
                            }
                        />
                        <Legend iconSize={10} wrapperStyle={{ fontSize: 11 }} />
                        {overview.permissions.sales && (
                            <Bar
                                yAxisId="money"
                                dataKey="sales"
                                name={t('Ventas')}
                                fill="var(--commercial-sales)"
                                radius={[3, 3, 0, 0]}
                                maxBarSize={22}
                                isAnimationActive={false}
                            />
                        )}
                        {overview.permissions.quotes && (
                            <Bar
                                yAxisId="money"
                                dataKey="quotes"
                                name={t('Presupuestos')}
                                fill="var(--commercial-quotes)"
                                radius={[3, 3, 0, 0]}
                                maxBarSize={22}
                                isAnimationActive={false}
                            />
                        )}
                        {overview.permissions.crm && (
                            <Line
                                yAxisId="rate"
                                dataKey="conversion"
                                name={t('Conversión')}
                                stroke="var(--commercial-sales)"
                                strokeWidth={2}
                                dot={{ r: 2 }}
                                connectNulls={false}
                                isAnimationActive={false}
                            />
                        )}
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
            <details className="commercial-chart-data">
                <summary>{t('Ver datos del gráfico')}</summary>
                <div className="home-table-wrap">
                    <table className="home-table">
                        <caption className="sr-only">
                            {t('Ventas, presupuestos y conversión por mes')}
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">{t('Mes')}</th>
                                {overview.permissions.sales && (
                                    <th scope="col">{t('Ventas')}</th>
                                )}
                                {overview.permissions.quotes && (
                                    <th scope="col">{t('Presupuestos')}</th>
                                )}
                                {overview.permissions.crm && (
                                    <th scope="col">{t('Conversión')}</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.month}>
                                    <th scope="row">{row.label}</th>
                                    {overview.permissions.sales && (
                                        <td>{money(row.sales ?? 0)}</td>
                                    )}
                                    {overview.permissions.quotes && (
                                        <td>{money(row.quotes ?? 0)}</td>
                                    )}
                                    {overview.permissions.crm && (
                                        <td>
                                            {row.conversion === null
                                                ? '—'
                                                : percent(row.conversion)}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </>
    );
}
