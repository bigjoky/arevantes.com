import { Head, Link, router } from '@inertiajs/react';
import {
    Boxes,
    CalendarClock,
    ClipboardList,
    PackageCheck,
    PackageMinus,
    TriangleAlert,
} from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { PageHeader, PageShell } from '@/components/page-shell';
import {
    Empty,
    Movements,
    NeedTable,
    Panel,
    qty,
} from '@/components/supply/workspace';
import type {
    Capabilities,
    Movement,
    Need,
    Page,
} from '@/components/supply/workspace';
const metricsConfig = [
    [
        'in_stock',
        'Artículos en stock',
        'Referencias con existencia física positiva',
        Boxes,
    ],
    [
        'low_stock',
        'Stock bajo mínimo',
        'Posiciones con política de reposición',
        TriangleAlert,
    ],
    [
        'pending_orders',
        'Pedidos pendientes',
        'Con material por recibir',
        ClipboardList,
    ],
    [
        'expected_receipts',
        'Recepciones previstas',
        'Próximos siete días',
        CalendarClock,
    ],
    ['deliveries', 'Entregas', 'Movimientos físicos del periodo', PackageCheck],
    [
        'consumption',
        'Consumo en proyectos',
        'Movimientos de consumo del periodo',
        PackageMinus,
    ],
] as const;
const colors = ['#00875e', '#87d3b6', '#5d9fea', '#b894ed'];
type Slice = { name: string; value: number };
function Donut({ rows }: { rows: Slice[] }) {
    return rows.some((r) => r.value > 0) ? (
        <div className="supply-chart">
            <ResponsiveContainer>
                <PieChart>
                    <Pie
                        data={rows}
                        dataKey="value"
                        nameKey="name"
                        innerRadius={50}
                        outerRadius={75}
                    >
                        {rows.map((row, index) => (
                            <Cell
                                key={row.name}
                                fill={colors[index % colors.length]}
                            />
                        ))}
                    </Pie>
                    <Tooltip />
                    <Legend wrapperStyle={{ fontSize: 11 }} />
                </PieChart>
            </ResponsiveContainer>
        </div>
    ) : (
        <Empty>No hay datos para este gráfico.</Empty>
    );
}
export default function SupplyDashboard({
    metrics,
    stockStates,
    materialTypes,
    series,
    needs,
    movements,
    period,
    capabilities,
}: {
    metrics: Record<string, number | null>;
    stockStates: Slice[];
    materialTypes: Slice[];
    series: { month: string; incoming: number; outgoing: number }[];
    needs: Page<Need> | null;
    movements: Movement[];
    period: string;
    capabilities: Capabilities;
}) {
    return (
        <>
            <Head title="Almacén y compras" />
            <PageShell className="supply-workspace">
                <PageHeader
                    title="Almacén y compras"
                    description="Control total del material desde la necesidad hasta su consumo."
                    actions={
                        <label className="supply-note">
                            Periodo{' '}
                            <select
                                aria-label="Periodo"
                                value={period}
                                onChange={(event) =>
                                    router.get('/supply', {
                                        period: event.target.value,
                                    })
                                }
                            >
                                <option value="year">Últimos 12 meses</option>
                                <option value="month">Este mes</option>
                            </select>
                        </label>
                    }
                />
                <div className="supply-kpis">
                    {metricsConfig.map(([key, label, note, Icon]) => (
                        <article className="supply-kpi" key={key}>
                            <Icon aria-hidden="true" />
                            <div>
                                <span>{label}</span>
                                <strong>
                                    {metrics[key] === null
                                        ? '—'
                                        : qty(metrics[key] ?? 0)}
                                </strong>
                                <small>
                                    {metrics[key] === null
                                        ? 'Sin autorización'
                                        : note}
                                </small>
                            </div>
                        </article>
                    ))}
                </div>
                {capabilities.catalog && (
                    <>
                        <div className="supply-chart-grid">
                            <Panel title="Estado del stock">
                                <Donut rows={stockStates} />
                            </Panel>
                            <Panel title="Movimientos de material">
                                {series.length ? (
                                    <div className="supply-chart">
                                        <ResponsiveContainer>
                                            <BarChart data={series}>
                                                <CartesianGrid
                                                    vertical={false}
                                                    stroke="#eef0f2"
                                                />
                                                <XAxis
                                                    dataKey="month"
                                                    tick={{ fontSize: 10 }}
                                                />
                                                <YAxis
                                                    allowDecimals={false}
                                                    tick={{ fontSize: 10 }}
                                                />
                                                <Tooltip />
                                                <Legend
                                                    wrapperStyle={{
                                                        fontSize: 11,
                                                    }}
                                                />
                                                <Bar
                                                    dataKey="incoming"
                                                    name="Líneas de entrada"
                                                    fill="#00875e"
                                                    radius={[3, 3, 0, 0]}
                                                />
                                                <Bar
                                                    dataKey="outgoing"
                                                    name="Líneas de salida"
                                                    fill="#87d3b6"
                                                    radius={[3, 3, 0, 0]}
                                                />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </div>
                                ) : (
                                    <Empty>
                                        No hay movimientos en este periodo.
                                    </Empty>
                                )}
                            </Panel>
                            <Panel title="Material por tipo">
                                <Donut rows={materialTypes} />
                            </Panel>
                        </div>
                        <div className="supply-bottom-grid">
                            <Panel
                                title="Necesidades de material"
                                action={
                                    <Link href="/inventory?view=needs">
                                        Ver todas ›
                                    </Link>
                                }
                            >
                                <NeedTable needs={needs?.data ?? []} />
                            </Panel>
                            <Panel
                                title="Últimos movimientos"
                                action={
                                    <Link href="/inventory?view=movements">
                                        Ver todos ›
                                    </Link>
                                }
                            >
                                <Movements rows={movements} />
                            </Panel>
                        </div>
                    </>
                )}
                <p className="supply-note">
                    Las existencias conservan su continuidad entre ejercicios.
                    Las compras pendientes no incrementan el stock físico.
                </p>
            </PageShell>
        </>
    );
}
