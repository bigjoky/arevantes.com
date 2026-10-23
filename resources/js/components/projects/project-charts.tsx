import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';
export function Distribution({
    data,
    total,
}: {
    data: {
        key: string;
        label: string;
        total: number;
        color: string;
        href?: string;
    }[];
    total: number;
}) {
    const { locale } = useLocale();

    if (!total) {
        return <p className="projects-empty">Sin datos en el periodo.</p>;
    }

    return (
        <div className="projects-distribution">
            <div className="projects-donut">
                <ResponsiveContainer width="100%" height={130}>
                    <PieChart>
                        <Pie
                            data={data.filter((d) => d.total > 0)}
                            dataKey="total"
                            nameKey="label"
                            innerRadius={39}
                            outerRadius={58}
                            paddingAngle={1}
                        >
                            {data
                                .filter((d) => d.total > 0)
                                .map((d) => (
                                    <Cell key={d.key} fill={d.color} />
                                ))}
                        </Pie>
                        <Tooltip
                            formatter={(value) => [
                                Number(value),
                                translate('Total', locale),
                            ]}
                        />
                    </PieChart>
                </ResponsiveContainer>
                <strong>{total}</strong>
            </div>
            <ul className="projects-distribution-legend">
                {data.map((d) => (
                    <li key={d.key}>
                        <span style={{ background: d.color }} />
                        {d.href ? (
                            <a href={d.href}>{translate(d.label, locale)}</a>
                        ) : (
                            translate(d.label, locale)
                        )}
                        <b>{d.total}</b>
                    </li>
                ))}
            </ul>
        </div>
    );
}
export function PowerChart({
    data,
    hasDates,
}: {
    data: { month: string; power: number }[];
    hasDates: boolean;
}) {
    const { locale } = useLocale();

    if (!hasDates) {
        return (
            <p className="projects-empty">
                No hay potencia FV con inicio programado en el periodo.
            </p>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={185}>
            <BarChart data={data}>
                <CartesianGrid vertical={false} stroke="var(--border)" />
                <XAxis
                    dataKey="month"
                    tick={{ fontSize: 10 }}
                    tickFormatter={(s) =>
                        new Intl.DateTimeFormat('es-ES', {
                            month: 'short',
                        }).format(new Date(s + '-01T12:00:00'))
                    }
                />
                <YAxis tick={{ fontSize: 10 }} width={35} />
                <Tooltip
                    formatter={(value) => [
                        new Intl.NumberFormat('es-ES').format(Number(value)) +
                            ' kW',
                        translate('Potencia FV', locale),
                    ]}
                />
                <Bar dataKey="power" fill="#00865f" radius={[2, 2, 0, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}
